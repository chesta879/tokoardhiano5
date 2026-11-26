<?php
require 'config.php'; wajib_login();
$p = pelanggan(); $id = (int)$p['id'];

// Pastikan kolom no_hp ada (idempotent)
$cols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".DB_NAME."' AND TABLE_NAME='tbl_pelanggan'")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('no_hp', $cols)) { $db->exec("ALTER TABLE tbl_pelanggan ADD COLUMN no_hp VARCHAR(20) NULL AFTER kota"); }

// Ambil data lengkap pelanggan + email
$st = $db->prepare('SELECT pl.nama_pelanggan, pl.kota, pl.no_hp, ak.email FROM tbl_pelanggan pl JOIN tbl_akun_pelanggan ak USING(id_pelanggan) WHERE pl.id_pelanggan=?');
$st->execute([$id]); $data = $st->fetch();

$err = ''; $sukses = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  cek_csrf(); $aksi = $_POST['aksi'] ?? '';
  if ($aksi === 'profil') {
    $nama = trim($_POST['nama'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $hp = trim($_POST['no_hp'] ?? '');
    if ($nama === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = 'Nama dan email harus diisi dengan benar.';
    } else {
      $cek = $db->prepare('SELECT 1 FROM tbl_akun_pelanggan WHERE email=? AND id_pelanggan<>?'); $cek->execute([$email, $id]);
      if ($cek->fetch()) { $err = 'Email sudah digunakan akun lain.'; }
      else {
        $db->prepare('UPDATE tbl_pelanggan SET nama_pelanggan=?, no_hp=? WHERE id_pelanggan=?')->execute([$nama, $hp === '' ? null : $hp, $id]);
        $db->prepare('UPDATE tbl_akun_pelanggan SET email=? WHERE id_pelanggan=?')->execute([$email, $id]);
        $_SESSION['pelanggan']['nama'] = $nama;
        $sukses = 'Profil berhasil diperbarui.';
        $data = $db->prepare('SELECT pl.nama_pelanggan, pl.kota, pl.no_hp, ak.email FROM tbl_pelanggan pl JOIN tbl_akun_pelanggan ak USING(id_pelanggan) WHERE pl.id_pelanggan=?');
        $data->execute([$id]); $data = $data->fetch();
      }
    }
  } elseif ($aksi === 'password') {
    $lama = $_POST['password_lama'] ?? ''; $baru = $_POST['password_baru'] ?? ''; $ulang = $_POST['password_ulang'] ?? '';
    $st2 = $db->prepare('SELECT password_hash FROM tbl_akun_pelanggan WHERE id_pelanggan=?'); $st2->execute([$id]); $u = $st2->fetch();
    if (!$u || !password_verify($lama, $u['password_hash'])) { $err = 'Kata sandi lama salah.'; }
    elseif (strlen($baru) < 6) { $err = 'Kata sandi baru minimal 6 karakter.'; }
    elseif ($baru !== $ulang) { $err = 'Konfirmasi kata sandi tidak cocok.'; }
    else {
      $db->prepare('UPDATE tbl_akun_pelanggan SET password_hash=? WHERE id_pelanggan=?')->execute([password_hash($baru, PASSWORD_DEFAULT), $id]);
      $sukses = 'Kata sandi berhasil diperbarui.';
    }
  }
}
$judul = 'Profil Saya';
include 'partials/header.php';
?>
<h2 class="judul">Profil Saya</h2>
<?php if ($err): ?><div class="info salah"><?= e($err) ?></div><?php endif; ?>
<?php if ($sukses): ?><div class="info"><?= e($sukses) ?></div><?php endif; ?>
<div class="dua">
  <form method="post" class="form">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="profil">
    <h3 style="margin-top:0">Ubah Profil</h3>
    <label>Nama lengkap<input type="text" name="nama" value="<?= e($data['nama_pelanggan'] ?? '') ?>" required></label>
    <label>Email<input type="email" name="email" value="<?= e($data['email'] ?? '') ?>" required></label>
    <label>Nomor HP<input type="text" name="no_hp" value="<?= e($data['no_hp'] ?? '') ?>" placeholder="Contoh: 081234567890"></label>
    <label>Kota<input type="text" name="kota" value="<?= e($data['kota'] ?? '') ?>" required></label>
    <button class="btn besar">Simpan Perubahan</button>
  </form>

  <form method="post" class="form">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="password">
    <h3 style="margin-top:0">Ubah Kata Sandi</h3>
    <label>Kata sandi lama<input type="password" name="password_lama" required></label>
    <label>Kata sandi baru<input type="password" name="password_baru" minlength="6" required></label>
    <label>Konfirmasi kata sandi baru<input type="password" name="password_ulang" minlength="6" required></label>
    <button class="btn besar">Ganti Kata Sandi</button>
  </form>
</div>
<?php include 'partials/footer.php'; ?>