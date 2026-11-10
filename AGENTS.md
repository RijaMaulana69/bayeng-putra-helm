# AGENTS.md --- Pedoman Pengembangan Bayeng Putra Helm

## 1. Tujuan dan sumber kebenaran

Dokumen ini mengatur cara AI coding agent dan developer bekerja pada
repository Bayeng Putra Helm.

Urutan acuan: 1. Instruksi eksplisit terbaru dari pemilik proyek. 2.
`docs/PRD.md` untuk kebutuhan bisnis, aturan, dan prioritas. 3.
`docs/ARCHITECTURE.md` untuk rancangan teknis. 4. `docs/DESIGN.md` untuk
UI/UX. 5. `docs/SECURITY.md`, `docs/TESTING.md`, dan dokumen pendukung.
6. Konvensi dan kondisi aktual repository.

Jika dokumen bertentangan, jangan memilih sendiri untuk perubahan
berisiko. Jelaskan konflik dan minta keputusan. PRD mengungguli dokumen
arsitektur untuk aturan bisnis.

## 2. Prinsip kerja: plan-first

Sebelum mengubah kode: 1. Pahami permintaan dan tujuan bisnisnya. 2.
Periksa file, route, komponen, model, migration, test, dan pola yang
sudah ada. 3. Identifikasi dependensi serta dampak perubahan. 4. Tulis
rencana singkat berisi file yang akan diubah dan cara verifikasi. 5.
Untuk tugas kecil dan berisiko rendah, lanjutkan setelah menyampaikan
rencana singkat. 6. Untuk perubahan besar atau berisiko tinggi, berhenti
setelah rencana dan minta persetujuan eksplisit.

Perubahan berisiko tinggi meliputi database/migration, autentikasi,
RBAC, kepemilikan data, reservasi stok, checkout, pembayaran, transisi
status pesanan, penyimpanan bukti pembayaran, dan deployment produksi.

## 3. Stack yang disepakati

-   Laravel.
-   Blade.
-   Livewire.
-   Alpine.js.
-   Tailwind CSS.
-   Vite.
-   MySQL.
-   Laravel Breeze dengan stack Livewire untuk autentikasi, jika sesuai
    dengan kondisi instalasi aktual.
-   Laragon untuk pengembangan lokal.

Frontend menggunakan pendekatan Hybrid SPA. Gunakan `wire:navigate`
untuk navigasi yang sesuai, Livewire untuk interaksi server, dan
Alpine.js untuk interaksi lokal. Jangan menambahkan React, Vue,
Filament, payment gateway, atau framework lain tanpa persetujuan.

Jangan memasang dependency baru tanpa menjelaskan kebutuhan, alternatif,
dampak, dan meminta persetujuan.

## 4. Struktur dan kualitas kode

-   Periksa struktur aktual sebelum membuat atau memindahkan folder.
-   Komponen Livewire harus relatif tipis.
-   Logika bisnis ditempatkan pada Action atau Service sesuai pola
    repository.
-   Gunakan Eloquent dan Query Builder terparameter.
-   Gunakan Form Request, Validator, atau mekanisme validasi yang
    konsisten dengan proyek.
-   Gunakan Policy/Gate dan middleware untuk otorisasi.
-   Hindari duplikasi aturan bisnis.
-   Gunakan nama dan istilah yang konsisten dengan PRD.
-   Antarmuka menggunakan Bahasa Indonesia dan format Rupiah.
-   Waktu operasional ditampilkan dalam WIB jika diperlukan.
-   Simpan snapshot harga pada detail transaksi.

## 5. Aturan transaksi dan stok

-   `stok_fisik` dan `stok_dipesan` merupakan sumber data stok sesuai
    PRD.
-   Stok tersedia dihitung sebagai `stok_fisik - stok_dipesan`.
-   Checkout online harus mengunci baris varian dan melakukan validasi
    serta reservasi stok secara atomik dalam transaksi database.
