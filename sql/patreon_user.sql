CREATE TABLE /*$wgDBprefix*/patreon_user (
  user_patreonid DECIMAL(25,0) unsigned NOT NULL PRIMARY KEY,
  user_id int(10) unsigned NOT NULL,
  KEY(user_id)
) /*$wgDBTableOptions*/;