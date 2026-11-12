<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\DTO\CartItemData;
use App\Models\Variant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

class CartService
{
    protected const SESSION_KEY = 'buyer_cart_items';

    protected const CHECKOUT_TOKEN_KEY = 'buyer_checkout_idempotency_token';

    public function __construct(
        protected readonly Session $session
    ) {}

    /**
     * Mendapatkan seluruh item di keranjang belanja pengguna.
     *
     * @return array<int, CartItemData> Peta [variant_id => CartItemData]
     */
    public function getItems(): array
    {
        $raw = $this->session->get(self::SESSION_KEY, []);
        $items = [];

        foreach ($raw as $variantId => $item) {
            if (is_array($item)) {
                $items[(int) $variantId] = CartItemData::fromArray($item);
            }
        }

        return $items;
    }

    /**
     * Menambahkan varian helm ke keranjang belanja.
     */
    public function addItem(int $variantId, int $quantity = 1, ?string $catatan = null): void
    {
        $items = $this->getItems();

        if (isset($items[$variantId])) {
            $newQty = $items[$variantId]->kuantitas + $quantity;
            if ($newQty > 999) {
                throw new \InvalidArgumentException('Total kuantitas untuk varian ini melebihi batas maksimal 999 unit.');
            }
            $items[$variantId] = new CartItemData($variantId, $newQty, $catatan ?? $items[$variantId]->catatan);
        } else {
            $items[$variantId] = new CartItemData($variantId, $quantity, $catatan);
        }

        $this->saveItems($items);
    }

    /**
     * Memperbarui kuantitas varian di keranjang belanja.
     */
    public function updateQuantity(int $variantId, int $quantity): void
    {
        $items = $this->getItems();

        if ($quantity <= 0) {
            $this->removeItem($variantId);

            return;
        }

        if (isset($items[$variantId])) {
            $items[$variantId] = new CartItemData($variantId, $quantity, $items[$variantId]->catatan);
            $this->saveItems($items);
        }
    }

    /**
     * Menghapus varian dari keranjang belanja.
     */
    public function removeItem(int $variantId): void
    {
        $items = $this->getItems();
        unset($items[$variantId]);
        $this->saveItems($items);
    }

    /**
     * Mengosongkan keranjang belanja dan token idempotency checkout.
     */
    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->clearCheckoutToken();
    }

    /**
     * Mendapatkan token idempotency checkout yang stabil untuk sesi pembeli.
     * Jika belum ada di session, buat UUID baru yang stabil hingga checkout berhasil.
     */
    public function getCheckoutToken(): string
    {
        $token = $this->session->get(self::CHECKOUT_TOKEN_KEY);
        if (! is_string($token) || $token === '') {
            $token = (string) Str::uuid();
            $this->session->put(self::CHECKOUT_TOKEN_KEY, $token);
        }

        return $token;
    }

    /**
     * Membuat ulang token idempotency baru untuk proses checkout baru.
     */
    public function regenerateCheckoutToken(): string
    {
        $token = (string) Str::uuid();
        $this->session->put(self::CHECKOUT_TOKEN_KEY, $token);

        return $token;
    }

    /**
     * Menghapus token idempotency dari session.
     */
    public function clearCheckoutToken(): void
    {
        $this->session->forget(self::CHECKOUT_TOKEN_KEY);
    }

    /**
     * Memvalidasi ketersediaan dan status aktif setiap item di keranjang berdasarkan database.
     *
     * @return array{
     *     valid: bool,
     *     items: array<int, array{variant: Variant, kuantitas: int, subtotal: int, error: string|null}>,
     *     total_produk: int,
     *     total_harga: int,
     *     errors: array<string>
     * }
     */
    public function validateCart(): array
    {
        $cartItems = $this->getItems();
        if (empty($cartItems)) {
            return [
                'valid' => false,
                'items' => [],
                'total_produk' => 0,
                'total_harga' => 0,
                'errors' => ['Keranjang belanja kosong.'],
            ];
        }

        $variantIds = array_keys($cartItems);
        $variants = Variant::with('product')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $validatedItems = [];
        $errors = [];
        $totalProduk = 0;
        $totalHarga = 0;

        foreach ($cartItems as $variantId => $item) {
            /** @var Variant|null $variant */
            $variant = $variants->get($variantId);
            $itemError = null;

            if (! $variant) {
                $itemError = "Varian #{$variantId} tidak ditemukan.";
            } elseif (! $variant->is_active) {
                $itemError = "Varian '{$variant->sku}' sedang tidak aktif.";
            } elseif (! $variant->product || ! $variant->product->is_active) {
                $itemError = "Produk untuk varian '{$variant->sku}' sedang tidak aktif.";
            } else {
                $stokTersedia = $variant->stok_fisik - $variant->stok_dipesan;
                if ($stokTersedia < $item->kuantitas) {
                    $itemError = "Stok helm {$variant->sku} tidak mencukupi (tersedia: {$stokTersedia}, diminta: {$item->kuantitas}).";
                }
            }

            if ($itemError !== null) {
                $errors[] = $itemError;
            }

            $subtotal = $variant ? ((int) $variant->harga * $item->kuantitas) : 0;
            $totalProduk += $item->kuantitas;
            $totalHarga += $subtotal;

            $validatedItems[$variantId] = [
                'variant' => $variant,
                'kuantitas' => $item->kuantitas,
                'subtotal' => $subtotal,
                'error' => $itemError,
            ];
        }

        return [
            'valid' => empty($errors),
            'items' => $validatedItems,
            'total_produk' => $totalProduk,
            'total_harga' => $totalHarga,
            'errors' => $errors,
        ];
    }

    /**
     * Menyimpan array CartItemData ke session.
     *
     * @param  array<int, CartItemData>  $items
     */
    protected function saveItems(array $items): void
    {
        $raw = [];
        foreach ($items as $variantId => $item) {
            $raw[$variantId] = $item->toArray();
        }

        $this->session->put(self::SESSION_KEY, $raw);
    }
}
