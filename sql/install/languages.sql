CREATE TABLE IF NOT EXISTS `PREFIX_swedbank_payment_language` (
  `id_swedbank_payment_language` INT(11) NOT NULL AUTO_INCREMENT,
  `id_language` INT(11) UNSIGNED NOT NULL,
  `id_shop` INT(11) UNSIGNED NOT NULL,
  `type` ENUM('banklink', 'card'),
  `environment` ENUM('test', 'live') NOT NULL,
  `selected_language` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`id_swedbank_payment_language`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;