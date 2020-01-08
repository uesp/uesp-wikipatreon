<?php


if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension and must be run from within MediaWiki.' );
}


class SpecialUespPatreon extends SpecialPage {
	
	
	public function __construct() {
		parent::__construct('UespPatreon');
	}
	
	
	public static function getPreferenceLink() {
		return "https://content3.uesp.net/wiki/Special:Preferences#mw-prefsection-uesppatreon";
		//return "https://en.uesp.net/wiki/Special:Preferences#mw-prefsection-uesppatreon";
	}
	
	
	public static function getLink($param) {
		//$link = $this->getTitle( $param )->getCanonicalURL();
					
		$link = "https://content3.uesp.net/wiki/Special:UespPatreon";
		//$link = "https://en.uesp.net/wiki/Special:UespPatreon";
		
		if ($param) $link .= "/" . $param;
		return $link;
	}
	
	
	public static function getAuthorizationLink() {
		global $uespPatreonClientId;
        global $uespPatreonClientSecret;
        
        $link = 'https://www.patreon.com/oauth2/authorize?response_type=code&client_id=' . $uespPatreonClientId;
 		$link .= '&redirect_uri=' . SpecialUespPatreon::getLink("callback");
 		
 		return $link;
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
		if ($row['user_patreonid'] == null) return -1;
		
		$cachedId = $row['user_patreonid'];
		return $row['user_patreonid'];
	}
	
	
	public static function isAPayingUser() {
		$patreonId = SpecialUespPatreon::loadPatreonUserId();
		return $patreonId > 0;
	}
	

	public function execute( $parameter ){
		$this->setHeaders();
		
		switch($parameter){
			case 'redirect':
				$this->redirect();
				break;
			case 'callback':
				$this->handleCallback();
				break;
			case 'unlink':
				$this->unlink();
				break;
			default:
				$this->_default();
			break;
		}
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
        require_once('Patreon/OAuth.php');
        
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
				'refresh_token' => $tokens['refresh_token']
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
	
	
	private function unlink() {
		global $wgOut;
		
		$this->deletePatreonUser();
		
		$wgOut->redirect( SpecialUespPatreon::getPreferenceLink() );
		return true;
	}
	
	
	private function _default() {
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
	
};