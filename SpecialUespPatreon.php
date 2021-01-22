<?php


if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension and must be run from within MediaWiki.' );
}


class SpecialUespPatreon extends SpecialPage {
	
	public $UESP_CAMPAIGN_ID = 2731208;
	
	
	public function __construct() {
		parent::__construct('UespPatreon');
	}
	
	
	public static function getPreferenceLink() {
		//return "https://content3.uesp.net/wiki/Special:Preferences#mw-prefsection-uesppatreon";
		return "https://en.uesp.net/wiki/Special:Preferences#mw-prefsection-uesppatreon";
	}
	
	
	public static function getLink($param, $query) {
		//$link = $this->getTitle( $param )->getCanonicalURL();
					
		//$link = "https://content3.uesp.net/wiki/Special:UespPatreon";
		$link = "https://en.uesp.net/wiki/Special:UespPatreon";
		
		if ($param) $link .= "/" . $param;
		if ($query) $link .= "?" . $query;
		return $link;
	}
	
	
	public static function getAuthorizationLink() {
		global $uespPatreonClientId;
        global $uespPatreonClientSecret;
        
        $link = 'https://www.patreon.com/oauth2/authorize?response_type=code&client_id=' . $uespPatreonClientId;
 		$link .= '&redirect_uri=' . SpecialUespPatreon::getLink("callback");
 		
 		return $link;
	}
	
	
	public static function loadPatreonUser() {
		global $wgUser;
		static $cachedUser = null;
		
		if (!$wgUser->isLoggedIn()) return null;
		if ($cachedUser != null) return $cachedUser;
		
		$db = wfGetDB(DB_SLAVE);
		
		$res = $db->select('patreon_user', '*', ['user_id' => $wgUser->getId()]);
		if ($res->numRows() == 0) return null;
		
		$row = $res->fetchRow();
		if ($row == null) return -1;
		
		$cachedUser = $row;
		return $cachedUser;
	}
	
	
	public static function loadPatreonUserId() {
		global $wgUser;
		static $cachedId = -2; 
	
		if (!$wgUser->isLoggedIn()) return -1;
		if ($cachedId > 0) return $cachedId;
		
		$db = wfGetDB(DB_SLAVE);
		
		$res = $db->select('patreon_user', 'user_patreonid', ['user_id' => $wgUser->getId()]);
		if ($res->numRows() == 0) return -1;
		
		$row = $res->fetchRow();
		if ($row == null) return -1;
		if ($row['user_patreonid'] == null) return -1;
		
		$cachedId = $row['user_patreonid'];
		return $row['user_patreonid'];
	}
	
	
	public static function isAPayingUser() {
		$patreon = SpecialUespPatreon::loadPatreonUser();
		if ($patreon == null) return false;
		
		//error_log("IsPayingUser: " . $patreon['has_donated']);
		
		return $patreon['has_donated'] > 0;
	}
	
	
	public static function refreshTokens() {
		global $uespPatreonClientId;
        global $uespPatreonClientSecret;
        global $wgOut;
        
		require_once('Patreon/API.php');
        require_once('Patreon/OAuth2.php');
        
        $patron = SpecialUespPatreon::loadPatreonUser();
        if ($patron == null) return false;
                
	    $oauth = new Patreon\OAuth($uespPatreonClientId, $uespPatreonClientSecret);
        $tokens = $oauth->refresh_token($patron['refresh_token']);
        
		SpecialUespPatreon::updatePatreonTokens($patron, $tokens);
		
		return true;
	}
	

