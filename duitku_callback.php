<?php
require 'duitku.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); header('Allow: POST'); exit('Method not allowed.');
}
if (!duitku_credentials_available()) {
  http_response_code(503); exit('Duitku is not configured.');
}

$merchantCode = $_POST['merchantCode'] ?? '';
$amount = $_POST['amount'] ?? '';
$merchantOrderId = $_POST['merchantOrderId'] ?? '';
$signature = $_POST['signature'] ?? '';
$resultCode = $_POST['resultCode'] ?? '';
if ($merchantCode !== DUITKU_MERCHANT_CODE || !preg_match('/\A[0-9]+\z/', (string)$amount)
  || $merchantOrderId === '' || $signature === '') {
  http_response_code(400); exit('Invalid callback parameters.');
}

$expectedSignature = hash_hmac(
  'sha256',
  $merchantCode.$amount.$merchantOrderId,
  DUITKU_API_KEY
);
if (!hash_equals($expectedSignature, $signature)) {
  http_response_code(400); exit('Invalid callback signature.');
}

$db->beginTransaction();
try {
  $st = $db->prepare('
    SELECT d.kode_pesanan,d.amount,o.metode_pembayaran
    FROM tbl_duitku_pembayaran d
    JOIN tbl_pesanan o USING(kode_pesanan)
    WHERE d.merchant_order_id=? FOR UPDATE
  ');
  $st->execute([$merchantOrderId]); $payment = $st->fetch();
  if (!$payment || (int)$payment['amount'] !== (int)$amount
    || $payment['metode_pembayaran'] !== 'Duitku') {
    $db->rollBack(); http_response_code(404); exit('Payment not found.');
  }
  $db->prepare('UPDATE tbl_duitku_pembayaran SET result_code=? WHERE merchant_order_id=?')
    ->execute([$resultCode, $merchantOrderId]);
  if ($resultCode === '00') {
    $db->prepare("UPDATE tbl_pesanan SET status_pembayaran='Sudah dibayar',status='Selesai' WHERE kode_pesanan=?")
      ->execute([$payment['kode_pesanan']]);
  }
  $db->commit();
} catch (Throwable $ex) {
  if ($db->inTransaction()) $db->rollBack();
  error_log('Duitku callback: '.$ex->getMessage());
  http_response_code(500); exit('Callback processing failed.');
}

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'SUCCESS';
