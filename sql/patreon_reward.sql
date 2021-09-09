CREATE TABLE /*$wgDBprefix*/patreon_reward (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patreon_id DECIMAL(25,0) unsigned NOT NULL,
  rewardDate DATETIME NOT NULL,
  rewardNote TINYTEXT NOT NULL,
  rewardValueCents INT(10) UNSIGNED NOT NULL DEFAULT 0,
  shipmentId INT(10) UNSIGNED DEFAULT 0,
  KEY(patreon_id),
  KEY(shipmentId)
) /*$wgDBTableOptions*/;