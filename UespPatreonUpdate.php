<?php

if (php_sapi_name() != "cli") die("Can only be run from command line!");

require_once("/home/uesp/secrets/patreon.secrets");
require_once("/home/uesp/secrets/wiki.secrets");
require_once("Patreon/API2.php");
require_once("Patreon/OAuth2.php");
require_once("UespPatreonCommon.php");


class UespPatreonUpdate {
	
	
	public $patreonUsers = array();
	public $existingUsers = array();
	public $tierChanges = array();
	
	public $db = null;
	public $lastQuery = "";
	public $accessToken = "";
	public $refreshToken = "";
	public $tokenExpires = "";
	
	
	public function __construct() {
		$this->initDatabase();
		$this->loadInfo();
	}
	
	
	public function initDatabase() {
		global $uespWikiDB;
		global $uespWikiUser;
		global $uespWikiPW;
		global $UESP_SERVER_DB1;
		
		$this->db = new mysqli($UESP_SERVER_DB1, $uespWikiUser, $uespWikiPW, $uespWikiDB);
		if ($this->db->connect_error) exit("Could not connect to mysql database!");
	}
	
	
	public function loadInfo() {
		$this->lastQuery = "SELECT * FROM patreon_info;";
		$result = $this->db->query($this->lastQuery);
		
		if ($result === false) {
			print("\tError: Failed to load patreon info records! " . $this->db->error . "\n\t" . $this->lastQuery . "\n");
			return false;
		}
		
		while ($row = $result->fetch_assoc()) {
			$key   = $row['k'];
			$value = $row['v'];
			
			if ($key == "access_token") 
				$this->accessToken = $value;
			else if ($key == "refresh_token") 
				$this->refreshToken = $value;
			else if ($key == "token_expires") 
				$this->tokenExpires = intval($value);
		}
		
		$timeDiff = $this->tokenExpires - time();
		if ($timeDiff <= 86400) $this->refreshToken();
		
		return true;
	}
	
	
	public function refreshToken() {
		global $uespPatreonClientId2;
		global $uespPatreonClientSecret2;
		
		print("\tAttempting to refresh access/refresh tokens!\n");
		
		if ($this->refreshToken == "") {
			print("\tError: Can't renew the access token as the refresh token is empty!\n");
			return false;
		}
		
		$oauth = new Patreon\OAuth($uespPatreonClientId2, $uespPatreonClientSecret2);
		
		$result = $oauth->refresh_token($this->refreshToken, "https://en.uesp.net/wiki/Special:UespPatreon/callback2");
		
		print("OAuth Refresh Tokens:\n");
		print_r($result);
		
		if ($result['access_token'] == null) {
			print("\tError: Failed to fresh access token!\n");
			return false;
		}
		
		$this->accessToken = $result['access_token'];
		$this->refreshToken = $result['refresh_token'];
		$this->tokenExpires = time() + intval($result['expires_in']);
		
		$safeValue = $this->db->real_escape_string($this->accessToken);
		$this->lastQuery = "UPDATE patreon_info SET v='$safeValue' WHERE k='access_token';";
		$result = $this->db->query($this->lastQuery);
		if ($result === false) print("\tError: Failed to save access_token to database! " . $this->db->error . "\n\t" . $this->lastQuery . "\n");
		
		$safeValue = $this->db->real_escape_string($this->refreshToken);
		$this->lastQuery = "UPDATE patreon_info SET v='$safeValue' WHERE k='refresh_token';";
		$result = $this->db->query($this->lastQuery);
		if ($result === false) print("\tError: Failed to save refresh_token to database! " . $this->db->error . "\n\t" . $this->lastQuery . "\n");
		
		$safeValue = $this->db->real_escape_string(strval($this->tokenExpires));
		$this->lastQuery = "UPDATE patreon_info SET v='$safeValue' WHERE k='token_expires';";
		$result = $this->db->query($this->lastQuery);
		if ($result === false) print("\tError: Failed to save token_expires to database! " . $this->db->error . "\n\t" . $this->lastQuery . "\n");
		
		print("\tSuccessfully refresh access/refresh tokens!\n");
		
		return true;
	}
	
	
	public function loadAllPatreonUsers($quiet = true) {
		
		if (!$quiet) print("\tLoading user data from Patreon...\n");
		
		$api = new Patreon\API($this->accessToken);
		$nextCursor = null;
		$responses = [];
		$pageCount = 0;
		
		do {
			$response = $api->fetch_page_of_members_from_campaign(UespPatreonCommon::$UESP_CAMPAIGN_ID, 2000, $nextCursor);
			
			if ($response == null || count($response) == 0 || is_string($response)) {
				if (!$quiet) print("\tError: Failed to load user data from Patreon: $response\n");
				return false;
			}
			
			$responses[] = $response;
			$nextCursor = null;
			
			$meta = $response['meta'];
			
			if ($meta)
			{
				$pagination = $meta['pagination'];
				
				if ($pagination)
				{
					$cursors = $pagination['cursors'];
					
					if ($cursors)
					{
						$nextCursor = $cursors['next'];
					}
				}
			}
			
			++$pageCount;
			if ($nextCursor != null) print("\t$pageCount) Found next cursor: $nextCursor\n");
		} while ($nextCursor != null);
		
		//* Debug Output
		//$output = print_r($response, true);
		//print($output);
		//file_put_contents("/tmp/response.json", $output);
		//exit(); //*/
		
		$this->patreonUsers = [];
		
		foreach ($responses as $response)
		{
			$this->patreonUsers = array_merge($this->patreonUsers, UespPatreonCommon::parsePatronData($response, false, false));
		}
		
		$count = count($this->patreonUsers);
		if (!$quiet) print("\tLoaded $count patron records from Patreon!\n");
		return true;
	}
	
	
	public function loadAllExistingUsers($quiet = true) {
		
		$this->lastQuery = "SELECT * FROM patreon_user;";
		$result = $this->db->query($this->lastQuery);
		
		if ($result === false) {
			if (!$quiet) print("\tError: Failed to load data from patreon_user table!\n\t" . $this->db->error . "\n\t" . $this->lastQuery . "\n");
			return false;
		}
		
		while ($user = $result->fetch_assoc()) {
			$id = intval($user['patreon_id']);
			if ($id <= 0) continue;
			$this->existingUsers[$id] = $user;
		}
		
		$count = count($this->existingUsers);
		if (!$quiet) print("\tLoaded $count existing user data from database!\n");
		
		return true;
	}
	
	
	public function mergeUsers() {
		
		foreach ($this->patreonUsers as $user) {
			$id = $user['patreon_id'];
			
			$existingUser = &$this->existingUsers[$id];
			
			if ($existingUser == null) {
				$this->existingUsers[$id] = array( '__isnew' => true, 'id' => -1, 'patreon_id' => $id);
				$existingUser = &$this->existingUsers[$id];
			}
			
			$existingUser['__isupdated'] = true;
			
				// TODO: Tier Check
			if ($existingUser['tier'] != $user['tier'] && $existingUser['tier'] != "" && $user['tier'] != "") {
				
					// Don't update tier changes on new or non-active users
				if ( !$existingUser['__isnew'] && !($user['tier'] == "" && $user['status'] != "active_patron") ) {
					$newTier = array();
					$newTier['user_id'] = $existingUser['id']; 
					$newTier['patreon_id'] = $id;
					$newTier['oldTier'] = $existingUser['tier'];
					$newTier['newTier'] = $user['tier'];
					$newTier['date'] = time();
					$this->tierChanges[] = $newTier;
				}
			}
			
			$existingUser['name'] = $user['name'];
			$existingUser['email'] = $user['email'];
			$existingUser['discord'] = $user['discord'];
			
			//print("{$user['name']} : {$user['status']} : {$user['tier']} : {$existingUser['tier']}\n");
			
				// Don't change tiers for non-active users
			//if ( !($user['tier'] == "" && $user['status'] != "active_patron") ) {
			//if ($user['tier'] != "" || $user['status'] == "active_patron") {
			if ($user['tier'] != "") {
				//print("\tUpdated User Tier\n");
				$existingUser['tier'] = $user['tier'];
			}
			
			$existingUser['status'] = $user['status'];
			$existingUser['pledgeCadence'] = $user['pledgeCadence'];
			$existingUser['note'] = $user['note'];
			$existingUser['lifetimePledgeCents'] = $user['lifetimePledgeCents'];
			$existingUser['startDate'] = $user['startDate'];
			$existingUser['lastChargeDate'] = $user['lastChargeDate'];
			$existingUser['addressName'] = $user['addressName'];
			$existingUser['addressLine1'] = $user['addressLine1'];
			$existingUser['addressLine2'] = $user['addressLine2'];
			$existingUser['addressCity'] = $user['addressCity'];
			$existingUser['addressState'] = $user['addressState'];
			$existingUser['addressZip'] = $user['addressZip'];
			$existingUser['addressCountry'] = $user['addressCountry'];
			$existingUser['addressPhone'] = $user['addressPhone'];
			
			//print("Name: " . $user['full_name'] . "\n");
		}
		
			/* Check for deleted users */
		foreach ($this->existingUsers as &$user)
		{
			if ($user['__isupdated']) continue;
			
			//print("\tSetting {$user['name']} to deleted\n");
			
			$user['status'] = 'deleted_patron';
		}
		
		return true;
	}
	
	
	public function updateTierChanges() {
		
		$count = 0;
		
		foreach ($this->tierChanges as $tierChange) {
			$cols = "patreon_id,oldTier,newTier,date";
			$vals  = "'" . $this->db->real_escape_string($tierChange['patreon_id']) . "'";
			$vals .= ", '" . $this->db->real_escape_string($tierChange['oldTier']) . "'";
			$vals .= ", '" . $this->db->real_escape_string($tierChange['newTier']) . "'";
			$vals .= ", FROM_UNIXTIME('" . intval($tierChange['date']) . "')";
			
			$this->lastQuery = "INSERT INTO patreon_tierchange($cols) VALUES($vals);";
			
			$result = $this->db->query($this->lastQuery);
			if ($result === false) print("\tError: Failed to update tier change! " . $this->db->error . "\n" . $this->lastQuery . "\n");
			
			++$count;
		}
		
		print("\tUpdated $count tier changes!\n");
		return true;
	}
	
	
	public function updateUsers() {
		
		$COLNAMES = array("patreon_id", "name", "email", "discord", "tier", "status", "pledgeCadence", "note", "lifetimePledgeCents", "startDate", "lastChargeDate", "addressName", "addressLine1", "addressLine2", 
							"addressCity", "addressState", "addressZip", "addressCountry", "addressPhone");
		$newUsers = 0;
		$updatedUsers = 0;
		
		foreach ($this->existingUsers as $id => $user) 
		{
			$colNames = $COLNAMES;
			
			if ($user['__isnew']) {
				$colNames[] = "appCode";
				$user['appCode'] = UespPatreonCommon::generateRandomAppCode();
				
				$newUsers++;
				$cols = array();
				$vals = array();
				
				foreach ($colNames as $field) {
					$cols[] = $field;
					$vals[] = $this->db->real_escape_string(strval($user[$field]));
				}
				
				$cols = implode(",", $cols);
				$vals = "'" . implode("','", $vals) . "'";
				$this->lastQuery = "INSERT INTO patreon_user($cols) VALUES($vals);";
			}
			else {
				$updatedUsers++;
				$vars = array();
				
				if ($user['appCode'] == '')
				{
					$colNames[] = "appCode";
					$user['appCode'] = UespPatreonCommon::generateRandomAppCode();
				}
				
				foreach ($colNames as $field) {
					$value = $this->db->real_escape_string(strval($user[$field]));
					$vars[] = "$field='$value'";
				}
				
				$vars = implode(",", $vars);
				$userId = $user['id'];
				$this->lastQuery = "UPDATE patreon_user SET $vars WHERE id='$userId';";
			}
			
			$result = $this->db->query($this->lastQuery);
			if ($result === false) print("Error: Failed to update user $id in database! " . $this->db->error . "\n" . $this->lastQuery);
		}
		
		print("\tUpdated $updatedUsers existing users and added $newUsers new users!\n"); 
		
		return true;
	}
	
	
	public function updateInfo() {
		$now = time();
		$this->lastQuery = "UPDATE patreon_info SET v='$now' WHERE k='last_update';";
		$result = $this->db->query($this->lastQuery);
		
		if ($result === false) print("Error: Failed to update last_update record database! " . $this->db->error . "\n" . $this->lastQuery);
		return true;
	}
	
	
	public function run() {
		
		if (!$this->loadAllPatreonUsers(false)) return false;
		if (!$this->loadAllExistingUsers(false)) return false;
		
		$this->mergeUsers();
		$this->updateUsers();
		$this->updateTierChanges();
		$this->updateInfo();
		
		return true;
	}
	
};

$update = new UespPatreonUpdate();
$update->run();

