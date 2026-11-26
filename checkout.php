<?php
require 'duitku.php'; wajib_login();
if (empty($_SESSION['cart'])) { flash('Keranjang masih kosong.'); pindah('keranjang.php'); }
$p = pelanggan(); $err = '';
$ids = array_keys($_SESSION['cart']); $in = implode(',', array_fill(0, count($ids), '?'));
$st = $db->prepare("SELECT * FROM tbl_produk WHERE id_produk IN ($in)"); $st->execute($ids);
$items = $st->fetchAll(); $total = 0;
foreach ($items as $r) $total += $r['harga'] * ($_SESSION['cart'][$r['id_produk']] ?? 0);
$duitku_total_valid = $total > 0 && abs($total - round($total)) < 0.00001;
foreach ($items as $r) {
  if (abs((float)$r['harga'] - round((float)$r['harga'])) >= 0.00001) $duitku_total_valid = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  cek_csrf(); $alamat = trim($_POST['alamat'] ?? ''); $metode = $_POST['metode'] ?? '';
  $pakai_duitku = $metode === 'Duitku';
  if ($pakai_duitku && !duitku_configured()) {
    $err = 'Konfigurasi Duitku belum lengkap.';
  } elseif (!$pakai_duitku && !in_array($metode, ['Transfer bank', 'COD', 'E-wallet', 'QRIS'], true)) {
    $err = 'Pilih metode pembayaran yang tersedia.';
  }
  if ($alamat === '') $err = 'Isi alamat pengiriman.';
  if ($pakai_duitku && !$duitku_total_valid) {
    $err = 'Pembayaran Duitku hanya mendukung total pesanan dalam rupiah bulat.';
  }
  if ($err === '') {
    $metode_pesanan = $metode;
    try {
      $db->beginTransaction();
      $kode = 'PSN'.date('ymd').strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
      $status_pembayaran = $metode === 'COD' ? 'Tidak perlu konfirmasi' : 'Belum dibayar';
      $db->prepare('INSERT INTO tbl_pesanan(kode_pesanan,id_pelanggan,alamat_pengiriman,metode_pembayaran,status_pembayaran) VALUES(?,?,?,?,?)')->execute([$kode, $p['id'], $alamat, $metode_pesanan, $status_pembayaran]);
      foreach ($_SESSION['cart'] as $id => $jml) {
        $st = $db->prepare('SELECT nama_produk,harga,stok FROM tbl_produk WHERE id_produk=? FOR UPDATE'); $st->execute([$id]); $r = $st->fetch();
        if (!$r || $r['stok'] < $jml) throw new Exception('Stok '.($r['nama_produk'] ?? 'produk').' tidak mencukupi. Kurangi jumlah di keranjang.');
        $db->prepare('INSERT INTO tbl_transaksi(tanggal,id_pelanggan,total_belanja,kode_pesanan) VALUES(CURDATE(),?,?,?)')->execute([$p['id'], $r['harga'] * $jml, $kode]);
        $idt = $db->lastInsertId();
        $db->prepare('INSERT INTO tbl_detail_transaksi(id_transaksi,id_produk,jumlah,harga) VALUES(?,?,?,?)')->execute([$idt, $id, $jml, $r['harga']]);
        $db->prepare('UPDATE tbl_produk SET stok=stok-? WHERE id_produk=?')->execute([$jml, $id]);
      }
      $db->commit(); $_SESSION['cart'] = [];
      flash("Pesanan $kode berhasil dibuat.");
      if ($pakai_duitku) pindah('duitku_bayar.php?kode='.urlencode($kode));
      pindah('pesanan.php');
    } catch (Exception $ex) { if ($db->inTransaction()) $db->rollBack(); $err = $ex->getMessage(); }
  }
}
$judul = 'Pembayaran'; include 'partials/header.php';
?>
<h2 class="judul">Pembayaran</h2>
<?php if ($err): ?><div class="info salah"><?= e($err) ?></div><?php endif; ?>
<?php if (!duitku_configured()): ?><div class="info">Pembayaran Duitku belum dikonfigurasi. Metode pembayaran lama tetap dapat digunakan.</div>
<?php elseif (!$duitku_total_valid): ?><div class="info">Duitku tersedia untuk pembayaran dengan nominal rupiah bulat.</div><?php endif; ?>
<div class="dua">
<form method="post" class="form"><?= csrf_field() ?>
  <label>Alamat pengiriman<textarea name="alamat" rows="4" required><?= e($_POST['alamat'] ?? '') ?></textarea></label>
  <label>Metode pembayaran<select name="metode"><?php foreach (['Transfer bank', 'COD', 'E-wallet', 'QRIS'] as $pilihan): ?><option value="<?= e($pilihan) ?>" <?= ($_POST['metode'] ?? 'Transfer bank') === $pilihan ? 'selected' : '' ?>><?= e($pilihan) ?></option><?php endforeach; ?>
  <?php if (duitku_configured() && $duitku_total_valid): ?><option value="Duitku" <?= ($_POST['metode'] ?? '') === 'Duitku' ? 'selected' : '' ?>>Duitku (pilih metode di halaman Duitku)</option><?php endif; ?></select></label>
  <p class="muted">Jika memilih QRIS, pastikan QRIS merchant resmi toko tersedia.</p>
  <button class="btn besar">Buat pesanan</button>
</form>
<aside class="ringkas"><h3>Ringkasan</h3>
<?php foreach ($items as $r): ?><p><?= e($r['nama_produk']) ?> × <?= $_SESSION['cart'][$r['id_produk']] ?><span><?= rupiah($r['harga'] * $_SESSION['cart'][$r['id_produk']]) ?></span></p><?php endforeach; ?>
<p class="tot">Total<span><?= rupiah($total) ?></span></p></aside></div>
<?php include 'partials/footer.php'; ?>
