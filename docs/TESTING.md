# TESTING.md --- Strategi Pengujian Bayeng Putra Helm

  Informasi   Keterangan
  ----------- ----------------------------------------------
  Versi       0.1 --- Draft
  Tanggal     10 November 2026
  Fokus       Fungsional, integrasi, keamanan, dan regresi

## 1. Tujuan

Pengujian memastikan fitur berjalan sesuai PRD, data transaksi
konsisten, kontrol akses benar, dan kerentanan utama tidak muncul akibat
perubahan kode.

## 2. Prinsip

-   Uji perilaku, bukan hanya implementasi internal.
-   Gunakan database dan data uji terisolasi.
-   Jangan memakai data pelanggan atau bukti pembayaran asli.
-   Uji akses dengan beberapa role.
-   Setiap bug yang diperbaiki sebaiknya memiliki regression test.
-   Jangan menyatakan test berhasil sebelum benar-benar dijalankan.
-   Catat command dan hasil aktual.

## 3. Jenis pengujian

### 3.1 Unit test

Untuk fungsi atau aturan kecil, misalnya: - Perhitungan stok tersedia. -
Perhitungan subtotal dan total. - Validasi transisi status. - Aturan
pembatalan. - Perhitungan snapshot harga.

### 3.2 Feature/integration test

Untuk alur yang melibatkan Laravel, database, Livewire, middleware,
Policy, dan storage: - Login dan role. - Katalog. - Keranjang. -
Checkout. - Pembayaran. - Tracking. - Chat. - Penyelesaian pickup. - PoS
jika sudah masuk P1.

### 3.3 Security test

Menguji SQL Injection, XSS, IDOR, RBAC, manipulasi Livewire, CSRF,
upload, rate limit, dan integritas transaksi.

### 3.4 UI dan navigasi

Uji responsivitas dan perilaku Hybrid SPA: - Navigasi `wire:navigate`. -
Search/filter. - Loading/empty/error states. - Modal/dropdown Alpine. -
Komponen setelah navigasi. - Polling yang aktif hanya pada kondisi yang
diperlukan.

## 4. Matriks role

  Skenario                     Tamu   Pembeli       Karyawan          Owner
  ------------------------- ------- --------- -------------- --------------
  Melihat katalog                Ya        Ya             Ya             Ya
  Checkout                    Tidak        Ya          Tidak          Tidak
  Melihat pesanan sendiri     Tidak        Ya   Sesuai tugas   Sesuai tugas
  Melihat semua pesanan       Tidak     Tidak             Ya             Ya
  Verifikasi pembayaran       Tidak     Tidak             Ya             Ya
  Mengubah harga              Tidak     Tidak          Tidak             Ya
  Melihat HPP/laba            Tidak     Tidak          Tidak             Ya
  PoS walk-in                 Tidak     Tidak             P1             P1

Matriks final harus dicocokkan dengan PRD dan permission aktual.

## 5. Skenario fungsional

  ------------------------------------------------------------------------
  ID                      Skenario                 Hasil yang diharapkan
  ----------------------- ------------------------ -----------------------
  F-01                    Tamu membuka katalog     Produk aktif tampil

  F-02                    Tamu mencari produk      Hasil sesuai kata kunci

  F-03                    Pembeli memilih varian   Varian dan informasi
                                                   yang benar tampil

  F-04                    Pembeli menambah item ke Item tersimpan dan
                          keranjang                jumlah/total diperbarui

  F-05                    Pembeli mengubah jumlah  Server memvalidasi
                                                   jumlah dan ketersediaan

  F-06                    Pembeli menghapus item   Item hilang dari
                                                   keranjang

  F-07                    Pembeli checkout         Pesanan dibuat setelah
                                                   validasi dan reservasi
                                                   stok

  F-08                    Pembeli mengunggah bukti File tervalidasi dan
                                                   tersimpan privat

  F-09                    Petugas memverifikasi    Status pembayaran
                          pembayaran               berubah sesuai izin

  F-10                    Karyawan mengubah status Hanya transisi yang sah
                          pesanan                  diterima

  F-11                    Pembeli melihat tracking Hanya pesanan miliknya
                                                   yang tampil

  F-12                    Pesanan selesai diambil  Stok fisik dan stok
                                                   dipesan diperbarui satu
                                                   kali

  F-13                    Pesanan                  Reservasi dilepas satu
                          dibatalkan/kedaluwarsa   kali

  F-14                    Pembeli mengirim chat    Pesan tersimpan dan
                                                   hanya pihak berwenang
                                                   dapat membacanya

  F-15                    Karyawan melakukan PoS   Transaksi P1 mengurangi
                                                   stok fisik secara benar
  ------------------------------------------------------------------------

