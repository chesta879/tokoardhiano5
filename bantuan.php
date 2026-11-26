<?php
require 'config.php';
$cabang = $db->query('SELECT nama_cabang, kota FROM tbl_cabang ORDER BY nama_cabang')->fetchAll();
$judul = 'Bantuan';
include 'partials/header.php';
?>
<h2 class="judul">Bantuan</h2>
<div class="dua">
  <section class="ringkas">
    <h3>Kontak Toko Kita</h3>
    <p>Butuh bantuan mengenai pesanan, pembayaran, atau produk? Hubungi kami melalui salah satu cabang kami:</p>
    <ul>
      <?php foreach ($cabang as $c): ?><li><strong><?= e($c['nama_cabang']) ?></strong> &mdash; <?= e($c['kota']) ?></li><?php endforeach; ?>
    </ul>
    <p class="muted">Jam layanan: Senin &mdash; Jumat, 08.00 &mdash; 17.00 WIB.</p>
  </section>

  <section class="ringkas">
    <h3>Pertanyaan Umum</h3>
    <ul>
      <li><strong>Bagaimana cara memesan?</strong><br>Pilih produk di Katalog Produk, tambahkan ke Keranjang, lalu lanjut ke Pembayaran.</li>
      <li><strong>Bagaimana cara membayar?</strong><br>Pilih metode Transfer bank, COD, E-wallet, atau QRIS. Untuk pembayaran manual, unggah bukti di menu Pembayaran &rarr; Bayar Pesanan. Jika memilih Duitku, selesaikan pembayaran pada halaman Duitku; status diperbarui otomatis setelah callback diterima.</li>
      <li><strong>Apakah saya dapat mengubah pesanan?</strong><br>Pesanan yang belum dibayar dapat diubah melalui Keranjang. Pesanan yang sudah diproses silakan hubungi toko.</li>
      <li><strong>Di mana bukti pembayaran saya?</strong><br>Lihat di menu Pembayaran &rarr; Riwayat Pembayaran pada pesanan masing-masing.</li>
      <li><strong>Bagaimana cara cetak struk?</strong><br>Buka detail pesanan lalu klik tombol Cetak struk.</li>
    </ul>
  </section>
</div>
<?php include 'partials/footer.php'; ?>