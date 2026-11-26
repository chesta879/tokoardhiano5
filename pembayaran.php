<?php
require 'config.php'; wajib_login();
$p = pelanggan(); $id = (int)$p['id'];
$tab = $_GET['tab'] ?? 'bayar';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'kirim_bukti') {
  cek_csrf();
  $kode = trim($_POST['kode'] ?? '');
  $st = $db->prepare('SELECT id_pelanggan, status_pembayaran, status, metode_pembayaran FROM tbl_pesanan WHERE kode_pesanan=?');
  $st->execute([$kode]); $o = $st->fetch();
  if (!$o || (int)$o['id_pelanggan'] !== $id) { http_response_code(404); exit('Pesanan tidak ditemukan.'); }
  if ($o['metode_pembayaran'] === 'Duitku') { flash('Pembayaran pesanan ini harus diselesaikan melalui Duitku.'); pindah('pembayaran.php?tab=bayar'); }
  if (!in_array($o['status_pembayaran'], ['Belum dibayar', 'Ditolak'], true) || in_array($o['status'], ['Dibatalkan', 'Selesai'], true)) {
    flash('Pesanan tidak dapat dikonfirmasi pembayarannya.'); pindah('pembayaran.php?tab=bayar');
  }
  if (!isset($_FILES['bukti']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) { flash('Pilih file bukti pembayaran terlebih dahulu.'); pindah('pembayaran.php?tab=bayar'); }
  $file = $_FILES['bukti'];
  $allowed = ['image/jpeg','image/png','image/webp'];
  if (!in_array($file['type'], $allowed, true)) { flash('Format file harus JPG, PNG, atau WebP.'); pindah('pembayaran.php?tab=bayar'); }
  if ($file['size'] > 5*1024*1024) { flash('Ukuran file maksimal 5 MB.'); pindah('pembayaran.php?tab=bayar'); }
  $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
  $nama_file = 'bukti_'.$kode.'_'.bin2hex(random_bytes(4)).'.'.strtolower($ext);
  $tujuan = __DIR__.'/assets/bukti/'.$nama_file;
  if (!is_dir(__DIR__.'/assets/bukti')) { mkdir(__DIR__.'/assets/bukti', 0775, true); }
  if (!move_uploaded_file($file['tmp_name'], $tujuan)) { flash('Gagal menyimpan bukti pembayaran.'); pindah('pembayaran.php?tab=bayar'); }
  $db->prepare('UPDATE tbl_pesanan SET bukti_pembayaran=?, status_pembayaran="Menunggu verifikasi" WHERE kode_pesanan=?')->execute(['bukti/'.$nama_file, $kode]);
  flash('Bukti pembayaran berhasil dikirim. Tunggu verifikasi staf.'); pindah('pembayaran.php?tab=riwayat');
}

$st = $db->prepare("
  SELECT o.kode_pesanan, o.status, o.status_pembayaran, o.metode_pembayaran, o.bukti_pembayaran, o.dibuat_pada, o.alamat_pengiriman
  FROM tbl_pesanan o WHERE o.id_pelanggan=?
");
if ($tab === 'bayar') {
  $st = $db->prepare("
    SELECT o.kode_pesanan, o.status, o.status_pembayaran, o.metode_pembayaran, o.bukti_pembayaran, o.dibuat_pada, o.alamat_pengiriman
    FROM tbl_pesanan o WHERE o.id_pelanggan=? AND o.status_pembayaran='Belum dibayar' AND o.status NOT IN ('Dibatalkan','Selesai') ORDER BY o.dibuat_pada DESC
  ");
  $st->execute([$id]); $daftar = $st->fetchAll();
} else {
  $st->execute([$id]); $daftar = $st->fetchAll();
}
$judul = 'Pembayaran';
include 'partials/header.php';
?>
<h2 class="judul">Pembayaran</h2>
<div class="baris" style="gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <a class="btn abu <?= $tab === 'bayar' ? 'current' : '' ?>" href="pembayaran.php?tab=bayar">Bayar Pesanan</a>
  <a class="btn abu <?= $tab === 'riwayat' ? 'current' : '' ?>" href="pembayaran.php?tab=riwayat">Riwayat Pembayaran</a>
</div>

<?php if ($tab === 'bayar'): ?>
  <?php if (!$daftar): ?>
    <p class="kosong">Tidak ada pesanan yang menunggu pembayaran. <a href="index.php">Mulai belanja</a></p>
  <?php endif; ?>
  <?php foreach ($daftar as $o):
    $stt = $db->prepare('SELECT pr.nama_produk, d.jumlah, d.harga FROM tbl_transaksi t JOIN tbl_detail_transaksi d USING(id_transaksi) JOIN tbl_produk pr USING(id_produk) WHERE t.kode_pesanan=?');
    $stt->execute([$o['kode_pesanan']]); $it = $stt->fetchAll(); $tot = 0; foreach ($it as $i) $tot += $i['jumlah'] * $i['harga']; ?>
  <section class="pesanan">
    <header><strong><?= e($o['kode_pesanan']) ?></strong><span class="status s-diproses"><?= e($o['status']) ?></span></header>
    <p class="muted"><?= e($o['dibuat_pada']) ?> · <?= e($o['metode_pembayaran']) ?> · <?= e($o['alamat_pengiriman']) ?></p>
    <div class="konfirmasi-pembayaran"><strong>Pembayaran: <?= e($o['status_pembayaran']) ?></strong><p class="muted" style="margin:4px 0 0">Total <?= rupiah($tot) ?>. Bayar sesuai total, lalu unggah bukti.</p>
    <?php if ($o['metode_pembayaran'] === 'QRIS'): ?>
      <?php if (is_file(__DIR__.'/assets/qris.jpg')): ?><p>Pindai QRIS toko dan bayar sesuai total pesanan, lalu unggah bukti pembayaran.</p><img class="qris-image" src="assets/qris.jpg" alt="QRIS toko"><?php elseif (is_file(__DIR__.'/assets/qris.png')): ?><p>Pindai QRIS toko dan bayar sesuai total pesanan, lalu unggah bukti pembayaran.</p><img class="qris-image" src="assets/qris.png" alt="QRIS toko"><?php else: ?><p class="muted">QRIS toko belum tersedia. Hubungi toko untuk menyelesaikan pembayaran.</p><?php endif; ?>
    <?php endif; ?>
    <?php if ($o['metode_pembayaran'] === 'Duitku'): ?>
      <p>Pembayaran diproses otomatis oleh Duitku.</p><a class="btn" href="duitku_bayar.php?kode=<?= urlencode($o['kode_pesanan']) ?>">Lanjutkan pembayaran Duitku</a>
    <?php else: ?>
    <form method="post" action="pembayaran.php?tab=bayar" enctype="multipart/form-data" class="unggah-bukti" style="margin-top:8px">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="kirim_bukti"><input type="hidden" name="kode" value="<?= e($o['kode_pesanan']) ?>">
      <label>Bukti pembayaran (JPG, PNG, atau WebP; maks. 5 MB)<input type="file" name="bukti" accept="image/jpeg,image/png,image/webp" required></label>
      <button class="btn">Kirim bukti</button>
    </form>
    <?php endif; ?>
    </div>
    <ul><?php foreach ($it as $i): ?><li><?= e($i['nama_produk']) ?> × <?= $i['jumlah'] ?><span><?= rupiah($i['jumlah'] * $i['harga']) ?></span></li><?php endforeach; ?></ul>
    <p class="tot">Total<span><?= rupiah($tot) ?></span></p>
    <a class="btn abu cetak-struk" href="struk.php?kode=<?= urlencode($o['kode_pesanan']) ?>">Cetak struk</a>
  </section>
  <?php endforeach; ?>
<?php else: ?>
  <?php if (!$daftar): ?>
    <p class="kosong">Belum ada riwayat pembayaran.</p>
  <?php endif; ?>
  <?php foreach ($daftar as $o):
    $stt = $db->prepare('SELECT pr.nama_produk, d.jumlah, d.harga FROM tbl_transaksi t JOIN tbl_detail_transaksi d USING(id_transaksi) JOIN tbl_produk pr USING(id_produk) WHERE t.kode_pesanan=?');
    $stt->execute([$o['kode_pesanan']]); $it = $stt->fetchAll(); $tot = 0; foreach ($it as $i) $tot += $i['jumlah'] * $i['harga']; ?>
  <section class="pesanan">
    <header><strong><?= e($o['kode_pesanan']) ?></strong><span class="status s-<?= e(strtolower($o['status'])) ?>"><?= e($o['status']) ?></span></header>
    <p class="muted"><?= e($o['dibuat_pada']) ?> · <?= e($o['metode_pembayaran']) ?> · Bayar: <?= e($o['status_pembayaran']) ?></p>
    <div class="konfirmasi-pembayaran">
      <?php if ($o['bukti_pembayaran']): ?><strong>Bukti:</strong> <a href="bukti_pembayaran.php?kode=<?= urlencode($o['kode_pesanan']) ?>" target="_blank" rel="noopener">Lihat bukti</a><?php else: ?><span class="muted">Belum ada bukti</span><?php endif; ?>
    </div>
    <ul><?php foreach ($it as $i): ?><li><?= e($i['nama_produk']) ?> × <?= $i['jumlah'] ?><span><?= rupiah($i['jumlah'] * $i['harga']) ?></span></li><?php endforeach; ?></ul>
    <p class="tot">Total<span><?= rupiah($tot) ?></span></p>
    <a class="btn abu cetak-struk" href="struk.php?kode=<?= urlencode($o['kode_pesanan']) ?>">Cetak struk</a>
  </section>
  <?php endforeach; ?>
<?php endif; ?>
<?php include 'partials/footer.php'; ?>