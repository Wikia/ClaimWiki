CREATE TABLE /*_*/wiki_claims_answers (
  `caid` int(12) NOT NULL AUTO_INCREMENT,
  `claim_id` int(12) NOT NULL DEFAULT '0',
  `question_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `answer` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`caid`),
  KEY `claim_id` (`claim_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;