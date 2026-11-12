# AI_USAGE.md --- Catatan Penggunaan AI

## 1. Tujuan

Dokumen ini mencatat penggunaan AI selama perencanaan, pengembangan,
debugging, dan pengujian Bayeng Putra Helm. Tujuannya menjaga
transparansi, membantu verifikasi, dan memastikan mahasiswa memahami
kode yang digunakan.

## 2. Prinsip penggunaan

1.  AI merupakan alat bantu, bukan pengganti keputusan developer.
2.  Semua saran AI harus diperiksa terhadap PRD, AGENTS, repository, dan
    dokumentasi resmi yang relevan.
3.  AI tidak boleh mengarang data toko, hasil wawancara, harga, stok,
    atau kebijakan operasional.
4.  Kode yang dihasilkan AI harus dipahami, diuji, dan dapat dijelaskan
    oleh mahasiswa.
5.  Perubahan berisiko tinggi wajib mengikuti plan-first dan meminta
    persetujuan sebelum implementasi.
6.  Jangan memasukkan password, token, secret, data pembayaran, atau
    data pribadi pelanggan ke prompt maupun log AI.

## 3. Ruang lingkup bantuan AI

AI dapat membantu: - Menyusun dan meninjau dokumentasi. - Menjelaskan
konsep Laravel, Livewire, Alpine.js, database, dan keamanan. -
Mengusulkan struktur kode. - Membantu menulis kode setelah rencana
disetujui. - Membuat test dan skenario pengujian. - Membantu debugging
berdasarkan bukti error. - Meninjau kualitas dan risiko implementasi.

AI tidak boleh: - Menjalankan commit/push tanpa instruksi. - Mengubah
schema, auth, RBAC, stok, checkout, pembayaran, atau status tanpa
persetujuan. - Mengklaim test berhasil tanpa menjalankannya. -
Menganggap file atau fitur sudah ada tanpa memeriksa repository. -
Menggunakan dependency baru tanpa persetujuan.

## 4. Format pencatatan

Tambahkan entri untuk setiap bantuan AI yang berdampak pada rancangan
atau kode.

---

Tanggal Area Bantuan AI File terdampak Verifikasi Status

---

10-11-2026 Perencanaan Konsolidasi README, AGENTS, Pemilik Perlu
dokumentasi PRD, proyek perlu review
proyek dan ARCHITECTURE, mencocokkan
keputusan DESIGN, dengan
Hybrid SPA AI_USAGE, dokumen
SECURITY, sumber dan
TESTING, repository
THREAT_MODEL, aktual
DATABASE

10-11-2026 Auth & RBAC Implementasi Role.php, PHPUnit test Selesai &
Sistem Role EnsureUserHas (36 passed), Lulus
Owner, Role.php, Laravel Pint
Karyawan, CreateUser
Pembeli Command.php,
User.php,
app.php,
RoleAccessTest

10-11-2026 Auth & RBAC Integrasi Role.php, PHPUnit test Selesai &
Alur Login, IntendedUrl (50 passed, Lulus
Dispatcher, Sanitizer.php, 189 asserts),
Sanitizer, & Dashboard Laravel Pint
Dashboard Dispatcher
Role Controller.php,
login.blade,
web.php,
User.php,
RoleDashboard
IntegrationTest

---

## 5. Rincian Implementasi

### 10 November 2026 --- Sistem Role dan Hak Akses (Tahap 1)

- **Tujuan:** Membangun mekanisme otorisasi role untuk Owner, Karyawan, dan Pembeli, memastikan registrasi publik selalu aman dengan default pembeli, menyediakan command interaktif pembuatan akun administratif, dan memproteksi rute server-side.
- **Bantuan AI:** Desain arsitektur PHP Backed Enum `Role`, middleware `EnsureUserHasRole`, command `CreateUserCommand`, perbaruan model `User`, pendaftaran middleware alias pada `bootstrap/app.php`, pembuatan suite pengujian otomatis `RoleAccessTest`, dan pembersihan gaya kode dengan Pint.
- **File yang dibuat/diubah:**
    - `app/Enums/Role.php` (baru)
    - `app/Http/Middleware/EnsureUserHasRole.php` (baru)
    - `app/Console/Commands/CreateUserCommand.php` (baru)
    - `tests/Feature/RoleAccessTest.php` (baru)
    - `app/Models/User.php` (diubah)
    - `bootstrap/app.php` (diubah)
    - `docs/AI_USAGE.md` (diubah)
