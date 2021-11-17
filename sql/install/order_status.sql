CREATE TABLE IF NOT EXISTS `PREFIX_swedbank_order_status` (
  `id_order` int(11) UNSIGNED NOT NULL,
  `pay_status` int(11) UNSIGNED NOT NULL,
  `date_created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp(),
  `merchant_ref` varchar(16) NOT NULL,
  `pmmm` varchar(32) NOT NULL,
  `t` varchar(16) NOT NULL,
  `lnv` varchar(3) NOT NULL,
  PRIMARY KEY (`date_created`, `merchant_ref`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;