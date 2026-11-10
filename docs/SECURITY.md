# SECURITY.md --- Standar Keamanan Bayeng Putra Helm

  -----------------------------------------------------------------------
  Informasi                           Keterangan
  ----------------------------------- -----------------------------------
  Versi                               0.1 --- Draft

  Tanggal                             10 November 2026

  Cakupan                             Website publik, pembeli, karyawan,
                                      Owner, transaksi, dan file
  -----------------------------------------------------------------------

## 1. Tujuan dan prinsip

Dokumen ini menetapkan kontrol keamanan minimum untuk melindungi akun,
data pembeli, transaksi, pembayaran, stok, dan operasional Bayeng Putra
Helm.

Prinsip: - Defense in depth. - Least privilege. - Server-side validation
dan authorization. - Secure by default. - Data minimization. -
Auditability untuk operasi penting.

## 2. Aset yang dilindungi

-   Akun dan kredensial pengguna.
-   Data profil pembeli.
-   Pesanan dan detail transaksi.
-   Harga, stok fisik, dan stok reservasi.
-   Bukti pembayaran.
-   Pesan chat dan notifikasi.
-   Data laporan, HPP, dan laba.
-   Konfigurasi aplikasi, secret, dan backup.

## 3. Model akses

Peran: - Tamu. - Pembeli. - Karyawan. - Owner.

Kontrol: - Gunakan middleware untuk area berbasis role. - Gunakan
Policy/Gate untuk resource dan aksi. - Terapkan pemeriksaan kepemilikan
untuk pesanan, chat, dan bukti pembayaran. - Karyawan hanya mendapat
izin operasional yang diperlukan. - HPP dan laba hanya dapat dilihat
Owner sesuai PRD. - Jangan menjadikan ID acak atau tombol tersembunyi
sebagai pengganti otorisasi.

## 4. Autentikasi dan sesi

-   Gunakan mekanisme autentikasi Laravel yang terpelihara.
-   Password disimpan menggunakan hashing yang disediakan Laravel
    (bcrypt/Argon2id sesuai konfigurasi).
-   Jangan pernah menyimpan atau mencatat password plaintext.
-   Terapkan rate limiting pada login dan pemulihan akun.
-   Regenerasi session ID setelah autentikasi.
-   Logout harus menginvalidasi sesi.
-   Cookie produksi menggunakan Secure, HttpOnly, dan SameSite yang
    sesuai.
-   Pesan autentikasi tidak boleh mengungkap apakah akun tertentu
    terdaftar bila hal itu meningkatkan risiko enumerasi.

## 5. Validasi, SQL Injection, dan mass assignment

-   Validasi semua input di server.
-   Gunakan Eloquent atau Query Builder dengan binding parameter.
-   Hindari menyusun SQL dari string input pengguna.
-   Batasi field yang boleh diisi melalui mass assignment.
-   Validasi ID, jumlah, status, harga, dan file.
-   Jangan percaya hidden input atau nilai dari browser.

## 6. XSS dan keamanan output

-   Gunakan escaping Blade `{{ }}` untuk output teks.
-   Hindari `{!! !!}` pada data pengguna.
-   Perlakukan pesan chat, nama produk, deskripsi, dan catatan sebagai
    input tidak tepercaya.
-   Jika rich text kelak diperlukan, gunakan sanitasi yang disetujui dan
    dibatasi.
-   Evaluasi Content Security Policy dan security headers untuk
    deployment.

## 7. CSRF dan request

-   Jangan menonaktifkan proteksi CSRF untuk mengatasi error.
-   Gunakan mekanisme CSRF bawaan Laravel/Livewire.
-   Uji request lintas origin dan aksi yang mengubah state.
-   Operasi perubahan data harus menggunakan metode dan mekanisme
    framework yang sesuai.

## 8. Keamanan Livewire dan Alpine.js

Properti publik Livewire dapat dimanipulasi oleh klien.

Wajib: - Validasi ulang nilai pada setiap action. - Gunakan `#[Locked]`
untuk properti identitas yang memang tidak boleh diubah klien. -
Otorisasi setiap action sensitif. - Ambil ulang data penting dari
database. - Hitung harga dan total di server. - Jangan percaya ID
varian, user ID, order ID, harga, diskon, atau status yang dikirim
browser. - Terapkan rate limit pada aksi yang rawan disalahgunakan. -
Jangan menyimpan secret atau keputusan otorisasi hanya di Alpine.js.

## 9. IDOR dan kontrol kepemilikan

Setiap endpoint, route model binding, komponen Livewire, dan akses file
harus memastikan pengguna berhak mengakses objek tersebut.

Contoh pola: - Pembeli hanya membaca pesanan miliknya. - Pembeli hanya
membaca chat thread miliknya. - Bukti pembayaran hanya dapat dilihat
oleh pembeli pemilik transaksi dan petugas berwenang. - Karyawan/Owner
dibatasi sesuai matriks izin.