- **Keputusan manusia:** Kolom `role` tetap di luar fillable publik, Breeze/Volt dipertahankan tanpa modifikasi berlebih, command `user:create` interaktif tanpa akun default otomatis, middleware mengembalikan HTTP 403, dan pengujian isolasi rute bersih tanpa halaman dummy.
- **Verifikasi:**
    - `php vendor/phpunit/phpunit/phpunit tests/Feature/RoleAccessTest.php` (10 passed, 52 assertions, exit code 0).
    - `php vendor/phpunit/phpunit/phpunit` (36 passed, 129 assertions, exit code 0).
    - `php vendor/bin/pint` (Gaya kode diformat dan tervalidasi).
- **Hasil:** Berhasil dan lulus 100%.

### 10 November 2026 --- Integrasi Alur Autentikasi dan Dashboard Multi-Role

- **Tujuan:** Mengintegrasikan pengalihan login berbasis role, perlindungan intended URL dari ancaman Open Redirect & akses silang, implementasi Central Dispatcher pada rute `/dashboard`, dan pembuatan dashboard minimal untuk Owner, Karyawan, dan Pembeli.
- **Bantuan AI:** Implementasi pemetaan rute pada `Role::dashboardRoute()`, pembuatan service `IntendedUrlSanitizer`, controller `DashboardDispatcherController`, integrasi aksi pada `login.blade.php`, perbaruan rute di `routes/web.php`, penambahan view dashboard minimal, pembuatan suite pengujian `RoleDashboardIntegrationTest`, dan pemformatan via Pint.
- **File yang dibuat/diubah:**
    - `app/Enums/Role.php` (diubah: pemetaan `dashboardRoute()`)
    - `app/Services/Auth/IntendedUrlSanitizer.php` (baru: sanitasi intended URL)
    - `app/Http/Controllers/DashboardDispatcherController.php` (baru: central dispatcher `/dashboard`)
    - `resources/views/livewire/pages/auth/login.blade.php` (diubah: integrasi sanitizer & redirect)
    - `routes/web.php` (diubah: rute dispatcher dan route group `/owner/*`, `/karyawan/*`, `/pembeli/*`)
    - `resources/views/owner/dashboard.blade.php` (baru: layout dashboard Owner)
    - `resources/views/karyawan/dashboard.blade.php` (baru: layout dashboard Karyawan)
    - `resources/views/pembeli/dashboard.blade.php` (baru: layout dashboard Pembeli)
    - `app/Models/User.php` (diubah: aktivasi kontrak `MustVerifyEmail` dan atribut default)
    - `database/factories/UserFactory.php` (diubah: default role pembeli pada factory)
    - `tests/Feature/Auth/AuthenticationTest.php` (diubah: penyesuaian redirect dashboard role)
    - `tests/Feature/RoleDashboardIntegrationTest.php` (baru: 14 skenario pengujian integrasi)
    - `docs/AI_USAGE.md` (diubah)
- **Keputusan manusia:** Mempertahankan native enum casting (fail-fast), allowlist rute intended terbatas pada rute yang sudah didefinisikan, penanganan URL malformed via `parse_url() === false`, penolakan open redirect, dan pemisahan tegas bahwa CLI `user:create` bukan hak akses web Owner.
- **Verifikasi:**
    - `php vendor/phpunit/phpunit/phpunit tests/Feature/RoleDashboardIntegrationTest.php` (14 passed, 60 assertions, exit code 0).
    - `php vendor/phpunit/phpunit/phpunit` (50 passed, 189 assertions, exit code 0).
    - `php vendor/bin/pint` (Semua file kode terformat bersih).
- **Hasil:** Seluruh pengujian berhasil dan lulus 100%.

### 11 November 2026 --- Implementasi Master Category Seeder (Stage 2.3B)

