<?php
require_once __DIR__.'/config.php';

function duitku_credentials_available() {
  return DUITKU_MERCHANT_CODE !== '' && DUITKU_API_KEY !== '';
}

function duitku_configured() {
  $baseUrlParts = parse_url(DUITKU_BASE_URL);
  $apiUrlParts = parse_url(DUITKU_API_URL);
  return duitku_credentials_available()
    && is_array($baseUrlParts) && !empty($baseUrlParts['host'])
    && !isset($baseUrlParts['user']) && !isset($baseUrlParts['pass'])
    && !isset($baseUrlParts['query']) && !isset($baseUrlParts['fragment'])
    && filter_var(DUITKU_BASE_URL, FILTER_VALIDATE_URL)
    && parse_url(DUITKU_BASE_URL, PHP_URL_SCHEME) === 'https'
    && is_array($apiUrlParts) && !empty($apiUrlParts['host'])
    && filter_var(DUITKU_API_URL, FILTER_VALIDATE_URL)
    && parse_url(DUITKU_API_URL, PHP_URL_SCHEME) === 'https'
    && in_array(DUITKU_MODE, ['sandbox', 'production'], true);
}

function duitku_request(array $params) {
  if (!function_exists('curl_init')) {
    throw new RuntimeException('Ekstensi PHP cURL belum aktif.');
  }
  $timestamp = (string)round(microtime(true) * 1000);
  $signature = hash('sha256', DUITKU_MERCHANT_CODE.$timestamp.DUITKU_API_KEY);
  $body = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($body === false) {
    throw new RuntimeException('Data permintaan Duitku tidak dapat diproses.');
  }

  $curl = curl_init(DUITKU_API_URL);
  if ($curl === false) {
    throw new RuntimeException('Koneksi ke Duitku tidak dapat dimulai.');
  }
  curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Accept: application/json',
      'x-duitku-signature: '.$signature,
      'x-duitku-timestamp: '.$timestamp,
      'x-duitku-merchantcode: '.DUITKU_MERCHANT_CODE
    ],
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
  ]);
  $response = curl_exec($curl);
  $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  $curlError = curl_error($curl);
  curl_close($curl);

  if ($response === false) {
    throw new RuntimeException('Koneksi ke Duitku gagal: '.$curlError);
  }
  $result = json_decode($response, true);
  if ($status < 200 || $status >= 300 || !is_array($result)) {
    throw new RuntimeException('Duitku menolak permintaan (HTTP '.$status.').');
  }
  return $result;
}

function duitku_create_invoice($merchantOrderId, $amount, $productDetails, $email, $customerName, $customerId, array $items) {
  if (!duitku_configured()) {
    throw new RuntimeException('Konfigurasi Duitku belum lengkap atau URL publik belum valid.');
  }
  $params = [
    'merchantCode' => DUITKU_MERCHANT_CODE,
    'paymentAmount' => $amount,
    'merchantOrderId' => $merchantOrderId,
    'productDetails' => mb_substr($productDetails, 0, 255),
    'additionalParam' => '',
    'merchantUserInfo' => (string)$customerId,
    'customerVaName' => mb_substr($customerName, 0, 20),
    'email' => $email,
    'itemDetails' => $items,
    'callbackUrl' => DUITKU_BASE_URL.'/duitku_callback.php',
    'returnUrl' => DUITKU_RETURN_URL,
    'expiryPeriod' => 60
  ];
  $result = duitku_request($params);
  if (($result['statusCode'] ?? '') !== '00' || empty($result['paymentUrl'])) {
    $message = $result['statusMessage'] ?? 'Respons invoice tidak valid.';
    throw new RuntimeException('Invoice Duitku gagal dibuat: '.$message);
  }
  if (parse_url($result['paymentUrl'], PHP_URL_SCHEME) !== 'https') {
    throw new RuntimeException('Duitku mengembalikan tautan pembayaran yang tidak aman.');
  }
  return $result;
}
