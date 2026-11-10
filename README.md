# Bayeng Putra Helm

Aplikasi web terintegrasi untuk katalog produk, pemesanan online dengan pengambilan langsung di toko (*store pickup*), dan sistem operasional penjualan **Bayeng Putra Helm**.

---

## 1. Informasi & Deskripsi Proyek

| Informasi | Keterangan |
| :--- | :--- |
| **Nama Proyek** | Bayeng Putra Helm |
| **Alamat Toko Fisik** | Jl. RE Martadinata No.49, Cijoho — Kuningan, Jawa Barat |
| **Arsitektur Sistem** | Modular Monolith |
| **Pendekatan Frontend** | Hybrid SPA: Laravel Blade + Livewire + Alpine.js |
| **Styling & Aset** | Tailwind CSS v4, Vite |
| **Backend & Database** | Laravel 13, MySQL |
| **Lingkungan Lokal** | Laragon (Windows) |
| **Fokus Keamanan** | Role-Based Access Control (RBAC), Anti-Open Redirect, Fail-Fast Integrity, Server-Side Authorization |

Aplikasi ini dirancang untuk menjawab kebutuhan operasional toko fisik dalam mengelola pesanan online yang diambil langsung di toko (*store pickup*), sinkronisasi stok antara penjualan online dan walk-in, serta pengawasan operasional oleh Owner.

---

## 2. Peran Pengguna (Role-Based Access Control)

Aplikasi menerapkan kontrol hak akses berbasis peran secara ketat di sisi server:

* **Tamu (Guest):** Melihat beranda dan katalog produk publik.
* **Pembeli:** Registrasi mandiri, melihat katalog, mengelola keranjang, membuat pesanan online, mengunggah bukti pembayaran, pelacakan status pesanan, dan area `/pembeli/dashboard`.
* **Karyawan:** Memantau antrean pesanan masuk, memverifikasi pembayaran transfer manual, menyiapkan barang, melakukan serah terima pickup di toko, dan area `/karyawan/dashboard`.
* **Owner:** Memiliki wewenang operasional toko penuh, manajemen harga produk, ringkasan laporan penjualan usaha, dan area `/owner/dashboard`.

---

## 3. Status Fitur Aplikasi

### A. Fitur yang Sudah Selesai (Tahap 1 — Autentikasi & RBAC)
* **Autentikasi Aman:** Menggunakan stack Laravel Breeze dengan Livewire Volt.
* **Sistem Peran (RBAC):** Implementasi PHP Backed Enum (`owner`, `karyawan`, `pembeli`) dengan strict native enum casting pada model `User`.
* **Proteksi Registrasi Publik:** Registrasi web publik secara otomatis mengunci role ke `pembeli` (atribut `role` di luar mass-assignment `$fillable` untuk mencegah *privilege escalation*).
* **Navigasi Aman (*Intended URL Sanitizer*):** Validasi URL intended saat login untuk memblokir celah *Open Redirect* (CWE-601), protocol-relative URL, userinfo, backslash, serta akses silang antar dashboard peran.
* **Central Dispatcher (`/dashboard`):** Mengarahkan pengguna terautentikasi secara dinamis ke dashboard perannya masing-masing via HTTP 302.
* **Tool Administrasi CLI (`user:create`):** Pembuatan akun staf/owner secara interaktif melalui terminal dengan input password tersembunyi.
* **Verifikasi Email:** Integrasi middleware `verified` pada seluruh dashboard peran.

### B. Fitur yang Direncanakan (Tahap Selanjutnya)
* **Tahap 2:** Skema database & Model Katalog (Merek, Jenis Helm, Model Produk, Galeri Gambar, dan Varian Stok `stok_fisik` vs `stok_dipesan`).
* **Tahap 3:** Keranjang Belanja & Checkout Atomik (Reservasi stok atomik menggunakan `DB::transaction()` dan `lockForUpdate()`).
* **Tahap 4:** Pembayaran Manual & Unggah Bukti Transfer (Penyimpanan bukti pembayaran di private storage dengan otorisasi anti-IDOR).
* **Tahap 5:** Antarmuka Operasional Karyawan & Owner (Verifikasi pembayaran, serah terima pickup, dan pelepasan stok pembatalan).
* **Tahap 6:** Fitur Komunikasi & Notifikasi Toko.
* **Prioritas P1:** Point of Sale (PoS) kasir offline walk-in, void transaksi, dan laporan HPP/laba.

---

## 4. Persyaratan Sistem

* **PHP:** Versi 8.3 atau lebih baru (direkomendasikan PHP 8.3 / 8.5)
  - Ekstensi yang dibutuhkan: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`
* **Composer:** Versi 2.x
* **Node.js & npm:** Node.js v18+ dan npm v9+
* **Database Server:** MySQL 8.0+ atau MariaDB 10.4+ (dapat menggunakan Laragon)
* **Web Server:** Nginx / Apache / PHP Built-in Server

---

## 5. Panduan Instalasi Lokal

Ikuti langkah-langkah berikut untuk menjalankan aplikasi di lingkungan lokal:

```bash
# 1. Clone repository
git clone https://github.com/<username>/bayeng-putra-helm.git
cd bayeng-putra-helm

# 2. Pasang dependensi PHP
composer install

# 3. Siapkan file konfigurasi environment
cp .env.example .env

# 4. Generate Application Encryption Key
php artisan key:generate

# 5. Konfigurasi Database pada file .env
# Sesuaikan koneksi database MySQL lokal Anda:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=bayeng_putra_helm
# DB_USERNAME=root
# DB_PASSWORD=

# 6. Buat database baru di MySQL dengan nama 'bayeng_putra_helm'

# 7. Jalankan migrasi database
php artisan migrate

