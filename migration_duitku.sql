CREATE TABLE IF NOT EXISTS tbl_duitku_pembayaran (
  id_pembayaran BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode_pesanan VARCHAR(32) NOT NULL,
  merchant_order_id VARCHAR(50) NOT NULL UNIQUE,
  reference VARCHAR(100) NULL,
  payment_method VARCHAR(10) NOT NULL,
  result_code VARCHAR(2) NULL,
  amount BIGINT UNSIGNED NOT NULL,
  payment_url VARCHAR(1000) NOT NULL,
  expires_at DATETIME NOT NULL,
  dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_duitku_pesanan (kode_pesanan),
  CONSTRAINT fk_duitku_pesanan FOREIGN KEY (kode_pesanan)
    REFERENCES tbl_pesanan(kode_pesanan) ON DELETE CASCADE
) ENGINE=InnoDB;
