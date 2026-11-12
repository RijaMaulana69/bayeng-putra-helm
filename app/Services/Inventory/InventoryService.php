<?php

namespace App\Services\Inventory;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Database\ConnectionInterface;

class InventoryService
{
    /**
     * Memastikan transaksi database aktif pada koneksi yang sama persis
     * antara model Variant, StockMovement, User, dan jika ada Order/OrderItem.
     *
     * Service TIDAK membuka atau melakukan commit transaksi sendiri;
     * transaksi wajib diinisiasi oleh pemanggil (caller).
     *
     * @param  Order|null  $order  Objek pesanan opsional untuk validasi keselarasan koneksi pesanan
     *
     * @throws \LogicException Jika transaksi belum aktif atau koneksi antar-model tidak identik
     */
    public function assertActiveTransaction(?Order $order = null): ConnectionInterface
    {
        $variantConnection = $this->getVariantConnection();
        $connectionName = $variantConnection->getName() ?: 'default';

        // 1. Verifikasi transaksi aktif pada objek koneksi model Variant
        if ($variantConnection->transactionLevel() === 0) {
            throw new \LogicException(
                'Pelanggaran Kontrak Arsitektur: Method mutasi inventaris pada InventoryService '.
                "wajib dieksekusi di dalam transaksi database aktif pada koneksi '{$connectionName}'."
            );
        }

        // 2. Verifikasi kesamaan objek koneksi dengan model StockMovement
        $ledgerConnection = $this->getStockMovementConnection();
        if ($ledgerConnection !== $variantConnection) {
            throw new \LogicException(
                "Inkonsistensi Konfigurasi: Model Variant (koneksi: '{$variantConnection->getName()}') ".
                "dan StockMovement (koneksi: '{$ledgerConnection->getName()}') wajib menggunakan objek koneksi database yang sama persis."
            );
        }

        // 3. Verifikasi kesamaan objek koneksi dengan model User
        $userConnection = $this->getUserConnection();
        if ($userConnection !== $variantConnection) {
            throw new \LogicException(
                "Inkonsistensi Konfigurasi: Model Variant (koneksi: '{$variantConnection->getName()}') ".
                "dan User (koneksi: '{$userConnection->getName()}') wajib menggunakan objek koneksi database yang sama persis."
            );
        }

        // 4. Jika Order diberikan, verifikasi kesamaan objek koneksi dengan model Order dan OrderItem
        if ($order !== null) {
            $orderConnection = $order->getConnection();
            if ($orderConnection !== $variantConnection) {
                throw new \LogicException(
                    "Inkonsistensi Konfigurasi: Model Variant (koneksi: '{$variantConnection->getName()}') ".
                    "dan Order (koneksi: '{$orderConnection->getName()}') wajib menggunakan objek koneksi database yang sama persis."
                );
            }

            $orderItemConnection = $this->getOrderItemConnection();
            if ($orderItemConnection !== $variantConnection) {
                throw new \LogicException(
                    "Inkonsistensi Konfigurasi: Model Variant (koneksi: '{$variantConnection->getName()}') ".
                    "dan OrderItem (koneksi: '{$orderItemConnection->getName()}') wajib menggunakan objek koneksi database yang sama persis."
                );
            }
        }

        return $variantConnection;
    }

