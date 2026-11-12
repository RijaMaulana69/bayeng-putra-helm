<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\DTO\CartItemData;
use App\Enums\AuditActorType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Variant;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class CheckoutAction
{
    public function __construct(
        protected readonly InventoryService $inventoryService
    ) {}

    /**
     * Mengeksekusi proses checkout dan pembuatan pesanan secara atomik dengan jaminan idempotency.
     *
     * @param  User  $buyer  Pengguna pembeli yang sedang login
     * @param  PaymentMethod  $paymentMethod  Metode pembayaran yang dipilih
     * @param  array<int, CartItemData|array{variant_id: mixed, kuantitas?: mixed, qty?: mixed}>  $items  Daftar item belanja
     * @param  string  $idempotencyKey  Token UUID idempotency yang stabil dari klien
     * @param  string|null  $catatan  Catatan opsional pembeli
     * @return Order Objek pesanan yang berhasil dibuat/dikembalikan dan direservasi stoknya
     *
     * @throws InvalidArgumentException Jika token tidak valid, format item salah, atau payload tidak cocok
     * @throws DomainException Jika produk/varian tidak aktif, stok tidak cukup, atau token milik user lain
     * @throws \Throwable Jika terjadi kegagalan sistem yang memicu rollback transaksi
     */
    public function execute(
        User $buyer,
        PaymentMethod $paymentMethod,
        array $items,
        string $idempotencyKey,
        ?string $catatan = null
    ): Order {
        // 1. Validasi ketat token idempotency (Wajib UUID v4 valid)
        $trimmedKey = trim($idempotencyKey);
        if ($trimmedKey === '' || strlen($trimmedKey) > 100) {
            throw new InvalidArgumentException('Token idempotency wajib berupa string dan tidak boleh melebihi 100 karakter.');
        }

        if (! preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $trimmedKey)) {
            throw new InvalidArgumentException("Token idempotency '{$idempotencyKey}' bukan format UUID yang valid.");
        }

        // 2. Validasi keranjang belanja tidak boleh kosong
        if (empty($items)) {
            throw new InvalidArgumentException('Keranjang belanja kosong. Checkout tidak dapat diproses.');
        }

        // 3. Validasi tipe data dan agregasi kuantitas item per variant_id
        $aggregatedItems = [];
        foreach ($items as $rawItem) {
            $variantId = null;
            $qty = null;

            if ($rawItem instanceof CartItemData) {
                $variantId = $rawItem->variantId;
                $qty = $rawItem->kuantitas;
            } elseif (is_array($rawItem)) {
                $variantId = $rawItem['variant_id'] ?? null;
                $qty = $rawItem['kuantitas'] ?? ($rawItem['qty'] ?? null);
            }

            if ($variantId === null || $qty === null) {
                throw new InvalidArgumentException('Format item checkout tidak valid: wajib memiliki variant_id dan kuantitas.');
            }

            $validatedVariantId = $this->inventoryService->validateStrictPositiveInt(
                $variantId,
                min: 1,
                max: PHP_INT_MAX,
                fieldName: 'ID varian checkout'
            );

            $validatedQty = $this->inventoryService->validateStrictPositiveInt(
                $qty,
                min: 1,
                max: 999,
                fieldName: "Kuantitas untuk varian #{$validatedVariantId}"
            );

            if (isset($aggregatedItems[$validatedVariantId])) {
                $totalQty = $aggregatedItems[$validatedVariantId] + $validatedQty;
                if ($totalQty > 999) {
                    throw new InvalidArgumentException("Total kuantitas untuk varian #{$validatedVariantId} melebihi batas maksimal 999 unit per pesanan.");
                }
                $aggregatedItems[$validatedVariantId] = $totalQty;
            } else {
                $aggregatedItems[$validatedVariantId] = $validatedQty;
            }
        }

        // Normalisasi urutan array item agar perbandingan payload bersifat deterministik
        ksort($aggregatedItems, SORT_NUMERIC);

        // 4. FAST-PATH IDEMPOTENCY: Cek apakah pesanan dengan token ini sudah ada di database
        $existingOrder = Order::where('idempotency_key', $trimmedKey)->with('items')->first();
        if ($existingOrder !== null) {
            // Validasi kesesuaian pemilik dan isi payload
            $this->assertIdempotentPayloadMatches($existingOrder, $buyer, $paymentMethod, $aggregatedItems, $catatan);

            // Replay pesanan eksisting (Zero Side-Effect: tidak membuat order, tidak menambah stok dipesan, tidak menulis ledger)
            return $existingOrder;
        }

        // 5. Eksekusi alur checkout baru di dalam transaksi database atomik
        $orderConnection = (new Order)->getConnection();
        $maxOrderAttempts = 3;

        for ($attempt = 1; $attempt <= $maxOrderAttempts; $attempt++) {
            try {
                return $orderConnection->transaction(function () use ($buyer, $paymentMethod, $aggregatedItems, $catatan, $trimmedKey) {
                    $now = Carbon::now('UTC');

                    // a. Urutkan ID varian secara numerik menaik (Deterministic Lock Ordering)
                    $sortedVariantIds = array_keys($aggregatedItems);
                    sort($sortedVariantIds, SORT_NUMERIC);

                    // b. Ambil dan kunci baris varian secara pesimistik (SELECT FOR UPDATE)
                    $lockedVariants = Variant::with('product')
                        ->whereIn('id', $sortedVariantIds)
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    // Pastikan seluruh varian ditemukan di database
                    if ($lockedVariants->count() !== count($sortedVariantIds)) {
                        $foundIds = $lockedVariants->keys()->all();
                        $missingIds = array_diff($sortedVariantIds, $foundIds);
                        throw new DomainException('Varian dengan ID ['.implode(', ', $missingIds).'] tidak ditemukan.');
                    }

                    // c. Validasi keaktifan dan kecukupan stok setelah penguncian baris
                    $totalProduk = 0;
                    $totalHarga = 0;
                    $itemsData = [];

                    foreach ($sortedVariantIds as $variantId) {
                        $qty = $aggregatedItems[$variantId];
                        /** @var Variant $variant */
                        $variant = $lockedVariants->get($variantId);

                        if (! $variant->is_active) {
                            throw new DomainException("Varian '{$variant->sku}' sedang tidak aktif dan tidak dapat dibeli.");
                        }

                        if (! $variant->product || ! $variant->product->is_active) {
                            throw new DomainException("Produk '{$variant->product?->nama}' sedang tidak aktif dan tidak dapat dibeli.");
                        }

                        $stokTersedia = $variant->stok_fisik - $variant->stok_dipesan;
                        if ($stokTersedia < $qty) {
                            throw new DomainException(
                                "Stok helm {$variant->sku} tidak mencukupi untuk checkout ".
                                "(tersedia: {$stokTersedia}, diminta: {$qty})."
                            );
                        }

                        // Ambil harga resmi dari database (bukan input pengguna)
                        $hargaSatuan = (int) $variant->harga;
                        $subtotal = $hargaSatuan * $qty;

                        $totalProduk += $qty;
                        $totalHarga += $subtotal;

                        $itemsData[] = [
                            'variant' => $variant,
                            'kuantitas' => $qty,
                            'harga_satuan' => $hargaSatuan,
                            'subtotal' => $subtotal,
                        ];
                    }

                    // d. Buat nomor pesanan unik format PRD: ORD-YYYYMMDD-XXXX
                    $nomorPesanan = $this->generateOrderNumber($now);

                    // e. Tentukan batas waktu kedaluwarsa sesuai PRD & status pembayaran
                    $ttlHours = $paymentMethod->defaultTtlHours(); // 2 jam online, 24 jam bayar di toko
                    $expiresAt = $now->copy()->addHours($ttlHours);

                    // f. Buat entitas Order induk dengan idempotency_key tersimpan
                    $order = new Order;
                    $order->nomor_pesanan = $nomorPesanan;
                    $order->idempotency_key = $trimmedKey;
                    $order->user_id = $buyer->id;
                    $order->status = OrderStatus::MENUNGGU_PEMBAYARAN;
                    $order->status_pembayaran = PaymentStatus::BELUM_DIBAYAR;
                    $order->metode_pembayaran = $paymentMethod;
                    $order->total_produk = $totalProduk;
                    $order->total_harga = $totalHarga;
                    $order->diskon = 0;
                    $order->total_akhir = $totalHarga;
                    $order->nominal_kewajiban = $totalHarga;
                    $order->catatan = $catatan ? trim($catatan) : null;
                    $order->expires_at = $expiresAt;
                    $order->save();

                    // g. Buat rincian OrderItem dengan snapshot katalog lengkap
                    $itemsForInventory = [];
                    foreach ($itemsData as $data) {
                        /** @var Variant $variant */
                        $variant = $data['variant'];
                        $qty = $data['kuantitas'];
                        $hargaSatuan = $data['harga_satuan'];
                        $subtotal = $data['subtotal'];

                        $orderItem = OrderItem::create([
                            'order_id' => $order->id,
                            'variant_id' => $variant->id,
                            'nama_produk' => $variant->product->nama,
                            'sku' => $variant->sku,
                            'warna' => $variant->warna,
                            'ukuran' => $variant->ukuran,
                            'harga_satuan' => $hargaSatuan,
                            'kuantitas' => $qty,
                            'subtotal' => $subtotal,
                            'diskon_dialokasikan' => 0,
                            'subtotal_bersih' => $subtotal,
                            'kuantitas_diserahkan' => 0,
                            'kuantitas_dibatalkan' => 0,
                            'kuantitas_kedaluwarsa' => 0,
                        ]);

                        $itemsForInventory[] = [
                            'variant_id' => $variant->id,
                            'qty' => $qty,
                            'order_item_id' => $orderItem->id,
                        ];
                    }

                    // h. Delegasikan penambahan stok dipesan dan pencatatan buku besar ke InventoryService
                    $this->inventoryService->reserveStockForOrder($order, $itemsForInventory, $buyer->id);

                    // i. Catat riwayat audit awal pembuatan pesanan
                    OrderAuditLog::create([
                        'order_id' => $order->id,
                        'tipe_aktor' => AuditActorType::PEMBELI,
                        'buyer_user_id' => $buyer->id,
                        'status_sebelum' => null,
                        'status_sesudah' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
                        'catatan' => "Pesanan berhasil dibuat melalui checkout {$paymentMethod->label()}.",
                    ]);

                    return $order;
                });
            } catch (QueryException $e) {
                // 6. PENANGANAN REQUEST PARALEL / RACE CONDITION:
                // Jika request paralel lain yang menggunakan token idempotency sama berhasil commit terlebih dahulu
                if ($this->isIdempotencyKeyDuplicateException($e)) {
                    $winnerOrder = Order::where('idempotency_key', $trimmedKey)->with('items')->first();
                    if ($winnerOrder !== null) {
                        $this->assertIdempotentPayloadMatches($winnerOrder, $buyer, $paymentMethod, $aggregatedItems, $catatan);

                        return $winnerOrder;
                    }
                }

                // Jika benturan terjadi pada nomor_pesanan, lakukan retry loop untuk membuat sequence baru
                if ($this->isOrderNumberDuplicateException($e) && $attempt < $maxOrderAttempts) {
                    continue;
                }

                // Jika bukan duplicate key atau batas percobaan habis, lempar kembali exception
                throw $e;
            }
        }

        throw new DomainException('Gagal memproses checkout setelah beberapa kali percobaan.');
    }

    /**
     * Memvalidasi kesesuaian identitas pemilik dan isi payload pesanan
     * untuk mencegah penipuan token atau token reuse pada payload berbeda.
     *
     * @param  array<int, int>  $aggregatedItems
     *
     * @throws DomainException Jika token digunakan oleh pengguna lain
     * @throws InvalidArgumentException Jika payload tidak identik dengan pesanan yang sudah ada
     */
    protected function assertIdempotentPayloadMatches(
        Order $existingOrder,
        User $buyer,
        PaymentMethod $paymentMethod,
        array $aggregatedItems,
        ?string $catatan
    ): void {
        // 1. Validasi kepemilikan token: dilarang mengungkap pesanan orang lain
        if ($existingOrder->user_id !== $buyer->id) {
            throw new DomainException(
                "Akses Ditolak: Token idempotency '{$existingOrder->idempotency_key}' tidak valid untuk pengguna ini."
            );
        }

        // 2. Validasi metode pembayaran
        if ($existingOrder->metode_pembayaran !== $paymentMethod) {
            throw new InvalidArgumentException(
                "Payload Tidak Cocok: Metode pembayaran '{$paymentMethod->value}' berbeda dengan pesanan eksisting '{$existingOrder->metode_pembayaran->value}'."
            );
        }

        // 3. Validasi catatan pesanan
        $normalizedExistingCatatan = $existingOrder->catatan ? trim($existingOrder->catatan) : null;
        $normalizedNewCatatan = $catatan ? trim($catatan) : null;
        if ($normalizedExistingCatatan !== $normalizedNewCatatan) {
            throw new InvalidArgumentException(
                'Payload Tidak Cocok: Catatan pesanan berbeda dengan pesanan eksisting.'
            );
        }

        // 4. Validasi daftar item dan kuantitas
        $existingItemsMap = $existingOrder->items
            ->pluck('kuantitas', 'variant_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
        ksort($existingItemsMap, SORT_NUMERIC);

        $newItemsMap = [];
        foreach ($aggregatedItems as $variantId => $qty) {
            $newItemsMap[(int) $variantId] = (int) $qty;
        }
        ksort($newItemsMap, SORT_NUMERIC);

        if ($existingItemsMap !== $newItemsMap) {
            throw new InvalidArgumentException(
                'Payload Tidak Cocok: Rincian item atau kuantitas varian tidak sesuai dengan pesanan yang telah terdaftar.'
            );
        }
    }

    /**
     * Memeriksa apakah QueryException disebabkan oleh pelanggaran UNIQUE constraint pada kolom idempotency_key.
     */
    protected function isIdempotencyKeyDuplicateException(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'orders_idempotency_key_unique')
            || str_contains($message, 'orders.idempotency_key')
            || (str_contains($message, 'idempotency_key') && str_contains(strtolower($message), 'unique'));
    }

    /**
     * Memeriksa apakah QueryException disebabkan oleh pelanggaran UNIQUE constraint pada kolom nomor_pesanan.
     */
    protected function isOrderNumberDuplicateException(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'orders_nomor_pesanan_unique')
            || str_contains($message, 'orders.nomor_pesanan')
            || (str_contains($message, 'nomor_pesanan') && str_contains(strtolower($message), 'unique'));
    }

    /**
     * Menghasilkan nomor pesanan unik format PRD: ORD-YYYYMMDD-XXXX.
     * Menggunakan tanggal lokal toko (WIB) dan sequence 4 digit harian.
     */
    protected function generateOrderNumber(Carbon $now): string
    {
        $wibDate = $now->copy()->setTimezone('Asia/Jakarta')->format('Ymd');
        $prefix = "ORD-{$wibDate}-";

        $latestOrder = Order::where('nomor_pesanan', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $nextSequence = 1;
        if ($latestOrder) {
            $suffix = substr($latestOrder->nomor_pesanan, strlen($prefix));
            if (is_numeric($suffix)) {
                $nextSequence = ((int) $suffix) + 1;
            }
        }

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }
}
