# PRD --- Website Penjualan Bayeng Putra Helm

  Informasi    Keterangan
  ------------ ----------------------------------------------
  Versi        0.3 --- Draft konsolidasi
  Tanggal      10 November 2026
  Status       Perlu verifikasi terhadap PRD sumber
  Nama usaha   Bayeng Putra Helm
  Alamat       Jl. RE Martadinata No.49, Cijoho -- Kuningan

> Dokumen ini merupakan konsolidasi berdasarkan keputusan proyek yang
> telah dibahas. Cocokkan kembali setiap detail dengan PRD sumber/hasil
> wawancara sebelum dianggap sebagai baseline final.

## 1. Ringkasan

Bayeng Putra Helm membutuhkan aplikasi web yang menggabungkan katalog
publik, pemesanan online dengan pengambilan langsung di toko,
pengelolaan pesanan, stok, komunikasi pembeli, dan operasional
penjualan. Sistem juga menyediakan PoS untuk transaksi walk-in pada
tahap pengembangan berikutnya.

## 2. Tujuan

1.  Memudahkan calon pembeli melihat produk dan varian.
2.  Memungkinkan pembeli membuat pesanan online untuk diambil di toko.
3.  Membantu karyawan mengelola pesanan, pembayaran, dan stok.
4.  Memberikan Owner ringkasan dan laporan operasional.
5.  Menjaga konsistensi stok dan keamanan transaksi.

## 3. Pengguna dan peran

-   **Tamu:** melihat katalog dan detail produk.
-   **Pembeli:** mengelola keranjang, checkout, pembayaran, riwayat,
    tracking, dan chat.
-   **Karyawan:** menangani pesanan, verifikasi pembayaran, stok, dan
    pengambilan sesuai izin.
-   **Owner:** mengelola usaha, akun karyawan, harga, laporan, dan
    pengaturan sesuai kewenangan.

Hak akses harus diterapkan di server melalui middleware dan Policy/Gate.
Menyembunyikan tombol bukan kontrol akses.

## 4. Ruang lingkup

### 4.1 P0 --- MVP

-   Website publik dan katalog.
-   Detail produk serta varian.
-   Autentikasi dan role.
-   Keranjang.
-   Checkout pesanan online.
-   Reservasi stok.
-   Pembayaran manual.
-   Unggah bukti pembayaran jika metode memerlukannya.
-   Riwayat dan tracking pesanan.
-   Pembatalan sesuai aturan.
-   Pemrosesan pesanan oleh Karyawan.
-   Penyelesaian pesanan online ketika diambil di toko.
-   Notifikasi dan chat sesuai cakupan PRD.
-   Laporan dasar yang ditetapkan PRD.
-   Pengujian keamanan kritis.

### 4.2 P1 --- Pengembangan lanjutan

-   PoS transaksi walk-in.
-   Void transaksi dengan persetujuan Owner.
-   HPP dan laporan laba untuk Owner.
-   Audit operasional yang lebih lengkap.
-   Fitur tambahan yang telah diprioritaskan pada PRD sumber.

### 4.3 P2 --- Pengembangan masa depan

Fitur masa depan hanya dikerjakan setelah prioritas P0 dan P1
diselesaikan dan disetujui. WebSocket/broadcasting dapat dievaluasi pada
tahap ini jika polling tidak lagi memadai.

## 5. Aturan bisnis utama

### 5.1 Produk

Hierarki katalog: **Merek → Jenis → Model Produk → Varian**.

Varian merupakan kombinasi warna/motif dan ukuran. Harga, SKU, dan stok
dikelola pada level varian. Data aktual produk harus berasal dari toko,
bukan asumsi.

### 5.2 Stok

-   `stok_fisik`: jumlah stok fisik yang dimiliki.
-   `stok_dipesan`: jumlah stok yang sedang direservasi untuk pesanan
    online.
-   Stok tersedia = `stok_fisik - stok_dipesan`.

Checkout online melakukan reservasi stok secara atomik. Pembatalan atau
kedaluwarsa melepaskan reservasi. Pesanan yang selesai diambil
mengurangi stok fisik dan stok dipesan. Penjualan walk-in PoS mengurangi
stok fisik secara langsung dan tidak boleh memakai stok yang sedang
direservasi.

