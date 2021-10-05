<?php
/*
 * TODO:
 * 		- Shipment method drop down list.
 * 		- Auto shipping method by country and de-minimis
 */


if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension and must be run from within MediaWiki.' );
}


class SpecialUespPatreon extends SpecialPage 
{
	
	public $WIKILIST_STEEL_MAXLENGTH = 90;
	
	public $SHIRTSIZES = array(
				"Small",
				"Medium",
				"Large",
				"X-Large",
			 	"XX-Large",
				"XXX-Large",
				"XXXX-Large",
			);
	
	public static $YEARLY_DISCOUNT = 0.10;			//TODO: Put in database?
	
	public $accessToken = "";
	public $lastPatronUpdate = 0;
	
		/* These are the default values only. Actual values are stored and loaded from the database */
	public $orderSuffix = "21";
	public $orderIndex = array( "Iron" => 1, "Steel" => 1, "Elven" => 1, "Orcish" => 1, "Glass" => 1, "Daedric" => 1, "Other" => 1);
	public $orderSku = array(
			"Iron" => "UESPIron{suffix}",
			"Steel" => "UESPSteel{suffix}",
			"Elven" => "UESPElven{suffix}",
			"Orcish" => "UESPOrcish{suffix}-{shirtsize}",
			"Glass" => "UESPOrcish{suffix}-{shirtsize}",
			"Daedric" => "UESPOrcish{suffix}-{shirtsize}",
			"Other" => "UESPOther{suffix}");
	
	public $inputAction = "";
	public $inputOnlyActive = 0;
	public $inputShowNewPeriod = 7;
	public $inputHideTierIron = 0;
	public $inputHideTierSteel = 0;
	public $inputHideTierElven = 0;
	public $inputHideTierOrcish = 0;
	public $inputHideTierGlass = 0;
	public $inputHideTierDaedric = 0;
	public $inputHideTierOther = 0;
	public $inputPatronIds = array();
	public $inputShipmentIds = array();
	public $inputPatronId = 0;
	public $inputRewardId = 0;
	public $inputShipmentId = 0;
	public $inputFilter = "";
	public $inputShowOnlyUnprocessed = 0;
	
	public $saveInfoOnExit = false;
	
