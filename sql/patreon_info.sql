CREATE TABLE /*$wgDBprefix*/patreon_info (
  k TINYTEXT NOT NULL,
  v TINYTEXT NOT NULL,
  PRIMARY KEY index_k(k(16))
) /*$wgDBTableOptions*/;
INSERT IGNORE INTO /*$wgDBprefix*/patreon_info(k, v) VALUES('access_token',''),('refresh_token',''),('last_update','0'),('token_expires','0');