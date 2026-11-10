# ARCHITECTURE.md --- Arsitektur Bayeng Putra Helm

  Informasi   Keterangan
  ----------- --------------------------------------------
  Versi       0.2
  Tanggal     10 November 2026
  Status      Draft untuk review
  Pola        Modular Monolith
  Frontend    Hybrid SPA: Laravel + Livewire + Alpine.js

## 1. Tujuan

Dokumen ini menjelaskan rancangan teknis aplikasi Bayeng Putra Helm.
`PRD.md` tetap menjadi sumber utama kebutuhan bisnis dan aturan
transaksi.

## 2. Keputusan arsitektur

Menggunakan **Modular Monolith**: satu aplikasi Laravel dengan modul
terpisah berdasarkan tanggung jawab. Frontend menggunakan **Hybrid
SPA**, bukan React/Vue SPA terpisah.

### Teknologi

  Lapisan            Teknologi
  ------------------ --------------------------------------------------------------
  Backend            Laravel
  Struktur UI        Blade
  Interaksi server   Livewire
  Interaksi lokal    Alpine.js
  Styling            Tailwind CSS
  Asset build        Vite
  Database           MySQL
  ORM                Eloquent
  Auth               Laravel Breeze Livewire, setelah diverifikasi pada instalasi
  Grafik             Chart.js bila sudah disetujui/tersedia pada proyek
  Lokal              Laragon

Jangan memasang dependency baru tanpa persetujuan sesuai `AGENTS.md`.

## 3. Alur komunikasi

``` mermaid
flowchart TD
    U[Pengguna] --> B[Browser]
    B --> BL[Blade dan Tailwind]
    B --> AL[Alpine.js]
    BL --> NAV[wire:navigate]
    BL --> LW[Livewire Components]
    AL --> LW
    NAV --> LAR[Laravel]
    LW --> LAR
    LAR --> AUTH[Middleware, Policy, Validation]
    AUTH --> ACT[Actions dan Services]
    ACT --> ORM[Eloquent]
    ORM --> DB[(MySQL)]
    ACT --> FS[File Storage]
```

-   `wire:navigate`: navigasi internal tanpa full page reload untuk
    tautan yang sesuai.
-   Livewire: interaksi yang memerlukan pemrosesan server.
-   Alpine.js: modal, dropdown, tab, sidebar, dan interaksi lokal.
-   Laravel: validasi, otorisasi, logika bisnis, dan akses data.

Livewire tetap melakukan request jaringan. Hindari menyebut sistem
sebagai aplikasi offline atau real-time instan.

## 4. Standar interaksi frontend

  Kebutuhan               Pola
  ----------------------- -------------------------------------------------
  Navigasi                `wire:navigate`
  Search                  `wire:model.live.debounce.300ms`
  Filter dan paginasi     Livewire
  Tambah/ubah keranjang   Livewire action
  Modal/dropdown          Alpine.js
  Loading                 `wire:loading`
  Transisi                `wire:transition` bila sesuai
  Chat                    Polling sekitar 5 detik saat dibuka, sesuai PRD
  Badge notifikasi        Polling sekitar 30 detik, sesuai PRD

Angka interval adalah rancangan awal dan harus diverifikasi terhadap PRD
sumber serta hasil pengujian. Polling hanya aktif saat dibutuhkan.

## 5. Layer aplikasi

1.  **Presentation:** Blade, Livewire, Alpine.js, Tailwind.
2.  **Application:** Actions dan Services untuk aturan bisnis.
3.  **Data:** Eloquent Models, migrations, relationships, MySQL.
4.  **Security:** Middleware, Policies, validation, rate limiting, file
    authorization, audit.

Komponen Livewire dibuat tipis. Logika transaksi tidak ditempatkan hanya
di komponen UI.

## 6. Modul

-   Publik: beranda, katalog, pencarian, filter, detail produk.
-   Pembeli: keranjang, checkout, pembayaran, tracking, chat,
    notifikasi.
-   Karyawan: pesanan, verifikasi pembayaran, stok, pengambilan, chat,
    PoS P1.
-   Owner: pengguna, produk, harga, pengaturan, laporan, HPP/laba sesuai
    prioritas PRD.

## 7. Model data konseptual

Entitas yang direncanakan: `users`, `brands`, `helmet_types`,
`products`, `product_images`, `variants`, `orders`, `order_items`,
`payments`, `stock_movements`, `order_status_logs`, `chat_threads`,
`chat_messages`, `notifications`, `audit_logs`, dan `settings`.

Nama tabel, kolom, relasi, dan kardinalitas harus diperiksa dan
disepakati dalam `DATABASE.md` sebelum migration final. Jangan
menganggap daftar ini sebagai schema yang sudah dibuat.