	public $breadcrumb = array();
	public $patrons = array();
	public $tierChanges = array();
	public $shipments = array();
	public $patronData = array();
	
	
	public function __construct() 
	{
		global $wgOut;
		
		parent::__construct('UespPatreon');
		
		$wgOut->addModules( 'ext.UespPatreon.modules' );
		
		register_shutdown_function(array($this, 'callRegisteredShutdown'));
	}
	
	
	public function callRegisteredShutdown()
	{
		if ($this->saveInfoOnExit) $this->saveInfo();
	}
	
	
	public static function getPledgeCadenceText($cadence)
	{
		if ($cadence == 1)  return "Monthly";
		if ($cadence == 12) return "Annual";
		
		$pledgeType = self::escapeHtml($cadence);
		$pledgeType = "Every $pledgeType Months";
		return $pledgeType;
	}
	
	
	public static function getYearlyTierAmount($tier, $pledgeCadence)
	{
		$pledgeCadence = intval($pledgeCadence);
		if ($pledgeCadence <= 0) $pledgeCadence = 1;
		
		$tier = strtolower($tier);
		$yearlyAmt = 0;
		
			//TODO: Put in database?
		if ($tier == "iron")
			$yearlyAmt = 2400;
		elseif ($tier == "steel")
			$yearlyAmt = 4800;
		elseif ($tier == "elven")
			$yearlyAmt = 9600;
		elseif ($tier == "orcish")
			$yearlyAmt = 14400;
		elseif ($tier == "glass")
			$yearlyAmt = 30000;
		elseif ($tier == "daedric")
			$yearlyAmt = 60000;
		
		if ($pledgeCadence >= 12)
		{
			$yearlyAmt = floor($yearlyAmt * (1 - self::$YEARLY_DISCOUNT));
		}
		
		return $yearlyAmt;
	}
	
	
	public static function getTierRewardShippingValue($tier)
	{
		$tier = strtolower($tier);
		$value = 0;
		
			//TODO: Put in database?
		if ($tier == "iron")
			$value = 200;
		elseif ($tier == "steel")
			$value = 1000;
		elseif ($tier == "elven")
			$value = 2000;
		elseif ($tier == "orcish")
			$value = 4500;
		elseif ($tier == "glass")
			$value = 4500;
		elseif ($tier == "daedric")
			$value = 4500;
		
		return $value;
	}
	
	
	public static function getPreferenceLink() {
		//return "https://content3.uesp.net/wiki/Special:Preferences#mw-prefsection-uesppatreon";
		return "https://en.uesp.net/wiki/Special:Preferences#mw-prefsection-uesppatreon";
	}
	
	
	public static function getLink($param = null, $query = null) {
		//$link = $this->getTitle( $param )->getCanonicalURL();
		
		$link = "https://content3.uesp.net/wiki/Special:UespPatreon";
		//$link = "https://en.uesp.net/wiki/Special:UespPatreon";
		
		if ($param) $link .= "/" . $param;
		if ($query) $link .= "?" . $query;
		return $link;
	}
	
	
	public static function escapeHtml($html) {
		return htmlspecialchars($html);
	}
	
	
	public static function getAuthorizationLink() {
		global $uespPatreonClientId;
		global $uespPatreonClientSecret;
		
		$link = 'https://www.patreon.com/oauth2/authorize?response_type=code&client_id=' . $uespPatreonClientId;
		$link .= '&redirect_uri=' . SpecialUespPatreon::getLink("callback");
		
		return $link;
	}
	
	
	private function makeOrderSku($patron, $tier = 'Other', $shirtSize = '?') 
	{
		if ($patron)
		{
			$shirtSize = $patron['shirtSize'];
			$tier = $patron['tier'];
		}
		
		$orderSku = $this->orderSku[$tier];
		if ($orderSku == null) $orderSku = "UESPOther{suffix}";
		
		$orderSku = str_replace("{suffix}", $this->orderSuffix, $orderSku);
		$orderSku = str_replace("{shirtsize}", $this->convertShirtSizeToSuffix($shirtSize), $orderSku);
		
		return $orderSku;
	}
	
	
	private function makeOrderNumber($tier) 
	{
		if ($tier == null || $tier == "") $tier = 'Other';
		
		if ($this->orderIndex[$tier] == null)
		{
			$tier = 'Other';
			$orderIndex = $this->orderIndex['Other'];
			++$this->orderIndex['Other'];
		}
		else
		{
			$orderIndex = $this->orderIndex[$tier];
			++$this->orderIndex[$tier];
		}
		
		$orderIndex = str_pad ($orderIndex, 3, '0', STR_PAD_LEFT);
		$orderNumber = "$tier{$this->orderSuffix}-$orderIndex";
		
		$this->saveInfoOnExit = true;
		
		return $orderNumber;
	}
	
	
	private function convertShirtSizeToSuffix($shirtSize)
	{
		$shirtSize = strtolower($shirtSize);
		
		if ($shirtSize == "small"     || $shirtSize == "s") return "S";
		if ($shirtSize == "medium"    || $shirtSize == "m") return "M";
		if ($shirtSize == "large"     || $shirtSize == "l") return "L";
		if ($shirtSize == "xlarge"    || $shirtSize == "x-large" || $shirtSize == "xl") return "XL";
		if ($shirtSize == "xxlarge"   || $shirtSize == "xx-large" || $shirtSize == "xxl" || $shirtSize == "2xl" || $shirtSize == "2x-large" || $shirtSize == "2xlarge") return "2XL";
		if ($shirtSize == "xxxlarge"  || $shirtSize == "xxx-large" || $shirtSize == "xxxl" || $shirtSize == "3xl" || $shirtSize == "3x-large" || $shirtSize == "3xlarge") return "3XL";
		if ($shirtSize == "xxxxlarge" || $shirtSize == "xxxx-large" || $shirtSize == "xxxxl" || $shirtSize == "4xl" || $shirtSize == "3x-large" || $shirtSize == "4xlarge") return "4XL";
		
		return $shirtSize;
	}
	
	
	public function parseRequest() {
		$req = $this->getRequest();
		
		$action = $req->getVal('action');
		if ($action != null) $this->inputAction = $action;
		
		$showPeriod = $req->getVal('period');
		
		if ($showPeriod != null && $showPeriod != "") {
			$this->inputShowNewPeriod = intval($showPeriod);
			if ($this->inputShowNewPeriod <= 0) $this->inputShowNewPeriod = 1;
			if ($this->inputShowNewPeriod > 365) $this->inputShowNewPeriod = 365;
		}
		
		$onlyUnprocessed = $req->getVal('onlyunprocess');
		if ($onlyUnprocessed != null) $this->inputShowOnlyUnprocessed = intval($onlyUnprocessed);
		
		$onlyActive = $req->getVal('onlyactive');
		if ($onlyActive != null) $this->inputOnlyActive = intval($onlyActive);
		
		$hideIron = $req->getVal('hideiron');
		$hideSteel = $req->getVal('hidesteel');
		$hideElven = $req->getVal('hideelven');
		$hideOrcish = $req->getVal('hideorcish');
		$hideGlass = $req->getVal('hideglass');
		$hideDaedric = $req->getVal('hidedaedric');
		$hideOther = $req->getVal('hideother');
		
		if ($hideIron != null) $this->inputHideTierIron = intval($hideIron);
		if ($hideSteel != null) $this->inputHideTierSteel = intval($hideSteel);
		if ($hideElven != null) $this->inputHideTierElven = intval($hideElven);
		if ($hideOrcish != null) $this->inputHideTierOrcish = intval($hideOrcish);
		if ($hideGlass != null) $this->inputHideTierGlass = intval($hideGlass);
		if ($hideDaedric != null) $this->inputHideTierDaedric = intval($hideDaedric);
		if ($hideOther != null) $this->inputHideTierOther = intval($hideOther);
		
		$patronIds = $req->getArray("patronids");
		if ($patronIds != null) $this->inputPatronIds = $patronIds;
		
		$shipmentIds = $req->getArray("shipmentids");
		if ($shipmentIds != null) $this->inputShipmentIds = $shipmentIds;
		
		$patronId = $req->getVal("patronid");
		if ($patronId != null) $this->inputPatronId = intval($patronId);
		
		$rewardId = $req->getVal("rewardid");
		if ($rewardId != null) $this->inputRewardId = intval($rewardId);
		
		$shipmentId = $req->getVal("shipmentid");
		if ($shipmentId != null) $this->inputShipmentId = intval($shipmentId);
		
		$filter = $req->getVal("filter");
		if ($filter != null) $this->inputFilter = $filter;
	}
	
	
	public function loadInfo() {
		
		$db = wfGetDB(DB_SLAVE);
		
		$res = $db->select('patreon_info', '*');
		
		while ($row = $res->fetchRow()) {
			
			if ($row['k'] == 'last_update')
				$this->lastPatronUpdate = intval($row['v']);
			elseif ($row['k'] == 'access_token')
				$this->accessToken = $row['v'];
			elseif ($row['k'] == 'orderSuffix') 
				$this->orderSuffix = $row['v'];
			elseif ($row['k'] == 'orderIndex_Iron')
				$this->orderIndex['Iron'] = intval($row['v']);
			elseif ($row['k'] == 'orderIndex_Steel')
				$this->orderIndex['Steel'] = intval($row['v']);
			elseif ($row['k'] == 'orderIndex_Elven')
				$this->orderIndex['Elven'] = intval($row['v']);
			elseif ($row['k'] == 'orderIndex_Orcish')
				$this->orderIndex['Orcish'] = intval($row['v']);
			elseif ($row['k'] == 'orderIndex_Glass')
				$this->orderIndex['Glass'] = intval($row['v']);
			elseif ($row['k'] == 'orderIndex_Daedric')
				$this->orderIndex['Daedric'] = intval($row['v']);
			elseif ($row['k'] == 'orderIndex_Other')
				$this->orderIndex['Other'] = intval($row['v']);
			elseif ($row['k'] == 'orderSku_Iron')
				$this->orderSku['Iron'] = $row['v'];
			elseif ($row['k'] == 'orderSku_Steel')
				$this->orderSku['Steel'] = $row['v'];
			elseif ($row['k'] == 'orderSku_Elven')
				$this->orderSku['Elven'] = $row['v'];
			elseif ($row['k'] == 'orderSku_Orcish')
				$this->orderSku['Orcish'] = $row['v'];
			elseif ($row['k'] == 'orderSku_Glass')
				$this->orderSku['Glass'] = $row['v'];
			elseif ($row['k'] == 'orderSku_Daedric')
				$this->orderSku['Daedric'] = $row['v'];
			elseif ($row['k'] == 'orderSku_Other')
				$this->orderSku['Other'] = $row['v'];
		}
		
		return true;
	}
	
	
	public function saveInfo()
	{
		$db = wfGetDB(DB_MASTER);
		
		$db->update('patreon_info', [ 'v' => $this->orderIndex['Iron'] ], [ 'k' => 'orderIndex_Iron' ]);
		$db->update('patreon_info', [ 'v' => $this->orderIndex['Steel'] ], [ 'k' => 'orderIndex_Steel' ]);
		$db->update('patreon_info', [ 'v' => $this->orderIndex['Elven'] ], [ 'k' => 'orderIndex_Elven' ]);
		$db->update('patreon_info', [ 'v' => $this->orderIndex['Orcish'] ], [ 'k' => 'orderIndex_Orcish' ]);
		$db->update('patreon_info', [ 'v' => $this->orderIndex['Glass'] ], [ 'k' => 'orderIndex_Glass' ]);
		$db->update('patreon_info', [ 'v' => $this->orderIndex['Daedric'] ], [ 'k' => 'orderIndex_Daedric' ]);
		$db->update('patreon_info', [ 'v' => $this->orderIndex['Other'] ], [ 'k' => 'orderIndex_Other' ]);
		
	}
	
	
	public function loadShipment($shipmentId) 
	{
		$db = wfGetDB(DB_SLAVE);
		
		$res = $db->select('patreon_shipment', '*', [ 'id' => $shipmentId]);
		
		if ($res->numRows() == 0) return null;
		
		return $res->fetchRow();
	}
	
	
	public function loadShipments() {
		$db = wfGetDB(DB_SLAVE);
		
		if ($this->inputShowOnlyUnprocessed)
			$res = $db->select('patreon_shipment', '*', 'isProcessed = 0');
		else
			$res = $db->select('patreon_shipment', '*');
		
		while ($row = $res->fetchRow()) {
			$id = intval($row['id']);
			$this->shipments[$id] = $row;
		}
		
		return true;
	}
	
	
	public function loadShipmentsByIds($ids) 
	{
		$db = wfGetDB(DB_SLAVE);
		
		$idList = implode(",", $ids);
		$res = $db->select('patreon_shipment', '*', "id IN ($idList)");
		
		while ($row = $res->fetchRow()) 
		{
			$id = intval($row['id']);
			$this->shipments[$id] = $row;
		}
		
		return $this->shipments;
	}
	
	
	public static function loadPatreonUser() {
		global $wgUser;
		static $cachedUser = null;
		
		if (!$wgUser->isLoggedIn()) return null;
		if ($cachedUser != null) return $cachedUser;
		
		$db = wfGetDB(DB_SLAVE);
		
		$res = $db->select('patreon_user', '*', ['wikiuser_id' => $wgUser->getId()]);
		if ($res->numRows() == 0) return null;
		
		$row = $res->fetchRow();
		if ($row == null) return null;
		
		$cachedUser = $row;
		return $cachedUser;
	}
	
	
	public static function loadPatreonUserId() {
		global $wgUser;
		static $cachedId = -2; 
	
		if (!$wgUser->isLoggedIn()) return -1;
		if ($cachedId > 0) return $cachedId;
		
		$db = wfGetDB(DB_SLAVE);
		
		$res = $db->select('patreon_user', 'patreon_id', ['wikiuser_id' => $wgUser->getId()]);
		if ($res->numRows() == 0) return -1;
		
		$row = $res->fetchRow();
		if ($row == null) return -1;
		if ($row['patreon_id'] == null) return -1;
		
		$cachedId = $row['patreon_id'];
		return $row['patreon_id'];
	}
	
	
	public static function isAPayingUser() {
		$patreon = SpecialUespPatreon::loadPatreonUser();
		if ($patreon == null) return false;
		
		//error_log("IsPayingUser: " . $patreon['has_donated']);
		
		return ($patreon['lifetimePledgeCents'] > 0 || $patreon['has_donated'] > 0);
	}
	
	
	public static function refreshPatreonTokens() {
		global $uespPatreonClientId;
		global $uespPatreonClientSecret;
		global $wgOut;
		
		require_once('Patreon/API.php');
		require_once('Patreon/OAuth2.php');
		
		$patron = SpecialUespPatreon::loadPatreonUser();
		if ($patron == null) return false;
		
		$oauth = new Patreon\OAuth($uespPatreonClientId, $uespPatreonClientSecret);
		$tokens = $oauth->refresh_token($patron['refresh_token']);
		
		SpecialUespPatreon::savePatreonTokens($patron, $tokens);
		
		return true;
	}
	
	
	private function hasPermission($permission) {
		
			/* Admins have all permissions */
		//if (in_array( 'sysop', $this->getUser()->getEffectiveGroups() )) return true;
		
		$permission = "patreon-" . $permission;
		
		//return in_array( $permission, $this->getUser()->getEffectiveGroups() );
		return $this->getUser()->isAllowed($permission);
	}
	
	
	private function loadPatronDataPatreon($onlyActive = true, $includeFollowers = false) {
		require_once('Patreon/API2.php');
		require_once('Patreon/OAuth2.php');
		
		if (!$this->loadInfo()) return array();
		
		$api = new Patreon\API($this->accessToken);
		
		$response = $api->fetch_page_of_members_from_campaign(UespPatreonCommon::$UESP_CAMPAIGN_ID, 2000, null);
		
		//$wgOut = $this->getOutput();
		//$raw = print_r($response, true);
		//$wgOut->addHTML("Response: <pre>$raw</pre>");
		
		$patrons = UespPatreonCommon::parsePatronData($response, $onlyActive, $includeFollowers);
		$this->patrons = $patrons;
		
		return $this->patrons;
	}
	
	
	private function loadPatronDataForMember($memberId) {
		require_once('Patreon/API2.php');
		require_once('Patreon/OAuth2.php');
		
		if (!$this->loadInfo()) return array();
		
		$api = new Patreon\API($this->accessToken);
		
		$response = $api->fetch_member_details($memberId);
		
		$wgOut = $this->getOutput();
		$raw = print_r($response, true);
		$wgOut->addHTML("Response: <pre>$raw</pre>");
		
		$this->patronData = UespPatreonCommon::parsePatronDataForMember($response);
		
		$response = $api->fetch_user_details($memberId);
		
		$wgOut = $this->getOutput();
		$raw = print_r($response, true);
		$wgOut->addHTML("Response: <pre>$raw</pre>");
		
		$response = $api->fetch_page_of_members_from_campaign(UespPatreonCommon::$UESP_CAMPAIGN_ID, 10, null);
		
		$wgOut = $this->getOutput();
		$raw = print_r($response, true);
		$wgOut->addHTML("Response: <pre>$raw</pre>");
		
		return $this->patronData;
	}
	
	
	private function saveRewardToDB($reward)
	{
		$db = wfGetDB(DB_MASTER);
		
		$safeId = intval($reward['id']);
		
		$values = array();
		$values['shipmentId'] = intval($reward['shipmentId']);
		$values['patreon_id'] = intval($reward['patreon_id']);
		$values['rewardDate'] = $reward['rewardDate'];
		$values['rewardNote'] = $reward['rewardNote'];
		$values['rewardValueCents'] = $db->strencode($reward['rewardValueCents']);
		
		if ($safeId < 0)
			$res = $db->insert('patreon_reward', $values);
		else
			$res = $db->update('patreon_reward', $values, ['id' => $safeId]);
		
		return $res;
	}
	
	
	private function loadRewardDataFromDb ($rewardId)
	{
		$db = wfGetDB(DB_SLAVE);
		
		$safeId = intval($rewardId);
		$res = $db->select('patreon_reward', '*',  "id='$safeId'");
		if ($res->numRows() == 0) return array();
		
		$reward = $res->fetchRow();
		return $reward;
	}
	
	
	private function loadRewardsFromShipment ($shipmentId)
	{
		$db = wfGetDB(DB_SLAVE);
		
		$safeId = intval($shipmentId);
		$res = $db->select('patreon_reward', '*',  "shipmentId='$safeId'");
		if ($res->numRows() == 0) return array();
		
		$rewards = array();
		
		while ($row = $res->fetchRow()) {
			$rewards[] = $row;
		}
		
		return $rewards;
	}
	
	
	private function loadRewardDataForMemberFromDb ($patronId)
	{
		$db = wfGetDB(DB_SLAVE);
		
		$safeId = intval($patronId);
		$res = $db->select('patreon_reward', '*',  "patreon_id='$safeId'");
		if ($res->numRows() == 0) return array();
		
		$rewards = array();
		
		while ($row = $res->fetchRow()) {
			$rewards[] = $row;
		}
		
		return $rewards;
	}
	
	
	private function loadPatronDataForMemberFromDb ($patronId)
	{
		$db = wfGetDB(DB_SLAVE);
		
		$safeId = intval($patronId);
		$res = $db->select('patreon_user', '*', "patreon_id='$safeId'");
		if ($res->numRows() == 0) return array();
		
		$patron = $res->fetchRow();
		$patron['origShirtSize'] = $patron['shirtSize'];
		
		$rewards = $this->loadRewardDataForMemberFromDb($patronId);
		
		$patron['rewards'] = $rewards;
		$patron['totalRewardValue'] = 0;
		
		foreach ($rewards as $reward)
		{
			$patron['totalRewardValue'] += intval($reward['rewardValueCents']);
		}
		
		$shirtSize = $this->loadPatronShirtSize($patron['wikiuser_id']);
		if ($shirtSize) $patron['shirtSize'] = $shirtSize;
		
		return $patron;
	}
	
	
	private function loadAllPatronRewardDataDB() {
		$db = wfGetDB(DB_SLAVE);
		
		$res = $db->select('patreon_reward', '*');
		if ($res->numRows() == 0) return false;
		
		while ($row = $res->fetchRow()) {
			$id = intval($row['patreon_id']);
			
			if ($this->patrons[$id] != null)
			{
				$this->patrons[$id]['rewards'][] = $row;
				$this->patrons[$id]['totalRewardValue'] += intval($row['rewardValueCents']);
			}
		}
		
		return true;
	}
	
	
	private function loadAllPatronDataDB($onlyActive = true, $includeFollowers = false) {
		$db = wfGetDB(DB_SLAVE);
		
		$res = $db->select('patreon_user', '*');
		if ($res->numRows() == 0) return array();
		
		$patrons = array();
		$index = 1;
		
		while ($row = $res->fetchRow()) {
			if ($row['name'] == "") continue;
			
			$id = intval($row['patreon_id']);
			
			if ($onlyActive && $row['status'] != 'active_patron') continue;
			if ($this->inputHideTierIron && $row['tier'] == 'Iron') continue;
			if ($this->inputHideTierSteel && $row['tier'] == 'Steel') continue;
			if ($this->inputHideTierElven && $row['tier'] == 'Elven') continue;
			if ($this->inputHideTierOrcish && $row['tier'] == 'Orcish') continue;
			if ($this->inputHideTierGlass && $row['tier'] == 'Glass') continue;
			if ($this->inputHideTierDaedric && $row['tier'] == 'Daedric') continue;
			if ($this->inputHideTierOther && $row['tier'] == '') continue;
			
			$row['rewards'] = array();
			$row['totalRewardValue'] = 0;
			$row['origShirtSize'] = $row['shirtSize'];
			
			if ($id == null || $id <= 0) $id = ++$index;
			$patrons[$id] = $row;
		}
		
		$this->patrons = $patrons;
		uasort($this->patrons, array("SpecialUespPatreon", "sortPatronsByStartDate"));
		
		$this->loadAllPatronRewardDataDB();
		$this->loadAllPatronShirtSizes();
		
		return $this->patrons;
	}
	
	
	private function loadPatronShirtSize($wikiUserId)
	{
		$db = wfGetDB(DB_SLAVE);
		
		$res = $db->select('user_properties', '*', [ 'up_user' => $wikiUserId, 'up_property' => 'uesppatreon-shirtsize']);
		if ($res->numRows() == 0) return false;
		
		$row = $res->fetchRow();
		return $row['up_value'];
	}
	
	
	private function loadAllPatronShirtSizes()
	{
		$db = wfGetDB(DB_SLAVE);
		
		$res = $db->select('user_properties', '*', [ 'up_property' => 'uesppatreon-shirtsize']);
		if ($res->numRows() == 0) return false;
		
		$shirtSizes = array();
		
		while ($row = $res->fetchRow()) 
		{
			$id = intval($row['up_user']);
			$shirtSizes[$id] = $row['up_value'];
		}
		
		foreach ($this->patrons as &$patron)
		{
			$wikiUserId = intval($patron['wikiuser_id']);
			
			if ($shirtSizes[$wikiUserId])
			{
				$patron['shirtSize'] = $shirtSizes[$wikiUserId];
			}
		}
		
		return true;
	}
	
	
	public static function sortPatronsByStartDate($a, $b) {
		return strcmp($a['startDate'], $b['startDate']);
	}
	
	
	public static function sortTierChangesByDate($a, $b) {
		return strcmp($a['date'], $b['date']);
	}
	
	
	private function loadTierChanges() {
		$db = wfGetDB(DB_SLAVE);
		
		$this->tierChanges = array();
		
		$res = $db->select(['patreon_tierchange', 'patreon_user'], '*', '', __METHOD__, [], [ 'patreon_user' => [ 'LEFT JOIN', 'patreon_tierchange.patreon_id = patreon_user.patreon_id']]);
		if ($res->numRows() == 0) return $this->tierChanges;
		
		while ($row = $res->fetchRow()) {
			$this->tierChanges[] = $row;
		}
		
		usort($this->tierChanges, array("SpecialUespPatreon", "sortTierChangesByDate"));
		
		return $this->tierChanges;
	}
	
	
	private function showNew() {
		global $wgOut;
		
		if (!$this->hasPermission("view")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		$periodName = "Last {$this->inputShowNewPeriod} Days";
		
		if ($this->inputShowNewPeriod == 7)
			$periodName = "Last Week";
		elseif ($this->inputShowNewPeriod == 30 || $this->inputShowNewPeriod == 31)
			$periodName = "Last Month";
		elseif ($this->inputShowNewPeriod == 365)
			$periodName = "Last Year";
		
		$this->loadInfo();
		$patrons = $this->loadAllPatronDataDB($this->inputOnlyActive, false);
		
		if ($patrons == null || count($patrons) == 0) {
			$wgOut->addHTML("No patrons found!");
			return;
		}
		
		$newPatrons = array();
		
		$now = time();
		
		foreach ($patrons as $patron) {
			$startDateStr = $patron['startDate'];
			$timestamp = strtotime( $startDateStr );
			
			$interval = $now - $timestamp;
			$days = $interval / 86400;
			if ($days > $this->inputShowNewPeriod) continue;
			
			$newPatrons[] = $patron;
		}
		
		$homeLink = SpecialUespPatreon::getLink("");
		$weekLink = SpecialUespPatreon::getLink("shownew", "period=7");
		$week2Link = SpecialUespPatreon::getLink("shownew", "period=14");
		$monthLink = SpecialUespPatreon::getLink("shownew", "period=31");
		
		$this->addBreadcrumb("Home", $homeLink);
		$this->addBreadcrumb("Last Week", $weekLink);
		$this->addBreadcrumb("Last 2 Weeks", $week2Link);
		$this->addBreadcrumb("Last Month", $monthLink);
		
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$count = count($newPatrons);
		$wgOut->addHTML("Showing $count new patrons in the $periodName.");
		
		$lastUpdate = $this->getLastUpdateFormat();
		$wgOut->addHTML(" Patron data last updated $lastUpdate ago. ");
		
		$this->outputPatronTable($newPatrons);
	}
	
	
	private function saveReward()
	{
		$wgOut = $this->getOutput();
		$req = $this->getRequest();
		
		if (!$this->hasPermission("edit")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		if ($this->inputRewardId <= 0 && $this->inputRewardId != -111)
		{
			$wgOut->addHTML("No reward specified!");
			return;
		}
		
		$rewardDate = $req->getVal('rewardDate');
		$rewardNote = $req->getVal('rewardNote');
		$rewardValue = $req->getVal('rewardValue');
		$shipmentId = $req->getVal('shipmentId');
		$patreonId = $req->getVal('patreonid');
		
		if ($rewardDate === null || $rewardNote === null || $rewardValue === null || $shipmentId === null || $patreonId === null)
		{
			$wgOut->addHTML("Missing reward data to save!");
			return;
		}
		
		$rewardValue = str_replace("$", "", $rewardValue);
		$rewardValue = floor(floatval($rewardValue) * 100);
		
		$reward = array();
		$reward['id'] = $this->inputRewardId;
		$reward['rewardDate'] = $rewardDate;
		$reward['rewardNote'] = $rewardNote;
		$reward['rewardValueCents'] = $rewardValue;
		$reward['shipmentId'] = intval($shipmentId);
		$reward['patreon_id'] = intval($patreonId);
		
		$saveResult = $this->saveRewardToDB($reward);
		
		$this->addBreadcrumb("Home", $this->getLink());
		$this->addBreadcrumb("Patrons", $this->getLink("list"));
		$this->addBreadcrumb("View Patron", $this->getLink("viewpatron", "patronid=$patreonId"));
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		if (!$saveResult)
		{
			$wgOut->addHTML("Error: Failed to save reward!");
			return;
		}
		
		if ($this->inputRewardId > 0)
			$wgOut->addHTML("Successfully saved reward #{$this->inputRewardId}!<br/>");
		else
			$wgOut->addHTML("Successfully added new reward for patron #$patreonId!<br/>");
		
		$this->inputPatronId = $patreonId;
		$this->showRewards(true);
	}
	
	
	private function addReward()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		if ($this->inputPatronId <= 0)
		{
			$wgOut->addHTML("No patron specified!");
			return;
		}
		
		$patron = $this->loadPatronDataForMemberFromDb($this->inputPatronId);
		
		if ($patron == null)
		{
			$wgOut->addHTML("Failed to load the specified patron!");
			return;
		}
		
		$rewardId = "-111";
		$patreonId = $this->inputPatronId;
		$shipmentId = 0;
		$date = date("Y-m-d H:i:s");
		$note = "";
		$value = $this->getYearlyTierAmount($patron['tier'], $patron['pledgeCadence']);
		$value = number_format($value / 100, 2);
		$actionLink = $this->getLink("savereward");
		
		$wgOut->addHTML("Creating New Reward:<p/>");
		$wgOut->addHTML("<form method='post' action='$actionLink' class='uespPatEditRewardForm'>");
		$wgOut->addHTML("<input type='hidden' name='rewardid' value='$rewardId' />");
		$wgOut->addHTML("<input type='hidden' name='shipmentId' value='$shipmentId' />");
		$wgOut->addHTML("<input type='hidden' name='patreonid' value='$patreonId' />");
		$wgOut->addHTML("<label for='rewardDate'>Date:</label> <input type='text' name='rewardDate' value='$date' size='24' /><br/>");
		$wgOut->addHTML("<label for='rewardNote'>Note:</label> <input type='text' name='rewardNote' value='$note' size='64' /><br/>");
		$wgOut->addHTML("<label for='rewardValue'>Value:</label> <input type='text' name='rewardValue' value='$value' size='16' /><br/>");
		$wgOut->addHTML("<input type='submit' value='Save' /><br/>");
		$wgOut->addHTML("</form>");
	}
	
	
	private function editReward()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		if ($this->inputRewardId <= 0)
		{
			$wgOut->addHTML("No reward specified!");
			return;
		}
		
		$reward = $this->loadRewardDataFromDb($this->inputRewardId);
		
		if ($reward == null)
		{
			$wgOut->addHTML("Failed to load the specified reward!");
			return;
		}
		
		$rewardId = $reward['id'];
		$patreonId = $reward['patreon_id'];
		
		$patron = $this->loadPatronDataForMemberFromDb($patreonId);
		
		$shipmentId = $reward['shipmentId'];
		$date = $this->escapeHtml($reward['rewardDate']);
		$note = $this->escapeHtml($reward['rewardNote']);
		$value = number_format($reward['rewardValueCents'] / 100, 2);
		$actionLink = $this->getLink("savereward");
		
		$wgOut->addHTML("Editing Reward #$rewardId:<p/>");
		$wgOut->addHTML("<form method='post' action='$actionLink' class='uespPatEditRewardForm'>");
		$wgOut->addHTML("<input type='hidden' name='rewardid' value='$rewardId' />");
		$wgOut->addHTML("<input type='hidden' name='shipmentId' value='$shipmentId' />");
		$wgOut->addHTML("<input type='hidden' name='patreonid' value='$patreonId' />");
		$wgOut->addHTML("<label for='rewardDate'>Date:</label> <input type='text' name='rewardDate' value='$date' size='24' /><br/>");
		$wgOut->addHTML("<label for='rewardNote'>Note:</label> <input type='text' name='rewardNote' value='$note' size='64' /><br/>");
		$wgOut->addHTML("<label for='rewardValue'>Value:</label> <input type='text' name='rewardValue' id='uesppatRewardValue' list='uesppatRewardValueList' value='$value' size='16' /><br/>");
		
		if ($patron)
		{
			$pledgeCadence = $patron['pledgeCadence'];
			$tierValues = $this->getTierRewardValues($pledgeCadence);
			$listValues = $tierValues[$patron['tier']];
			
			if ($listValues)
			{
				$wgOut->addHTML("<datalist id='uesppatRewardValueList'>");
				
				foreach ($listValues as $value)
				{
					$value = "$" . number_format($value/100, 2);
					$wgOut->addHTML("<option>$value</option>");
				}
				
				$wgOut->addHTML("</datalist>");
			}
		}
		
		$wgOut->addHTML("<input type='submit' value='Save' /><br/>");
		$wgOut->addHTML("</form>");
	}
	
	
	private function savePatronShirtSize()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		if ($this->inputPatronId <= 0)
		{
			$wgOut->addHTML("No patron specified!");
			return;
		}
		