	public function execute( $parameter ){
		$this->setHeaders();
		
		switch($parameter){
			case 'redirect':
				$this->redirect();
				break;
			case 'callback':
			case 'callback2':
				$this->handleCallback();
				break;
			case 'unlink':
				$this->unlink();
				break;
			case 'update':
				$this->update();
				break;
			case 'list':
			case 'view':
				$this->showList();
				break;
			case 'shownew':
			case 'new':
				$this->showNew();
				break;
			case 'link':
				$this->showLink();
				break;
			default:
				$this->_default();
				break;
		}
	}
	
	
	private function hasPermission($permission) {
		
			/* Admins have all permissions */
		if (in_array( 'sysop', $this->getUser()->getEffectiveGroups() )) return true;
  		
		$permission = "patreon_" . $permission;
		
		return $this->getUser()->isAllowed($permission);
	}
	
	
	private function loadPatronData($onlyActive = true, $includeFollowers = false) {
		global $uespPatreonCreatorAccessToken;
		global $uespPatreonCreatorAccessToken2;
		global $wgOut;
		
		require_once('Patreon/API2.php');
		require_once('Patreon/OAuth2.php');
		
		$api = new Patreon\API($uespPatreonCreatorAccessToken2);
		
		$response = $api->fetch_page_of_members_from_campaign($this->UESP_CAMPAIGN_ID, 2000, null);
		
		//$raw = print_r($response, true);
		//$wgOut->addHTML("<pre>$raw</pre>");
		
		$patrons = $this->parsePatronData($response, $onlyActive, $includeFollowers);
		
		return $patrons;
	}
	
	
	private function parsePatronData($responseData, $onlyActive, $includeFollowers) {
		$result = array();
		
		if ($responseData == null || count($responseData) == 0) return $result;
		
		$data = $responseData['data'];
		$included = $responseData['included'];
		$meta = $responseData['meta'];
		
		if ($data == null || $included == null) return $result;
		
		$addresses = array();
		$tiers = array();
		$users = array();
		
		foreach ($included as $row) {
			$id = $row['id'];
			$type = $row['type'];
			$attr = $row['attributes'];
			
			if ($id == null || $type == null || $attr == null) continue;
			
			if ($type == "tier") {
				$tiers[$id] = $attr;
			}
			else if ($type == "address") {
				$addresses[$id] = $attr;
			}
			else if ($type == "user") {
				$users[$id] = $attr;
			}
			else {
					//? 
			}
		}
		
		foreach ($data as $row) {
			$attr = $row['attributes'];
			$rel = $row['relationships'];
			
			if ($attr == null) continue;
			
			if ($onlyActive && $attr['patron_status'] != "active_patron") continue;
			if (!$includeFollowers && $attr['patron_status'] == "") continue;
			
			$patron = $attr;
			$patron['tier'] = "None";
			$patron['address'] = "Unknown";
			$patron['discord'] = "Unknown";
			
			if ($rel['address'] != null && $rel['address']['data'] != null) {
				$id = $rel['address']['data']['id'];
				$patron['address'] = $addresses[$id];
				if ($patron['address']== null) $patron['address'] = "Unknown";
			}
			
			if ($rel['currently_entitled_tiers'] != null && $rel['currently_entitled_tiers']['data'] != null && $rel['currently_entitled_tiers']['data'][0] != null) {
				$id = $rel['currently_entitled_tiers']['data'][0]['id'];
				
				if ($tiers[$id] != null) {
					$patron['tier'] = $tiers[$id]['title'];
					if ($patron['tier']== null) $patron['tier'] = "Unknown";
				}
			}
			
			if ($rel['user'] != null && $rel['user']['data'] != null) {
				$id = $rel['user']['data']['id'];
				
				if ($users[$id] != null && $users[$id]['social_connections'] != null && $users[$id]['social_connections']['discord'] != null) {
					$patron['discord'] = $users[$id]['social_connections']['discord'];
				}
			}
			
			$result[] = $patron;
		}
		
		usort($result, array("SpecialUespPatreon", "sortPatronsByStartDate"));
		
		return $result;
	}
	
	
	private static function sortPatronsByStartDate($a, $b) {
		return strcmp($a['pledge_relationship_start'], $b['pledge_relationship_start']);
	}
	
	
	private function showNew() {
		global $wgOut;
		
		$req = $this->getRequest();
		$showPeriod = $req->getVal('period');
		
		if ($showPeriod == "" || $showPeriod == null) $showPeriod = 7;
		if ($showPeriod <= 0) $showPeriod = 1;
		if ($showPeriod > 365) $showPeriod = 365;
		
		$periodName = "Last $showPeriod Days";
		
		if ($showPeriod == 7)
			$periodName = "Last Week";
		elseif ($showPeriod == 30 || $showPeriod == 31)
			$periodName = "Last Month";
		elseif ($showPeriod == 365)
			$periodName = "Last Year";
		
		if (!$this->hasPermission("view")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		$patrons = $this->loadPatronData(false, false);
		
		if ($patrons == null || count($patrons) == 0) {
			$wgOut->addHTML("No patrons found!");
			return;
		}
		
		$newPatrons = array();
		
		$now = new DateTime("now");
		
		foreach ($patrons as $patron) {
			$startDateStr = $patron['pledge_relationship_start'];
			
				//2020-02-22T00:31:24.967+00:00
			$startDate = DateTime::createFromFormat("Y-m-d\TH:i:s??????????", $startDateStr);
			if ($startDate === false) continue;
			
			$interval = $startDate->diff($now);
			$days = $interval->format("%a");
			if ($days > $showPeriod) continue;
			
			$newPatrons[] = $patron;
		}
		
		$homeLink = $viewLink = SpecialUespPatreon::getLink("");
		$weekLink = $viewLink = SpecialUespPatreon::getLink("shownew", "period=7");
		$week2Link = $viewLink = SpecialUespPatreon::getLink("shownew", "period=14");
		$monthLink = $viewLink = SpecialUespPatreon::getLink("shownew", "period=31");
		
		$wgOut->addHTML("<a href='$homeLink'>Home</a> : ");
		$wgOut->addHTML("<a href='$weekLink'>Last Week</a> : ");
		$wgOut->addHTML("<a href='$week2Link'>Last 2 Weeks</a> : ");
		$wgOut->addHTML("<a href='$monthLink'>Last Month</a> ");
		$wgOut->addHTML("<p/>");
		
		$count = count($newPatrons);
		$wgOut->addHTML("Showing $count new patrons in the $periodName...");
		
		$this->outputPatronTable($newPatrons);
	}
	
	
	private function showList() {
		global $wgOut;
		
		if (!$this->hasPermission("view")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		//$wgOut->addHTML("Show all patrons/pledges:");
		
		$patrons = $this->loadPatronData(false, false);
		
		if ($patrons == null || count($patrons) == 0) {
			$wgOut->addHTML("No patrons found!");
			return;
		}
		
		$count = count($patrons);
		$wgOut->addHTML("Showing data for $count patrons...");
		
		//$raw = print_r($patrons, true);
		//$wgOut->addHTML("<pre>$raw</pre>");
		
		$this->outputPatronTable($patrons);
	}
	
	
	private function outputPatronTable($patrons) {
		global $wgOut;
		
		$wgOut->addHTML("<table class='wikitable sortable jquery-tablesorter' id='uesppatrons'>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Full Name</th>");
		$wgOut->addHTML("<th>Tier</th>");
		$wgOut->addHTML("<th>Status</th>");
		$wgOut->addHTML("<th>Pledge Type</th>");
		$wgOut->addHTML("<th>Lifetime $</th>");
		$wgOut->addHTML("<th>Patron Since</th>");
		$wgOut->addHTML("</tr>");
		
		foreach ($patrons as $patron) {
			$name = $patron['full_name'];
			$tier = $patron['tier'];
			$status = $patron['patron_status'];
			$lifetime = '$' . number_format($patron['lifetime_support_cents'] / 100, 2);
			$pledgeType = $patron['pledge_cadence'];
			$pledgeStart = $patron['pledge_relationship_start'];
			
			$pledgeStart = preg_replace('/(.*)T(.*)\+.*/', '\1 \2', $pledgeStart);
			
			if ($pledgeType == 1)
				$pledgeType = "Monthly";
			else if ($pledgeType == 12)
				$pledgeType = "Annual";
			else
				$pledgeType = "Every $pledgeType Months";
			
			if ($status == "active_patron")
				$status = "Active";
			elseif ($status == "declined_patron")
				$status = "Declined";
			elseif ($status == "former_patron")
				$status = "Former";
			
			$wgOut->addHTML("<tr>");
			$wgOut->addHTML("<td>$name</td>");
			$wgOut->addHTML("<td>$tier</td>");
			$wgOut->addHTML("<td>$status</td>");
			$wgOut->addHTML("<td>$pledgeType</td>");
			$wgOut->addHTML("<td>$lifetime</td>");
			$wgOut->addHTML("<td>$pledgeStart</td>");
			$wgOut->addHTML("</tr>");
		}
		
		$wgOut->addHTML("</table>");
	}
	
	
	private function update() {
		global $uespPatreonWebHookSecret;
		
		$event = $_SERVER['HTTP_X_PATREON_EVENT'];
		$sig = $_SERVER['HTTP_X_PATREON_SIGNATURE'];
		
		$post = file_get_contents("php://input");
		$postData = json_decode($post, true);
		
		$compareSig = hash_hmac('md5', $post, $uespPatreonWebHookSecret);
		
		//error_log("Event: $event");
		//error_log("Signature: $sig / $compareSig");
		//error_log("Post: $post");
		
		if ($sig != $compareSig) {
			error_log("SpecialUespPatreon::update() -- Hash signatures do not match!");
			return false;
		}
		
		$data = $postData['data'];
		if ($data == null) return false;
		
		$attributes = $data['attributes'];
		if ($attributes == null) return false;
		
		$relationships = $data['relationships'];
		if ($relationships == null) return false;
		
		$user = $relationships['user'];
		if ($user == null) return false;
		
		$userData = $user['data'];
		if ($userData == null) return false;
		
		$patreonId = $userData['id'];
		if ($patreonId == null) return false;
		
		if ($event == "pledge:create" || $event == "pledge:update" ||
			$event == "members:pledge:create" || $event == "members:pledge:update") 
		{ 
			$lifetimeSupport = $attributes['lifetime_support_cents'];
			$pledgeAmount = $attributes['pledge_amount_cents'];
			//error_log("Updating Event: $lifetimeSupport / $pledgeAmount");
		}		
		else
		{
			//error_log("Unknown Event");
			return false;
		}
		
		if ($lifetimeSupport > 0 || $pledgeAmount > 0) {
			//error_log("Updating Has Donated for user $patreonId");
			SpecialUespPatreon::updatePatreonHasDonated($patreonId, 1);
		}
		
		return true;
	}
	
	
	private static function updatePatreonHasDonated($patreonId, $value) {
		$db = wfGetDB(DB_MASTER);
		
		$db->update('patreon_user', ['has_donated' => $value],
				[ 'user_patreonid' => $patreonId ]);
		
		return true;
	}
	
	
	private function redirect() {
		global $wgOut;
        
        $authorizationUrl = SpecialUespPatreon::getAuthorizationLink();
 		$wgOut->redirect( $authorizationUrl );
 		
 		return true;
	}
	
	
	private function handleCallback() {
		global $uespPatreonClientId;
        global $uespPatreonClientSecret;
        global $wgOut;
        
		require_once('Patreon/API.php');
        require_once('Patreon/OAuth2.php');
        
	    $oauth = new Patreon\OAuth($uespPatreonClientId, $uespPatreonClientSecret);
        $tokens = $oauth->get_tokens($_GET['code'], SpecialUespPatreon::getLink("callback"));
        $accessToken = $tokens['access_token'];
        $refreshToken = $tokens['refresh_token'];
        
        //$json = json_encode($tokens);
        //error_log($json);
        
        $api = new Patreon\API($accessToken);
        $patronResponse = $api->fetch_user();
        $patron = $patronResponse['data'];
        
		$user = $this->addPatreonUser($patron, $tokens);
		
		$wgOut->redirect( SpecialUespPatreon::getPreferenceLink() );
		return true;
	}
	
	
	private function addPatreonUser($patron, $tokens) {
		global $wgUser;
		
		if (!$wgUser->isLoggedIn()) return false;

		$hasDonated = 0;
		$relationships = $patron['relationships'];
		
		if ($relationships) {
			$pledges = $relationships['pledges'];
			
			if ($pledges) {
				$pledgeData = $pledges['data'];
				
				if ($pledgeData && count($pledgeData) > 0) {
					$hasDonated = 1;
				}					
			}
		}
		
		//$json = json_encode($patron);
		//error_log($json);
		
		$db = wfGetDB(DB_MASTER);
		
		$expires = time() + $tokens['expires_in'];
		//error_log("expires: " . time() . ":" . $tokens['expires_in'] . ":" . $expires);
		
		$db->delete('patreon_user', ['user_id' => $wgUser->getId()]);
		$db->insert('patreon_user', ['user_patreonid' => $patron['id'], 
				'user_id' => $wgUser->getId(), 
				'token_expires' => wfTimestamp(TS_DB, $expires),
				'access_token' => $tokens['access_token'],
				'refresh_token' => $tokens['refresh_token'],
				'has_donated' => $hasDonated
		]);
		
		return true;
	}
	
	
	private function deletePatreonUser() {
		global $wgUser;
		
		if (!$wgUser->isLoggedIn()) return false;
		
		$db = wfGetDB(DB_MASTER);
		
		$db->delete('patreon_user', ['user_id' => $wgUser->getId()]);
		return true;
	}
	
	
	private static function updatePatreonTokens($patron, $tokens) {
		$db = wfGetDB(DB_MASTER);
		
		$db->update('patreon_user', ['token_expires' => wfTimestamp(TS_DB, $expires),
				'access_token' => $tokens['access_token'],
				'refresh_token' => $tokens['refresh_token'] ],
				[ 'user_patreonid' => $patron['id'] ]);		
		
		return true;
	}
	
	
	private function unlink() {
		global $wgOut;
		
		$this->deletePatreonUser();
		
		$wgOut->redirect( SpecialUespPatreon::getPreferenceLink() );
		return true;
	}
	
	
	private function showLink() {
		global $wgOut, $wgUser;
		
		if ( !$wgUser->isLoggedIn() ) {
			$wgOut->addHTML("You must log into the Wiki in order to link your Patreon account!");
			return;
		}

		$patreonId = SpecialUespPatreon::loadPatreonUserId();
		
		if ($patreonId <= 0) 
		{
			$wgOut->addHTML("Follow the link below to link your Patreon account to your UESP Wiki account! ");
			$url = SpecialUespPatreon::getLink("redirect");
			$wgOut->addHTML( '<p><br><a href="'.$url.'"><b>Link to Patreon</b></a>');
		}
		else
		{
			$wgOut->addHTML("Your accounts have been linked! Follow the link below to unlink your accounts. ");
			$url = SpecialUespPatreon::getLink("unlink");
			$wgOut->addHTML( '<p><br><a href="'.$url.'"><b>Unlink Patreon Account</b></a>');
		}
		
		
		return true;
	}
	
	
	private function showMainMenu() {
		global $wgOut;
		
		if (!$this->hasPermission("view")) {
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$viewLink = SpecialUespPatreon::getLink("list");
		$linkLink = SpecialUespPatreon::getLink("link");
		$newLink = SpecialUespPatreon::getLink("shownew");
		
		$wgOut->addHTML("<ul>");
		$wgOut->addHTML("<li><a href='$viewLink'>View Current Patrons</a></li>");
		$wgOut->addHTML("<li><a href='$newLink'>Show New Patrons</a></li>");
		$wgOut->addHTML("<li><a href='$linkLink'>Link to Patreon Account</a></li>");
		$wgOut->addHTML("</ul>");
		
		return true;
	}
	
	
	private function _default() {
		return $this->showMainMenu();
	}
	
};