# 8. Pasang dependensi JavaScript & build aset
npm install
npm run build
# (atau 'npm run dev' untuk mode live reload)

# 9. Buat akun administratif awal (Owner/Karyawan) via CLI
php artisan user:create

# 10. Jalankan server lokal
php artisan serve
```

Aplikasi dapat diakses melalui browser di: `http://127.0.0.1:8000`

---

## 6. Utilitas Administrasi CLI: `user:create`

Untuk menjamin keamanan, pembuatan akun administratif (Owner dan Karyawan) **tidak disediakan melalui form web publik**, melainkan melalui perintah terminal:

```bash
php artisan user:create
```

Perintah ini akan menanyakan data secara interaktif:
1. **Nama lengkap pengguna**
2. **Alamat email** (divalidasi format dan keunikannya di database)
3. **Pilihan role** (`owner`, `karyawan`, atau `pembeli`)
4. **Password akun** (karakter disembunyikan saat diketik, minimal 8 karakter)
5. **Konfirmasi data sebelum disimpan**

Password tidak pernah dicetak ke layar terminal maupun file log sistem.

---

## 7. Arsitektur Keamanan Siber (Cyber Security Highlights)

* **Server-Side Enforcement:** Hak akses diperiksa langsung di level server melalui middleware `auth`, `verified`, dan `role`. Menyembunyikan menu di frontend bukan dianggap sebagai proteksi.
* **Mass-Assignment Protection:** Kolom `role` dikunci di luar properti `$fillable` model `User`, sehingga parameter `role` yang diselipkan pada request POST registrasi publik otomatis diabaikan.
* **Fail-Fast Enum Integrity:** Menggunakan native PHP Backed Enum casting. Jika data di database rusak atau diisi nilai anomali, model langsung melempar `ValueError` agar anomali data terdeteksi dan tidak disamarkan.
* **Intended URL Sanitizer:** Mencegah serangan *Open Redirect* (CWE-601). URL tujuan setelah login divalidasi terhadap skema HTTP/HTTPS, host aplikasi, port, penolakan userinfo, penolakan protocol-relative (`//`), penolakan backslash, dan pencocokan terhadap allowlist rute resmi peran pengguna.

---

## 8. Laporan Self-Testing (Pengujian Mandiri)

Pengujian otomatis dijalankan secara mandiri menggunakan test suite PHPUnit untuk memverifikasi fungsionalitas dan ketahanan keamanan:

* **Perintah Pengujian:**
  ```bash
  php vendor/phpunit/phpunit/phpunit
  ```
* **Hasil Eksekusi:**
  - Total Tests: **50 tests**
  - Assertions: **189 assertions**
  - Status: **PASSED (100% Lulus, Exit Code: 0)**
* **Cakupan Pengujian Saat Ini:**
  - 26 pengujian autentikasi bawaan (login, verifikasi email, reset password, logout).
  - 10 pengujian sistem role (registrasi default pembeli, penolakan privilege escalation, redirect guest, pembatasan hak akses Karyawan & Pembeli, akses Owner, dan eksekusi CLI `user:create`).
  - 14 pengujian integrasi dashboard & navigasi (dispatcher dinamis, mitigasi open redirect, pembersihan URL malformed, allowlist intended URL, penolakan akses silang HTTP 403, dan fail-fast enum integrity).

> *Catatan Integritas:* Hasil pengujian di atas mencakup verifikasi pada modul Autentikasi dan RBAC (Tahap 1), bukan klaim bahwa seluruh transaksi bisnis (Tahap 2–6) telah selesai.

---

## 9. Transparansi Penggunaan AI (Academic Integrity Statement)

Sesuai ketentuan etika akademik dan dokumen penjaminan mutu [`docs/AI_USAGE.md`](docs/AI_USAGE.md):

* **Peran AI:** Kecerdasan Buatan (AI) digunakan sebagai alat bantu analisis arsitektur, penyusunan draf kode program, pembuatan skenario pengujian unit/feature, dan perapian dokumentasi teknis.
* **Peran Mahasiswa:** Seluruh keputusan arsitektur, pemilihan pola desain, pengujian keamanan, peninjauan kode, dan verifikasi akhir dilakukan secara mandiri oleh mahasiswa.
* **Kerahasiaan Data:** Tidak ada kredensial nyata, kunci API, password produksi, maupun data pribadi pelanggan yang dimasukkan ke dalam prompt atau log AI. Seluruh pengujian menggunakan data sintetis (*mock data*).
* **Riwayat Bantuan AI:** Seluruh interaksi penting dicatat secara transparan pada dokumen `docs/AI_USAGE.md`.

---

## 10. Struktur Dokumentasi Proyek

Dokumentasi lengkap proyek tersimpan pada folder [`docs/`](docs/):

* [`PRD.md`](docs/PRD.md) — Kebutuhan fungsional dan aturan bisnis toko.
* [`ARCHITECTURE.md`](docs/ARCHITECTURE.md) — Rancangan teknis Modular Monolith & Hybrid SPA.
* [`SECURITY.md`](docs/SECURITY.md) — Standar keamanan minimum aplikasi.
* [`THREAT_MODEL.md`](docs/THREAT_MODEL.md) — Identifikasi ancaman dan kontrol mitigasi.
* [`TESTING.md`](docs/TESTING.md) — Strategi dan matriks pengujian aplikasi.
* [`DATABASE.md`](docs/DATABASE.md) — Rancangan entitas dan integritas data.
* [`DESIGN.md`](docs/DESIGN.md) — Pedoman antarmuka visual dan tema toko.
* [`AI_USAGE.md`](docs/AI_USAGE.md) — Buku log transparansi penggunaan kecerdasan buatan.
* [`AGENTS.md`](AGENTS.md) — Pedoman kerja teknis pengembang di root repository.