		$patron = $this->loadPatronDataForMemberFromDb($this->inputPatronId);
		
		if ($patron == null)
		{
			$wgOut->addHTML("Failed to load the specified patron!");
			return;
		}
		
		$this->addBreadcrumb("Home", $this->getLink());
		$this->addBreadcrumb("Patrons", $this->getLink("list"));
		$this->addBreadcrumb("View Patron", $this->getLink("viewpatron", "patronid={$this->inputPatronId}"));
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$req = $this->getRequest();
		$shirtSize = $req->getVal("shirtSize");
		if ($shirtSize == null) $shirtSize = "";
		
		$db = wfGetDB(DB_MASTER);
		
		if ($shirtSize == "")
		{
			$res = $db->update("patreon_user", [ "shirtSize" => "" ], [ 'patreon_id' => $this->inputPatronId ]);
			if (!$res) $wgOut->addHTML("Error: Failed to clear shirt size in patron table!");
			
			if ($patron['wikiuser_id'] > 0)
			{
				$res = $db->delete('user_properties', ['up_user' => $patron['wikiuser_id'], 'up_property' => 'uesppatreon-shirtsize' ]);
				if (!$res) $wgOut->addHTML("Error: Failed to clear shirt size in wiki properties table!");
			}
			
		}
		elseif ($patron['wikiuser_id'] > 0)
		{
			$res = $db->update("patreon_user", [ "shirtSize" => "" ], [ 'patreon_id' => $this->inputPatronId ]);
			if (!$res) $wgOut->addHTML("Error: Failed to clear shirt size in patron table!");
			
			$newValues = array(
					'up_user' => $patron['wikiuser_id'],
					'up_property' => "uesppatreon-shirtsize",
					'up_value' => $shirtSize,
			);
			
			$res = $db->delete('user_properties', ['up_user' => $patron['wikiuser_id'], 'up_property' => 'uesppatreon-shirtsize' ]);
			$res = $db->insert('user_properties', $newValues);
			if (!$res) $wgOut->addHTML("Error: Failed to save shirt size in wiki properties table!");
		}
		else
		{
			$res = $db->update("patreon_user", [ "shirtSize" => $shirtSize ], [ 'patreon_id' => $this->inputPatronId ]);
			if (!$res) $wgOut->addHTML("Error: Failed to save shirt size in patron table!");
		}
		