- **Tujuan:** Mengimplementasikan seeder master kategori resmi (`MasterCategorySeeder`) berdasarkan keputusan resmi pemilik proyek untuk dua kategori yang disetujui (`Retro` dan `Half Face`), dengan proteksi idempoten, penanganan entitas soft-deleted, status inaktif, dan integrasi teruji ke `DatabaseSeeder`.
- **Bantuan AI:** Penyusunan kelas seeder `MasterCategorySeeder`, integrasi ke `DatabaseSeeder`, pembuatan suite pengujian Feature `MasterCategorySeederTest` (9 pengujian mencakup 8 kriteria spesifikasi dan integrasi seeder), verifikasi eksekusi SQLite in-memory, audit keamanan database utama, serta perbaikan format kode menggunakan Laravel Pint.
- **File yang dibuat/diubah:**
    - `database/seeders/MasterCategorySeeder.php` (baru: seeder idempoten untuk kategori resmi Retro dan Half Face)
    - `database/seeders/DatabaseSeeder.php` (diubah: integrasi pemanggilan `MasterCategorySeeder::class`)
    - `tests/Feature/MasterCategorySeederTest.php` (baru: 9 test feature mencakup seluruh batasan Stage 2.3B)
    - `docs/AI_USAGE.md` (diubah: pencatatan kepatuhan AI dan pengujian)
- **Keputusan manusia:** Pemilik proyek hanya menyetujui dua kategori (`Retro` dengan slug `retro`, dan `Half Face` dengan slug `half-face`) untuk lima kandidat produk teridentifikasi. Persetujuan ini tidak mencakup 45 aset foto lain yang belum diverifikasi maupun kategori helm lainnya (`Full Face`, `Cross`, `Modular`). Kategori soft-deleted tidak di-restore otomatis. Kategori tidak aktif tidak diaktifkan otomatis. Zero-variant, zero-product, zero-brand pada seeder kategori ini.
- **Verifikasi:**
    - `php artisan test --filter=MasterCategorySeederTest` (9 passed, 42 assertions, exit code 0).
    - `php artisan test` (67 passed, 291 assertions, exit code 0).
    - `./vendor/bin/pint database/seeders/MasterCategorySeeder.php database/seeders/DatabaseSeeder.php tests/Feature/MasterCategorySeederTest.php` (Memperbaiki formatting spasi dan newline EOF).
    - `./vendor/bin/pint database/seeders/MasterCategorySeeder.php database/seeders/DatabaseSeeder.php tests/Feature/MasterCategorySeederTest.php --test` (Pemeriksaan ulang terkonfirmasi passed, exit code 0).
    - Audit database utama MySQL (`bayeng_putra_helm`) sebelum eksekusi resmi: 0 baris berubah selama tahap pengujian (categories = 0, brands = 0, products = 0).
    - Verifikasi database utama MySQL (`bayeng_putra_helm`) setelah eksekusi resmi `php artisan db:seed --class=MasterCategorySeeder`: tepat 2 kategori dibuat (`Retro` id: 10000, `Half Face` id: 10001, `is_active` = 1, `deleted_at` = null), sedangkan tabel `brands` = 0, `products` = 0, `variants` = 0, `product_images` = 0, dan `stock_movements` = 0 tetap steril. Selama tugas revisi dokumentasi ini, tidak ada operasi seeder, insert, update, delete, truncate, maupun migration yang dijalankan pada database utama. Kondisi database yang ditampilkan merupakan hasil verifikasi setelah eksekusi resmi Stage 2.3B sebelumnya.
- **Hasil:** Seluruh pengujian dan pemeriksaan formatting berhasil dengan exit code 0 dan terverifikasi secara empiris.

### 11 November 2026 --- Implementasi Catalog Preparation Seeder (Stage 2.3C)

