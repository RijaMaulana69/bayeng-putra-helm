<?php

declare(strict_types=1);

namespace App\DTO;

class CartItemData
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $kuantitas,
        public readonly ?string $catatan = null,
    ) {
        if ($this->variantId < 1) {
            throw new \InvalidArgumentException('Variant ID harus berupa bilangan bulat positif.');
        }

        if ($this->kuantitas < 1 || $this->kuantitas > 999) {
            throw new \InvalidArgumentException('Kuantitas item keranjang harus berada dalam rentang 1 s.d. 999 unit.');
        }
    }

    /**
     * Membuat instance dari array data mentah.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $variantId = isset($data['variant_id']) ? (int) $data['variant_id'] : 0;
        $kuantitas = isset($data['kuantitas']) ? (int) $data['kuantitas'] : (isset($data['qty']) ? (int) $data['qty'] : 1);
        $catatan = isset($data['catatan']) ? (string) $data['catatan'] : null;

        return new self($variantId, $kuantitas, $catatan);
    }

    /**
     * Konversi ke representasi array.
     *
     * @return array{variant_id: int, kuantitas: int, catatan: string|null}
     */
    public function toArray(): array
    {
        return [
            'variant_id' => $this->variantId,
            'kuantitas' => $this->kuantitas,
            'catatan' => $this->catatan,
        ];
    }
}
