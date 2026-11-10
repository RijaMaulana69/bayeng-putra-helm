# DATABASE.md --- Rancangan Data Bayeng Putra Helm

## 1. Tujuan

Dokumen ini menjadi tempat merancang entitas, atribut, relasi,
constraint, dan indeks sebelum migration dibuat. Ini bukan schema final
dan tidak boleh langsung diterjemahkan menjadi migration tanpa review.

## 2. Entitas konseptual

  Entitas             Tanggung jawab
  ------------------- -------------------------------------------------
  users               Akun dan role
  brands              Merek helm
  helmet_types        Jenis/kategori helm, perlu verifikasi kebutuhan
  products            Model produk
  product_images      Foto produk
  variants            SKU, ukuran, warna/motif, harga, stok
  orders              Header pesanan
  order_items         Item pesanan dan snapshot harga
  payments            Metode, status, dan bukti pembayaran
  stock_movements     Riwayat pergerakan stok
  order_status_logs   Riwayat status
  chat_threads        Thread percakapan
  chat_messages       Isi pesan
  notifications       Notifikasi
  audit_logs          Aktivitas penting
  settings            Konfigurasi toko

## 3. Aturan penting

-   SKU varian unik.
-   Harga dan stok berada pada level varian.
-   `stok_tersedia = stok_fisik - stok_dipesan`.
-   `order_items` menyimpan snapshot harga dan informasi produk yang
    diperlukan untuk riwayat.
-   Pesanan terhubung dengan pembeli.
-   Bukti pembayaran menyimpan referensi path privat, bukan URL publik.
-   Relasi transaksi memakai foreign key dan constraint.
-   Penghapusan data historis harus dibatasi; gunakan status/nonaktif
    bila sesuai.

## 4. Keputusan yang perlu dibuat sebelum migration

-   Apakah `helmet_types` merupakan entitas terpisah atau
    atribut/kategori produk.
-   Apakah satu order dapat memiliki beberapa record pembayaran.
-   Apakah keranjang disimpan di tabel atau mekanisme lain yang
    disetujui.
-   Kardinalitas chat thread terhadap user dan order.
-   Format ID (integer, UUID, atau ULID).
-   Soft delete untuk entitas mana.
-   Unique constraints dan indeks pencarian.
-   Aturan retensi audit log dan file.
-   Detail status pembayaran dan status pesanan final.

## 5. Prosedur perubahan schema

1.  Jelaskan kebutuhan dan dampak.
2.  Tinjau relasi dan data historis.
3.  Buat rencana migration dan rollback.
4.  Minta persetujuan untuk perubahan besar.
5.  Implementasikan migration dan model.
6.  Tambahkan test constraint dan relasi.
7.  Jalankan pada database pengujian.
8.  Dokumentasikan perubahan.

Jangan menjalankan `migrate:fresh` pada database yang berisi data
penting.