- **Tujuan:** Mengimplementasikan `CatalogPreparationSeeder` khusus untuk lima kandidat produk yang disetujui pemilik proyek (`cargloss-cf-retro`, `cargloss-devil`, `cargloss-ghotic`, `gm-g1`, `gm-g2`) dengan preflight check ketat, pencegahan duplikasi/bentrok, transaksi atomik, pemeriksaan keberadaan fisik file gambar sebelum transaksi database, penentuan urutan gambar dinamis (`max(urutan) + 1`), dan penegasan produk baru inaktif (`is_active = false`) tanpa varian (zero-variant).
- **Bantuan AI:** Implementasi kelas `CatalogPreparationSeeder`, pembuatan 16 skenario Feature Test pada `CatalogPreparationSeederTest`, verifikasi eksekusi SQLite in-memory, audit keamanan database utama, dan pemformatan via Laravel Pint.
- **File yang dibuat/diubah:**
    - `database/seeders/CatalogPreparationSeeder.php` (baru: seeder persiapan katalog idempoten untuk 5 produk yang disetujui)
    - `tests/Feature/CatalogPreparationSeederTest.php` (baru: 16 test feature mencakup seluruh batasan Stage 2.3C)
    - `docs/AI_USAGE.md` (diubah: dokumentasi kepatuhan AI dan pengujian)
- **Keputusan manusia:** Seeder hanya memproses 5 kandidat produk teridentifikasi dan tidak memproses 45 foto lainnya. Kategori yang digunakan harus aktif dan tidak soft-deleted. Kategori baru tidak dibuat otomatis. Brand baru tidak dibuat jika produk sudah ada namun brand belum ada. Produk baru dibuat `is_active = false`. Tidak ada pembuatan Variant atau StockMovement. Database utama Laragon tidak dieksekusi seeder katalog sebelum persetujuan mentor.
- **Verifikasi:**
    - `php artisan test --filter=CatalogPreparationSeederTest` (16 passed, exit code 0).
    - `php artisan test` (83 passed, exit code 0).
    - `./vendor/bin/pint database/seeders/CatalogPreparationSeeder.php tests/Feature/CatalogPreparationSeederTest.php --test` (Passed 100%).
    - Audit database utama MySQL (`bayeng_putra_helm`): 0 baris berubah pada tabel `brands`, `products`, `variants`, `product_images`, `stock_movements`.
- **Hasil:** Seluruh pengujian dan linter berhasil dengan exit code 0; database utama tetap aman dan steril.

### 11 November 2026 --- Implementasi dan Koreksi Terarah InventoryService Fondasi POS (Stage 2.4)

- **Tujuan:** Mengimplementasikan fondasi `InventoryService` khusus untuk transaksi penjualan langsung toko (walk-in PoS `deductPosSale`) dengan verifikasi ketat transaksi aktif pada koneksi model (`assertActiveTransaction`), penegakan isolasi stok pesanan online, pengurutan lock ID deterministik (`ORDER BY id ASC`), penegakan strict integer contract sesuai Proposal Database v2.9 §5.3 (`/^[1-9]\d*$/`, penolakan leading zero `"024"`, float, ekspresi ilmiah), dan pencatatan buku besar mutasi stok melalui `StockMovement::createLedgerEntry()`.
- **Bantuan AI:** Perancangan kelas `InventoryService`, implementasi method `assertActiveTransaction()`, `deductPosSale()`, dan `validateStrictPositiveInt()`, pembuatan test suite `InventoryServiceTest` (19 unit test mencakup seluruh 15 kriteria pengujian minimal, strict integer contract, SQLite trigger ledger rollback, dan determinisme lock), serta pemformatan kode via Laravel Pint.
- **File yang dibuat/diubah:**
    - `app/Services/Inventory/InventoryService.php` (baru: service layer utama mutasi inventaris, validasi strict integer, dan proteksi transaksi PoS)
    - `tests/Feature/InventoryServiceTest.php` (baru: 19 test feature mencakup kontrak transaksi, isolasi PoS, rollback, strict integer contract, dan determinisme lock)
    - `docs/AI_USAGE.md` (diubah: pencatatan kepatuhan AI dan pengujian Stage 2.4)