    /**
     * Memvalidasi dan menormalisasi integer positif secara ketat pada platform PHP 64-bit
     * sesuai spesifikasi Proposal v2.9 §5.3.
     *
     * Menolak boolean, float (termasuk 24.0), notasi ilmiah (2.4e1), desimal, string kosong,
     * string berawalan nol (seperti "024" dan "00024"), dan overflow.
     * Menormalisasi spasi leading/trailing pada string digit bulat positif murni via trim().
     *
     * @throws \InvalidArgumentException Jika format atau tipe data bukan integer positif dalam rentang valid
     * @throws \OverflowException Jika nilai melebihi kapasitas integer 64-bit sistem (PHP_INT_MAX)
     */
    public function validateStrictPositiveInt(mixed $value, int $min = 1, int $max = PHP_INT_MAX, string $fieldName = 'Nilai'): int
    {
        // 1. Tolak tipe boolean, float, dan null secara tegas
        if (is_bool($value) || is_float($value) || $value === null) {
            throw new \InvalidArgumentException("{$fieldName} tidak boleh berupa boolean, float, atau null.");
        }

        // 2. Evaluasi integer PHP murni
        if (is_int($value)) {
            if ($value < $min || $value > $max) {
                throw new \InvalidArgumentException("{$fieldName} bernilai {$value}, berada di luar batas ({$min} s.d. {$max}).");
            }

            return $value;
        }

        // 3. Evaluasi numeric string (digit bulat positif murni tanpa eksponen atau desimal)
        if (is_string($value)) {
            // Normalisasi spasi di awal dan akhir string
            $trimmed = trim($value);

            // Proposal v2.9 §5.3: format string wajib cocok dengan regex /^[1-9]\d*$/
            // Menolak karakter non-digit, angka nol ("0"), dan string berawalan nol ("024", "00024")
            if (! preg_match('/^[1-9]\d*$/', $trimmed)) {
                throw new \InvalidArgumentException("{$fieldName} dengan format string '{$value}' bukan bilangan bulat positif yang valid.");
            }

            // Pengecekan overflow leksikografis terhadap PHP_INT_MAX (19 digit pada PHP 64-bit)
            $maxIntStr = (string) PHP_INT_MAX; // "9223372036854775807"
            if (strlen($trimmed) > strlen($maxIntStr) || (strlen($trimmed) === strlen($maxIntStr) && strcmp($trimmed, $maxIntStr) > 0)) {
                throw new \OverflowException("{$fieldName} bernilai '{$value}', melampaui kapasitas integer 64-bit sistem (PHP_INT_MAX).");
            }

            $intValue = (int) $trimmed;
            if ($intValue < $min || $intValue > $max) {
                throw new \InvalidArgumentException("{$fieldName} bernilai {$intValue}, berada di luar batas ({$min} s.d. {$max}).");
            }

            return $intValue;
        }

        throw new \InvalidArgumentException("Tipe data pada {$fieldName} tidak didukung untuk representasi integer.");
    }

