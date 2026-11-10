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

  ----------------------------------------------------------------------------------
  Tanggal      Area          Bantuan AI    File terdampak  Verifikasi    Status
  ------------ ------------- ------------- --------------- ------------- -----------
  10-11-2026   Perencanaan   Konsolidasi   README, AGENTS, Pemilik       Perlu
                             dokumentasi   PRD,            proyek perlu  review
                             proyek dan    ARCHITECTURE,   mencocokkan
                             keputusan     DESIGN,         dengan
                             Hybrid SPA    AI_USAGE,       dokumen
                                           SECURITY,       sumber dan
                                           TESTING,        repository
                                           THREAT_MODEL,   aktual
                                           DATABASE

  10-11-2026   Auth & RBAC   Implementasi  Role.php,       PHPUnit test  Selesai &
                             Sistem Role   EnsureUserHas   (36 passed),  Lulus
                             Owner,        Role.php,       Laravel Pint
                             Karyawan,     CreateUser
                             Pembeli       Command.php,
                                           User.php,
                                           app.php,
                                           RoleAccessTest

  10-11-2026   Auth & RBAC   Integrasi     Role.php,       PHPUnit test  Selesai &
                             Alur Login,   IntendedUrl     (50 passed,   Lulus
                             Dispatcher,   Sanitizer.php,  189 asserts),
                             Sanitizer, &  Dashboard       Laravel Pint
                             Dashboard     Dispatcher
                             Role          Controller.php,
                                           login.blade,
                                           web.php,
                                           User.php,
                                           RoleDashboard
                                           IntegrationTest
  ----------------------------------------------------------------------------------

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

## 6. Template entri berikutnya

### [Tanggal] --- [Area/Fitur]

-   **Tujuan:** [masalah yang ingin diselesaikan]
-   **Bantuan AI:** [analisis, rancangan, kode, test, atau debugging]
-   **File yang diubah:** [daftar file]
-   **Keputusan manusia:** [keputusan yang disetujui]
-   **Verifikasi:** [test/perintah/review yang benar-benar dilakukan]
-   **Hasil:** [berhasil, perlu revisi, atau belum diuji]
-   **Risiko/catatan:** [hal yang perlu diperhatikan]

## 7. Privasi dan keamanan

Jangan mencatat: - Password, API key, token, secret, atau isi `.env`. -
Nomor rekening pribadi yang tidak diperlukan. - Data identitas dan
kontak pelanggan. - Bukti pembayaran asli. - Informasi sensitif lain.

Gunakan data dummy saat meminta bantuan AI.