- **Keputusan manusia:** Pembatasan ruang lingkup implementasi hanya pada fondasi transaksi PoS (`deductPosSale`). Penundaan implementasi tiga method pesanan online (`reserveStockForOrder`, `releaseReservationForOrder`, `fulfillReservationAtPickup`) ke Stage 3 ketika dependensi model `Order` dan `OrderItem` tersedia. Larangan penggunaan stub/dummy model atau modifikasi kontrak database. Penyelarasan strict integer validation dengan Proposal v2.9 §5.3 tanpa kompromi (menolak format digit string dengan awalan nol).
- **Verifikasi:**
    - `php artisan test --filter=InventoryServiceTest` (19 passed, 88 assertions, exit code 0).
    - `php artisan test` (102 passed, 492 assertions, exit code 0).
    - `./vendor/bin/pint app/Services/Inventory/InventoryService.php tests/Feature/InventoryServiceTest.php --test` (Passed 100%).
    - Audit database utama MySQL (`bayeng_putra_helm`): 0 baris berubah (tetap categories = 2, brands = 2, products = 5, product_images = 5, variants = 0, stock_movements = 0).
- **Hasil:** Seluruh pengujian dan pemeriksaan formatting berhasil dengan exit code 0; database utama tetap aman dan steril.

### 11 November 2026 --- Pengujian Integritas Skema & Transaksi PoS (Stage 2.5A)

- **Tujuan:** Melaksanakan pengujian komprehensif integritas skema database katalog Stage 2.1 dan fondasi mutasi inventaris `InventoryService` PoS Stage 2.4, meliputi: verifikasi unique index varian aktif pasca soft-delete, composite unique key `variants(id, product_id)`, composite foreign key `product_images(variant_id, product_id)`, single primary image per produk, penolakan transaksi di luar koneksi aktif model, penolakan perubahan saat kegagalan validasi, strict integer contract (PHP_INT_MAX, PHP_INT_MAX - 1, $max = 999, float, ekspresi ilmiah), atomic multi-variant rollback (3 varian A, B, C dengan abort khusus varian C), dan isolasi kapasitas penjualan PoS terhadap reservasi online.
- **Bantuan AI:** Perancangan test suite `SchemaIntegrityTest` (9 pengujian skema & constraint, termasuk pemisahan kondisional pengujian driver SQLite dan MySQL), penyempurnaan test suite `InventoryServiceTest` (21 pengujian feature mencakup kontrak transaksi antar-koneksi, atomic multi-variant rollback 3 varian via SQLite trigger, dan pengujian strict integer), serta pemformatan via Laravel Pint.
- **File yang dibuat/diubah:**
    - `tests/Feature/SchemaIntegrityTest.php` (baru: 9 test integritas skema, index, composite key, constraint, dan trigger)
    - `tests/Feature/InventoryServiceTest.php` (diubah: 21 test feature mencakup kontrak transaksi, isolasi PoS, atomic rollback 3 varian, dan strict integer validation)
    - `docs/AI_USAGE.md` (diubah: dokumentasi verifikasi Stage 2.5A)
- **Keputusan manusia:** Membedakan dengan tegas hasil pengujian SQLite `:memory:` dan MySQL Laragon. Menandai pengujian CHECK constraint dan trigger MySQL secara kondisional (`markTestSkipped`) selama database testing MySQL terpisah belum dikonfigurasi, dan menolak menjalankan pengujian destruktif pada database utama `bayeng_putra_helm`. Menunda pengujian alur pesanan online dan konkurensi (Stage 2.5B) hingga Stage 3 selesai.
- **Verifikasi:**
    - `php artisan test --filter="SchemaIntegrityTest|InventoryServiceTest"` (30 tests: 25 passed, 5 skipped kondisional MySQL, 122 assertions, exit code 0).
    - `php artisan test` (113 tests: 108 passed, 5 skipped, 526 assertions, exit code 0).
    - `./vendor/bin/pint tests/Feature/SchemaIntegrityTest.php tests/Feature/InventoryServiceTest.php --test` (Passed 100%).
    - Audit database utama MySQL (`bayeng_putra_helm`): 0 baris berubah (tetap categories = 2, brands = 2, products = 5, product_images = 5, variants = 0, stock_movements = 0).
