ALTER TABLE /*_*/wiki_claims DROP `pending`;
ALTER TABLE /*_*/wiki_claims CHANGE `approved` `status` TINYINT( 1 ) NOT NULL DEFAULT 0;
UPDATE /*_*/wiki_claims SET status = 2 WHERE status = 1;
UPDATE /*_*/wiki_claims SET status = 3 WHERE status = 0;
UPDATE /*_*/wiki_claims SET status = 0 WHERE status = NULL;