-   Pembatalan/kedaluwarsa harus melepaskan reservasi tepat satu kali.
-   Penyelesaian pesanan pickup mengurangi stok fisik dan stok dipesan
    sesuai aturan PRD.
-   Penjualan walk-in PoS mengurangi stok fisik, bukan stok pesanan
    online.
-   Operasi yang dapat dipanggil ulang harus idempotent atau memiliki
    perlindungan terhadap pemrosesan ganda.
-   Jangan mengubah alur stok tanpa rencana rinci dan persetujuan.

## 6. Keamanan wajib

-   Semua input browser tidak tepercaya, termasuk properti publik
    Livewire.
-   Validasi ulang input pada server.
-   Otorisasi setiap aksi sensitif di server.
-   Periksa kepemilikan data untuk mencegah IDOR.
-   Harga, total, diskon, status, dan stok dihitung atau diputuskan
    server.
-   Gunakan escaping Blade; jangan render input pengguna sebagai HTML
    mentah.
-   Jangan menyimpan password plaintext atau secret di repository.
-   Bukti pembayaran disimpan privat dan dilayani melalui pemeriksaan
    otorisasi.
-   Terapkan rate limiting untuk login dan aksi berisiko.
-   Jangan menonaktifkan CSRF atau proteksi keamanan untuk mengatasi
    error.
-   Jangan menampilkan detail internal atau stack trace kepada pengguna
    produksi.

## 7. Frontend Hybrid SPA

-   Gunakan `wire:navigate` pada tautan internal yang sesuai.
-   Jangan menjanjikan navigasi tanpa request jaringan; Livewire tetap
    berkomunikasi dengan server.
-   Gunakan debounce untuk pencarian agar request tidak berlebihan.
-   Tampilkan loading, empty, success, error, dan disabled states.
-   Alpine.js hanya untuk interaksi lokal; jangan jadikan state browser
    sebagai sumber kebenaran transaksi.
-   Pastikan komponen tetap berfungsi setelah navigasi Livewire.
-   Gunakan polling hanya pada komponen dan kondisi yang diperlukan.
-   Hindari event listener ganda dan kebocoran lifecycle komponen.

## 8. Pengujian dan verifikasi

Setiap perubahan harus disertai pengujian yang relevan. Minimal: - Test
fitur yang berubah. - Test validasi dan otorisasi. - Test regresi untuk
alur terkait. - Test keamanan untuk perubahan sensitif. - Jalankan
formatter/linter yang tersedia, termasuk Laravel Pint jika terpasang.

Jangan menyatakan test berhasil jika belum benar-benar dijalankan.
Laporkan perintah, hasil, dan test yang belum dijalankan.

## 9. Dokumentasi dan AI usage

Untuk bantuan AI yang berdampak pada desain, implementasi, debugging,
atau pengujian, tambahkan catatan pada `docs/AI_USAGE.md`. Catatan harus
menyebutkan bagian yang dibantu AI dan verifikasi manusia yang dilakukan
atau masih diperlukan.

Jangan mencatat secret, password, data pribadi pembeli, atau informasi
sensitif ke dokumentasi.

## 10. Git

-   Jangan melakukan commit, push, merge, atau mengubah remote tanpa
    instruksi.
-   Boleh menyarankan pesan commit.
-   Jangan menghapus pekerjaan pengguna yang sudah ada.
-   Periksa `git status` sebelum perubahan besar.

## 11. Definition of Done

Pekerjaan dianggap selesai jika: - Sesuai PRD dan keputusan yang
disetujui. - Validasi dan otorisasi berada di server. - Tidak
menambahkan dependency tanpa persetujuan. - Tidak ada query raw
berbahaya, output HTML tidak aman, atau secret yang bocor. - Test
relevan berhasil, atau keterbatasannya dilaporkan jelas. - Formatter
yang tersedia dijalankan. - Dokumentasi AI diperbarui bila relevan. -
Perubahan dapat dijelaskan dan dipahami oleh mahasiswa.