- **Hasil:** Seluruh pengujian dan pemeriksaan formatting berhasil dengan exit code 0; integritas skema dan transaksi PoS terbukti aman, konsisten, dan database utama tetap steril.

### 12 November 2026 --- Implementasi Fondasi Database dan Model MVP (Stage 3.2 Milestone 1)

- **Tujuan:** Mengimplementasikan fondasi database dan model Eloquent untuk transaksi MVP (Orders, Order Items, Payments, Order Audit Logs) beserta PHP Backed Enums, relasi integritas referensial, proteksi mass assignment status/keuangan, dan automated feature test suite.
- **Bantuan AI:**
    1. Perancangan dan pembuatan 7 Backed Enums: `OrderStatus`, `PaymentStatus`, `PaymentMethod`, `PaymentRecordType`, `PaymentRecordStatus`, `AuditActorType`, `EwalletProvider`.
    2. Implementasi 4 database migrations: `orders` (2026_11_12_000001), `order_items` (2026_11_12_000002), `payments` (2026_11_12_000003), `order_audit_logs` (2026_11_12_000004).
    3. Implementasi 4 Eloquent models: `Order`, `OrderItem`, `Payment`, `OrderAuditLog` dengan model casting, invariant helpers, dan proteksi mutasi status/keuangan via mass-assignment restriction.
    4. Pembuatan test suite `Stage3DatabaseFoundationTest` yang menguji skema tabel, kolom, casting backed enums, model invariant (`remainingReservedQuantity`), dan relasi Eloquent antar model.
- **File yang dibuat/diubah:**
    - `app/Enums/OrderStatus.php` (baru)
    - `app/Enums/PaymentStatus.php` (baru)
    - `app/Enums/PaymentMethod.php` (baru)
    - `app/Enums/PaymentRecordType.php` (baru)
    - `app/Enums/PaymentRecordStatus.php` (baru)
    - `app/Enums/AuditActorType.php` (baru)
    - `app/Enums/EwalletProvider.php` (baru)
    - `database/migrations/2026_11_12_000001_create_orders_table.php` (baru)
    - `database/migrations/2026_11_12_000002_create_order_items_table.php` (baru)
    - `database/migrations/2026_11_12_000003_create_payments_table.php` (baru)
    - `database/migrations/2026_11_12_000004_create_order_audit_logs_table.php` (baru)
    - `app/Models/Order.php` (baru)
    - `app/Models/OrderItem.php` (baru)
    - `app/Models/Payment.php` (baru)
    - `app/Models/OrderAuditLog.php` (baru)
    - `tests/Feature/Stage3DatabaseFoundationTest.php` (baru)
    - `docs/AI_USAGE.md` (diubah: dokumentasi kepatuhan AI dan pengujian)
- **Keputusan manusia:**
    1. Stage 2 Database Foundation tetap FROZEN.
    2. Composite foreign key tambahan pada `stock_movements` DIBATALKAN.
    3. Karantina bertingkat dan refund parsial kompleks DITUNDA pasca-MVP.
    4. Database utama MySQL `bayeng_putra_helm` tetap steril (tidak ada `migrate` langsung pada DB utama).
    5. Pengujian dieksekusi pada environment database testing terisolasi (SQLite `:memory:`).
- **Verifikasi:**
    - `php vendor/bin/phpunit tests/Feature/Stage3DatabaseFoundationTest.php` (6 passed, 53 assertions, exit code 0).
    - `php vendor/bin/phpunit` (119 tests: 114 passed, 5 skipped, 579 assertions, exit code 0).
    - Syntax check `php -l` pada seluruh 15 file enum, migration, model, dan test (0 syntax errors).
- **Hasil:** Seluruh pengujian berhasil dengan exit code 0; fondasi database dan model Stage 3.2 Milestone 1 selesai dan siap diverifikasi pemilik proyek sebelum lanjut ke Milestone 2.

### 12 November 2026 --- Implementasi Keranjang Belanja & Checkout (Stage 3.2 Milestone 2)