Uji akses menggunakan akun berbeda, bukan hanya akun administrator.

## 10. Integritas harga dan transaksi

-   Harga produk diambil dari database pada saat checkout.
-   Total dihitung ulang di server.
-   Simpan snapshot harga pada `order_items`.
-   Perubahan harga setelah transaksi tidak mengubah transaksi historis.
-   Perubahan status harus mengikuti state transition yang sah.
-   Cegah double submission dan pemrosesan ganda.

## 11. Keamanan stok dan race condition

-   Gunakan transaksi database.
-   Gunakan row-level lock (`lockForUpdate`) saat reservasi atau
    perubahan stok kritis.
-   Periksa stok tersedia di dalam transaksi yang sama.
-   Pastikan pelepasan reservasi dan penyelesaian pesanan hanya
    dilakukan satu kali.
-   PoS walk-in tidak boleh mengurangi stok yang sudah direservasi untuk
    pesanan online.
-   Catat pergerakan stok dan alasan penyesuaian.
-   Uji dua checkout bersamaan pada varian dengan stok terbatas.

## 12. Upload dan penyimpanan file

-   Validasi ukuran, MIME, dan tipe file di server.
-   Jangan hanya mengandalkan ekstensi file.
-   Gunakan nama file acak dan hindari nama asli sebagai path.
-   Simpan bukti pembayaran pada private storage.
-   Akses file melalui route/controller yang memeriksa otorisasi.
-   Jangan membuat symbolic/public link untuk direktori bukti pembayaran
    privat.
-   Batasi ukuran dan jumlah upload.
-   Jangan mengeksekusi file upload.

## 13. Rate limiting dan penyalahgunaan

Pertimbangkan rate limit untuk: - Login dan reset password. - Pengiriman
chat. - Checkout. - Upload bukti pembayaran. - Percobaan pembatalan. -
Aksi verifikasi dan perubahan status. - Endpoint pencarian yang
berpotensi disalahgunakan.

Nilai limit ditentukan setelah melihat pola penggunaan dan diuji; jangan
mengarang angka final.

## 14. Secret dan konfigurasi

-   `.env` tidak boleh masuk Git.
-   Gunakan `.env.example` tanpa secret nyata.
-   Jangan hardcode API key, password database, atau token.
-   `APP_DEBUG=false` di produksi.
-   Gunakan HTTPS.
-   Batasi izin akun database.
-   Jangan menampilkan stack trace atau detail query kepada pengguna.
-   Backup database dan file harus dilindungi aksesnya.

## 15. Security headers

Untuk produksi, evaluasi dan konfigurasi: - Content-Security-Policy. -
Strict-Transport-Security setelah HTTPS benar-benar aktif. -
X-Content-Type-Options: nosniff. - Referrer-Policy. - Frame protection
melalui CSP `frame-ancestors` atau konfigurasi setara. -
Permissions-Policy.

Header harus diuji agar tidak merusak aset dan alur Livewire. Hindari
menyalin CSP ketat tanpa memeriksa kebutuhan script/style aplikasi.

## 16. Logging dan audit

Catat peristiwa penting seperti: - Login gagal/berhasil sesuai kebijakan
privasi. - Perubahan status pesanan. - Verifikasi atau penolakan
pembayaran. - Pergerakan dan penyesuaian stok. - Pembatalan dan
penyelesaian pesanan. - Aksi administratif penting.

Jangan mencatat password, token, secret, atau bukti pembayaran ke log.
Batasi akses log dan tentukan retensi.

## 17. Respons insiden

Jika ditemukan dugaan insiden: 1. Amankan bukti log yang relevan. 2.
Batasi akun atau akses yang diduga disalahgunakan. 3. Jangan menghapus
bukti. 4. Identifikasi data dan fitur yang terdampak. 5. Pulihkan dari
backup yang tervalidasi jika diperlukan. 6. Catat tindakan dan
keputusan. 7. Perbaiki akar masalah dan tambahkan test regresi.

## 18. Checklist sebelum produksi

-   [ ] HTTPS aktif dan valid.
-   [ ] `APP_DEBUG=false`.
-   [ ] Secret tidak berada di Git.
-   [ ] Akun database memakai hak minimum.
-   [ ] Login dan aksi sensitif memiliki rate limit.
-   [ ] Policy dan ownership checks diuji.
-   [ ] Bukti pembayaran berada di private storage.
-   [ ] Upload tervalidasi.
-   [ ] Stok diuji untuk race condition.
-   [ ] Status pesanan dan pembayaran diuji.
-   [ ] Security headers diuji.
-   [ ] Backup dan pemulihan diuji.
-   [ ] Log tidak mengandung secret atau data sensitif.
-   [ ] Semua temuan keamanan kritis ditangani.

## 19. Referensi implementasi

Gunakan dokumentasi resmi Laravel dan Livewire yang sesuai dengan versi
dependency aktual proyek. Jangan menyalin contoh dari versi berbeda
tanpa memeriksa kompatibilitas.
