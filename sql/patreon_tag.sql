CREATE TABLE /*$wgDBprefix*/patreon_tag (
  patreon_id DECIMAL(25,0) unsigned NOT NULL,
  tag TINYTEXT NOT NULL,
  KEY(patreon_id)
) /*$wgDBTableOptions*/;