# THREAT_MODEL.md --- Model Ancaman Bayeng Putra Helm

## 1. Tujuan

Mengidentifikasi aset, pihak yang dapat mengancam, jalur serangan,
dampak, dan kontrol mitigasi pada website Bayeng Putra Helm.

## 2. Aset dan batas kepercayaan

Aset utama: akun, data pembeli, pesanan, pembayaran, bukti pembayaran,
harga, stok, chat, laporan, secret, dan backup.

Batas kepercayaan: - Browser pengguna tidak tepercaya. - Request
Livewire tidak tepercaya. - File upload tidak tepercaya. - Database dan
private storage hanya boleh diakses aplikasi/administrator yang
berwenang. - Karyawan dan Owner memiliki tingkat kewenangan berbeda.

## 3. Aktor dan ancaman

-   Pengguna anonim yang mencoba menyalahgunakan katalog atau form.
-   Pembeli yang mencoba mengakses data pembeli lain.
-   Akun pembeli yang diambil alih.
-   Pengguna internal yang menyalahgunakan kewenangan.
-   Bot yang melakukan brute force atau spam.
-   Penyerang yang memanipulasi request browser atau upload file.

## 4. Daftar ancaman

  ---------------------------------------------------------------------------
  ID                Ancaman           Dampak                Mitigasi utama
  ----------------- ----------------- --------------------- -----------------
  T-01              SQL Injection     Kebocoran/perubahan   Eloquent,
                                      data                  binding, validasi

  T-02              XSS               Eksekusi script pada  Blade escaping,
                                      browser               sanitasi bila
                                                            diperlukan

  T-03              IDOR              Data                  Policy dan
                                      pesanan/chat/file     ownership checks
                                      bocor

  T-04              Broken access     Pembeli mengakses     Middleware,
                    control           fungsi staf           Policy, server
                                                            authorization

  T-05              Manipulasi        Kerugian transaksi    Hitung ulang
                    harga/total                             seluruh nilai di
                                                            server

  T-06              Manipulasi        Perubahan             Locked property,
                    Livewire          ID/status/kuantitas   validation,
                                      ilegal                authorization

  T-07              Race condition    Overselling dan stok  DB transaction,
                    stok              tidak konsisten       row lock,
                                                            idempotensi

  T-08              Double submission Stok/uang diproses    Idempotent
                                      ganda                 operation dan
                                                            constraint

  T-09              Upload berbahaya  Eksekusi/penyebaran   Validasi file dan
                                      file berbahaya        private storage

  T-10              Brute force       Pengambilalihan akun  Rate limit dan
                                                            kebijakan
                                                            autentikasi

  T-11              Session           Pengambilalihan sesi  HTTPS, secure
                    theft/fixation                          cookie, session
                                                            regeneration

  T-12              Secret exposure   Akses tidak sah ke    `.env`, secret
                                      layanan               management, akses
                                                            minimum

  T-13              Log leakage       Data sensitif terbuka Redaksi log dan
                                                            pembatasan akses

  T-14              CSRF              Aksi tanpa            Proteksi CSRF
                                      persetujuan pengguna  framework
  ---------------------------------------------------------------------------

## 5. Prioritas penanganan

Prioritaskan: 1. Broken access control dan IDOR. 2. Manipulasi harga,
pembayaran, dan status. 3. Race condition dan integritas stok. 4.
Kebocoran bukti pembayaran. 5. SQL Injection dan XSS. 6. Pengambilalihan
akun dan penyalahgunaan endpoint.

Prioritas dapat diperbarui setelah threat review dan hasil pengujian.

## 6. Skenario penyalahgunaan penting

### Pembeli mengakses pesanan orang lain

Penyerang mengubah identifier order pada URL atau request Livewire.
Sistem harus memeriksa kepemilikan pada server dan menolak akses.

### Pembeli mengubah harga

Penyerang mengubah nilai harga/total pada browser. Backend harus
mengambil harga dari database dan menghitung ulang total.

### Dua pembeli memesan stok terakhir

Dua checkout berjalan bersamaan. Backend harus mengunci baris varian dan
memeriksa stok dalam transaksi sehingga hanya transaksi yang memenuhi
stok yang berhasil.

### Pemanggilan penyelesaian berulang

Aksi selesai dikirim beberapa kali. Backend harus memastikan stok hanya
dipotong satu kali.

### Bukti pembayaran diakses publik

Penyerang menebak URL file. Bukti harus berada di private storage dan
disajikan melalui pemeriksaan otorisasi.

## 7. Validasi dan pembaruan

Threat model ditinjau: - Saat modul baru dirancang. - Saat ada perubahan
database, auth, RBAC, stok, pembayaran, atau file. - Setelah temuan
keamanan. - Sebelum deployment produksi.

Setiap ancaman harus ditautkan dengan kontrol di `SECURITY.md` dan
skenario verifikasi di `TESTING.md`.
