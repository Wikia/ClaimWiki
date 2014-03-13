ALTER TABLE /*_*/wiki_claims
	ADD COLUMN `pending` tinyint( 1 ) DEFAULT NULL AFTER `approved`
;
