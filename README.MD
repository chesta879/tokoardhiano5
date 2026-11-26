CARA PAKAI (XAMPP / Laragon / hosting PHP 8+)
1. Salin folder "tokoardhiano" ke htdocs (XAMPP) atau www (Laragon).
2. Impor db_toko.sql lewat phpMyAdmin (tab Import).
3. Cek config.php (DB_USER/DB_PASS). Bawaan XAMPP: root, tanpa kata sandi.
4. Buka http://localhost/tokoardhiano/
5. Buka http://localhost/tokoardhiano/admin/login.php. Jika database belum memiliki pengguna staf,
   buat admin pertama di halaman tersebut dengan username dan kata sandi pilihan sendiri
   (kata sandi minimal 6 karakter).
6. Akun kasir dibuat setelah admin masuk melalui menu Pengguna staf. Kasir dapat masuk melalui
   http://localhost/tokoardhiano/kasir/login.php (halaman ini meneruskan ke login staf).

PERSIAPAN DEMO / PENGUMPULAN
- Database dari db_toko.sql berisi produk contoh, tetapi tidak membuat akun admin atau kasir.
  Buat admin pertama saat membuka halaman login staf, lalu buat akun kasir dari panel admin bila
  fitur kasir perlu didemokan.
- Buat akun pelanggan dari menu Daftar untuk mencoba keranjang, checkout, dan riwayat pesanan.
- Untuk demo yang stabil tanpa akses jaringan, pilih COD atau metode pembayaran manual. Duitku
  memerlukan koneksi internet, kredensial sandbox, dan callback HTTPS publik agar status lunas
  diperbarui otomatis. Jangan tampilkan atau kumpulkan API key bersama source code.
- URL callback Duitku lokal `localhost` tidak dapat dijangkau server Duitku. Jangan anggap
  halaman pembayaran sandbox yang masih Pending sebagai bukti pembayaran berhasil.
- Sebelum dikumpulkan, salin folder proyek tanpa file rahasia/kredensial. Sertakan db_toko.sql
  agar guru dapat mengimpor struktur dan data contoh.

KONFIRMASI PEMBAYARAN
- Database baru: impor db_toko.sql seperti biasa.
- Database yang sudah digunakan: jalankan sekali di phpMyAdmin:
  ALTER TABLE tbl_pesanan 
    ADD status_pembayaran VARCHAR(30) NOT NULL DEFAULT 'Belum dibayar',
    ADD bukti_pembayaran VARCHAR(40) NULL;
  UPDATE tbl_pesanan SET status_pembayaran='Tidak perlu konfirmasi'
    WHERE metode_pembayaran='COD';
- Pelanggan mengunggah bukti JPG, PNG, atau WebP dari halaman Pesanan saya.
  Staf memeriksa bukti dan mengubah status konfirmasi pada panel Pesanan.
- Untuk QRIS, pelanggan memindai QR toko lalu mengunggah bukti pembayaran di halaman Pesanan saya.
- Gambar QRIS merchant disimpan di `assets/qris.jpg` dan ditampilkan pada pesanan QRIS yang belum dibayar.
- Metode Duitku tidak menggunakan unggah bukti manual. Status menjadi "Sudah dibayar" setelah
  callback Duitku yang valid diterima.

PAYMENT GATEWAY DUITKU
- Untuk instalasi XAMPP ini, konfigurasi merchant lokal di folder `tokokita/config/duitku.php`
  digunakan otomatis jika ada. Nilai API key tidak disalin ke source proyek ini.
- Hosting: atur variabel lingkungan `DUITKU_MERCHANT_CODE`, `DUITKU_API_KEY`,
  `DUITKU_MODE` (`sandbox` atau `production`), dan `DUITKU_BASE_URL`
  (URL HTTPS publik situs tanpa garis miring di akhir).
- `DUITKU_RETURN_URL` mengatur halaman setelah tombol Tutup di halaman Duitku.
  Default XAMPP: `http://localhost/tokoardhiano/index.php`. Untuk pengunjung situs
  hosting, atur ke URL HTTPS toko yang publik. URL ini terpisah dari callback.
- URL dasar yang digunakan saat ini: `https://sterida.my.id/ardhiano`.
- `DUITKU_BASE_URL` harus dapat diakses dari internet oleh Duitku untuk callback.
  Callback dikirim ke `DUITKU_BASE_URL/duitku_callback.php`. Situs perlu diunggah
  pada URL tersebut sebelum pembayaran sandbox bisa memperbarui status pesanan otomatis.
- Aktifkan ekstensi PHP cURL. Pilihan Duitku tampil saat checkout; pelanggan memilih kanal
  pembayaran pada halaman hosted Duitku.
- Database baru: tabel `tbl_duitku_pembayaran` sudah termasuk di `db_tokoku.sql`.
  Database yang sudah digunakan: impor `migration_duitku.sql` sekali lewat phpMyAdmin.
- Pembayaran dianggap lunas dan status pesanan menjadi "Selesai" hanya setelah callback Duitku
  dengan signature dan nominal valid diterima. Jangan mengandalkan redirect kembali dari halaman
  pembayaran sebagai bukti lunas.