    /**
     * Penjualan langsung di toko fisik (Walk-in PoS).
     *
     * KONTRAK OTORISASI & PEMANGGIL (CALLER):
     * Pemanggil (misalnya PosController / CreatePosSaleAction) wajib melakukan
     * pemeriksaan otorisasi RBAC (seperti Gate::authorize('create-pos-sale') atau
     * verifikasi role karyawan/owner) sebelum memanggil method ini. Service bertanggung
     * jawab atas integritas batas transaksi, validasi keberadaan staf kasir, dan penegakan
     * invariant inventaris buku besar.
     *
     * Parameter $staffUserId bertipe mixed agar service dapat memvalidasi tipe data
     * mentah secara ketat melalui validateStrictPositiveInt() tanpa konversi implisit PHP.
     *
     * @param  array<int|string, mixed>  $aggregatedItems  Peta pasangan [variant_id => kuantitas]
     * @param  mixed  $staffUserId  ID pengguna staf kasir yang mengeksekusi transaksi
     * @param  string  $noStruk  Nomor struk/bukti transaksi kasir toko
     *
     * @throws \InvalidArgumentException Jika parameter input tidak valid
     * @throws \OverflowException Jika input integer melampaui kapasitas 64-bit (PHP_INT_MAX)
     * @throws \LogicException Jika dipanggil di luar transaksi aktif atau koneksi tidak selaras
     * @throws \DomainException Jika varian tidak ditemukan, tidak aktif, atau stok tidak mencukupi
     */
    public function deductPosSale(array $aggregatedItems, mixed $staffUserId, string $noStruk): void
    {
        // 1. Verifikasi kontrak transaksi aktif terikat koneksi model
        $this->assertActiveTransaction();

        // 2. Validasi keranjang POS tidak boleh kosong
        if (empty($aggregatedItems)) {
            throw new \InvalidArgumentException('Daftar item penjualan POS tidak boleh kosong.');
        }

        // 3. Validasi ID staf kasir secara ketat terhadap tipe mentah
        $validatedStaffId = $this->validateStrictPositiveInt(
            $staffUserId,
            min: 1,
            max: PHP_INT_MAX,
            fieldName: 'ID staf kasir'
        );

        $staff = User::find($validatedStaffId);
        if (! $staff) {
            throw new \InvalidArgumentException("Staf kasir dengan ID #{$validatedStaffId} tidak ditemukan di sistem.");
        }

        // 4. Validasi nomor struk
        $trimmedNoStruk = trim($noStruk);
        if ($trimmedNoStruk === '') {
            throw new \InvalidArgumentException('Nomor struk penjualan POS tidak boleh kosong.');
        }
        if (strlen($trimmedNoStruk) > 50) {
            throw new \InvalidArgumentException('Nomor struk penjualan POS maksimal 50 karakter.');
        }

        // 5. Validasi tipe data ketat dan normalisasi per baris item
        $normalizedItems = [];
        foreach ($aggregatedItems as $rawVariantId => $rawQty) {
            $variantId = $this->validateStrictPositiveInt(
                $rawVariantId,
                min: 1,
                max: PHP_INT_MAX,
                fieldName: 'ID varian'
            );

            $qty = $this->validateStrictPositiveInt(
                $rawQty,
                min: 1,
                max: 999,
                fieldName: "Kuantitas penjualan untuk varian #{$variantId}"
            );

            // Jika ada pengulangan ID varian pasca-normalisasi
            if (isset($normalizedItems[$variantId])) {
                $totalQty = $normalizedItems[$variantId] + $qty;
                if ($totalQty > 999) {
                    throw new \InvalidArgumentException("Total kuantitas untuk varian #{$variantId} melebihi batas maksimal 999 unit per transaksi.");
                }
                $normalizedItems[$variantId] = $totalQty;
            } else {
                $normalizedItems[$variantId] = $qty;
            }
        }

        // 6. Urutkan seluruh ID varian secara numerik menaik (Deterministic Lock Ordering)
        $sortedVariantIds = array_keys($normalizedItems);
        sort($sortedVariantIds, SORT_NUMERIC);

        // 7. Ambil dan kunci baris varian secara pesimistik (lockForUpdate) berurutan
        $lockedVariants = Variant::with('product')
            ->whereIn('id', $sortedVariantIds)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // 8. Pastikan seluruh varian ditemukan di database
        if ($lockedVariants->count() !== count($sortedVariantIds)) {
            $foundIds = $lockedVariants->keys()->all();
            $missingIds = array_diff($sortedVariantIds, $foundIds);
            throw new \DomainException('Varian dengan ID ['.implode(', ', $missingIds).'] tidak ditemukan.');
        }

        // 9. Validasi kelayakan varian (aktif dan produk aktif) serta kecukupan stok PoS
        foreach ($sortedVariantIds as $variantId) {
            $qty = $normalizedItems[$variantId];

            /** @var Variant $variant */
            $variant = $lockedVariants->get($variantId);

            if (! $variant->is_active) {
                throw new \DomainException("Varian '{$variant->sku}' (ID #{$variant->id}) berstatus tidak aktif dan tidak dapat dijual.");
            }

            if (! $variant->product || ! $variant->product->is_active) {
                throw new \DomainException("Produk untuk varian '{$variant->sku}' (ID #{$variant->id}) berstatus tidak aktif atau tidak ditemukan.");
            }

            // PRD Bab 5.2 & Proposal v2.9 §4.8:
            // Kapasitas penjualan langsung PoS: stok_tersedia_pos = stok_fisik - stok_dipesan
            // Penjualan PoS dilarang keras memotong stok yang sedang direservasi online.
            $stokTersediaPos = $variant->stok_fisik - $variant->stok_dipesan;
            if ($stokTersediaPos < $qty) {
                throw new \DomainException(
                    "Stok helm {$variant->sku} untuk penjualan langsung tidak mencukupi ".
                    "(fisik di rak: {$variant->stok_fisik}, sedang direservasi online: {$variant->stok_dipesan}, tersedia jual: {$stokTersediaPos}, diminta: {$qty})."
                );
            }
        }

        // 10. Eksekusi mutasi fisik dan pencatatan buku besar (ledger)
        foreach ($sortedVariantIds as $variantId) {
            $qty = $normalizedItems[$variantId];

            /** @var Variant $variant */
            $variant = $lockedVariants->get($variantId);

            $fisikSebelum = $variant->stok_fisik;
            $dipesanSebelum = $variant->stok_dipesan;

            // PoS mengurangi stok fisik; stok dipesan TETAP (tidak tersentuh)
            $variant->stok_fisik -= $qty;
            $variant->save();

            StockMovement::createLedgerEntry([
                'variant_id' => $variant->id,
                'order_id' => null,
                'order_item_id' => null,
                'user_id' => $validatedStaffId,
                'tipe' => 'keluar_pos',
                'perubahan_stok_fisik' => -$qty,
                'perubahan_stok_dipesan' => 0,
                'stok_fisik_sebelum' => $fisikSebelum,
                'stok_fisik_sesudah' => $fisikSebelum - $qty,
                'stok_dipesan_sebelum' => $dipesanSebelum,
                'stok_dipesan_sesudah' => $dipesanSebelum,
                'catatan' => "Penjualan langsung toko / PoS Struk #{$trimmedNoStruk}",
            ]);
        }
    }

