CREATE TABLE IF NOT EXISTS `PREFIX_swedbank_card_payment_data` (
  `id_swedbank_card_payment_data` INT(11) UNSIGNED AUTO_INCREMENT,
  `id_order` INT(11) UNSIGNED NOT NULL,
  `expiry_date` VARCHAR(100) NOT NULL DEFAULT '',
  `pan` VARCHAR(100) NOT NULL DEFAULT '',
  `authorization_code` VARCHAR(100) NOT NULL DEFAULT '',
  `merchant_reference` VARCHAR(100) NOT NULL DEFAULT '',
  `fulfill_date` DATETIME,
  PRIMARY KEY (`id_swedbank_card_payment_data`),
  UNIQUE (`id_order`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;