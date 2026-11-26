<?php
require 'config.php';
if (($_GET['duitku'] ?? '') === 'return') pindah(DUITKU_RETURN_URL);
wajib_login();
$p = pelanggan(); $id = (int)$p['id'];
$filter = $_GET['status'] ?? 'semua';
$where = 'o.id_pelanggan=?'; $params = [$id];
if ($filter === 'pending') { $where .= ' AND o.status_pembayaran="Belum dibayar"'; }
elseif ($filter === 'diproses') { $where .= ' AND o.status="Diproses"'; }
elseif ($filter === 'selesai') { $where .= ' AND o.status="Selesai"'; }
$st = $db->prepare("SELECT * FROM tbl_pesanan o WHERE $where ORDER BY o.dibuat_pada DESC");
$st->execute($params); $daftar = $st->fetchAll();
$judul = 'Pesanan Saya';
include 'partials/header.php';
?>
<h2 class="judul">Pesanan Saya</h2>
<div class="baris" style="gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <a class="btn abu <?= $filter === 'semua' ? 'current' : '' ?>" href="pesanan.php">Semua</a>
  <a class="btn abu <?= $filter === 'pending' ? 'current' : '' ?>" href="pesanan.php?status=pending">Pesanan Pending</a>
  <a class="btn abu <?= $filter === 'diproses' ? 'current' : '' ?>" href="pesanan.php?status=diproses">Pesanan Diproses</a>
  <a class="btn abu <?= $filter === 'selesai' ? 'current' : '' ?>" href="pesanan.php?status=selesai">Riwayat Pesanan</a>
</div>
<?php if (!$daftar): ?>
  <p class="kosong">Belum ada pesanan<?= $filter !== 'semua' ? ' dengan status ini' : '' ?>. <a href="index.php">Mulai belanja</a></p>
<?php endif; ?>
<?php foreach ($daftar as $o):
  $st = $db->prepare('SELECT pr.nama_produk, d.jumlah, d.harga FROM tbl_transaksi t JOIN tbl_detail_transaksi d USING(id_transaksi) JOIN tbl_produk pr USING(id_produk) WHERE t.kode_pesanan=?');
  $st->execute([$o['kode_pesanan']]); $it = $st->fetchAll(); $tot = 0;
  $bisa_unggah = $o['metode_pembayaran'] !== 'COD'
    && $o['metode_pembayaran'] !== 'Duitku'
    && in_array($o['status_pembayaran'], ['Belum dibayar', 'Ditolak'], true)
    && !in_array($o['status'], ['Dibatalkan', 'Selesai'], true); ?>
<section class="pesanan">
  <header><strong><?= e($o['kode_pesanan']) ?></strong><span class="status s-<?= e(strtolower($o['status'])) ?>"><?= e($o['status']) ?></span></header>
  <p class="muted"><?= e($o['dibuat_pada']) ?> · <?= e($o['metode_pembayaran']) ?> · <?= e($o['alamat_pengiriman']) ?></p>
  <div class="konfirmasi-pembayaran"><strong>Pembayaran: <?= e($o['status_pembayaran']) ?></strong>
  <?php if ($o['bukti_pembayaran']): ?> <a href="bukti_pembayaran.php?kode=<?= urlencode($o['kode_pesanan']) ?>" target="_blank" rel="noopener">Lihat bukti</a><?php endif; ?>
  <?php if ($o['metode_pembayaran'] === 'QRIS' && in_array($o['status_pembayaran'], ['Belum dibayar', 'Ditolak'], true)): ?>
  <?php if (is_file(__DIR__.'/assets/qris.jpg')): ?><p>Pindai QRIS toko dan bayar sesuai total pesanan, lalu unggah bukti pembayaran.</p><img class="qris-image" src="assets/qris.jpg" alt="QRIS toko"><?php elseif (is_file(__DIR__.'/assets/qris.png')): ?><p>Pindai QRIS toko dan bayar sesuai total pesanan, lalu unggah bukti pembayaran.</p><img class="qris-image" src="assets/qris.png" alt="QRIS toko"><?php else: ?><p class="muted">QRIS toko belum tersedia. Silakan hubungi toko untuk menyelesaikan pembayaran.</p><?php endif; ?>
  <?php endif; ?>
  <?php if ($o['metode_pembayaran'] === 'Duitku' && $o['status_pembayaran'] === 'Belum dibayar' && $o['status'] !== 'Dibatalkan'): ?>
  <p>Pembayaran diproses otomatis oleh Duitku.</p><a class="btn" href="duitku_bayar.php?kode=<?= urlencode($o['kode_pesanan']) ?>">Lanjutkan pembayaran Duitku</a>
  <?php endif; ?>
  <?php if ($bisa_unggah): ?>
  <form method="post" action="konfirmasi_pembayaran.php" enctype="multipart/form-data" class="unggah-bukti">
    <?= csrf_field() ?><input type="hidden" name="kode" value="<?= e($o['kode_pesanan']) ?>">
    <label>Bukti pembayaran (JPG, PNG, atau WebP; maks. 5 MB)<input type="file" name="bukti" accept="image/jpeg,image/png,image/webp" required></label>
    <button class="btn">Kirim bukti</button>
  </form>
  <?php elseif ($o['metode_pembayaran'] === 'COD'): ?><p class="muted">Pembayaran dilakukan saat barang diterima.</p><?php elseif ($o['status_pembayaran'] === 'Menunggu verifikasi'): ?><p class="muted">Bukti pembayaran sedang diperiksa staf.</p><?php endif; ?>
  </div>
  <ul><?php foreach ($it as $i): $tot += $i['jumlah'] * $i['harga']; ?><li><?= e($i['nama_produk']) ?> × <?= $i['jumlah'] ?><span><?= rupiah($i['jumlah'] * $i['harga']) ?></span></li><?php endforeach; ?></ul>
  <p class="tot">Total<span><?= rupiah($tot) ?></span></p>
  <a class="btn abu cetak-struk" href="struk.php?kode=<?= urlencode($o['kode_pesanan']) ?>">Cetak struk</a>
</section>
<?php endforeach; include 'partials/footer.php'; ?>