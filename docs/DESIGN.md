# DESIGN.md --- Pedoman UI/UX Bayeng Putra Helm

  Informasi     Keterangan
  ------------- ----------------------------------
  Versi         0.1 --- Draft
  Gaya          Modern
  Warna aksen   Oranye
  Kepadatan     Balanced
  Cakupan       Publik, Pembeli, Karyawan, Owner

## 1. Tujuan desain

Membangun antarmuka toko helm yang modern, mudah dipahami, responsif,
dan konsisten. Pengalaman katalog terinspirasi pola marketplace modern,
tetapi identitas visual tetap milik Bayeng Putra Helm.

## 2. Identitas dan alamat toko

Nama: **Bayeng Putra Helm**\
Alamat: **Jl. RE Martadinata No.49, Cijoho -- Kuningan**

Warna aksen utama: oranye. Gunakan warna netral untuk latar, teks, dan
permukaan agar foto produk tetap menjadi fokus. Nilai warna final
ditentukan saat implementasi token desain.

## 3. Prinsip desain

-   Utamakan keterbacaan dan kejelasan tindakan.
-   Konsisten pada tombol, form, kartu produk, tabel, dan status.
-   Mobile-first dan responsif.
-   Tampilkan harga dalam format Rupiah.
-   Berikan feedback untuk loading, sukses, error, dan keadaan kosong.
-   Jangan mengandalkan warna saja untuk menyampaikan status.
-   Konfirmasi sebelum tindakan yang berdampak pada transaksi.

## 4. Area antarmuka

### Publik dan Pembeli

-   Header dengan identitas toko, pencarian, dan akses keranjang.
-   Katalog dengan filter yang mudah dijangkau.
-   Kartu produk dengan foto, nama, harga, dan ringkasan varian.
-   Detail produk dengan pemilihan warna/motif dan ukuran.
-   Keranjang dengan kuantitas dan ringkasan total.
-   Checkout yang menampilkan metode pembayaran dan informasi pickup.
-   Riwayat dan tracking pesanan.
-   Chat dan notifikasi.

### Karyawan

-   Dashboard operasional.
-   Antrean pesanan.
-   Detail pesanan dan verifikasi pembayaran.
-   Pengelolaan produk/stok sesuai izin.
-   Konfirmasi penyerahan pesanan.

### Owner

-   Ringkasan penjualan.
-   Pengelolaan produk, varian, harga, pengguna, dan pengaturan.
-   Laporan sesuai hak akses.
-   Informasi HPP/laba hanya untuk Owner jika fitur tersebut telah
    disetujui.

### PoS

PoS walk-in merupakan prioritas P1. Antarmuka harus mendukung pencarian
produk cepat, pemilihan varian, jumlah, ringkasan, pembayaran, dan
struk.

## 5. Interaksi Hybrid SPA

-   Gunakan `wire:navigate` untuk navigasi internal yang sesuai.
-   Gunakan Livewire untuk pencarian, filter, keranjang, checkout, dan
    data operasional.
-   Gunakan Alpine.js untuk dropdown, modal, tab, dan sidebar lokal.
-   Berikan indikator loading saat request berlangsung.
-   Hindari mengirim request berlebihan; gunakan debounce pada
    pencarian.
-   Ingat bahwa interaksi server tetap memerlukan jaringan.

## 6. Komponen reusable

Komponen yang disarankan: - Button dan icon button. - Input, select,
textarea, checkbox. - Product card. - Price display. - Stock badge. -
Order status badge. - Modal confirmation. - Empty state. - Loading
skeleton. - Alert/toast. - Pagination. - Table dan filter toolbar.

Komponen harus mengikuti konvensi yang sudah ada di repository sebelum
membuat abstraksi baru.

## 7. Responsivitas

-   Mobile: navigasi ringkas, filter mudah dibuka, tombol utama mudah
    disentuh.
-   Tablet: manfaatkan ruang dua kolom bila sesuai.
-   Desktop: gunakan sidebar/dashboard dan grid katalog yang
    proporsional.
-   Hindari overflow horizontal yang tidak diperlukan.

## 8. UX states

Setiap fitur data harus mempertimbangkan: - Initial/loading. -
Success. - Empty. - Validation error. - Server/network error. -
Disabled/pending. - Unauthorized/not found.

## 9. Aksesibilitas

-   Label form jelas.
-   Kontras teks memadai.
-   Fokus keyboard terlihat.
-   Tombol memiliki nama yang jelas.
-   Gambar produk memiliki alt text yang sesuai.
-   Status tidak hanya dibedakan melalui warna.

## 10. Keamanan pada UI

Jangan tampilkan bukti pembayaran privat melalui URL publik. Jangan
mengandalkan penyembunyian tombol sebagai otorisasi. Pesan error tidak
boleh membocorkan stack trace, query, atau detail internal.
