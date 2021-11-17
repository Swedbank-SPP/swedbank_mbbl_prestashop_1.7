CREATE TABLE IF NOT EXISTS `PREFIX_swedbank_order_payment` (
  `id_order` INT(11) UNSIGNED NOT NULL,
  `id_shop` INT(11) UNSIGNED NOT NULL,
  `id_unique` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`id_order`, `id_shop`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;