    /**
     * Reservasi stok untuk pesanan online baru (Checkout).
     *
     * KONTRAK TRANSAKSI & PEMANGGIL (CALLER):
     * Pemanggil (CheckoutAction) wajib membuka transaksi database aktif pada koneksi model.
     * Method ini melakukan:
     * 1. Verifikasi transaksi aktif dan keselarasan koneksi model.
     * 2. Pengurutan ID varian secara numerik menaik (Deterministic Lock Ordering).
     * 3. Penguncian pesimistik (SELECT FOR UPDATE) berurutan.
     * 4. Validasi ketersediaan stok reservasi: (stok_fisik - stok_dipesan >= qty).
     * 5. Peningkatan atomik pada stok_dipesan (stok_fisik TETAP).
     * 6. Pencatatan buku besar StockMovement dengan tipe 'reservasi_masuk'.
     *
     * @param  Order  $order  Objek pesanan yang sudah dibuat
     * @param  array<int, array{variant_id: mixed, qty: mixed, order_item_id: mixed}>  $itemsPreparation
     * @param  mixed  $userId  ID pengguna pembeli pembuat pesanan
     *
     * @throws \LogicException Jika dipanggil di luar transaksi aktif atau koneksi tidak selaras
     * @throws \InvalidArgumentException Jika parameter input tidak valid
     * @throws \OverflowException Jika input integer melampaui batas PHP 64-bit
     * @throws \DomainException Jika varian tidak ditemukan, tidak aktif, atau stok tidak mencukupi
     */
    public function reserveStockForOrder(Order $order, array $itemsPreparation, mixed $userId): void
    {
        // 1. Verifikasi transaksi aktif dan kesamaan objek koneksi lintas entitas
        $this->assertActiveTransaction($order);

        // 2. Validasi daftar item tidak boleh kosong
        if (empty($itemsPreparation)) {
            throw new \InvalidArgumentException('Daftar item untuk reservasi pesanan tidak boleh kosong.');
        }

        // 3. Validasi ID pengguna pembeli
        $validatedUserId = $this->validateStrictPositiveInt(
            $userId,
            min: 1,
            max: PHP_INT_MAX,
            fieldName: 'ID pengguna pembuat pesanan'
        );

        $user = User::find($validatedUserId);
        if (! $user) {
            throw new \InvalidArgumentException("Pengguna dengan ID #{$validatedUserId} tidak ditemukan di sistem.");
        }

        // 4. Normalisasi dan validasi per baris item
        $normalizedItems = [];
        $orderItemIds = [];
        foreach ($itemsPreparation as $item) {
            if (! is_array($item) || ! isset($item['variant_id'], $item['qty'], $item['order_item_id'])) {
                throw new \InvalidArgumentException('Setiap item reservasi wajib memiliki variant_id, qty, dan order_item_id.');
            }

            $variantId = $this->validateStrictPositiveInt(
                $item['variant_id'],
                min: 1,
                max: PHP_INT_MAX,
                fieldName: 'ID varian'
            );

            $qty = $this->validateStrictPositiveInt(
                $item['qty'],
                min: 1,
                max: 999,
                fieldName: "Kuantitas reservasi untuk varian #{$variantId}"
            );

            $orderItemId = $this->validateStrictPositiveInt(
                $item['order_item_id'],
                min: 1,
                max: PHP_INT_MAX,
                fieldName: "ID order item untuk varian #{$variantId}"
            );

            // Jika ada pengulangan varian, akumulasikan kuantitasnya
            if (isset($normalizedItems[$variantId])) {
                $totalQty = $normalizedItems[$variantId] + $qty;
                if ($totalQty > 999) {
                    throw new \InvalidArgumentException("Total kuantitas untuk varian #{$variantId} melebihi batas maksimal 999 unit per pesanan.");
                }
                $normalizedItems[$variantId] = $totalQty;
            } else {
                $normalizedItems[$variantId] = $qty;
            }

            $orderItemIds[$variantId] = $orderItemId;
        }

        // 5. Urutkan seluruh ID varian secara numerik menaik (Deterministic Lock Ordering)
        $sortedVariantIds = array_keys($normalizedItems);
        sort($sortedVariantIds, SORT_NUMERIC);

        // 6. Kunci baris varian secara pesimistik (lockForUpdate)
        $lockedVariants = Variant::with('product')
            ->whereIn('id', $sortedVariantIds)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // 7. Pastikan seluruh varian ditemukan di database
        if ($lockedVariants->count() !== count($sortedVariantIds)) {
            $foundIds = $lockedVariants->keys()->all();
            $missingIds = array_diff($sortedVariantIds, $foundIds);
            throw new \DomainException('Varian dengan ID ['.implode(', ', $missingIds).'] tidak ditemukan.');
        }

        // 8. Validasi keaktifan varian & produk serta kecukupan stok reservasi
        foreach ($sortedVariantIds as $variantId) {
            $qty = $normalizedItems[$variantId];

            /** @var Variant $variant */
            $variant = $lockedVariants->get($variantId);

            if (! $variant->is_active) {
                throw new \DomainException("Varian '{$variant->sku}' (ID #{$variant->id}) berstatus tidak aktif dan tidak dapat dipesan.");
            }

            if (! $variant->product || ! $variant->product->is_active) {
                throw new \DomainException("Produk untuk varian '{$variant->sku}' (ID #{$variant->id}) berstatus tidak aktif atau tidak ditemukan.");
            }

            // Kapasitas reservasi online: stok_tersedia = stok_fisik - stok_dipesan
            $stokTersedia = $variant->stok_fisik - $variant->stok_dipesan;
            if ($stokTersedia < $qty) {
                throw new \DomainException(
                    "Stok helm {$variant->sku} tidak mencukupi untuk reservasi online ".
                    "(fisik di rak: {$variant->stok_fisik}, sedang dipesan: {$variant->stok_dipesan}, tersedia: {$stokTersedia}, diminta: {$qty})."
                );
            }
        }

        // 9. Eksekusi penambahan stok dipesan dan pencatatan buku besar (Ledger)
        foreach ($sortedVariantIds as $variantId) {
            $qty = $normalizedItems[$variantId];

            /** @var Variant $variant */
            $variant = $lockedVariants->get($variantId);

            $fisikSebelum = $variant->stok_fisik;
            $dipesanSebelum = $variant->stok_dipesan;

            // Reservasi online: stok_fisik TETAP, stok_dipesan BERTAMBAH
            $variant->stok_dipesan += $qty;
            $variant->save();

            StockMovement::createLedgerEntry([
                'variant_id' => $variant->id,
                'order_id' => $order->id,
                'order_item_id' => $orderItemIds[$variantId],
                'user_id' => $validatedUserId,
                'tipe' => 'reservasi_masuk',
                'perubahan_stok_fisik' => 0,
                'perubahan_stok_dipesan' => $qty,
                'stok_fisik_sebelum' => $fisikSebelum,
                'stok_fisik_sesudah' => $fisikSebelum,
                'stok_dipesan_sebelum' => $dipesanSebelum,
                'stok_dipesan_sesudah' => $dipesanSebelum + $qty,
                'catatan' => "Reservasi pesanan online #{$order->nomor_pesanan}",
            ]);
        }
    }

    /**
     * Mendapatkan objek koneksi database dari model Variant.
     */
    protected function getVariantConnection(): ConnectionInterface
    {
        return (new Variant)->getConnection();
    }

    /**
     * Mendapatkan objek koneksi database dari model StockMovement.
     */
    protected function getStockMovementConnection(): ConnectionInterface
    {
        return (new StockMovement)->getConnection();
    }

    /**
     * Mendapatkan objek koneksi database dari model User.
     */
    protected function getUserConnection(): ConnectionInterface
    {
        return (new User)->getConnection();
    }

    /**
     * Mendapatkan objek koneksi database dari model OrderItem.
     */
    protected function getOrderItemConnection(): ConnectionInterface
    {
        return (new OrderItem)->getConnection();
    }
}
