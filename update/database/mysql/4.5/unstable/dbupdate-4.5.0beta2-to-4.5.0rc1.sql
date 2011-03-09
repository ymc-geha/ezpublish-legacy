SET storage_engine=InnoDB;
UPDATE ezsite_data SET value='4.5.0rc1' WHERE name='ezpublish-version';
UPDATE ezsite_data SET value='1' WHERE name='ezpublish-release';

ALTER TABLE ezrss_import DROP COLUMN class_description, DROP COLUMN class_title, DROP COLUMN class_url;
