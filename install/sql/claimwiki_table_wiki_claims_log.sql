CREATE TABLE /*_*/wiki_claims_log (
  `lid` int(14) NOT NULL AUTO_INCREMENT,
  `claim_id` int(14) NOT NULL,
  `actor_id` int(14) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `timestamp` int(14) NOT NULL DEFAULT '0',
  PRIMARY KEY (`lid`),
  KEY `claim_id` (`claim_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;