- **Tujuan:** Mengimplementasikan alur transaksi checkout atomik (`CheckoutAction`), mekanisme keranjang belanja (`CartService` & `CartItemData`), reservasi stok online pada `InventoryService` (`reserveStockForOrder`), snapshot data katalog ke `order_items`, kalkulasi total finansial integer, pembuatan nomor pesanan PRD (`ORD-YYYYMMDD-XXXX`), serta pengujian otomatis alur checkout.
- **Bantuan AI:**
    1. Penambahan method `reserveStockForOrder()` pada `InventoryService` dengan penegakan transaksi aktif caller-managed, pengurutan lock ID deterministik (`ORDER BY id ASC`), mutasi penambahan `stok_dipesan` secara atomik (`stok_fisik` tetap), dan pencatatan buku besar ledger `stock_movements` tipe `reservasi_masuk`.
    2. Implementasi DTO keranjang belanja `CartItemData` dan session-based service `CartService` dengan validasi ketersediaan stok riil database.
    3. Perancangan dan pembuatan `CheckoutAction` yang membungkus seluruh tahapan checkout (validasi keranjang, penguncian varian, pembuatan `Order`, snapshot `OrderItem`, pemanggilan reservasi inventaris, dan pembuatan `OrderAuditLog`) dalam satu unit kerja transaksi atomik (`attempts: 3`).
    4. Generator nomor pesanan berurutan bebas benturan/race condition berbasis tanggal WIB dan sequence 4 digit (`ORD-YYYYMMDD-XXXX`).
    5. Pembuatan test suite `CheckoutActionTest` (12 test komprehensif mencakup checkout transfer bank, e-wallet, bayar di toko, rollback stok tidak cukup, penolakan kuantitas nol/negatif, pengabaian harga input browser, keutuhan snapshot harga, multi-varian, agregasi varian duplikat, rollback zero-orphaned record, sequence nomor pesanan, dan verifikasi kontrak koneksi transaksi).
- **File yang dibuat/diubah:**
    - `app/Services/Inventory/InventoryService.php` (diubah: penambahan `reserveStockForOrder()`, dukungan verifikasi koneksi `Order` & `OrderItem`)
    - `app/DTO/CartItemData.php` (baru: DTO tervalidasi untuk item keranjang belanja)
    - `app/Services/Cart/CartService.php` (baru: service keranjang belanja berbasis session dengan validasi database)
    - `app/Actions/Order/CheckoutAction.php` (baru: action orkestrator transaksi checkout atomik)
    - `tests/Feature/CheckoutActionTest.php` (baru: 12 test feature skenario checkout)
    - `docs/AI_USAGE.md` (diubah: dokumentasi kepatuhan AI dan pengujian)
- **Keputusan manusia:**
    1. Metode `bayar_di_toko` tidak membuat payment record saat checkout dan memiliki batas kedaluwarsa 24 jam.
    2. Metode transfer bank dan e-wallet memiliki batas kedaluwarsa 2 jam.
    3. Reservasi stok online tidak mengubah `stok_fisik`, melainkan meningkatkan `stok_dipesan`.
    4. Analisis idempotency checkout: skema `orders` belum memiliki kolom `idempotency_key`, dan tidak dilakukan penambahan kolom secara diam-diam (dilaporkan secara formal untuk review pemilik).
    5. Pengujian konkurensi aktual dan `SELECT FOR UPDATE` pada SQLite diakui sebagai verifikasi logika transaksional, sedangkan konkurensi paralel multi-proses membutuhkan MySQL aktual.
- **Verifikasi:**
    - `php vendor/bin/phpunit tests/Feature/CheckoutActionTest.php` (12 passed, 58 assertions, exit code 0).
    - `php vendor/bin/phpunit` (131 tests: 126 passed, 5 skipped, 637 assertions, exit code 0).
    - `php vendor/bin/pint` (Gaya kode diformat dan tervalidasi bersih).
    - Audit database utama MySQL (`bayeng_putra_helm`): 0 baris berubah (tetap steril: categories = 2, brands = 2, products = 5, product_images = 5, variants = 0, stock_movements = 0).
- **Hasil:** Seluruh pengujian berhasil dengan exit code 0; implementasi Milestone 2 selesai dan siap direview oleh pemilik proyek.