### 5.3 Pesanan

Alur status utama:
`dibuat → dikonfirmasi → disiapkan → siap_diambil → selesai`.

Pembatalan hanya boleh dilakukan pada status dan kondisi yang diizinkan
aturan bisnis. Semua transisi divalidasi di server dan dicatat.

### 5.4 Pembayaran

Status pembayaran dikelola terpisah dari status pesanan. Status
rancangan: `belum_dibayar`, `menunggu_verifikasi`, `lunas`, `ditolak`,
dan `dikembalikan`. Verifikasi pembayaran dilakukan manual oleh
Karyawan/Owner sesuai kewenangan. Tidak ada payment gateway pada ruang
lingkup awal.

Metode pembayaran yang telah dibahas: bayar di toko, transfer bank, dan
e-wallet. Rekening dan nomor tujuan harus dikonfirmasi oleh pemilik
toko.

### 5.5 Pengambilan

Semua pesanan online diambil langsung di toko. Pengiriman/delivery tidak
termasuk ruang lingkup saat ini.

Alamat toko: **Jl. RE Martadinata No.49, Cijoho -- Kuningan**.

## 6. Persyaratan frontend

Aplikasi menggunakan Hybrid SPA: - Laravel Blade untuk struktur
halaman. - Livewire untuk interaksi server dan pembaruan komponen. -
Alpine.js untuk interaksi lokal. - Tailwind CSS untuk tampilan. - Vite
untuk build aset.

Target UX: - Navigasi internal tanpa full page reload menggunakan
`wire:navigate` pada tautan yang sesuai. - Pencarian dan filter katalog
dinamis. - Pemilihan varian yang jelas. - Keranjang yang responsif. -
Loading, error, empty, dan success states. - Layout responsif untuk
desktop, tablet, dan mobile.

Hybrid SPA bukan berarti tanpa komunikasi jaringan. Validasi dan
keputusan transaksi tetap dilakukan server.

## 7. Keamanan

Aplikasi harus menerapkan hashing password, validasi server-side,
Eloquent/parameterized query, escaping output, CSRF, session security,
rate limiting, RBAC, Policy, pemeriksaan kepemilikan data, proteksi
upload, penyimpanan bukti pembayaran privat, dan pengelolaan secret
melalui environment.

Properti publik Livewire dianggap input tidak tepercaya. Gunakan
`#[Locked]` bila sesuai, validasi ulang, dan otorisasi setiap action.

## 8. Persyaratan nonfungsional

-   Konsistensi stok ketika terjadi transaksi bersamaan.
-   Antarmuka responsif dan mudah digunakan.
-   Kode modular dan dapat dipelihara.
-   Error ditangani tanpa membocorkan informasi sensitif.
-   Riwayat transaksi tetap konsisten meskipun data produk berubah.
-   Semua waktu operasional menggunakan WIB bila ditampilkan.

## 9. Pengujian minimum

Wajib menguji: - Login dan pembatasan role. - IDOR pada
pesanan/chat/bukti pembayaran. - Manipulasi properti Livewire. - SQL
Injection dan XSS. - Race condition reservasi stok. -
Pembatalan/kedaluwarsa dan pelepasan stok. - Transisi status ilegal. -
Double processing saat menyelesaikan pesanan. - Validasi upload bukti
pembayaran.

## 10. Hal yang harus dikonfirmasi

Jangan mengarang: - Jam operasional dan hari libur. - Daftar merek,
jenis, model, ukuran, warna, dan SKU. - Harga dan stok aktual. -
Kebijakan durasi kedaluwarsa dan pembatalan. - Rekening bank dan nomor
e-wallet. - HPP dan metode perhitungan laba. - Detail proses operasional
berdasarkan wawancara. - Hosting dan kebijakan backup.

## 11. Kriteria penerimaan

Fitur diterima jika alurnya sesuai aturan bisnis, input divalidasi
server, hak akses diperiksa, perubahan stok atomik, test relevan
berhasil, tampilan memiliki state yang jelas, dan dokumentasi
diperbarui.