Hierarki katalog: Merek → Jenis → Model Produk → Varian. Harga dan stok
berada di level varian. `order_items` menyimpan snapshot harga saat
transaksi.

## 8. Keranjang dan checkout

Keranjang boleh menggunakan mekanisme server-side yang dipilih setelah
memeriksa kebutuhan autentikasi dan desain database. Browser tidak
dipercaya untuk harga, total, diskon, atau ketersediaan stok.

Checkout: 1. Validasi item dan jumlah di server. 2. Ambil harga terbaru
dari database. 3. Hitung total di server. 4. Mulai transaksi database.
5. Kunci baris varian menggunakan `lockForUpdate()`. 6. Periksa stok
tersedia. 7. Buat pesanan dan detail item. 8. Reservasi stok. 9. Commit
transaksi. 10. Tampilkan ringkasan dan instruksi pembayaran.

Seluruh operasi harus konsisten dan aman dari double submission.

## 9. Stok

Rumus: `stok_tersedia = stok_fisik - stok_dipesan`.

-   Checkout online: tambah `stok_dipesan`.
-   Batal/kedaluwarsa: kurangi `stok_dipesan` tepat satu kali.
-   Pesanan pickup selesai: kurangi `stok_fisik` dan `stok_dipesan`.
-   PoS walk-in: kurangi `stok_fisik` setelah pemeriksaan stok tersedia.

Gunakan transaksi DB, row lock, validasi, dan idempotensi. Jangan
mengimplementasikan perubahan stok tanpa persetujuan rencana sesuai
`AGENTS.md`.

## 10. Status pesanan dan pembayaran

Status pesanan utama:
`dibuat → dikonfirmasi → disiapkan → siap_diambil → selesai`, dengan
pembatalan sesuai transisi yang diizinkan PRD.

Status pembayaran terpisah dari status pesanan. Nilai final harus
diselaraskan dengan PRD sumber sebelum migration dan kode dibuat.

Setiap perubahan status dicatat. Backend memvalidasi transisi; frontend
tidak dapat memaksakan status.

## 11. Auth dan otorisasi

Peran: `owner`, `karyawan`, `pembeli`.

Gunakan middleware untuk pembatasan area dan Policy/Gate untuk otorisasi
resource. Pembeli hanya dapat melihat/mengubah resource miliknya. Semua
aksi sensitif memeriksa hak akses di server.

## 12. Penyimpanan file

Foto produk dapat disajikan publik sesuai kebutuhan. Bukti pembayaran
disimpan di private storage dan hanya dapat diakses setelah otorisasi.
Validasi MIME, ukuran, dan ekstensi; gunakan nama file acak.

## 13. Chat, notifikasi, dan laporan

-   Chat dan notifikasi menggunakan Livewire polling pada interval yang
    disetujui PRD.
-   WebSocket/broadcasting bukan bagian implementasi awal.
-   Grafik dashboard dapat menggunakan Chart.js bila dependency
    disetujui dan tersedia.
-   Lifecycle grafik perlu dikelola saat komponen Livewire diperbarui
    atau dinavigasikan.
-   Data HPP/laba hanya dapat diakses Owner sesuai PRD.

## 14. Struktur folder target

``` text
app/
├── Actions/
│   ├── Cart/
│   ├── Orders/
│   ├── Payments/
│   └── Stock/
├── Livewire/
│   ├── Publik/
│   ├── Pembeli/
│   ├── Karyawan/
│   └── Owner/
├── Models/
├── Policies/
├── Services/
└── Http/
    ├── Middleware/
    └── Requests/
database/
├── migrations/
├── factories/
└── seeders/
resources/
├── views/
├── css/
└── js/
routes/
tests/
docs/
```

Ini target, bukan klaim tentang isi repository saat ini. Periksa
struktur aktual sebelum memindahkan atau membuat file.

## 15. Pengujian

Strategi detail berada di `TESTING.md`. Fokus arsitektur: authorization,
validasi, IDOR, manipulasi Livewire, race condition stok, transisi
status, file upload, dan double processing.

## 16. Deployment

Hosting belum dipilih. Jangan membuat konfigurasi khusus penyedia
hosting sebelum keputusan dibuat. Produksi memerlukan HTTPS,
`APP_DEBUG=false`, secret aman, storage persisten, backup, dan scheduler
bila digunakan.

## 17. Keputusan yang belum final

Konfirmasi data produk, aturan kedaluwarsa, kebijakan pembatalan, metode
pembayaran aktual, jam operasional, kebutuhan HPP, detail relasi
database, dan hosting sebelum implementasi terkait.
