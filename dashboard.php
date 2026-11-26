<?php
require 'config.php';
if (isset($_GET['resultCode'], $_GET['merchantOrderId'], $_GET['reference'])) pindah(DUITKU_RETURN_URL);
wajib_login();
$p = pelanggan();
$id = (int)$p['id'];

// Statistik ringkasan milik pelanggan login
$st = $db->prepare("
  SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status_pembayaran='Belum dibayar' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status='Diproses' THEN 1 ELSE 0 END) AS diproses,
    SUM(CASE WHEN status='Selesai' THEN 1 ELSE 0 END) AS selesai
  FROM tbl_pesanan WHERE id_pelanggan=?
");
$st->execute([$id]); $s = $st->fetch();
$stb = $db->prepare('SELECT COALESCE(SUM(total_belanja),0) AS belanja FROM tbl_transaksi WHERE id_pelanggan=?');
$stb->execute([$id]); $b = $stb->fetch();

// Pesanan terbaru
$st = $db->prepare("
  SELECT kode_pesanan, status, status_pembayaran, metode_pembayaran, dibuat_pada
  FROM tbl_pesanan WHERE id_pelanggan=? ORDER BY dibuat_pada DESC LIMIT 5
");
$st->execute([$id]); $recent = $st->fetchAll();

// Produk tersedia untuk ditekankan di dashboard
$st = $db->prepare('SELECT p.*, k.nama_kategori FROM tbl_produk p JOIN tbl_kategori k USING(id_kategori) WHERE p.stok>0 ORDER BY p.id_produk DESC LIMIT 6');
$st->execute(); $produk = $st->fetchAll();

$judul = 'Dashboard';
include 'partials/header.php';
?>
<h2 class="judul">Dashboard</h2>
<div class="statistik">
  <div><span>Total Pesanan</span><b><?= (int)($s['total'] ?? 0) ?></b><small>Semua pesanan Anda</small></div>
  <div><span>Pending Pembayaran</span><b><?= (int)($s['pending'] ?? 0) ?></b><small>Menunggu konfirmasi</small></div>
  <div><span>Diproses</span><b><?= (int)($s['diproses'] ?? 0) ?></b><small>Sedang diproses toko</small></div>
  <div><span>Total Belanja</span><b><?= rupiah((float)($b['belanja'] ?? 0)) ?></b><small>Pesanan selesai</small></div>
</div>

<div class="dua">
  <section class="pesanan">
    <header><strong>Pesanan Terbaru</strong><a class="link" href="pesanan.php">Lihat semua →</a></header>
    <?php if (!$recent): ?>
      <p class="muted">Belum ada pesanan. <a href="index.php">Mulai belanja</a></p>
    <?php endif; ?>
    <?php foreach ($recent as $o): ?>
      <div class="baris" style="border-bottom:1px solid var(--b);padding:8px 0;align-items:flex-start">
        <div style="flex:1">
          <strong><?= e($o['kode_pesanan']) ?></strong>
          <p class="muted" style="margin:2px 0 0"><?= e($o['dibuat_pada']) ?> · <?= e($o['metode_pembayaran']) ?></p>
        </div>
        <span class="status s-<?= e(strtolower($o['status'])) ?>"><?= e($o['status']) ?></span>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="ringkas" style="position:static">
    <h3>Produk Tersedia</h3>
    <div class="grid">
      <?php foreach ($produk as $r): ?>
        <article class="kartu">
          <a class="foto" href="produk.php?id=<?= $r['id_produk'] ?>">
            <?= $r['gambar'] ? '<img src="'.e($r['gambar']).'" alt="'.e($r['nama_produk']).'" loading="lazy">' : '<span class="inisial">'.e(mb_strtoupper(mb_substr($r['nama_produk'], 0, 1))).'</span>' ?>
          </a>
          <div class="isi">
            <small><?= e($r['nama_kategori']) ?></small>
            <h3><a href="produk.php?id=<?= $r['id_produk'] ?>"><?= e($r['nama_produk']) ?></a></h3>
            <p class="harga"><?= rupiah($r['harga']) ?></p>
            <form method="post" action="keranjang.php"><?= csrf_field() ?>
              <input type="hidden" name="aksi" value="tambah">
              <input type="hidden" name="id" value="<?= $r['id_produk'] ?>">
              <button class="btn" <?= $r['stok'] <= 0 ? 'disabled' : '' ?>><?= $r['stok'] <= 0 ? 'Stok Habis' : 'Tambah ke Keranjang' ?></button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php include 'partials/footer.php'; ?>