### 2026-11-12 --- Implementasi Idempotency Checkout (Stage 3.2 Penyempurnaan Milestone 2)

- **Tujuan:** Menambahkan kolom `idempotency_key` pada tabel `orders` melalui migration baru, menerapkan validasi token UUID pada `CheckoutAction`, mengisolasi payload matching (replay tanpa side-effects), mengamatur race condition request paralel, serta menguji seluruh 10 skenarionya.
- **Bantuan AI:**
    1. Pembuatan migration baru `database/migrations/2026_11_12_000005_add_idempotency_key_to_orders_table.php` (`VARCHAR(100) NULL UNIQUE`).
    2. Pembaruan model `Order` (`idempotency_key` di fillable dan casts).
    3. Perancangan 2-lapisan idempotency di `CheckoutAction` (Fast-path DB check + atomic transaction race catching).
    4. Penambahan lifecycle helper token idempotency pada `CartService` (`getCheckoutToken`, `regenerateCheckoutToken`, `clearCheckoutToken`).
    5. Pembuatan dan pembaruan test suite `CheckoutActionTest` (13 test mencakup Test A hingga Test J).
- **File yang dibuat/diubah:**
    - `database/migrations/2026_11_12_000005_add_idempotency_key_to_orders_table.php` (baru)
    - `app/Models/Order.php` (diubah: fillable dan casts)
    - `app/Actions/Order/CheckoutAction.php` (diubah: validasi UUID, fast-path check, payload matching, parallel race handling)
    - `app/Services/Cart/CartService.php` (diubah: lifecycle token checkout di session)
    - `tests/Feature/CheckoutActionTest.php` Test A-J (diubah)
    - `tests/Feature/Stage3DatabaseFoundationTest.php` (diubah: cek kolom idempotency_key)
    - `docs/STAGE_3_2_IDEMPOTENCY_IMPLEMENTATION_REPORT.md` (baru: laporan komprehensif)
    - `docs/AI_USAGE.md` (diubah: dokumentasi kepatuhan AI)
- **Keputusan manusia:**
    1. Persetujuan Opsi A: penambahan kolom `orders.idempotency_key` (UNIQUE) via migration baru.
    2. Penolakan token yang dimiliki oleh pengguna lain tanpa membocorkan pesanan.
    3. Penolakan token yang sama jika digunakan untuk payload belanja berbeda.
    4. Replay pesanan eksisting dengan zero side-effects jika token dan payload cocok.
- **Verifikasi:**
    - `php vendor/bin/phpunit tests/Feature/CheckoutActionTest.php` (13 passed, 51 assertions, exit code 0).
    - `php vendor/bin/phpunit` (132 tests: 127 passed, 5 skipped, 630 assertions, exit code 0).
    - `php vendor/bin/pint` (Gaya kode diformat dan tervalidasi bersih).
    - Audit database utama MySQL (`bayeng_putra_helm`): 0 baris berubah (tetap steril: categories = 2, brands = 2, products = 5, product_images = 5, variants = 0, stock_movements = 0).
- **Hasil:** Seluruh pengujian berhasil dengan exit code 0; idempotency checkout terbukti solid pada lapisan aplikasi dan database.

## 6. Template entri berikutnya

### [Tanggal] --- [Area/Fitur]

- **Tujuan:** [masalah yang ingin diselesaikan]
- **Bantuan AI:** [analisis, rancangan, kode, test, atau debugging]
- **File yang diubah:** [daftar file]
- **Keputusan manusia:** [keputusan yang disetujui]
- **Verifikasi:** [test/perintah/review yang benar-benar dilakukan]
- **Hasil:** [berhasil, perlu revisi, atau belum diuji]
- **Risiko/catatan:** [hal yang perlu diperhatikan]

## 7. Privasi dan keamanan

Jangan mencatat: - Password, API key, token, secret, atau isi `.env`. -
Nomor rekening pribadi yang tidak diperlukan. - Data identitas dan
kontak pelanggan. - Bukti pembayaran asli. - Informasi sensitif lain.

Gunakan data dummy saat meminta bantuan AI.