- Jangan simpan API key di repositori atau bagikan ke pihak lain. Gunakan mode sandbox untuk pengujian.

KODE PRODUK & BARCODE
- Setiap produk mendapat kode otomatis `PRD` diikuti nomor produk, misalnya `PRD000001`.
- Kode dapat diganti dengan nomor barcode produk lewat menu staf > Produk. Kode/barcode unik dan dicari lewat kolom pencarian toko; scanner barcode yang mengirim hasil sebagai teks lalu Enter dapat langsung mencari produk.
- Database yang sudah digunakan: jalankan sekali di phpMyAdmin:
  ALTER TABLE tbl_produk ADD kode_produk VARCHAR(64) NULL UNIQUE;
  UPDATE tbl_produk SET kode_produk=CONCAT('PRD',LPAD(id_produk,6,'0')) WHERE kode_produk IS NULL;

FOTO PRODUK
- Staf dapat mengunggah foto JPG, PNG, atau WebP (maks. 5 MB) saat menambah
  atau mengubah produk di menu Produk. Foto yang diunggah menggantikan URL gambar.
- Folder uploads/produk harus dapat ditulisi oleh PHP. Foto tersimpan di folder
  tersebut dan tampil pada daftar serta halaman detail produk.

CETAK STRUK
- Pelanggan dapat mencetak struk dari halaman Pesanan saya.
- Staf dapat mencetak struk pesanan dari tabel Pesanan di panel staf.
- Halaman struk menyediakan tombol Cetak struk dan tata letak cetak thermal 80 mm.
- Struk mencetak barcode Code 39 dari kode pesanan. Saat barcode dipindai di panel Pesanan, detail struk transaksi yang cocok akan terbuka langsung.

EKSPOR LAPORAN (BARU)
- Laporan Penjualan & Transaksi (Kasir): menu Kasir > Laporan
  - Filter tanggal, tab Penjualan / Transaksi
  - Tombol "Ekspor CSV" → file .csv dengan UTF-8 BOM (bisa dibuka Excel)
  - Tombol "Cetak PDF" → halaman cetak dengan kop surat, bisa disimpan PDF via browser
- Laporan Admin: panel Admin > Dashboard
  - Filter periode, tombol Ekspor CSV & Cetak PDF
- Kop surat otomatis: nama toko, alamat, telepon, email, judul laporan, periode, timestamp

RANKING KINERJA KASIR (BARU)
- Panel Admin > Pengguna staf
- Tabel ranking: Rank, Kasir, Total Transaksi, Total Penjualan
- Diurutkan by Total Penjualan tertinggi (Rank 1 = 👑)
- Hanya transaksi kasir dengan status "Sudah dibayar"

MANAJEMEN AKUN KASIR (BARU)
- Panel Admin > Pengguna staf
- Tambah akun kasir (form sederhana)
- Edit: tombol Edit → modal untuk ubah nama, username, password (opsional)
- Hapus: tombol Hapus merah + konfirmasi, proteksi hapus akun sendiri
- Status: toggle Aktif/Nonaktif

DARK MODE
- Toggle tema Light/Dark di semua halaman (persist di localStorage)
- Warna teks otomatis menyesuaikan tema (body, tabel, form, card, modal, export)
- Mode cetak memaksa warna terang untuk hasil cetak yang baik

STRUKTUR FOLDER
tokoardhiano/
├── admin/           # Panel admin
│   ├── index.php    # Dashboard + ekspor laporan
│   ├── pengguna.php # Kelola akun + ranking kinerja
│   ├── produk.php   # Kelola produk
│   ├── login.php    # Login admin
│   └── _auth.php    # Auth middleware
├── kasir/           # Panel kasir
│   ├── index.php    # Dashboard kasir
│   ├── transaksi-baru.php  # POS kasir
│   ├── laporan.php  # Laporan kasir + ekspor
│   ├── pengguna.php # Profil kasir
│   ├── login.php    # Login kasir
│   └── _auth.php    # Auth middleware
├── partials/        # Header, footer, barcode
├── assets/          # CSS, JS, QRIS image
├── config.php       # DB config + helper functions
├── struk.php        # Cetak struk (thermal 80mm)
└── *.php            # Halaman pelanggan (toko online)

DATABASE
- db_toko.sql → struktur + data awal (produk, kategori, user, pelanggan)
- Tabel utama: tbl_pengguna, tbl_pelanggan, tbl_kategori, tbl_produk, tbl_pesanan, tbl_transaksi, tbl_detail_transaksi

URL UTAMA
- /                    → Toko online (katalog, keranjang, checkout)
- /admin/login.php     → Login admin
- /admin/index.php     → Dashboard admin + laporan
- /admin/pengguna.php  → Kelola akun staf + ranking
- /kasir/login.php     → Login kasir
- /kasir/transaksi-baru.php → POS kasir
- /kasir/laporan.php   → Laporan kasir + ekspor
- /struk.php?kode=...  → Cetak struk (pelanggan & staf)