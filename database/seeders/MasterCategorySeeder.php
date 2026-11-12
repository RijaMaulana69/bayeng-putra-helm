<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class MasterCategorySeeder extends Seeder
{
    /**
     * Daftar kategori resmi yang telah disetujui pemilik proyek.
     * Hanya kategori di bawah ini yang boleh diproses oleh seeder ini.
     *
     * @var array<int, array{nama: string, slug: string, urutan: int, deskripsi: string|null}>
     */
    protected array $approvedCategories = [
        [
            'nama' => 'Retro',
            'slug' => 'retro',
            'urutan' => 1,
            'deskripsi' => 'Helm bergaya klasik / retro.',
        ],
        [
            'nama' => 'Half Face',
            'slug' => 'half-face',
            'urutan' => 2,
            'deskripsi' => 'Helm open-face / half face standar harian.',
        ],
    ];

    /**
     * Jalankan seeder master kategori.
     */
    public function run(): void
    {
        foreach ($this->approvedCategories as $categoryData) {
            $slug = $categoryData['slug'];
            $nama = $categoryData['nama'];

            // 1. Periksa keberadaan kategori berdasarkan slug (termasuk soft-deleted)
            $existing = Category::withTrashed()->where('slug', $slug)->first();

            if ($existing) {
                // Skenario A: Kategori ditemukan dalam kondisi soft-deleted
                if ($existing->trashed()) {
                    $warningMessage = "Konflik: Kategori '{$nama}' (slug: '{$slug}') ditemukan dalam kondisi soft-deleted (deleted_at: {$existing->deleted_at}). Tidak dipulihkan atau dibuat ulang secara otomatis.";
                    $this->command?->warn($warningMessage);
                    Log::warning($warningMessage);

                    continue;
                }

                // Skenario B: Kategori ditemukan namun berstatus TIDAK AKTIF (is_active = false)
                if (! $existing->is_active) {
                    $inactiveMessage = "Kategori '{$nama}' (slug: '{$slug}') sudah ada namun berstatus TIDAK AKTIF (is_active = false). Status dipertahankan dan tidak diaktifkan otomatis.";
                    $this->command?->warn($inactiveMessage);
                    Log::info($inactiveMessage);

                    continue;
                }

                // Skenario C: Kategori sudah ada dan aktif -> pertahankan seluruh data tanpa menimpa
                $infoMessage = "Kategori '{$nama}' (slug: '{$slug}') sudah ada dan aktif. Data dipertahankan tanpa perubahan.";
                $this->command?->info($infoMessage);
                Log::info($infoMessage);

                continue;
            }

            // Skenario D: Kategori belum ada di database -> buat kategori baru dengan is_active = true
            Category::create([
                'nama' => $categoryData['nama'],
                'slug' => $categoryData['slug'],
                'deskripsi' => $categoryData['deskripsi'] ?? null,
                'urutan' => $categoryData['urutan'] ?? 0,
                'is_active' => true,
            ]);

            $createdMessage = "Kategori '{$nama}' (slug: '{$slug}') berhasil dibuat dengan status is_active = true.";
            $this->command?->info($createdMessage);
            Log::info($createdMessage);
        }
    }
}