## 6. Skenario keamanan wajib

  ------------------------------------------------------------------------
  ID                Risiko            Langkah uji       Hasil yang
                                                        diharapkan
  ----------------- ----------------- ----------------- ------------------
  S-01              SQL Injection     Masukkan payload  Input diperlakukan
                                      uji pada          sebagai data,
                                      search/form       bukan SQL

  S-02              XSS               Masukkan payload  Output di-escape
                                      HTML/script pada  dan tidak
                                      nama/chat         dieksekusi

  S-03              IDOR pesanan      Pembeli A mencoba Ditolak/404 sesuai
                                      membuka order     kebijakan
                                      Pembeli B

  S-04              IDOR chat         Pembeli mencoba   Ditolak
                                      membuka thread
                                      lain

  S-05              IDOR bukti        Akun tanpa izin   Akses ditolak
                    pembayaran        mencoba mengambil
                                      file

  S-06              RBAC              Pembeli memanggil Ditolak di server
                                      route/action
                                      Owner

  S-07              Manipulasi        Ubah ID, harga,   Data divalidasi
                    Livewire          role, atau status dan otorisasi
                                      pada request      diterapkan

  S-08              Manipulasi total  Ubah nilai total  Server menghitung
                                      dari browser      ulang

  S-09              Upload berbahaya  Unggah            Upload ditolak
                                      tipe/ukuran tidak
                                      diizinkan

  S-10              Brute force       Lakukan percobaan Rate limit
                                      login berulang    berjalan

  S-11              Race condition    Dua checkout      Stok tidak menjadi
                    stok              bersamaan untuk   negatif/oversold
                                      stok terbatas

  S-12              Double processing Kirim aksi        Perubahan stok
                                      selesai/batal dua hanya terjadi satu
                                      kali              kali

  S-13              Status ilegal     Lewati tahapan    Transisi ditolak
                                      status

  S-14              CSRF              Uji request       Ditolak oleh
                                      perubahan state   proteksi framework
                                      tanpa token sah
  ------------------------------------------------------------------------

Lakukan pengujian hanya pada lingkungan lokal/staging yang diizinkan,
bukan terhadap sistem pihak lain.

## 7. Pengujian stok bersamaan

Skenario: 1. Siapkan satu varian dengan stok tersedia terbatas. 2.
Jalankan dua proses checkout secara bersamaan. 3. Kedua proses meminta
kuantitas yang jika dijumlahkan melebihi stok tersedia. 4. Pastikan
transaksi yang tidak memenuhi stok ditolak. 5. Pastikan nilai stok tidak
negatif. 6. Pastikan jumlah reservasi sesuai pesanan yang berhasil. 7.
Ulangi untuk pembatalan dan penyelesaian agar tidak terjadi
pengurangan/pelepasan ganda.

Pengujian harus menggunakan database yang mendukung transaksi dan row
locking sebagaimana lingkungan target. SQLite in-memory tidak selalu
merepresentasikan perilaku locking MySQL.

## 8. Pengujian Hybrid SPA

-   Tautan internal yang menggunakan `wire:navigate` tidak melakukan
    full document reload.
-   Navigasi tetap menghormati middleware dan otorisasi.
-   Back/forward browser bekerja sesuai perilaku Livewire.
-   Search menggunakan debounce dan hasilnya sesuai.
-   Loading indicator muncul saat request berjalan.
-   Modal/dropdown Alpine dapat dibuka dan ditutup.
-   Event listener tidak terduplikasi setelah navigasi.
-   Komponen tidak menampilkan data lama milik pengguna sebelumnya.
-   Polling dihentikan atau dibatasi ketika komponen tidak relevan.
-   Error jaringan ditampilkan dengan aman.

## 9. Data uji

Gunakan data sintetis: - Akun satu Owner. - Akun Karyawan. - Dua akun
Pembeli. - Produk dan varian dummy. - Stok kecil untuk uji race
condition. - Pesanan dummy dengan beberapa status. - File bukti
pembayaran dummy yang tidak memuat data asli.

Jangan memasukkan kredensial produksi atau data pelanggan nyata ke
fixture.

## 10. Prosedur menjalankan test

Periksa dahulu script dan dependency yang tersedia pada repository.
Perintah umum Laravel:

``` bash
php artisan test
```

Jika Laravel Pint tersedia:

``` bash
./vendor/bin/pint --test
```

Untuk test tertentu, gunakan filter sesuai nama test yang benar-benar
ada:

``` bash
php artisan test --filter=NamaTest
```

Jangan menganggap semua perintah tersedia sebelum memeriksa proyek.
Jalankan migration/fresh hanya pada database pengujian yang aman dan
telah dipastikan bukan database berisi data penting.

## 11. Format laporan hasil

  Item                      Hasil
  ------------------------- -------
  Tanggal dan waktu
  Commit/versi yang diuji
  Lingkungan
  Perintah test
  Jumlah passed
  Jumlah failed
  Test dilewati
  Temuan keamanan
  Bukti/log
  Status akhir

Status akhir hanya boleh `Lulus`, `Lulus dengan catatan`, atau
`Belum lulus` berdasarkan hasil aktual.

## 12. Definition of Done

-   Test fitur yang berubah berhasil.
-   Test otorisasi dan validasi relevan berhasil.
-   Tidak ada temuan keamanan kritis yang belum ditangani.
-   Pengujian regresi alur terkait berhasil.
-   Format kode sesuai standar proyek.
-   Hasil dan keterbatasan test dicatat.
-   `AI_USAGE.md` diperbarui bila AI membantu pekerjaan.
