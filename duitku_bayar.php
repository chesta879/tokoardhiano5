<?php
require 'duitku.php'; wajib_login();

$kode = trim($_GET['kode'] ?? '');
$st = $db->prepare('
  SELECT o.kode_pesanan,o.id_pelanggan,o.metode_pembayaran,o.status,o.status_pembayaran,
    a.email,p.nama_pelanggan
  FROM tbl_pesanan o
  JOIN tbl_pelanggan p USING(id_pelanggan)
  JOIN tbl_akun_pelanggan a USING(id_pelanggan)
  WHERE o.kode_pesanan=? AND o.id_pelanggan=?
');
$st->execute([$kode, pelanggan()['id']]); $pesanan = $st->fetch();
if (!$pesanan || $pesanan['metode_pembayaran'] !== 'Duitku') {
  http_response_code(404); exit('Pesanan Duitku tidak ditemukan.');
}

$pesan_error = '';
$st = $db->prepare('SELECT *,expires_at > NOW() AS masih_aktif FROM tbl_duitku_pembayaran WHERE kode_pesanan=? ORDER BY id_pembayaran DESC LIMIT 1');
$st->execute([$kode]); $pembayaran = $st->fetch();
$itemsSt = $db->prepare('
  SELECT pr.nama_produk,d.jumlah,d.harga
  FROM tbl_transaksi t
  JOIN tbl_detail_transaksi d USING(id_transaksi)
  JOIN tbl_produk pr USING(id_produk)
  WHERE t.kode_pesanan=?
');
$itemsSt->execute([$kode]); $items = $itemsSt->fetchAll();
$total = 0;
$total_valid = true;
foreach ($items as $item) {
  $total += $item['jumlah'] * $item['harga'];
  if (abs((float)$item['harga'] - round((float)$item['harga'])) >= 0.00001) $total_valid = false;
}

if ($pesanan['status_pembayaran'] !== 'Sudah dibayar' && $pesanan['status'] !== 'Dibatalkan') {
  if (!duitku_configured()) {
    $pesan_error = 'Konfigurasi Duitku belum lengkap. Hubungi pengelola toko.';
  } elseif (!$total_valid || $total <= 0 || abs($total - round($total)) >= 0.00001) {
    $pesan_error = 'Total pesanan tidak valid untuk pembayaran Duitku.';
  } elseif (!$pembayaran || !$pembayaran['masih_aktif'] || $pembayaran['result_code'] === '02') {
    try {
      $amount = (int)round($total);
      $merchantOrderId = $kode.'-'.strtoupper(bin2hex(random_bytes(4)));
      $productNames = array_map(function ($item) { return $item['nama_produk']; }, $items);
      $invoice = duitku_create_invoice(
        $merchantOrderId,
        $amount,
        implode(', ', $productNames),
        $pesanan['email'],
        $pesanan['nama_pelanggan'],
        $pesanan['id_pelanggan'],
        array_map(function ($item) {
          return [
            'name' => mb_substr($item['nama_produk'], 0, 100),
            'price' => (int)round($item['harga']),
            'quantity' => (int)$item['jumlah']
          ];
        }, $items)
      );
      $st = $db->prepare('
        INSERT INTO tbl_duitku_pembayaran
          (kode_pesanan,merchant_order_id,reference,payment_method,amount,payment_url,expires_at)
        VALUES(?,?,?,?,?,?,DATE_ADD(NOW(), INTERVAL 60 MINUTE))
      ');
      $st->execute([$kode,$merchantOrderId,$invoice['reference'] ?? null,'POP',$amount,$invoice['paymentUrl']]);
      $st = $db->prepare('SELECT * FROM tbl_duitku_pembayaran WHERE id_pembayaran=?');
      $st->execute([$db->lastInsertId()]); $pembayaran = $st->fetch();
    } catch (RuntimeException $ex) {
      error_log('Duitku invoice '.$kode.': '.$ex->getMessage());
      $pesan_error = $ex->getMessage();
    }
  }
}

$judul = 'Pembayaran Duitku'; include 'partials/header.php';
?>
<h2 class="judul">Pembayaran Duitku</h2>
<section class="pesanan">
  <strong>Pesanan <?= e($kode) ?></strong>
  <p class="tot">Total<span><?= rupiah($total) ?></span></p>
  <?php if ($pesanan['status_pembayaran'] === 'Sudah dibayar'): ?>
    <div class="info">Pembayaran telah diterima.</div>
    <a class="btn abu" href="pesanan.php">Kembali ke pesanan</a>
  <?php elseif ($pesan_error): ?>
    <div class="info salah"><?= e($pesan_error) ?></div>
    <a class="btn abu" href="duitku_bayar.php?kode=<?= urlencode($kode) ?>">Coba lagi</a>
  <?php elseif ($pesanan['status'] === 'Dibatalkan'): ?>
    <div class="info salah">Pesanan ini sudah dibatalkan.</div>
  <?php elseif ($pembayaran): ?>
    <p>Metode: Duitku. Pilih kanal pembayaran pada halaman Duitku.</p>
    <p class="muted">Selesaikan pembayaran sebelum <?= e($pembayaran['expires_at']) ?>.</p>
    <a class="btn besar" href="<?= e($pembayaran['payment_url']) ?>" rel="noopener">Lanjutkan pembayaran</a>
    <p class="muted">Status pesanan akan diperbarui setelah Duitku mengirim konfirmasi.</p>
  <?php else: ?>
    <div class="info">Invoice Duitku belum tersedia.</div>
  <?php endif; ?>
</section>
<?php include 'partials/footer.php'; ?>
