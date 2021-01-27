CREATE TABLE /*$wgDBprefix*/patreon_tierchange (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patreon_id DECIMAL(25,0) unsigned NOT NULL,
  oldTier TINYTEXT NOT NULL,
  newTier TINYTEXT NOT NULL,
  date TIMESTAMP NOT NULL,
  KEY(patreon_id)
) /*$wgDBTableOptions*/;