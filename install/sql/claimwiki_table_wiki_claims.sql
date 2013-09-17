CREATE TABLE /*_*/wiki_claims (
  `cid` int(12) NOT NULL AUTO_INCREMENT,
  `user_id` int(12) NOT NULL DEFAULT '0',
  `claim_timestamp` int(14) NOT NULL DEFAULT '0',
  `start_timestamp` int(14) NOT NULL DEFAULT '0',
  `end_timestamp` int(14) NOT NULL DEFAULT '0',
  `agreed` tinyint(1) NOT NULL DEFAULT '0',
  `approved` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`cid`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;