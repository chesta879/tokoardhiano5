<?php
session_start();
const DB_HOST = 'localhost';
const DB_NAME = 'db_toko';
const DB_USER = 'root';
const DB_PASS = '';
const NAMA_TOKO = 'Toko Kita';

$duitkuConfigFile = getenv('DUITKU_CONFIG_FILE');
if (!$duitkuConfigFile) {
  $hasEnvironmentCredentials = getenv('DUITKU_MERCHANT_CODE') && getenv('DUITKU_API_KEY');
  if (!$hasEnvironmentCredentials) {
    $duitkuConfigFile = dirname(__DIR__).DIRECTORY_SEPARATOR.'tokokita'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'duitku.php';
  }
}
if ($duitkuConfigFile && is_file($duitkuConfigFile)) {
  ob_start();
  require_once $duitkuConfigFile;
  $duitkuConfigOutput = ob_get_clean();
  if (trim($duitkuConfigOutput) !== '') error_log('Duitku config file produced unexpected output.');
}
if (!defined('DUITKU_MERCHANT_CODE')) define('DUITKU_MERCHANT_CODE', getenv('DUITKU_MERCHANT_CODE') ?: '');
if (!defined('DUITKU_API_KEY')) define('DUITKU_API_KEY', getenv('DUITKU_API_KEY') ?: '');
if (!defined('DUITKU_MODE')) define('DUITKU_MODE', getenv('DUITKU_MODE') ?: 'sandbox');
if (!defined('DUITKU_BASE_URL')) define('DUITKU_BASE_URL', rtrim(getenv('DUITKU_BASE_URL') ?: 'https://sterida.my.id/ardhiano', '/'));
if (!defined('DUITKU_RETURN_URL')) define('DUITKU_RETURN_URL', getenv('DUITKU_RETURN_URL') ?: 'http://localhost/tokoardhiano/index.php');
if (!defined('DUITKU_API_URL')) {
  $duitkuApiUrl = getenv('DUITKU_API_URL') ?: (DUITKU_MODE === 'production'
    ? 'https://api.duitku.com/api/merchant/createInvoice'
    : 'https://api-sandbox.duitku.com/api/merchant/createInvoice');
  define('DUITKU_API_URL', $duitkuApiUrl);
}

try {
  $db = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $ex) { exit('Koneksi database gagal. Periksa pengaturan di config.php dan pastikan db_tokoku.sql sudah diimpor.'); }

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rupiah($n) { return 'Rp '.number_format((float)$n, 0, ',', '.'); }
function pindah($u) { header("Location: $u"); exit; }
function csrf_field() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return '<input type="hidden" name="csrf" value="'.$_SESSION['csrf'].'">'; }
function cek_csrf() { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Sesi tidak valid. Muat ulang halaman lalu coba lagi.'); } }
function flash($m = null) { if ($m !== null) { $_SESSION['flash'] = $m; return; } $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function pelanggan() { return $_SESSION['pelanggan'] ?? null; }
function wajib_login() { if (!pelanggan()) { $_SESSION['kembali'] = basename($_SERVER['PHP_SELF']); flash('Masuk dulu untuk melanjutkan.'); pindah('masuk.php'); } }
function jumlah_keranjang() { return array_sum($_SESSION['cart'] ?? []); }

function kartu_produk($r) {
  $gambar = $r['gambar'] ? '<img src="'.e($r['gambar']).'" alt="'.e($r['nama_produk']).'" loading="lazy">' : '<span class="inisial">'.e(mb_strtoupper(mb_substr($r['nama_produk'], 0, 1))).'</span>';
  $habis = $r['stok'] <= 0;
  echo '<article class="kartu"><a class="foto" href="produk.php?id='.$r['id_produk'].'">'.$gambar.'</a>
    <div class="isi"><small>'.e($r['nama_kategori']).' · Kode: '.e($r['kode_produk'] ?? '-').'</small>
    <h3><a href="produk.php?id='.$r['id_produk'].'">'.e($r['nama_produk']).'</a></h3>
    <p class="harga">'.rupiah($r['harga']).'</p>
    <form method="post" action="keranjang.php">'.csrf_field().'<input type="hidden" name="aksi" value="tambah"><input type="hidden" name="id" value="'.$r['id_produk'].'">
    <button class="btn" '.($habis ? 'disabled>Stok habis' : '>Tambah ke keranjang').'</button></form></div></article>';
}