		$wgOut->addHTML("Successfully updated shirt size for patron!");
	}
	
	
	private function editPatronShirtSize()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		if ($this->inputPatronId <= 0)
		{
			$wgOut->addHTML("No patron specified!");
			return;
		}
		
		$patron = $this->loadPatronDataForMemberFromDb($this->inputPatronId);
		
		if ($patron == null)
		{
			$wgOut->addHTML("Failed to load the specified patron!");
			return;
		}
		
		$this->addBreadcrumb("Home", $this->getLink());
		$this->addBreadcrumb("Patrons", $this->getLink("list"));
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$name = $this->escapeHtml($patron['name']);
		
		$wgOut->addHtml("Editing shirt size for patron $name, #{$this->inputPatronId}:");
		$actionLink = $this->getLink("saveshirtsize");
		$wgOut->addHtml("<form method='post' id='uespPatEditShirtSizeForm' action='$actionLink'>");
		$wgOut->addHtml("<input type='hidden' name='patronid' value='$this->inputPatronId' />");
		
		$wgOut->addHtml("<label for='uespPatShirtSize'>Shirt Size</label> ");
		
		$wgOut->addHtml("<select name='shirtSize' id='uespPatShirtSize'>");
		$wgOut->addHtml("<option value=''>None</option>");
		
		foreach ($this->SHIRTSIZES as $shirtSize)
		{
			$selected = "";
			if ($shirtSize == $patron['shirtSize']) $selected = "selected";
			$wgOut->addHtml("<option $selected>$shirtSize</option>");
		}
		
		$wgOut->addHtml("</select>");
		
		$wgOut->addHtml(" <input type='submit' value='Save' /> ");
		$wgOut->addHtml("</form>");
		
		return true;
	}
	
	
	private function showPatron()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		if ($this->inputPatronId <= 0)
		{
			$wgOut->addHTML("No patron specified!");
			return;
		}
		
		$patron = $this->loadPatronDataForMemberFromDb($this->inputPatronId);
		
		if ($patron == null)
		{
			$wgOut->addHTML("Failed to load the specified patron!");
			return;
		}
		
		$this->addBreadcrumb("Home", $this->getLink());
		$this->addBreadcrumb("Patrons", $this->getLink("list"));
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$patronId = $patron['patreon_id'];
		
		$wikiUserId = $patron['wikiuser_id'];
		$wikiUserText = $wikiUserId;
		
		if ($wikiUserText <= 0) 
		{
			$wikiUserText = "";
			$wikiUser = null;
		}
		else
		{
			$wikiUser = User::newFromId($wikiUserId);
		}
		
		if ($wikiUser)
		{
			$wikiUserText = "<a href='/wiki/User:" . $wikiUser->getName() . "'>" . $wikiUser->getName() . "</a>";
		}
		
		$patronName = $this->escapeHtml($patron['name']);
		$patronStatus = $this->makeNiceStatus($patron['status']);
		$patronTier = $patron['tier'];
		$patronEmail = $this->escapeHtml($patron['email']);
		$patronDiscord = $patron['discord'];
		$patronCadence = $patron['pledgeCadence'];
		$patronCadenceText = $this->getPledgeCadenceText($patronCadence);
		$patronNote = $this->escapeHtml($patron['note']);
		$patronLifetime = $patron['lifetimePledgeCents'];
		$patronLifetimeText = '$' . number_format($patronLifetime/100, 2);
		$patronTotalReward = $patron['totalRewardValue'];
		$patronRewardText = '$' . number_format($patronTotalReward/100, 2);
		$patronNetDue = '$' . number_format($patronLifetime/100 - $patronTotalReward/100, 2);
		$patronStart = $patron['startDate'];
		$patronStartDate = new DateTime($patronStart);
		$patronDiffTime = $patronStartDate->diff(new DateTime())->format("%y years, %m months");
		$addressName = $this->escapeHtml($patron['addressName']);
		$addressLine1 = $this->escapeHtml($patron['addressLine1']);
		$addressLine2 = $this->escapeHtml($patron['addressLine2']);
		$addressCity = $this->escapeHtml($patron['addressCity']);
		$addressState = $this->escapeHtml($patron['addressState']);
		$addressCountry = $this->escapeHtml($patron['addressCountry']);
		$addressZip = $this->escapeHtml($patron['addressZip']);
		$addressPhone = $this->escapeHtml($patron['addressPhone']);
		$patronShirtSize = $patron['shirtSize'];
		
		$wgOut->addHTML("Showing patron details:");
		
		$wgOut->addHTML("<table class='wikitable' id='uesppatron'><tbody>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Patron ID</th>");
		$wgOut->addHTML("<td>$patronId</td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Wiki User</th>");
		$wgOut->addHTML("<td>$wikiUserText</td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Name</th>");
		$wgOut->addHTML("<td>$patronName</td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Status</th>");
		$wgOut->addHTML("<td>$patronStatus</td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Tier</th>");
		$wgOut->addHTML("<td>$patronTier</td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Pledge Type</th>");
		$wgOut->addHTML("<td>$patronCadenceText</td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Start Date</th>");
		$wgOut->addHTML("<td>$patronStart ($patronDiffTime)</td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Lifetime Pledge</th>");
		$wgOut->addHTML("<td>$patronLifetimeText</td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Total Rewards</th>");
		$wgOut->addHTML("<td>$patronRewardText ($patronNetDue due)</td>");
		$wgOut->addHTML("</tr>");
		
		$editShirtSizeLink = $this->getLink("editshirtsize", "patronid=$patronId");
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Shirt Size</th>");
		$wgOut->addHTML("<td>$patronShirtSize &nbsp; &nbsp; <a href='$editShirtSizeLink'>Edit</a></td>");
		$wgOut->addHTML("</tr>");
		
		if ($addressLine1) $addressLine1 .= "<br/>";
		if ($addressLine2) $addressLine2 .= "<br/>";
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Address</th>");
		$wgOut->addHTML("<td>$addressName<br/>$addressLine1$addressLine2$addressCity, $addressState, $addressCountry<br/>$addressZip<br/>$addressPhone</td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("</tbody></table><p/>");
		
		$this->showRewards(true, $patron);
	}
	
	
	private function showRewards($skipBreadcrumb = false, $patron = null)
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		if ($this->inputPatronId <= 0)
		{
			$wgOut->addHTML("No patron specified!");
			return;
		}
		
		if ($patron == null)
		{
			$patron = $this->loadPatronDataForMemberFromDb($this->inputPatronId);
			
			if ($patron === null || $patron['rewards'] === null)
			{
				$wgOut->addHTML("Failed to load the specified patron!");
				return;
			}
		}
		
		if (!$skipBreadcrumb)
		{
			$this->addBreadcrumb("Home", $this->getLink());
			$this->addBreadcrumb("Patrons", $this->getLink("list"));
			$this->addBreadcrumb("View Patron", $this->getLink("viewpatron", "patronid={$this->inputPatronId}"));
			$wgOut->addHTML($this->getBreadcrumbHtml());
			$wgOut->addHTML("<p/>");
		}
		
		$name = $this->escapeHtml($patron['name']);
		$pledgeType = $this->getPledgeCadenceText($patron['pledgeCadence']);
		
		$lifetime = '$' . number_format($patron['lifetimePledgeCents'] / 100, 2);
		
		$wgOut->addHTML("Showing rewards for patron $name ($pledgeType, $lifetime total):");
		
		$this->outputRewardTable($patron['rewards']);
	}
	
	
	private function outputRewardTable($rewards)
	{
		$wgOut = $this->getOutput();
		
		$wgOut->addHTML("<table class='wikitable sortable jquery-tablesorter' id='uesprewards'><thead>");
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Date</th>");
		$wgOut->addHTML("<th>Note</th>");
		$wgOut->addHTML("<th>Shipment</th>");
		$wgOut->addHTML("<th>Value</th>");
		$wgOut->addHTML("<th></th>");
		$wgOut->addHTML("</tr></thead><tbody>");
		
		foreach ($rewards as $reward)
		{
			$rewardId = $reward['id'];
			$date = $this->escapeHtml($reward['rewardDate']);
			$note = $this->escapeHtml($reward['rewardNote']);
			$value = '$' . number_format($reward['rewardValueCents'] / 100, 2);
			$shipmentId = $reward['shipmentId'];
			$shipmentText = $shipmentId;
			if ($shipmentId <= 0) $shipmentText = "";
			
			$editLink = $this->getLink("editreward", "rewardid=$rewardId");
			
			$wgOut->addHTML("<tr>");
			$wgOut->addHTML("<td>$date</td>");
			$wgOut->addHTML("<td>$note</td>");
			$wgOut->addHTML("<td>$shipmentText</td>");
			$wgOut->addHTML("<td>$value</td>");
			$wgOut->addHTML("<td><a href='$editLink'>Edit</a></td>");
			$wgOut->addHTML("</tr>");
		}
		
		if ($this->inputPatronId > 0)
		{
			$wgOut->addHTML("<tr>");
			$addLink = $this->getLink("addreward", "patronid={$this->inputPatronId}");
			$wgOut->addHTML("<td colspan='100' style='text-align: center;'><a href='$addLink'>Add Reward</a></td>");
			$wgOut->addHTML("</tr>");
		}
		
		$wgOut->addHTML("</tobdy></table>");
	}
	
	
	private function showPledges()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		if ($this->inputPatronId <= 0)
		{
			$wgOut->addHTML("No patron specified!");
			return;
		}
		
		$userData = $this->loadPatronDataForMember($this->inputPatronId);
	}
	
	
	private function showWikiList() {
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("view")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		$this->loadInfo();
		$patrons = $this->loadAllPatronDataDB(true, false);
		$tiers = array();
		
		if ($patrons == null || count($patrons) == 0) {
			$wgOut->addHTML("No patrons found!");
			return;
		}
		
		foreach ($patrons as $patron) {
			$tier = $patron['tier'];
			if ($tier == null || $tier == "") continue;
			if ($tiers[$tier] == null) $tiers[$tier] = array();
			$tiers[$tier][] = $patron;
		}
		
		foreach ($tiers as $tier => $tierData) {
			usort($tiers[$tier], array("SpecialUespPatreon", "sortTierPatronByName"));
		}
		
		$this->addBreadcrumb("Home", $this->getLink());
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$count = count($patrons);
		$wgOut->addHTML("Showing wiki table for $count active patrons. Jump to <a href='#uespWikiPreview'>Table Preview</a> <button id='uespWikiPatronCopyButton'>Copy to Clipboard</button>");
		$wikiText  = "{| class=\"hiddentable vtop\"\n";
		
		foreach (array("Daedric", "Glass", "Orcish", "Elven", "Steel") as $tier) {
			$tierData = $tiers[$tier];
			
			if ($tier == "Daedric" || $tier == "Glass" || $tier == "Elven" || $tier == "Steel") {
				$wikiText .= "| {{C|5}}\n";
			}
			else if ($tier == "Orcish") {
				$wikiText .= ":&nbsp;\n";
			}
			
			$wikiText .= ";$tier Patrons\n";
			$outputCount = 0;
			
			foreach ($tierData as $patron) {
				++$outputCount;
				
				if ($tier == "Steel" && $outputCount > $this->WIKILIST_STEEL_MAXLENGTH) {
					$outputCount = 0;
					$wikiText .= "| {{C|5}}\n";
					$wikiText .= ";$tier Patrons (cont'd)\n";
				}
				
				$name = $this->escapeHtml($patron['name']);
				$wikiText .= ":$name\n";
			}
		}
		
		$wikiText .= "|}";
		
		$wgOut->addHTML("<pre id='uespWikiPatrons'>$wikiText</pre>");
		$wgOut->addHTML("<p><br/><a name='uespWikiPreview'></a>");
		$wgOut->addWikiText($wikiText);
	}
	
	
	public function sortTierPatronByName($a, $b)
	{
		$name1 = $a['name'];
		$name2 = $b['name'];
		return strcasecmp($name1, $name2);
	}
	
	
	private function showTierChanges() {
		global $wgOut;
		
		if (!$this->hasPermission("view")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		$this->loadInfo();
		$this->loadTierChanges();
		
		$this->addBreadcrumb("Home", $this->getLink());
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$count = count($this->tierChanges);
		$wgOut->addHTML("Showing data for $count tier changes. Jump");
		
		$wgOut->addHTML("<table class='wikitable sortable jquery-tablesorter' id='uesppatrons'><thead>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Full Name</th>");
		$wgOut->addHTML("<th>Old Tier</th>");
		$wgOut->addHTML("<th>New Tier</th>");
		$wgOut->addHTML("<th>Changed On</th>");
		$wgOut->addHTML("</tr></thead><tbody>");
		
		foreach ($this->tierChanges as $tierChange) {
			$name = $this->escapeHtml($tierChange['name']);
			$oldTier = $this->escapeHtml($tierChange['oldTier']);
			$newTier = $this->escapeHtml($tierChange['newTier']);
			$date = $this->escapeHtml($tierChange['date']);
			
			$wgOut->addHTML("<tr>");
			$wgOut->addHTML("<td>$name</td>");
			$wgOut->addHTML("<td>$oldTier</td>");
			$wgOut->addHTML("<td>$newTier</td>");
			$wgOut->addHTML("<td>$date</td>");
			$wgOut->addHTML("</tr>");
		}
		
		$wgOut->addHTML("</tbody></table>");
	}
	
	
	private function getLastUpdateFormat() {
		$lastUpdate = "";
		$diffTime = round((time() - $this->lastPatronUpdate) / 60);
		
		if ($diffTime < 60)
			$lastUpdate = "$diffTime minutes"; 
		else if ($diffTime < 24*60) {
			$diffTime = round($diffTime / 60); 
			$lastUpdate = "$diffTime hours";
		}
		else {
			$diffTime = round($diffTime / 60 / 24); 
			$lastUpdate = "$diffTime days";
		}
		
		return $lastUpdate;
	}
	
	
	private function getShowListParams($newParams = null) {
		$params = "";
		
		$onlyActive = $this->inputOnlyActive;
		if ($newParams && isset($newParams['onlyactive'])) $onlyActive = $newParams['onlyactive'];
		
		if ($onlyActive)
			$params .= "onlyactive=1";
		else
			$params .= "onlyactive=0";
		
		$hideTierIron = $this->inputHideTierIron;
		$hideTierSteel = $this->inputHideTierSteel;
		$hideTierElven = $this->inputHideTierElven;
		$hideTierOrcish = $this->inputHideTierOrcish;
		$hideTierGlass = $this->inputHideTierGlass;
		$hideTierDaedric = $this->inputHideTierDaedric;
		$hideTierOther = $this->inputHideTierOther;
		
		if ($newParams && isset($newParams['hideiron'])) $hideTierIron = $newParams['hideiron'];
		if ($newParams && isset($newParams['hidesteel'])) $hideTierSteel = $newParams['hidesteel'];
		if ($newParams && isset($newParams['hideelven'])) $hideTierElven = $newParams['hideelven'];
		if ($newParams && isset($newParams['hideorcish'])) $hideTierOrcish = $newParams['hideorcish'];
		if ($newParams && isset($newParams['hideglass'])) $hideTierGlass = $newParams['hideglass'];
		if ($newParams && isset($newParams['hidedaedric'])) $hideTierDaedric = $newParams['hidedaedric'];
		if ($newParams && isset($newParams['hideother'])) $hideTierOther = $newParams['hideother'];
		
		if ($hideTierIron) $params .= "&hideiron=1";
		if ($hideTierSteel) $params .= "&hidesteel=1";
		if ($hideTierElven) $params .= "&hideelven=1";
		if ($hideTierOrcish) $params .= "&hideorcish=1";
		if ($hideTierGlass) $params .= "&hideglass=1";
		if ($hideTierDaedric) $params .= "&hidedaedric=1";
		if ($hideTierOther) $params .= "&hideother=1";
		
		return $params;
	}
	
	
	private function getShowListTierOptionHtml() {
		$link = $this->getLink('list');
		$html = "<form id='uesppatShowListTierForm' method='get' action='$link' onsubmit='uesppatOnShowTierSubmit()'>";
		$html .= "<input type='hidden' name='onlyactive' value='{$this->inputOnlyActive}' />";
		$html .= " Show ";
		
		$ironCheck = !$this->inputHideTierIron ? "checked" : "";
		$steelCheck = !$this->inputHideTierSteel ? "checked" : "";
		$elvenCheck = !$this->inputHideTierElven ? "checked" : "";
		$orcishCheck = !$this->inputHideTierOrcish ? "checked" : "";
		$glassCheck = !$this->inputHideTierGlass ? "checked" : "";
		$daedricCheck = !$this->inputHideTierDaedric ? "checked" : "";
		$otherCheck = !$this->inputHideTierOther ? "checked" : "";
		
		$html .= "<input type='checkbox' id='uesppat_showiron_hidden' name='hideiron' value='1' style='display: none;' />";
		$html .= "<input type='checkbox' id='uesppat_showsteel_hidden' name='hidesteel' value='1' style='display: none;' />";
		$html .= "<input type='checkbox' id='uesppat_showelven_hidden' name='hideelven' value='1' style='display: none;' />";
		$html .= "<input type='checkbox' id='uesppat_showorcish_hidden' name='hideorcish' value='1' style='display: none;' />";
		$html .= "<input type='checkbox' id='uesppat_showglass_hidden' name='hideglass' value='1' style='display: none;' />";
		$html .= "<input type='checkbox' id='uesppat_showdaedric_hidden' name='hidedaedric' value='1' style='display: none;' />";
		$html .= "<input type='checkbox' id='uesppat_showother_hidden' name='hideother' value='1' style='display: none;' />";
		
		$html .= "<input type='checkbox' id='uesppat_showiron' value='1' $ironCheck /> <label for='uesppat_showiron'>Iron</label> &nbsp; ";
		$html .= "<input type='checkbox' id='uesppat_showsteel' value='1' $steelCheck /> <label for='uesppat_showsteel'>Steel</label> &nbsp; ";
		$html .= "<input type='checkbox' id='uesppat_showelven' value='1' $elvenCheck /> <label for='uesppat_showelven'>Elven</label> &nbsp; ";
		$html .= "<input type='checkbox' id='uesppat_showorcish' value='1' $orcishCheck /> <label for='uesppat_shoorcish'>Orcish</label> &nbsp; ";
		$html .= "<input type='checkbox' id='uesppat_showglass' value='1' $glassCheck /> <label for='uesppat_showglass'>Glass</label> &nbsp; ";
		$html .= "<input type='checkbox' id='uesppat_showdaedric' value='1' $daedricCheck /> <label for='uesppat_showdaedric'>Daedric</label> &nbsp; ";
		$html .= "<input type='checkbox' id='uesppat_showother' value='1' $otherCheck /> <label for='uesppat_showother'>Other</label> &nbsp; ";
		
		$safeFilter = $this->escapeHtml($this->inputFilter);
		$html .= "<label for='uesppat_filter'>Filter</label> <input type='text' id='uesppat_filter' name='filter' value='$safeFilter' maxlength='16'/> &nbsp; ";
		
		$html .= "<input type='submit' value='Refresh' />";
		$html .= "</form>";
		return $html;
	}
	
	
	private function showList() {
		global $wgOut;
		
		if (!$this->hasPermission("view")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		$this->addBreadcrumb("Home", $this->getLink());
		
		if ($this->inputOnlyActive)
			$this->addBreadcrumb("All Patrons", $this->getLink("list", $this->getShowListParams(["onlyactive" => 0])));
		else
			$this->addBreadcrumb("Only Active", $this->getLink("list", $this->getShowListParams(["onlyactive" => 1])));
		
		if ($this->hasPermission("edit")) {
			$this->addBreadcrumb($this->getShowListTierOptionHtml());
		}
		
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$this->loadInfo();
		$patrons = $this->loadAllPatronDataDB($this->inputOnlyActive, false);
		
		if ($patrons == null || count($patrons) == 0) {
			$wgOut->addHTML("No patrons found!");
			return;
		}
		
		$count = count($patrons);
		
		if ($this->inputOnlyActive)
			$wgOut->addHTML("Showing data for $count active patrons.");
		else
			$wgOut->addHTML("Showing data for $count patrons.");
		
		$lastUpdate = $this->getLastUpdateFormat();
		$wgOut->addHTML(" Patron data last updated $lastUpdate ago. ");
		
		$formLink = $this->getLink();
		$wgOut->addHTML("<form method='post' id='uesppatPatronTableForm' target='_blank' action='$formLink'>");
		$wgOut->addHTML("With Selected Patrons: ");
		$wgOut->addHTML("<input type='hidden' id='uesppatPatronTableAction' name='action' value='' />");
		
		if ($this->hasPermission("shipment")) {
			$wgOut->addHTML(" <input type='button' value='Create Shipment' onclick='uesppatOnCreateShipmentButton();' /> ");
		}
		
		if ($this->hasPermission("edit")) 
		{
			$wgOut->addHTML(" <input type='button' value='E-mail' onclick='uesppatOnEmailButton();'/> ");
			$wgOut->addHTML(" <input type='button' value='Export E-mails' onclick='uesppatOnExportEmailButton();'/> ");
		}
		
		$this->outputPatronTable($patrons);
		
		$wgOut->addHTML("</form>");
	}
	
	
	private function makeNiceStatus ($status) {
		
		if ($status == "active_patron") return "Active";
		if ($status == "declined_patron") return "Declined";
		if ($status == "former_patron") return "Former";
		if ($status == "deleted_patron") return "Deleted";
		return $this->escapeHtml($status);
	}
	
	
	private function outputPatronTable($patrons) {
		global $wgOut;
		
		$wgOut->addHTML("<table class='wikitable sortable jquery-tablesorter' id='uesppatrons'><thead>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th class='unsortable'><input id='uesppatPatronTableHeaderCheckbox' type='checkbox' value='0' /></th>");
		$wgOut->addHTML("<th>Full Name</th>");
		$wgOut->addHTML("<th>Tier</th>");
		$wgOut->addHTML("<th>Status</th>");
		$wgOut->addHTML("<th>Pledge Type</th>");
		$wgOut->addHTML("<th>Shirt Size</th>");
		$wgOut->addHTML("<th>Lifetime $</th>");
		$wgOut->addHTML("<th>Reward $</th>");
		$wgOut->addHTML("<th>Net Due $</th>");
		$wgOut->addHTML("<th>Patron Since</th>");
		$wgOut->addHTML("<th>Has Address</th>");
		$wgOut->addHTML("<th class='unsortable'></th>");
		$wgOut->addHTML("</tr></thead><tbody>");
		
		$outputCount = 0;
		
		foreach ($patrons as $patron) {
			
			if ($this->inputFilter != "")
			{
				if (stripos($patron['name'], $this->inputFilter) === false) continue;
			}
			
			++$outputCount;
			
			$patronId = $patron['patreon_id'];
			$name = $this->escapeHtml($patron['name']);
			$tier = $this->escapeHtml($patron['tier']);
			$status = $this->makeNiceStatus($patron['status']);
			$lifetime = $patron['lifetimePledgeCents'];
			$lifetimeText = '$' . number_format($lifetime / 100, 2);
			$pledgeType = $this->getPledgeCadenceText($patron['pledgeCadence']);
			$pledgeStart = $patron['startDate'];
			$shirtSize = $patron['shirtSize'];
			
			$tierYearly = $this->getYearlyTierAmount($tier, $patron['pledgeCadence']);
			
			$reward = '$' . number_format($patron['totalRewardValue'] / 100, 2);
			$netDue = $patron['lifetimePledgeCents'] - $patron['totalRewardValue'];
			$netDueText = '$' . number_format($netDue / 100, 2);
			
				/* Doesn't work currently */
			if (false && $this->hasPermission("edit")) {
				$pledgeLink = $this->getLink("viewpledges", "patronid=$patronId");
				$viewPledgeLink = "<a href='$pledgeLink'>Pledges</a>";
			}
			else {
				$viewPledgeLink = "";
			}
			
			if ($this->hasPermission("edit")) {
				$rewardLink = $this->getLink("viewrewards", "patronid=$patronId");
				$viewRewardLink = "<a href='$rewardLink'>Rewards</a>";
				
				$viewPatron = $this->getLink("viewpatron", "patronid=$patronId");
				$viewPatronLink = "<a href='$viewPatron'>Details</a>";
				
				$editShirt = $this->getLink("editshirtsize", "patronid=$patronId");
				$editShirtLink = "<a href='$editShirt'>Edit Shirt Size</a>";
			}
			else {
				$viewRewardLink = "";
				$viewPatronLink = "";
				$editShirtLink = "";
			}
			
			$hasAddress = "Yes";
			if ($patron['addressName'] == "" || $patron['addressLine1'] == "" || $patron['addressCountry'] == "") $hasAddress = "NO";
			
			$checkbox = "<input type='checkbox' name='patronids[]' class='uesppatPatronRowCheckbox' value='$patronId'/>";
			
			$netDueClass = "";
			
			if ($netDue < 0)
				$netDueClass = "uespPatError";
			else if ($netDue >= $tierYearly / 2)
				$netDueClass = "uespPatOk";
			
			$statusClass = "";
			if ($patron['status'] == "active_patron") $statusClass = "uespPatOk"; 
			
			$addressClass = "";
			if ($hasAddress == "NO") $addressClass = "uespPatError";
			
			$shirtSizeClass = "";
			if ($shirtSize == "" && ($tier == "Orcish" || $tier == "Glass" || $tier == "Daedric")) $shirtSizeClass = "uespPatError";
			
			$wgOut->addHTML("<tr>");
			$wgOut->addHTML("<td>$checkbox</td>");
			$wgOut->addHTML("<td>$name</td>");
			$wgOut->addHTML("<td>$tier</td>");
			$wgOut->addHTML("<td class='$statusClass'>$status</td>");
			$wgOut->addHTML("<td>$pledgeType</td>");
			$wgOut->addHTML("<td class='$shirtSizeClass'>$shirtSize</td>");
			$wgOut->addHTML("<td>$lifetimeText</td>");
			$wgOut->addHTML("<td>$reward</td>");
			$wgOut->addHTML("<td class='$netDueClass'>$netDueText</td>");
			$wgOut->addHTML("<td>$pledgeStart</td>");
			$wgOut->addHTML("<td class='$addressClass'>$hasAddress</td>");
			$wgOut->addHTML("<td>$viewPatronLink &nbsp; &nbsp; $viewRewardLink &nbsp; &nbsp; $editShirtLink $viewPledgeLink</td>");
			$wgOut->addHTML("</tr>");
		}
		
		if ($outputCount == 0)
		{
			$wgOut->addHTML("<tr><td colspan='20'>No patrons found matching the given options...</td></tr>");
		}
		
		$wgOut->addHTML("</table>");
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
		
		$api = new Patreon\API($accessToken);
		$patronResponse = $api->fetch_user();
		$patron = $patronResponse['data'];
		
		$user = $this->updatePatreonUser($patron, $tokens);
		
		$wgOut->redirect( SpecialUespPatreon::getPreferenceLink() );
		return true;
	}
	
	
	private function updatePatreonUser($patron, $tokens) {
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
		
		$db = wfGetDB(DB_MASTER);
		
		$expires = time() + $tokens['expires_in'];
		
		//$db->delete('patreon_user', ['wikiuser_id' => $wgUser->getId()]);
		/*
		$db->insert('patreon_user', ['patreon_id' => $patron['id'], 
				'wikiuser_id' => $wgUser->getId(), 
				'token_expires' => wfTimestamp(TS_DB, $expires),
				'access_token' => $tokens['access_token'],
				'refresh_token' => $tokens['refresh_token'],
				'has_donated' => $hasDonated
		]) //*/
		
		$newValues = array(
				'patreon_id' => $patron['id'], 
				'wikiuser_id' => $wgUser->getId(), 
				'token_expires' => wfTimestamp(TS_DB, $expires),
				'access_token' => $tokens['access_token'],
				'refresh_token' => $tokens['refresh_token'],
		);
		$updateValues = array(
				'wikiuser_id' => $wgUser->getId(), 
				'token_expires' => wfTimestamp(TS_DB, $expires),
				'access_token' => $tokens['access_token'],
				'refresh_token' => $tokens['refresh_token'],
		);
		
		$db->upsert('patreon_user', $newValues, array("patreon_id"), $updateValues);
		
		return true;
	}
	
	
	private function unlinkPatreonUser() {
		global $wgUser;
		
		if (!$wgUser->isLoggedIn()) return false;
		
		$db = wfGetDB(DB_MASTER);
		
		//$db->delete('patreon_user', ['user_id' => $wgUser->getId()]);
		$db->update('patreon_user', ['wikiuser_id' => 0], ['wikiuser_id' => $wgUser->getId()]);
		
		return true;
	}
	
	
	private static function savePatreonTokens($patron, $tokens) {
		$db = wfGetDB(DB_MASTER);
		
		$expires = time() + $tokens['expires_in'];
		
		$db->update('patreon_user', ['token_expires' => wfTimestamp(TS_DB, $expires),
				'access_token' => $tokens['access_token'],
				'refresh_token' => $tokens['refresh_token'] ],
				[ 'patreon_id' => $patron['id'] ]);
		
		return true;
	}
	
	
	private function unlink() {
		global $wgOut;
		
		$this->unlinkPatreonUser();
		
		$wgOut->redirect( SpecialUespPatreon::getPreferenceLink() );
		return true;
	}
	
	
	private function showLink() {
		global $wgOut, $wgUser;
		
		if ( !$wgUser->isLoggedIn() ) {
			$wgOut->addHTML("You must log into the Wiki in order to link your Patreon account!");
			return;
		}
		
		if (!$this->hasPermission("link")) {
			$wgOut->addHTML("Permission Denied!");
			return false;
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
	
	
	private function showMainMenu() 
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("view")) 
		{
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$viewLink = SpecialUespPatreon::getLink("list");
		$activeLink = SpecialUespPatreon::getLink("list", "onlyactive=1");
		$linkLink = SpecialUespPatreon::getLink("link");
		$newLink = SpecialUespPatreon::getLink("shownew");
		$tierChangeLink = SpecialUespPatreon::getLink("tierchange");
		$wikiLink = SpecialUespPatreon::getLink("showwiki");
		$statsLink = SpecialUespPatreon::getLink("showstats");
		
		$wgOut->addHTML("<ul>");
		$wgOut->addHTML("<li><b>Patron Info</b><ul>");
		$wgOut->addHTML("<li><a href='$activeLink'>View Active Patrons</a></li>");
		$wgOut->addHTML("<li><a href='$viewLink'>View All Patrons</a></li>");
		$wgOut->addHTML("<li><a href='$newLink'>Show New Patrons</a></li>");
		$wgOut->addHTML("<li><a href='$wikiLink'>View Wiki Patreon List</a></li>");
		$wgOut->addHTML("<li><a href='$tierChangeLink'>Show Tier Changes</a></li>");
		$wgOut->addHTML("</ul></li>");
		
		if ($this->hasPermission("shipment")) 
		{
			$wgOut->addHTML("<li><b>Shipments</b><ul>");
			
			$shipLink = SpecialUespPatreon::getLink("viewshipment");
			$wgOut->addHTML("<li><a href='$shipLink'>View Shipments</a></li>");
			
			$shipLink = SpecialUespPatreon::getLink("addshipment");
			$wgOut->addHTML("<li><a href='$shipLink'>Add Non-Patreon Shipment</a></li>");
			
			$shipLink = SpecialUespPatreon::getLink("addshipments");
			$wgOut->addHTML("<li><a href='$shipLink'>Add Multiple Non-Patreon Shipments</a></li>");
			
			$wgOut->addHTML("</ul></li>");
		}
		
		$wgOut->addHTML("<li><b>Misc</b><ul>");
		$wgOut->addHTML("<li><a href='$statsLink'>Statistics</a></li>");
		$wgOut->addHTML("<li><a href='$linkLink'>Link to Patreon Account</a></li>");
		
		if ($this->hasPermission("edit")) 
		{
			$infoLink = SpecialUespPatreon::getLink("editinfos");
			$wgOut->addHTML("<li><a href='$infoLink'>Edit Infos</a></li>");
		}
		
		$wgOut->addHTML("</ul></li>");
		$wgOut->addHTML("</ul>");
		
		return true;
	}
	
	
	private function addBreadcrumb($title, $link = null) {
		$newCrumb = array();
		$newCrumb['title'] = $title;
		$newCrumb['link'] = $link;
		$this->breadcrumb[] = $newCrumb;
	}
	
	
	private function getBreadcrumbHtml() {
		$html = "<div class='uesppatBreadcrumb'>";
		$index = 0;
		
		foreach ($this->breadcrumb as $breadcrumb) {
			if ($index != 0) $html .= " : ";
			
			$link = $breadcrumb['link'];
			$title = $breadcrumb['title'];
			
			if ($link == null)
				$html .= "$title";
			else
				$html .= "<a href='$link'>$title</a>";
			
			++$index;
		}
		
		$html .= "</div>";
		return $html;
	}
	
	
	private function createEmailList() 
	{
		$index = 1;
		$emails = array();
		
		foreach ($this->inputPatronIds as $patronId) 
		{
			$patron = $this->patrons[$patronId];
			if ($patron == null) continue;
			if ($patron['email'] == "") continue;
			
			$emails[] = $patron['email'];
		}
		
		return $emails;
	}
	
	
	private function createNewShipments()
	{
		$index = 1;
		
		foreach ($this->inputPatronIds as $patronId) 
		{
			$patron = $this->patrons[$patronId];
			
			$shipment = array();
			$shipment['id'] = $index++;
			$shipment['__isnew'] = true;
			
			if ($patron == null) 
			{
				$shipment['__isbad'] = true;
				$shipment['patreon_id'] = -1;
				$shipment['name'] = "Unknown Patron #$patronId";
				$shipment['email'] = "";
				$shipment['tier'] = "";
				$shipment['status'] = "Unknown";
				$shipment['pledgeCadence'] = 1;
				$shipment['addressName'] = "";
				$shipment['addressLine1'] = "";
				$shipment['addressLine2'] = "";
				$shipment['addressCity'] = "";
				$shipment['addressState'] = "";
				$shipment['addressZip'] = "";
				$shipment['addressCountry'] = "";
				$shipment['addressPhone'] = "";
				//$shipment['orderNumber'] = "Other" . $this->orderSuffix . "-" . str_pad(strval($this->orderIndex['Other']++), 3, '0', STR_PAD_LEFT);
				$shipment['orderNumber'] = $this->makeOrderNumber(null);
				$shipment['orderSku'] = $this->makeOrderSku($patron);
				$shipment['orderQnt'] = 1;
				$shipment['shipMethod'] = "";
				$shipment['shippingValue'] = 0;
				$shipment['rewardValue'] = 0;
			}
			else 
			{
				$shipment['patreon_id'] = $patron['patreon_id'];
				$shipment['name'] = $patron['name'];
				$shipment['email'] = $patron['email'];
				$shipment['tier'] = $patron['tier'];
				$shipment['status'] = $patron['status'];
				$shipment['pledgeCadence'] = $patron['pledgeCadence'];
				$shipment['addressName'] = $patron['addressName'];
				$shipment['addressLine1'] = $patron['addressLine1'];
				$shipment['addressLine2'] = $patron['addressLine2'];
				$shipment['addressCity'] = $patron['addressCity'];
				$shipment['addressState'] = $patron['addressState'];
				$shipment['addressZip'] = $patron['addressZip'];
				$shipment['addressCountry'] = $patron['addressCountry'];
				$shipment['addressPhone'] = $patron['addressPhone'];
				//$shipment['orderNumber'] = $patron['tier']. $this->orderSuffix . "-" . str_pad(strval($this->orderIndex[$patron['tier']]++), 3, '0', STR_PAD_LEFT);
				$shipment['orderNumber'] = $this->makeOrderNumber($patron['tier']);
				$shipment['orderSku'] = $this->makeOrderSku($patron);
				$shipment['orderQnt'] = 1;
				$shipment['shipMethod'] = "";
				$shipment['shippingValue'] = $this->getTierRewardShippingValue($patron['tier']);
				$shipment['rewardValue'] = $this->getYearlyTierAmount($patron['tier'], $patron['pledgeCadence']);
				
				if ($shipment['addressName'] == "" || $shipment['addressLine1'] == "" || $shipment['addressCountry'] == "" || $shipment['tier'] == "") $shipment['__isbad'] = true;
			}
			
			$this->shipments[] = $shipment;
		}
		
	}
	
	
	private function createNewShipmentsFromCsv($shipmentCsv) 
	{
		$wgOut = $this->getOutput();
		
		$header = $shipmentCsv[0];
		if ($header == null) return;
		$colIndex = array();
		
		foreach ($header as $i => $col)
		{
			$col = strtolower(trim($col));
			$colIndex[$col] = $i;
		}
		
		$hasError = false;
		
		if ($colIndex['name'] === null) {
			$wgOut->addHTML("Missing shipment column 'name'!<br/>");
			$hasError = true;
		}
		
		if ($colIndex['email'] === null) {
			$wgOut->addHTML("Missing shipment column 'email'!<br/>");
			$hasError = true;
		}
		
		if ($colIndex['line1'] === null) {
			$wgOut->addHTML("Missing shipment column 'line1'!<br/>");
			$hasError = true;
		}
		
		if ($colIndex['line2'] === null) {
			$wgOut->addHTML("Missing shipment column 'line2'!<br/>");
			$hasError = true;
		}
		
		if ($colIndex['city'] === null) {
			$wgOut->addHTML("Missing shipment column 'city'!<br/>");
			$hasError = true;
		}
		
		if ($colIndex['state'] === null) {
			$wgOut->addHTML("Missing shipment column 'state'!<br/>");
			$hasError = true;
		}
		
		if ($colIndex['country'] === null) {
			$wgOut->addHTML("Missing shipment column 'country'!<br/>");
			$hasError = true;
		}
		
		if ($colIndex['zip'] === null) {
			$wgOut->addHTML("Missing shipment column 'zip'!<br/>");
			$hasError = true;
		}
		
		if ($hasError) return false;
		$index = 1;
		
		for($i = 1; $i < count($shipmentCsv); ++$i) 
		{
			$row = $shipmentCsv[$i];
			
			$tier = 'Other';
			$shirtSize = '?';
			
			if ($colIndex['tier'] != null) 
			{
				$tier = trim($row[$colIndex['tier']]);
				if ($tier == null || $tier == "") $tier = 'Other';
			}
			
			if ($colIndex['shirtsize'] != null) 
			{
				$shirtSize = trim($row[$colIndex['shirtsize']]);
				if ($shirtSize == null || $shirtSize == "") $shirtSize = '?';
			}
			
			$shipment = array();
			$shipment['id'] = $index++;
			$shipment['__isnew'] = true;
			
			$shipment['patreon_id'] = -1;
			$shipment['name'] = trim($row[$colIndex['name']]);
			$shipment['email'] = trim($row[$colIndex['email']]);
			$shipment['tier'] = $tier;
			$shipment['status'] = 'Custom';
			$shipment['addressName'] = trim($row[$colIndex['name']]);
			$shipment['addressLine1'] = trim($row[$colIndex['line1']]);
			$shipment['addressLine2'] = trim($row[$colIndex['line2']]);
			$shipment['addressCity'] = trim($row[$colIndex['city']]);
			$shipment['addressState'] = trim($row[$colIndex['state']]);
			$shipment['addressZip'] = trim($row[$colIndex['zip']]);
			$shipment['addressCountry'] = trim($row[$colIndex['country']]);
			if ($colIndex['phone']) $shipment['addressPhone'] = trim($row[$colIndex['phone']]);
			
			$shipment['orderNumber'] = $this->makeOrderNumber($tier);
			$shipment['orderSku'] = $this->makeOrderSku(null, $tier, $shirtSize);
			$shipment['orderQnt'] = 1;
			$shipment['shipMethod'] = "";
			$shipment['shippingValue'] = $this->getTierRewardShippingValue($tier);
			$shipment['rewardValue'] = 0;
			
			if ($shipment['addressName'] == "" || $shipment['addressLine1'] == "" || $shipment['addressCountry'] == "" || $shipment['tier'] == "") $shipment['__isbad'] = true;
			
			$this->shipments[] = $shipment;
		}
		
	}
	
	
	private function showShipmentRewards()
	{
		global $wgOut;
		
		if (!$this->hasPermission("shipment")) 
		{
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		if ($this->inputShipmentId <= 0)
		{
			$wgOut->addHTML("No shipment specified!");
			return;
		}
		
		$this->addBreadcrumb("Home", $this->getLink());
		$this->addBreadcrumb("All Shipments", $this->getLink('viewshipment'));
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$rewards = $this->loadRewardsFromShipment($this->inputShipmentId);
		
		$wgOut->addHTML("Showing all rewards linked to shipment #{$this->inputShipmentId}:<p/>");
		
		$this->outputRewardTable($rewards);
		
		return true;
	}
	
	
	private function showShipments() {
		global $wgOut;
		
		if (!$this->hasPermission("shipment")) {
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$this->loadInfo();
		$this->loadShipments();
		
		$this->addBreadcrumb("Home", $this->getLink());
		$this->addBreadcrumb("Show All", $this->getLink('viewshipment'));
		$this->addBreadcrumb("Show Unprocessed", $this->getLink('viewshipment', 'onlyunprocess=1'));
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$count = count($this->shipments);
		
		if ($this->inputShowOnlyUnprocessed)
			$wgOut->addHTML("Showing $count unprocessed shipments.");
		else
			$wgOut->addHTML("Showing $count shipments.");
		
		$this->outputShipmentTable();
		
		return true;
	}
	
	
	private function outputShipmentTable() 
	{
		global $wgOut;
		
		$formLink = $this->getLink();
		$wgOut->addHTML("<form method='post' id='uesppatShipmentTableForm' target='_blank' action='$formLink'>");
		$wgOut->addHTML("With Selected Shipments: ");
		$wgOut->addHTML("<input type='hidden' id='uesppatShipmentTableAction' name='action' value='' />");
		$wgOut->addHTML(" <input type='button' value='Export CSV' onclick='uesppatOnExportCsvShipmentButton();'/> ");
		
		$wgOut->addHTML("<table class='wikitable sortable jquery-tablesorter' id='uesppatViewShipments'>");
		$wgOut->addHTML("<thead><tr>");
		$wgOut->addHTML("<th class='unsortable'><input id='uesppatShipmentTableHeaderCheckbox' type='checkbox' value='0' /></th>");
		$wgOut->addHTML("<th>#</th>");
		$wgOut->addHTML("<th class='unsortable'>Process</th>");
		$wgOut->addHTML("<th>Created On</th>");
		$wgOut->addHTML("<th>Order #</th>");
		$wgOut->addHTML("<th>SKU</th>");
		$wgOut->addHTML("<th>Qnt</th>");
		$wgOut->addHTML("<th>Ship Method</th>");
		$wgOut->addHTML("<th>Addressee</th>");
		$wgOut->addHTML("<th>Line 1</th>");
		$wgOut->addHTML("<th>Line 2</th>");
		$wgOut->addHTML("<th>City</th>");
		$wgOut->addHTML("<th>State</th>");
		$wgOut->addHTML("<th>Postal Code</th>");
		$wgOut->addHTML("<th>Country</th>");
		$wgOut->addHTML("<th>Email</th>");
		$wgOut->addHTML("<th>Phone Number</th>");
		$wgOut->addHTML("<th class='unsortable'></th>");
		$wgOut->addHTML("</tr></thead><tbody>");
		$index = 1;
		
		foreach ($this->shipments as $shipment) {
			$id = $shipment['id'];
			
			$isProcessed = intval($shipment['isProcessed']);
			
			$createDate = $this->escapeHtml($shipment['createDate']);
			$orderNumber = $this->escapeHtml($shipment['orderNumber']);
			$orderSku = $this->escapeHtml($shipment['orderSku']);
			$orderQnt = $shipment['orderQnt'];
			$shipMethod = $this->escapeHtml($shipment['shipMethod']);
			$addressName = $this->escapeHtml($shipment['addressName']);
			$addressLine1 = $this->escapeHtml($shipment['addressLine1']);
			$addressLine2 = $this->escapeHtml($shipment['addressLine2']);
			$addressCity = $this->escapeHtml($shipment['addressCity']);
			$addressState = $this->escapeHtml($shipment['addressState']);
			$addressZip = $this->escapeHtml($shipment['addressZip']);
			$addressCountry = $this->escapeHtml($shipment['addressCountry']);
			$addressPhone = $this->escapeHtml($shipment['addressPhone']);
			$email = $this->escapeHtml($shipment['email']);
			$emailText = "<a href='mailto:$email' target='_blank'><small>$email</small></a>";
			
			$checkbox = "<input type='checkbox' name='shipmentids[]' class='uesppatShipmentRowCheckbox' value='$id'/>";
			
			$processClass = "";
			
			if ($isProcessed)
			{
				$isProcessed = "Yes";
			}
			else
			{
				$isProcessed = "";
				$processClass = "uespPatError";
			}
			
			$editLink = $this->getLink("editshipment", "shipmentid=$id");
			$rewardsLink = $this->getLink("shiprewards", "shipmentid=$id");
			$patronLink = $this->getLink("viewpatron", "patronid={$shipment['patreon_id']}");
			
			$wgOut->addHTML("<tr>");
			$wgOut->addHTML("<td>$checkbox</td>");
			$wgOut->addHTML("<td>$index</td>");
			$wgOut->addHTML("<td class='$processClass'>$isProcessed</td>");
			$wgOut->addHTML("<td><small>$createDate</small></td>");
			$wgOut->addHTML("<td>$orderNumber</td>");
			$wgOut->addHTML("<td>$orderSku</td>");
			$wgOut->addHTML("<td>$orderQnt</td>");
			$wgOut->addHTML("<td>$shipMethod</td>");
			$wgOut->addHTML("<td>$addressName</td>");
			$wgOut->addHTML("<td><small>$addressLine1</small></td>");
			$wgOut->addHTML("<td><small>$addressLine2</small></td>");
			$wgOut->addHTML("<td>$addressCity</td>");
			$wgOut->addHTML("<td>$addressState</td>");
			$wgOut->addHTML("<td>$addressZip</td>");
			$wgOut->addHTML("<td>$addressCountry</td>");
			$wgOut->addHTML("<td>$emailText</td>");
			$wgOut->addHTML("<td><small>$addressPhone</small></td>");
			$wgOut->addHTML("<td><a href='$editLink'>Edit</a> &nbsp; &nbsp; <a href='$rewardsLink'>Rewards</a> &nbsp; &nbsp; <a href='$patronLink'>Patron</a></td>");
			$wgOut->addHTML("</tr>");
			
			++$index;
		}
		
		$wgOut->addHTML("</tbody></table>");
		$wgOut->addHTML("</form>");
	}
	
	
	private function addNonPatreonShipment()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("shipment")) 
		{
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$shipment = array();
		$shipment['id'] = -1;
		$shipment['orderQnt'] = 1;
		$shipment['patreon_id'] = -1;
		$shipment['createDate'] = date("Y-m-d H:i:s");
		
		$this->outputShipmentForm($shipment);
	}
	
	
	private function addNonPatreonShipments()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("shipment")) 
		{
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$wgOut->addHTML("Paste shipment CSV below:");
		
		$actionLink = $this->getLink('importshipments');
		$wgOut->addHTML("<form action='$actionLink' method='post' id='uespPatImportShipmentCsvForm'>");
		
		$headerRow = "Name, Tier, Email, Line1, Line2, City, State, Country, Zip";
		
		$wgOut->addHTML("<textarea name='shipmentcsv' value='' id='uespPatShipmentCsv' rows='20' cols='100'>$headerRow</textarea>");
		
		$wgOut->addHTML("<input type='submit' value='Import Shipments' />");
		$wgOut->addHTML("</form>");
	}
	
	
	private function importShipments()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("shipment") || !$this->hasPermission("edit")) 
		{
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$req = $this->getRequest();
		$shipmentCsvText = $req->getVal("shipmentcsv");
		
		if ($shipmentCsvText == null || $shipmentCsvText == "")
		{
			$wgOut->addHTML("Missing shipment CSV to import!");
			return false;
		}
		
		$this->loadInfo();
		
		$rows = str_getcsv($shipmentCsvText, "\n");
		$shipmentCsv = array();
		
		foreach($rows as &$row) 
		{
			$shipmentCsv[] = str_getcsv($row);
		}
		
		$this->createNewShipmentsFromCsv($shipmentCsv);
		
		if (count($this->shipments) <= 0)
		{
			$wgOut->addHTML("No valid shipments to import!");
			return false;
		}
		
		$this->addBreadcrumb("Home", $this->getLink());
		$this->addBreadcrumb("Patrons", $this->getLink('list'));
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$count = count($shipmentCsv);
		$wgOut->addHTML("Created new shipment with $count patrons! ");
		$wgOut->addHTML("Edit below shipments as needed and save to update shipment data. Invalid shipments will not be saved. ");
		
		$formLink = $this->getLink("savenewshipments"); 
		$wgOut->addHTML("<form method='post' id='uesppatSaveNewShipmentForm' action='$formLink' onsubmit='uesppatOnSaveNewShipments()'>");
		$wgOut->addHTML("<input type='submit' value='Save Shipments'>");
		$wgOut->addHTML("</form>");
		
		$this->outputCreateShipmentTable();
	}
	
	
	private function saveNewShipment()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit") || !$this->hasPermission("shipment")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		$this->addBreadcrumb("Home", $this->getLink());
		$this->addBreadcrumb("Patrons", $this->getLink('list'));
		$this->addBreadcrumb("Shipments", $this->getLink('viewshipment'));
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$db = wfGetDB(DB_MASTER);
		$req = $this->getRequest();
		
		$values = array();
		$values['addressName'] = $req->getVal('shipmentName');
		$values['addressLine1'] = $req->getVal('shipmentLine1');
		$values['addressLine2'] = $req->getVal('shipmentLine2');
		$values['addressCity'] = $req->getVal('shipmentCity');
		$values['addressState'] = $req->getVal('shipmentState');
		$values['addressZip'] = $req->getVal('shipmentZip');
		$values['addressCountry'] = $req->getVal('shipmentCountry');
		$values['addressPhone'] = $req->getVal('shipmentPhone');
		$values['email'] = $req->getVal('shipmentEmail');
		$values['orderNumber'] = $req->getVal('shipmentOrder');
		$values['orderSku'] = $req->getVal('shipmentSku');
		$values['orderQnt'] = $req->getVal('shipmentQnt');
		$values['shipMethod'] = $req->getVal('shipmentMethod');
		$values['createDate'] = $req->getVal('shipmentDate');
		$values['isProcessed'] = $req->getVal('shipmentProcessed');
		
		$res = $db->update('patreon_shipment', $values, ['id' => $this->inputShipmentId]);
		
		if ($res)
		{
			$wgOut->addHTML("Successfully saved shipment #{$this->inputShipmentId}!");
		}
		else
		{
			$wgOut->addHTML("Error: Failed to save shipment!");
		}
	}
	
	
	private function saveNewShipments() 
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("shipment")) 
		{
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$req = $this->getRequest();
		$patronIds = $req->getArray("patreon_id");
		$orderNumbers = $req->getArray("orderNumber");
		$orderSkus = $req->getArray("orderSku");
		$orderQnts = $req->getArray("orderQnt");
		$shipMethods = $req->getArray("shipMethod");
		$addressNames = $req->getArray("addressName");
		$addressLine1s = $req->getArray("addressLine1");
		$addressLine2s = $req->getArray("addressLine2");
		$addressCities = $req->getArray("addressCity");
		$addressStates = $req->getArray("addressState");
		$addressZips = $req->getArray("addressZip");
		$addressCountries = $req->getArray("addressCountry");
		$emails = $req->getArray("email");
		$addressPhones = $req->getArray("addressPhone");
		$rewardValues = $req->getArray("rewardValue");
		
		$db = wfGetDB(DB_MASTER);
		$count = 0;
		$errorCount = 0;
		
		foreach ($patronIds as $i => $patronId) {
			$orderNumber = $orderNumbers[$i];
			$orderSku = $orderSkus[$i];
			$orderQnt = $orderQnt[$i];
			$shipMethod = $shipMethods[$i];
			$addressName = $addressNames[$i];
			$addressLine1 = $addressLine1s[$i];
			$addressLine2 = $addressLine2s[$i];
			$addressCity = $addressCities[$i];
			$addressState = $addressStates[$i];
			$addressZip = $addressZips[$i];
			$addressCountry = $addressCountries[$i];
			$addressPhone = $addressPhones[$i];
			$rewardValue = $rewardValues[$i];
			$email = $emails[$i];
			
			$result = $db->insert("patreon_shipment", [
					"patreon_id" => $patronId,
					"orderNumber" => $orderNumber,
					"orderSku" => $orderSku,
					"orderQnt" => $orderQnt,
					"shipMethod" => $shipMethod,
					"addressName" => $addressName,
					"addressLine1" => $addressLine1,
					"addressLine2" => $addressLine2,
					"addressCity" => $addressCity,
					"addressState" => $addressState,
					"addressZip" => $addressZip,
					"addressCountry" => $addressCountry,
					"addressPhone" => $addressPhone,
					"email" => $email,
					"isProcessed" => 0,
			]);
			
			if ($result)
			{
				++$count;
				
				if ($rewardValue > 0)
				{
					$shipmentId = $db->insertId();
					$rewardNote = "$orderSku, $orderNumber";
					
					$result = $db->insert("patreon_reward", [
							"patreon_id" => $patronId,
							"rewardDate" => date("Y-m-d H:i:s"),
							"rewardNote" => $rewardNote,
							"rewardValueCents" => $rewardValue * 100,
							"shipmentId" => $shipmentId,
					]);
				}
			}
			else
			{
				++$errorCount;
			}
			
		}
		
		$wgOut->addHTML("Saved $count new shipments with $errorCount errors!");
		$wgOut->addHTML("<p/>");
		$viewLink = $this->getLink("viewshipment");
		$wgOut->addHTML("<a href='$viewLink'>View Shipments</a>");
		
		return true;
	}
	
	
	private function editShipment()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit") || !$this->hasPermission("shipment")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		if ($this->inputShipmentId <= 0)
		{
			$wgOut->addHTML("No shipment specified!");
			return;
		}
		
		$shipment = $this->loadShipment($this->inputShipmentId);
		
		if ($shipment == null)
		{
			$wgOut->addHTML("Failed to load the specified shipment!");
			return;
		}
		
		$this->outputShipmentForm($shipment);
		
		return true;
	}
	
	
	private function outputShipmentForm($shipment)
	{
		$wgOut = $this->getOutput();
		
		$this->addBreadcrumb("Home", $this->getLink());
		$this->addBreadcrumb("Patrons", $this->getLink('list'));
		$this->addBreadcrumb("Shipments", $this->getLink('viewshipment'));
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		if ($shipment['id'] < 0)
			$wgOut->addHTML("Creating New Shipment:");
		else
			$wgOut->addHTML("Editing Shipment #{$this->inputShipmentId}:");
		
		$actionLink = $this->getLink("saveshipment");
		$wgOut->addHTML("<form id='uespPatEditShipmentForm' method='post' action='$actionLink' >");
		$wgOut->addHTML("<input type='hidden' name='shipmentid' value='{$this->inputShipmentId}' />");
		$wgOut->addHTML("<table class='wikitable'><tbody>");
		
		$processCheck = $shipment['isProcessed'] ? "checked" : "";
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Processed</th>");
		$wgOut->addHTML("<td><input type='checkbox' name='shipmentProcessed' value='1' $processCheck/></td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Created On</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentDate' value='{$shipment['createDate']}' size='16' maxlength='16' /></td>");
		$wgOut->addHTML("</tr>");
		
		$orderNumber = $this->escapeHtml($shipment['orderNumber']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Order #</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentOrder' value='$orderNumber' size='16' maxlength='16' /> ");
		$wgOut->addHTML("<select name='tempordertier' id='uespPatShipmentOrderTier'>");
		$wgOut->addHTML("<option>Other</option>");
		$wgOut->addHTML("<option>Iron</option>");
		$wgOut->addHTML("<option>Steel</option>");
		$wgOut->addHTML("<option>Elven</option>");
		$wgOut->addHTML("<option>Orcish</option>");
		$wgOut->addHTML("<option>Glass</option>");
		$wgOut->addHTML("<option>Daedric</option>");
		$wgOut->addHTML("</select>");
		$wgOut->addHTML(" <button type='button' id='uespPatronGetShipmentOrderNumberButton' onclick='uesppatOnGetShipmentOrderNumberButton();'>Get Order #</button> ");
		$wgOut->addHTML("</td></tr>");
		
		$sku = $this->escapeHtml($shipment['orderSku']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>SKU</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentSku' value='$sku' size='16' maxlength='16' /></td>");
		$wgOut->addHTML("</tr>");
		
		$qnt = $this->escapeHtml($shipment['orderQnt']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Qnt</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentQnt' value='$qnt' size='8' maxlength='8' /></td>");
		$wgOut->addHTML("</tr>");
		
		$shipMethod = $this->escapeHtml($shipment['shipMethod']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Ship Method</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentMethod' value='$shipMethod' size='16' maxlength='16' /></td>");
		$wgOut->addHTML("</tr>");
		
		$shipName = $this->escapeHtml($shipment['addressName']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Addressee</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentName' value='$shipName' size='24' maxlength='24' /></td>");
		$wgOut->addHTML("</tr>");
		
		$shipLine1 = $this->escapeHtml($shipment['addressLine1']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Line 1</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentLine1' value='$shipLine1' size='32' maxlength='32' /></td>");
		$wgOut->addHTML("</tr>");
		
		$shipLine2 = $this->escapeHtml($shipment['addressLine2']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Line 2</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentLine2' value='$shipLine2' size='32' maxlength='32' /></td>");
		$wgOut->addHTML("</tr>");
		
		$shipCity = $this->escapeHtml($shipment['addressCity']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>City</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentCity' value='$shipCity 'size='24' maxlength='24' /></td>");
		$wgOut->addHTML("</tr>");
		
		$shipState = $this->escapeHtml($shipment['addressState']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>State</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentState' value='$shipState' size='24' maxlength='24' /></td>");
		$wgOut->addHTML("</tr>");
		
		$shipZip = $this->escapeHtml($shipment['addressZip']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Postal Code</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentZip' value='$shipZip' size='16' maxlength='16' /></td>");
		$wgOut->addHTML("</tr>");
		
		$shipCountry = $this->escapeHtml($shipment['addressCountry']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Country</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentCountry' value='$shipCountry' size='8' maxlength='16' /></td>");
		$wgOut->addHTML("</tr>");
		
		$shipEmail = $this->escapeHtml($shipment['email']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>E-mail</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentEmail' value='$shipEmail' size='32' maxlength='64' /></td>");
		$wgOut->addHTML("</tr>");
		
		$shipPhone = $this->escapeHtml($shipment['addressPhone']);
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Phone Number</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentPhone' value='$shipPhone' size='24' maxlength='24' /></td>");
		$wgOut->addHTML("</tr>");
		
		$tier = "Other";
		$matchResult = preg_match('#([a-zA-Z]+)#', $shipment['orderNumber'], $matches);
		if ($matchResult) $tier = $matches[1];
		$shipValue = $this->getTierRewardShippingValue($tier);
		$shipValue = "$" . number_format($shipValue/100, 2);
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Ship Value</th>");
		$wgOut->addHTML("<td><input type='text' name='shipmentValue' value='$shipValue' size='8' maxlength='8' readonly /> <div id='uesppatEditShipmentDeminis'></div></td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<td colspan='10'><input type='submit' value='Save Shipment' /></td>");
		$wgOut->addHTML("</tr>");
		
		$wgOut->addHTML("</tbody></table></form>");
		
		$this->outputTierRewardValuesJS();
		$this->outputTierShippingValuesJS();
	}
	
	
	private function updateShipmentProcessed($ids, $isProcessed = 1)
	{
		$db = wfGetDB(DB_MASTER);
		
		$res = $db->update('patreon_shipment', [ 'isProcessed' => $isProcessed ], [ 'id' => $ids ]);
		
		return true;
	}
	
	
	private function saveShipment() 
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit") || !$this->hasPermission("shipment")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		$this->addBreadcrumb("Home", $this->getLink());
		$this->addBreadcrumb("Patrons", $this->getLink('list'));
		$this->addBreadcrumb("Shipments", $this->getLink('viewshipment'));
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		if ($this->inputShipmentId <= 0)
		{
			$wgOut->addHTML("No shipment specified!");
			return;
		}
		
		$db = wfGetDB(DB_MASTER);
		$req = $this->getRequest();
		
		$values = array();
		$values['addressName'] = $req->getVal('shipmentName');
		$values['addressLine1'] = $req->getVal('shipmentLine1');
		$values['addressLine2'] = $req->getVal('shipmentLine2');
		$values['addressCity'] = $req->getVal('shipmentCity');
		$values['addressState'] = $req->getVal('shipmentState');
		$values['addressZip'] = $req->getVal('shipmentZip');
		$values['addressCountry'] = $req->getVal('shipmentCountry');
		$values['addressPhone'] = $req->getVal('shipmentPhone');
		$values['email'] = $req->getVal('shipmentEmail');
		$values['orderNumber'] = $req->getVal('shipmentOrder');
		$values['orderSku'] = $req->getVal('shipmentSku');
		$values['orderQnt'] = $req->getVal('shipmentQnt');
		$values['shipMethod'] = $req->getVal('shipmentMethod');
		$values['createDate'] = $req->getVal('shipmentDate');
		$values['isProcessed'] = $req->getVal('shipmentProcessed');
		
		$res = $db->update('patreon_shipment', $values, ['id' => $this->inputShipmentId]);
		
		if ($res)
		{
			$wgOut->addHTML("Successfully saved shipment #{$this->inputShipmentId}!");
		}
		else
		{
			$wgOut->addHTML("Error: Failed to save shipment!");
		}
		
	}
	
	
	private function createEmail()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) {
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$this->loadInfo();
		$this->loadAllPatronDataDB(false, false);
		
		$emails = $this->createEmailList();
		
		if (count($emails) == 0)
		{
			$wgOut->addHTML("No valid e-mail addresses selected!");
			return false;
		}
		
		$emailList = implode(";", $emails);
		$url = "mailto:dave@uesp.net?subject=Patreon Mail&bcc=$emailList";
		
		header('Location: '.$url);
		
		die();
	}
	
	
	private function exportEmail()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) {
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$this->loadInfo();
		$this->loadAllPatronDataDB(false, false);
		
		$emails = $this->createEmailList();
		
		if (count($emails) == 0)
		{
			$wgOut->addHTML("No valid e-mail addresses selected!");
			return false;
		}
		
		$emailList = implode("\n", $emails);
		
		header( "Pragma:  no-cache" );
		header( "Expires: 0" );
		header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		header( "Cache-Control: public" );
		//header( "Content-Description: File Transfer" );
		header( "Content-type: text/plain" );
		//header( "Content-type: text/csv" );
		//header( "Content-Transfer-Encoding: binary" );
		//header( "Content-Disposition: attachment; filename=\"patreon-emails.csv\"" );
		//header( "Content-Length: " . filesize( $filePath ) );
		//header( "Accept-Ranges: bytes" );
		
		print($emailList);
		
		die();
	}
	
	
	private function exportShipment()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("shipment")) 
		{
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		if (count($this->inputShipmentIds) <= 0) 
		{
			$wgOut->addHTML("No shipments selected!");
			return false;
		}
		
		$this->loadInfo();
		$shipments = $this->loadShipmentsByIds($this->inputShipmentIds);
		
		if (count($shipments) <= 0) 
		{
			$wgOut->addHTML("No shipments selected!");
			return false;
		}
		
		$shipmentCsv = array();
		$shipmentCsv[] = ["OrderNumber", "SKU", "Quantity", "ShippingMethod", "FullName", "Company", "Address1" ,"Address2", "City", "State", "Zip", "Phone", "Country", "Email", "GiftMessage", "ParcelInsurance", "ThirdPartyShipping"];
		
		foreach ($shipments as $shipment)
		{
			$shipCsv = [];
			$shipCsv[] = trim($shipment['orderNumber']);
			$shipCsv[] = trim($shipment['orderSku']);
			$shipCsv[] = trim($shipment['orderQnt']);
			$shipCsv[] = trim($shipment['shipMethod']);
			$shipCsv[] = trim($shipment['addressName']);
			$shipCsv[] = "";
			$shipCsv[] = trim($shipment['addressLine1']);
			$shipCsv[] = trim($shipment['addressLine2']);
			$shipCsv[] = trim($shipment['addressCity']);
			$shipCsv[] = trim($shipment['addressState']);
			$shipCsv[] = trim($shipment['addressZip']);
			$shipCsv[] = trim($shipment['addressPhone']);
			$shipCsv[] = trim($shipment['addressCountry']);
			$shipCsv[] = trim($shipment['email']);
			$shipCsv[] = "";
			$shipCsv[] = "";
			$shipCsv[] = "";
			
			$shipmentCsv[] = $shipCsv;
		}
		
		header( "Pragma:  no-cache" );
		header( "Expires: 0" );
		header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		header( "Cache-Control: public" );
		//header( "Content-Description: File Transfer" );
		header( "Content-type: text/plain" );
		//header( "Content-type: text/csv" );
		//header( "Content-Transfer-Encoding: binary" );
		//header( "Content-Disposition: attachment; filename=\"patreon-emails.csv\"" );
		//header( "Content-Length: " . filesize( $filePath ) );
		//header( "Accept-Ranges: bytes" );
		
		$out = fopen('php://output', 'w');
		
		foreach ($shipmentCsv as $shipment)
		{
			fputcsv($out, $shipment);
		}
		
		fclose($out);
		
		
		$this->updateShipmentProcessed($this->inputShipmentIds, 1);
		
		die();
	}
	
	
	private function showCreateShipment() 
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("shipment")) {
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$this->loadInfo();
		$this->loadAllPatronDataDB(false, false);
		
		$this->createNewShipments();
		
		$this->addBreadcrumb("Home", $this->getLink());
		$this->addBreadcrumb("Patrons", $this->getLink('list'));
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$count = count($this->inputPatronIds);
		$wgOut->addHTML("Created new shipment with $count patrons! ");
		$wgOut->addHTML("Edit below shipments as needed and save to update shipment data. Invalid shipments will not be saved. ");
		
		$formLink = $this->getLink("savenewshipments"); 
		$wgOut->addHTML("<form method='post' id='uesppatSaveNewShipmentForm' action='$formLink' onsubmit='uesppatOnSaveNewShipments()'>");
		$wgOut->addHTML("<input type='submit' value='Save Shipments'>");
		$wgOut->addHTML("</form>");
		
		$this->outputCreateShipmentTable();
		
		$this->outputTierRewardValuesJS();
		$this->outputTierShippingValuesJS();
	}
	
	
	private function getTierShippingValues()
	{
		$tierValues = array();
		
		$tierValues["Iron"] = $this->getTierRewardShippingValue("Iron");
		$tierValues["Steel"] = $this->getTierRewardShippingValue("Steel");
		$tierValues["Elven"] = $this->getTierRewardShippingValue("Elven");
		$tierValues["Orcish"] = $this->getTierRewardShippingValue("Orcish");
		$tierValues["Glass"] = $this->getTierRewardShippingValue("Glass");
		$tierValues["Daedric"] = $this->getTierRewardShippingValue("Daedric");
		
		return $tierValues;
	}
	
	
	private function getTierRewardValues($pledgeCadence = 1)
	{
		$tierValues = array();
		
		$tierValues["Iron"] = array(); 
		$tierValues["Steel"] = array();
		$tierValues["Elven"] = array();
		$tierValues["Orcish"] = array();
		$tierValues["Glass"] = array();
		$tierValues["Daedric"] = array();
		
		$tierValues["Iron"][] = $this->getYearlyTierAmount("Iron", $pledgeCadence);
		$tierValues["Steel"][] = $this->getYearlyTierAmount("Steel", $pledgeCadence);
		$tierValues["Elven"][] = $this->getYearlyTierAmount("Elven", $pledgeCadence);
		$tierValues["Orcish"][] = $this->getYearlyTierAmount("Orcish", $pledgeCadence);
		$tierValues["Glass"][] = $this->getYearlyTierAmount("Glass", $pledgeCadence);
		$tierValues["Glass"][] = $tierValues["Orcish"][0];
		$tierValues["Glass"][] = $tierValues["Glass"][0] - $tierValues["Orcish"][0];
		$tierValues["Daedric"][] = $this->getYearlyTierAmount("Daedric", $pledgeCadence);
		$tierValues["Daedric"][] = $tierValues["Orcish"][0];
		$tierValues["Daedric"][] = $tierValues["Daedric"][0] - $tierValues["Orcish"][0];
		
		return $tierValues;
	}
	
	
	private function outputTierRewardValuesJS()
	{
		$wgOut = $this->getOutput();
		
		$tierValues = $this->getTierRewardValues(1);
		$yearlyTierValues = $this->getTierRewardValues(12);
		
		$wgOut->addHTML("<script type='text/javascript'>");
		
		$tierJson = json_encode($tierValues);
		$yearlyJson = json_encode($yearlyTierValues);
		
		$wgOut->addHTML("window.g_uesppatTierValues = $tierJson;");
		$wgOut->addHTML("window.g_uesppatYearlyTierValues = $yearlyJson;");
		
		$wgOut->addHTML("</script>");
	}
	
	
	private function outputTierShippingValuesJS()
	{
		$wgOut = $this->getOutput();
		
		$tierValues = $this->getTierShippingValues();
		
		$wgOut->addHTML("<script type='text/javascript'>");
		$tierJson = json_encode($tierValues);
		$wgOut->addHTML("window.g_uesppatTierShippingValues = $tierJson;");
		
		$wgOut->addHTML("</script>");
	}
	
	
	private function outputCreateShipmentTable()
	{
		$wgOut = $this->getOutput();
		
		$wgOut->addHTML("<table class='wikitable sortable jquery-tablesorter' id='uesppatCreateShipments'>");
		$wgOut->addHTML("<thead><tr>");
		$wgOut->addHTML("<th>#</th>");
		$wgOut->addHTML("<th>Name</th>");
		$wgOut->addHTML("<th>Tier</th>");
		$wgOut->addHTML("<th>Status</th>");
		$wgOut->addHTML("<th>Order #</th>");
		$wgOut->addHTML("<th>SKU</th>");
		$wgOut->addHTML("<th>Qnt</th>");
		$wgOut->addHTML("<th>Ship Method</th>");
		$wgOut->addHTML("<th>Addressee</th>");
		$wgOut->addHTML("<th>Line 1</th>");
		$wgOut->addHTML("<th>Line 2</th>");
		$wgOut->addHTML("<th>City</th>");
		$wgOut->addHTML("<th>State</th>");
		$wgOut->addHTML("<th>Postal Code</th>");
		$wgOut->addHTML("<th>Country</th>");
		$wgOut->addHTML("<th>Email</th>");
		$wgOut->addHTML("<th>Phone Number</th>");
		$wgOut->addHTML("<th>Cadence</th>");
		$wgOut->addHTML("<th>Reward Value</th>");
		$wgOut->addHTML("</tr></thead><tbody>");
		$index = 1;
		
		foreach ($this->shipments as $shipment) {
			$id = $shipment['id'];
			$name = $this->escapeHtml($shipment['name']);
			$email = $this->escapeHtml($shipment['email']);
			$tier = $this->escapeHtml($shipment['tier']);
			$patronId = $this->escapeHtml($shipment['patreon_id']);
			$status = $this->makeNiceStatus($shipment['status']);
			$addressName = $this->escapeHtml($shipment['addressName']);
			$addressLine1 = $this->escapeHtml($shipment['addressLine1']);
			$addressLine2 = $this->escapeHtml($shipment['addressLine2']);
			$addressCity = $this->escapeHtml($shipment['addressCity']);
			$addressState = $this->escapeHtml($shipment['addressState']);
			$addressZip = $this->escapeHtml($shipment['addressZip']);
			$addressCountry = $this->escapeHtml($shipment['addressCountry']);
			$addressPhone = $this->escapeHtml($shipment['addressPhone']);
			$orderNumber = $this->escapeHtml($shipment['orderNumber']);
			$orderSku = $this->escapeHtml($shipment['orderSku']);
			$orderQnt = $shipment['orderQnt'];
			$shipMethod = $this->escapeHtml($shipment['shipMethod']);
			$pledgeCadence = $shipment['pledgeCadence'];
			$shippingValue = number_format($shipment['shippingValue'] / 100, 2);
			
			$rewardValue = '';
			if ($shipment['rewardValue'] > 0) $rewardValue = '$' . number_format($shipment['rewardValue']/100, 2);
			
			$class = "";
			if ($shipment['__isbad']) $class = "uesppatBadShipment";
			
			$wgOut->addHTML("<tr class='$class' patronid='$patronId' shipmentid='$id' shippingvalue='$shippingValue'>");
			$wgOut->addHTML("<td>$index</td>");
			$wgOut->addHTML("<td>$name</td>");
			$wgOut->addHTML("<td>$tier</td>");
			$wgOut->addHTML("<td>$status</td>");
			$wgOut->addHTML("<td>$orderNumber</td>");
			$wgOut->addHTML("<td>$orderSku</td>");
			$wgOut->addHTML("<td>$orderQnt</td>");
			$wgOut->addHTML("<td>$shipMethod</td>");
			$wgOut->addHTML("<td>$addressName</td>");
			$wgOut->addHTML("<td>$addressLine1</td>");
			$wgOut->addHTML("<td>$addressLine2</td>");
			$wgOut->addHTML("<td>$addressCity</td>");
			$wgOut->addHTML("<td>$addressState</td>");
			$wgOut->addHTML("<td>$addressZip</td>");
			$wgOut->addHTML("<td>$addressCountry</td>");
			$wgOut->addHTML("<td>$email</td>");
			$wgOut->addHTML("<td>$addressPhone</td>");
			$wgOut->addHTML("<td>$pledgeCadence</td>");
			$wgOut->addHTML("<td>$rewardValue</td>");
			$wgOut->addHTML("</tr>");
			
			++$index;
		}
		
		$wgOut->addHTML("</tbody></table>");
		
		$wgOut->addHTML("<p/>Deleted Rows (click to restore):");
		$wgOut->addHTML("<table class='wikitable' id='uesppatDeletedShipments'>");
		$wgOut->addHTML("<thead><tr>");
		$wgOut->addHTML("<th>#</th>");
		$wgOut->addHTML("<th>Name</th>");
		$wgOut->addHTML("<th>Tier</th>");
		$wgOut->addHTML("<th>Status</th>");
		$wgOut->addHTML("<th>Order #</th>");
		$wgOut->addHTML("<th>SKU</th>");
		$wgOut->addHTML("<th>Qnt</th>");
		$wgOut->addHTML("<th>Ship Method</th>");
		$wgOut->addHTML("<th>Addressee</th>");
		$wgOut->addHTML("<th>Line 1</th>");
		$wgOut->addHTML("<th>Line 2</th>");
		$wgOut->addHTML("<th>City</th>");
		$wgOut->addHTML("<th>State</th>");
		$wgOut->addHTML("<th>Postal Code</th>");
		$wgOut->addHTML("<th>Country</th>");
		$wgOut->addHTML("<th>Email</th>");
		$wgOut->addHTML("<th>Phone Number</th>");
		$wgOut->addHTML("<th>Cadence</th>");
		$wgOut->addHTML("<th>Reward Value</th>");
		$wgOut->addHTML("</tr></thead><tbody>");
		$wgOut->addHTML("</tbody></table>");
	}
	
	
	private function saveInfos()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) 
		{
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$this->addBreadcrumb("Home", $this->getLink());
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$result = true;
		
		$req = $this->getRequest();
		$db = wfGetDB(DB_MASTER);
		
		$orderSuffix = $req->getVal("orderSuffix");
		if ($orderSuffix) $result &= $db->update("patreon_info", [ 'v' => $orderSuffix ], [ 'k' => 'orderSuffix' ]); 
		
		$orderIndex = $req->getVal("orderIndexIron");
		if ($orderIndex) $result &= $db->update("patreon_info", [ 'v' => $orderIndex ], [ 'k' => 'orderIndex_Iron' ]);
		
		$orderIndex = $req->getVal("orderIndexSteel");
		if ($orderIndex) $result &= $db->update("patreon_info", [ 'v' => $orderIndex ], [ 'k' => 'orderIndex_Steel' ]);
		
		$orderIndex = $req->getVal("orderIndexElven");
		if ($orderIndex) $result &= $db->update("patreon_info", [ 'v' => $orderIndex ], [ 'k' => 'orderIndex_Elven' ]);
		
		$orderIndex = $req->getVal("orderIndexOrcish");
		if ($orderIndex) $result &= $db->update("patreon_info", [ 'v' => $orderIndex ], [ 'k' => 'orderIndex_Orcish' ]);
		
		$orderIndex = $req->getVal("orderIndexGlass");
		if ($orderIndex) $result &= $db->update("patreon_info", [ 'v' => $orderIndex ], [ 'k' => 'orderIndex_Glass' ]);
		
		$orderIndex = $req->getVal("orderIndexDaedric");
		if ($orderIndex) $result &= $db->update("patreon_info", [ 'v' => $orderIndex ], [ 'k' => 'orderIndex_Daedric' ]);
		
		$orderIndex = $req->getVal("orderIndexOther");
		if ($orderIndex) $result &= $db->update("patreon_info", [ 'v' => $orderIndex ], [ 'k' => 'orderIndex_Other' ]);
		
		$orderSku = $req->getVal("orderSkuIron");
		if ($orderSku) $result &= $db->update("patreon_info", [ 'v' => $orderSku ], [ 'k' => 'orderSku_Iron' ]);
		
		$orderSku = $req->getVal("orderSkuSteel");
		if ($orderSku) $result &= $db->update("patreon_info", [ 'v' => $orderSku ], [ 'k' => 'orderSku_Steel' ]);
		
		$orderSku = $req->getVal("orderSkuElven");
		if ($orderSku) $result &= $db->update("patreon_info", [ 'v' => $orderSku ], [ 'k' => 'orderSku_Elven' ]);
		
		$orderSku = $req->getVal("orderSkuOrcish");
		if ($orderSku) $result &= $db->update("patreon_info", [ 'v' => $orderSku ], [ 'k' => 'orderSku_Orcish' ]);
		
		$orderSku = $req->getVal("orderSkuGlass");
		if ($orderSku) $result &= $db->update("patreon_info", [ 'v' => $orderSku ], [ 'k' => 'orderSku_Glass' ]);
		
		$orderSku = $req->getVal("orderSkuDaedric");
		if ($orderSku) $result &= $db->update("patreon_info", [ 'v' => $orderSku ], [ 'k' => 'orderSku_Daedric' ]);
		
		$orderSku = $req->getVal("orderSkuOther");
		if ($orderSku) $result &= $db->update("patreon_info", [ 'v' => $orderSku ], [ 'k' => 'orderSku_Other' ]);
		
		if ($result) 
			$wgOut->addHTML("Successfully updated all infos!");
		else
			$wgOut->addHTML("Error: Failed to update all infos!");
		
	}
	
	
	private function showEditInfos()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) 
		{
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$this->loadInfo();
		
		$this->addBreadcrumb("Home", $this->getLink());
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$wgOut->addHTML("Editing Infos:");
		$actionLink = $this->getLink("saveinfos");
		$wgOut->addHTML("<form id='uespPatEditShipmentForm' method='post' action='$actionLink' >");
		
		$wgOut->addHTML("<table class='wikitable'><tbody>");
		
		$value = $this->escapeHtml($this->orderSuffix);
		$wgOut->addHTML("<tr><th>Order Suffix</th>");
		$wgOut->addHTML("<td><input type='text' name='orderSuffix' value='$value' size='8' maxlength='8' /></td></tr>");
		
		$value = $this->orderIndex['Iron'];
		$wgOut->addHTML("<tr><th>Order Index Iron</th>");
		$wgOut->addHTML("<td><input type='text' name='orderIndexIron' value='$value' size='8' maxlength='8' /></td></tr>");
		
		$value = $this->orderIndex['Steel'];
		$wgOut->addHTML("<tr><th>Order Index Steel</th>");
		$wgOut->addHTML("<td><input type='text' name='orderIndexSteel' value='$value' size='8' maxlength='8' /></td></tr>");
		
		$value = $this->orderIndex['Elven'];
		$wgOut->addHTML("<tr><th>Order Index Elven</th>");
		$wgOut->addHTML("<td><input type='text' name='orderIndexElven' value='$value' size='8' maxlength='8' /></td></tr>");
		
		$value = $this->orderIndex['Orcish'];
		$wgOut->addHTML("<tr><th>Order Index Orcish</th>");
		$wgOut->addHTML("<td><input type='text' name='orderIndexOrcish' value='$value' size='8' maxlength='8' /></td></tr>");
		
		$value = $this->orderIndex['Glass'];
		$wgOut->addHTML("<tr><th>Order Index Glass</th>");
		$wgOut->addHTML("<td><input type='text' name='orderIndexGlass' value='$value' size='8' maxlength='8' /></td></tr>");
		
		$value = $this->orderIndex['Daedric'];
		$wgOut->addHTML("<tr><th>Order Index Daedric</th>");
		$wgOut->addHTML("<td><input type='text' name='orderIndexDaedric' value='$value' size='8' maxlength='8' /></td></tr>");
		
		$value = $this->orderIndex['Other'];
		$wgOut->addHTML("<tr><th>Order Index Other</th>");
		$wgOut->addHTML("<td><input type='text' name='orderIndexOther' value='$value' size='8' maxlength='8' /></td></tr>");
		
		$value = $this->escapeHtml($this->orderSku['Iron']);
		$wgOut->addHTML("<tr><th>Order SKU Iron</th>");
		$wgOut->addHTML("<td><input type='text' name='orderSkuIron' value='$value' size='32' maxlength='32' /></td></tr>");
		
		$value = $this->escapeHtml($this->orderSku['Steel']);
		$wgOut->addHTML("<tr><th>Order SKU Steel</th>");
		$wgOut->addHTML("<td><input type='text' name='orderSkuSteel' value='$value' size='32' maxlength='32' /></td></tr>");
		
		$value = $this->escapeHtml($this->orderSku['Elven']);
		$wgOut->addHTML("<tr><th>Order SKU Elven</th>");
		$wgOut->addHTML("<td><input type='text' name='orderSkuElven' value='$value' size='32' maxlength='32' /></td></tr>");
		
		$value = $this->escapeHtml($this->orderSku['Orcish']);
		$wgOut->addHTML("<tr><th>Order SKU Orcish</th>");
		$wgOut->addHTML("<td><input type='text' name='orderSkuOrcish' value='$value' size='32' maxlength='32' /></td></tr>");
		
		$value = $this->escapeHtml($this->orderSku['Glass']);
		$wgOut->addHTML("<tr><th>Order SKU Glass</th>");
		$wgOut->addHTML("<td><input type='text' name='orderSkuGlass' value='$value' size='32' maxlength='32' /></td></tr>");
		
		$value = $this->escapeHtml($this->orderSku['Daedric']);
		$wgOut->addHTML("<tr><th>Order SKU Daedric</th>");
		$wgOut->addHTML("<td><input type='text' name='orderSkuDaedric' value='$value' size='32' maxlength='32' /></td></tr>");
		
		$value = $this->escapeHtml($this->orderSku['Other']);
		$wgOut->addHTML("<tr><th>Order SKU Other</th>");
		$wgOut->addHTML("<td><input type='text' name='orderSkuOther' value='$value' size='32' maxlength='32' /></td></tr>");
		
		$wgOut->addHTML("</tbody></table>");
		$wgOut->addHTML("<input type='submit' value='Save Infos' /></form>");
	}
	
	
	private function getOrderNumber()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("edit")) 
		{
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$this->loadInfo();
		
		$req = $this->getRequest();
		$tier = $req->getVal("tier");
		if ($tier == null) $tier = 'Other';
		
		$orderNumber = $this->makeOrderNumber($tier);
		$orderSku = $this->makeOrderSku(null, $tier, '?');
		
		$jsonOutput = array(
				'orderNumber' => $orderNumber,
				'orderSku' => $orderSku,
		);
		
		header( "Expires: 0" );
		header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		header( "Cache-Control: public" );
		header( "Content-type: application/json" );
		
		print (json_encode($jsonOutput));
		
		die();
	}
	
	
	private function showStats()
	{
		$wgOut = $this->getOutput();
		
		if (!$this->hasPermission("view")) 
		{
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$this->loadInfo();
		$this->loadAllPatronDataDB(false, false);
		
		$tierCounts = array();
		$tierCounts['Iron'] = 0;
		$tierCounts['Steel'] = 0;
		$tierCounts['Elven'] = 0;
		$tierCounts['Orcish'] = 0;
		$tierCounts['Glass'] = 0;
		$tierCounts['Daedric'] = 0;
		$tierCounts['Other'] = 0;
		
		$shirtSizes = array();
		$shirtSizes['Small'] = 0;
		$shirtSizes['Medium'] = 0;
		$shirtSizes['Large'] = 0;
		$shirtSizes['X-Large'] = 0;
		$shirtSizes['XX-Large'] = 0;
		$shirtSizes['XXX-Large'] = 0;
		$shirtSizes['XXXX-Large'] = 0;
		
		$activeCount = 0;
		$totalShirtCount = 0;
		$shirtSizeTiers = array();
		$shirtSizeTierList = array();
		
		foreach ($this->orderSku as $tier => $sku)
		{
			if (strpos($sku, "{shirtsize}") !== false)
			{
				$shirtSizeTiers[$tier] = true;
				$shirtSizeTierList[] = $tier;
			}
		}
		
		foreach ($this->patrons as $patron)
		{
			if ($patron['status'] != "active_patron") continue;
			
			$tier = $patron['tier'];
			if ($tier == "") $tier = "Other";
			
			$tierCounts[$tier]++;
			
			$shirtSize = $patron['shirtSize'];
			if ($shirtSize == "?") $shirtSize = "";
			
			if ($shirtSizeTiers[$tier]) 
			{
				$shirtSizes[$shirtSize]++;
				$totalShirtCount++;
			}
			
			$activeCount++;
		}
		
		$this->addBreadcrumb("Home", $this->getLink());
		$wgOut->addHTML($this->getBreadcrumbHtml());
		$wgOut->addHTML("<p/>");
		
		$wgOut->addHTML("Showing stats for all active patrons.");
		$lastUpdate = $this->getLastUpdateFormat();
		$wgOut->addHTML(" Patron data last updated $lastUpdate ago. ");
		
		$wgOut->addHTML("<ul>");
		$wgOut->addHTML("<li><b>Active Patrons =</b> $activeCount</li>");
		$wgOut->addHTML("<li><b>Tier Counts</b><ul>");
		
		foreach ($tierCounts as $tier => $count)
		{
			$pct = floor($count / $activeCount * 100);
			$wgOut->addHTML("<li>$tier = $count ($pct%)</li>");
		}
		
		$wgOut->addHTML("</ul></li>");
		$wgOut->addHTML("<li><b>Cumulative Tier Counts</b><ul>");
		$baseCount = $activeCount - $tierCounts['Other'];
		
		foreach ($tierCounts as $tier => $count)
		{
			if ($tier == "Other") continue;
			
			$thisCount = $baseCount;
			$pct = floor($thisCount / $activeCount * 100);
			$wgOut->addHTML("<li>$tier = $thisCount ($pct%)</li>");
			$baseCount -= $count;
		}
		
		$wgOut->addHTML("</ul></li>");
		$tierList = implode(", ", $shirtSizeTierList);
		$wgOut->addHTML("<li><b>Shirt Sizes ($tierList)</b><ul>");
		
		foreach ($shirtSizes as $size => $count)
		{
			if ($size == "") $size = "&lt;Not Set&gt;";
			$pct = floor($count / $totalShirtCount * 100);
			$wgOut->addHTML("<li>$size = $count ($pct%)</li>");
		}
		
		$wgOut->addHTML("<li><b>Total = $totalShirtCount</b></li>");
		$wgOut->addHTML("</ul></li>");
		$wgOut->addHTML("</ul>");
	}
	
	
	private function _default() 
	{
		return $this->showMainMenu();
	}
	
	
	public function execute( $parameter ){
		
		$this->setHeaders();
		$this->parseRequest();
		
		if ($parameter == '') $parameter = $this->inputAction;
		
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
			case 'list':
			case 'view':
				$this->showList();
				break;
			case 'shownew':
			case 'new':
				$this->showNew();
				break;
			case 'tierchange':
				$this->showTierChanges();
				break;
			case 'showwiki':
				$this->showWikiList();
				break;
			case 'link':
				$this->showLink();
				break;
			case 'viewshipment':
			case 'viewship':
				$this->showShipments();
				break;
			case 'createship':
				$this->showCreateShipment();
				break;
			case 'savenewshipments':
				$this->saveNewShipments();
				break;
			case 'savenewshipment':
				$this->saveNewShipment();
				break;
			case 'addshipment':
				$this->addNonPatreonShipment();
				break;
			case 'addshipments':
				$this->addNonPatreonShipments();
				break;
			case 'editshipment':
				$this->editShipment();
				break;
			case 'saveshipment':
				$this->saveShipment();
				break;
			case 'shiprewards':
				$this->showShipmentRewards();
				break;
			case 'exportshipment':
				$this->exportShipment();
				break;
			case 'importshipments':
				$this->importShipments();
				break;
			/*
			case 'viewpledges':
				$this->showPledges();
				break; */
			case 'viewpatron':
				$this->showPatron();
				break;
			case 'editshirtsize':
				$this->editPatronShirtSize();
				break;
			case 'saveshirtsize':
				$this->savePatronShirtSize();
				break;
			case 'viewrewards':
				$this->showRewards();
				break;
			case 'editreward':
				$this->editReward();
				break;
			case 'addreward':
				$this->addReward();
				break;
			case 'savereward':
				$this->saveReward();
				break;
			case 'createemail':
				$this->createEmail();
				break;
			case 'exportemail':
				$this->exportEmail();
				break;
			case 'getordernumber':
				$this->getOrderNumber();
				break;
			case 'editinfos':
				$this->showEditInfos();
				break;
			case 'saveinfos':
				$this->saveInfos();
				break;
			case 'showstats':
				$this->showStats();
				break;
			default:
				$this->_default();
				break;
		}
	}
	
};

/*
 * 
 * 		header( "Pragma:  no-cache" );
		header( "Expires: 0" );
		header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		header( "Cache-Control: public" );
		header( "Content-Description: File Transfer" );
		header( "Content-type: text/csv" );
		header( "Content-Transfer-Encoding: binary" );
		header( "Content-Disposition: attachment; filename=\"mediawiki_users.csv\"" );
		header( "Content-Length: " . filesize( $filePath ) );
		header( "Accept-Ranges: bytes" );

		readfile( $filePath );
		unlink( $filePath );
		die;
 */
	