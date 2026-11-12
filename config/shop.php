<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Identitas Toko
    |--------------------------------------------------------------------------
    |
    | Informasi dasar profil toko untuk keperluan tampilan antarmuka katalog,
    | keranjang belanja, dan halaman instruksi pembayaran sesuai ketetapan PRD.
    |
    */
    'nama_toko' => 'Bayeng Putra Helm',
    'deskripsi' => 'Aplikasi E-Commerce Simulasi Penjualan Helm',
    'alamat' => 'Jl. RE Martadinata No. 49, Cijoho – Kuningan',

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Pembayaran Simulasi
    |--------------------------------------------------------------------------
    |
    | Proyek Bayeng Putra Helm adalah aplikasi demonstrasi dan tugas kuliah.
    | Seluruh metode pembayaran di bawah ini bersifat SIMULASI MANUAL tanpa
    | pemrosesan transaksi perbankan atau payment gateway sungguhan.
    |
    */
    'pembayaran' => [
        'simulasi' => true,
        'peringatan' => 'PERHATIAN: INI ADALAH APLIKASI SIMULASI TUGAS KULIAH. JANGAN MELAKUKAN TRANSFER UANG SUNGGUHAN KE REKENING/NOMOR DI BAWAH INI.',
        'instruksi_umum' => 'Gunakan bukti transfer dummy atau gambar sampel untuk menguji alur verifikasi pesanan.',

        /*
        |--------------------------------------------------------------------------
        | 1. Rekening Bank Transfer Manual (Simulasi)
        |--------------------------------------------------------------------------
        */
        'bank' => [
            'nama' => 'Bank BCA (SIMULASI)',
            'nomor' => 'DUMMY-BCA-0000',
            'atas_nama' => 'Bayeng Putra Helm Demo',
            'instruksi' => 'Silakan transfer simulasi ke nomor rekening dummy di atas dan unggah bukti transfer sampel pada halaman pesanan.',
        ],

        /*
        |--------------------------------------------------------------------------
        | 2. Akun E-Wallet (Simulasi)
        |--------------------------------------------------------------------------
        |
        | Kunci array harus selaras dengan nilai string pada enum EwalletProvider
        | (DANA, GoPay, ShopeePay).
        |
        */
        'ewallet' => [
            'DANA' => [
                'provider' => 'DANA',
                'nomor' => 'DUMMY-DANA-0001',
                'atas_nama' => 'Bayeng Putra Helm DANA Demo',
                'instruksi' => 'Simulasikan transfer saldo DANA ke nomor akun demo di atas.',
            ],
            'GoPay' => [
                'provider' => 'GoPay',
                'nomor' => 'DUMMY-GOPAY-0002',
                'atas_nama' => 'Bayeng Putra Helm GoPay Demo',
                'instruksi' => 'Simulasikan transfer saldo GoPay ke nomor akun demo di atas.',
            ],
            'ShopeePay' => [
                'provider' => 'ShopeePay',
                'nomor' => 'DUMMY-SHOPEEPAY-0003',
                'atas_nama' => 'Bayeng Putra Helm ShopeePay Demo',
                'instruksi' => 'Simulasikan transfer saldo ShopeePay ke nomor akun demo di atas.',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | 3. Pembayaran Tunai di Toko (Pickup)
        |--------------------------------------------------------------------------
        */
        'bayar_di_toko' => [
            'nama' => 'Bayar Tunai di Kasir Toko',
            'lokasi_pickup' => 'Kasir Toko Bayeng Putra Helm',
            'instruksi' => 'Tunjukkan nomor pesanan kepada kasir saat pengambilan barang dan bayar tunai sesuai nominal tagihan.',
        ],
    ],
];
