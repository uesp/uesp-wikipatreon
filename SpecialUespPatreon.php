<?php


if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension and must be run from within MediaWiki.' );
}


class SpecialUespPatreon extends SpecialPage {
	
	public $WIKILIST_STEEL_MAXLENGTH = 90; 
	
	public $accessToken = "";
	public $lastPatronUpdate = 0;
	public $orderSuffix = "00";
	public $orderIndex = array( "Iron" => 1, "Steel" => 1, "Elven" => 1, "Orcish" => 1, "Glass" => 1, "Daedric" => 1, "Other" => 1);
	public $orderSku = array(
			"Iron" => "UESPIron{suffix}",
			"Steel" => "UESPSteel{suffix}",
			"Elven" => "UESPElven{suffix}",
			"Orcish" => "UESPOrcish{suffix}{shirtsize}",
			"Glass" => "UESPOrcish{suffix}{shirtsize}",
			"Daedric" => "UESPOrcish{suffix}{shirtsize}",
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
	public $inputShowOnlyUnprocessed = 0;
	
	public $breadcrumb = array();
	public $patrons = array();
	public $tierChanges = array();
	public $shipments = array();
	
	
	public function __construct() {
		global $wgOut;
		
		parent::__construct('UespPatreon');
		
		$wgOut->addModules( 'ext.UespPatreon.modules' );
	}
	
	
	public static function getPreferenceLink() {
		//return "https://content3.uesp.net/wiki/Special:Preferences#mw-prefsection-uesppatreon";
		return "https://en.uesp.net/wiki/Special:Preferences#mw-prefsection-uesppatreon";
	}
	
	
	public static function getLink($param = null, $query = null) {
		//$link = $this->getTitle( $param )->getCanonicalURL();
		
		//$link = "https://content3.uesp.net/wiki/Special:UespPatreon";
		$link = "https://en.uesp.net/wiki/Special:UespPatreon";
		
		if ($param) $link .= "/" . $param;
		if ($query) $link .= "?" . $query;
		return $link;
	}
	
	
	public function escapeHtml($html) {
		return htmlspecialchars ($html);
	}
	
	
	public static function getAuthorizationLink() {
		global $uespPatreonClientId;
		global $uespPatreonClientSecret;
		
		$link = 'https://www.patreon.com/oauth2/authorize?response_type=code&client_id=' . $uespPatreonClientId;
		$link .= '&redirect_uri=' . SpecialUespPatreon::getLink("callback");
		
		return $link;
	}
	
	
	private function makeOrderSku($patron) {
		
		$orderSku = $this->orderSku[$patron['tier']];
		if ($orderSku == null) $orderSku = "UESPOther{suffix}";
		
		$orderSku = str_replace("{suffix}", $this->orderSuffix, $orderSku);
		$orderSku = str_replace("{shirtsize}", "?", $orderSku);
		
		return $orderSku;
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
		global $wgOut;
		
		require_once('Patreon/API2.php');
		require_once('Patreon/OAuth2.php');
		
		if (!$this->loadInfo()) return array();
		
		$api = new Patreon\API($this->accessToken);
		
		$response = $api->fetch_page_of_members_from_campaign(UespPatreonCommon::$UESP_CAMPAIGN_ID, 2000, null);
		
		//$raw = print_r($response, true);
		//$wgOut->addHTML("Response: <pre>$raw</pre>");
		
		$patrons = UespPatreonCommon::parsePatronData($response, $onlyActive, $includeFollowers);
		$this->patrons = $patrons;
		
		return $this->patrons;
	}
	
	
	private function loadPatronDataDB($onlyActive = true, $includeFollowers = false) {
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
			
			if ($id == null || $id <= 0) $id = ++$index;
			$patrons[$id] = $row;
		}
		
		$this->patrons = $patrons;
		uasort($this->patrons, array("SpecialUespPatreon", "sortPatronsByStartDate"));
		
		return $this->patrons;
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
		$patrons = $this->loadPatronDataDB($this->inputOnlyActive, false);
		
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
	
	
	private function showWikiList() {
		global $wgOut;
		
		if (!$this->hasPermission("view")) {
			$wgOut->addHTML("Permission Denied!");
			return;
		}
		
		$this->loadInfo();
		$patrons = $this->loadPatronDataDB(true, false);
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
		
		$wgOut->addHTML("<table class='wikitable sortable jquery-tablesorter' id='uesppatrons'>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th>Full Name</th>");
		$wgOut->addHTML("<th>Old Tier</th>");
		$wgOut->addHTML("<th>New Tier</th>");
		$wgOut->addHTML("<th>Changed On</th>");
		$wgOut->addHTML("</tr>");
		
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
		
		$wgOut->addHTML("</table>");
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
		$patrons = $this->loadPatronDataDB($this->inputOnlyActive, false);
		
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
		$wgOut->addHTML("<form method='post' id='uesppatPatronTableForm' action='$formLink'>");
		$wgOut->addHTML("With Selected Patrons: ");
		$wgOut->addHTML("<input type='hidden' id='uesppatPatronTableAction' name='action' value='' />");
		$wgOut->addHTML("<input type='button' value='Create Shipment' onclick='uesppatOnCreateShipmentButton();'/>");
		
		$this->outputPatronTable($patrons);
		
		$wgOut->addHTML("</form>");
	}
	
	
	private function makeNiceStatus ($status) {
		
		if ($status == "active_patron") return "Active";
		if ($status == "declined_patron") return "Declined";
		if ($status == "former_patron") return "Former";
		return $this->escapeHtml($status);
	}
	
	
	private function outputPatronTable($patrons) {
		global $wgOut;
		
		$wgOut->addHTML("<table class='wikitable sortable jquery-tablesorter' id='uesppatrons'>");
		
		$wgOut->addHTML("<tr>");
		$wgOut->addHTML("<th class='unsortable'><input id='uesppatPatronTableHeaderCheckbox' type='checkbox' value='0' /></th>");
		$wgOut->addHTML("<th>Full Name</th>");
		$wgOut->addHTML("<th>Tier</th>");
		$wgOut->addHTML("<th>Status</th>");
		$wgOut->addHTML("<th>Pledge Type</th>");
		$wgOut->addHTML("<th>Lifetime $</th>");
		$wgOut->addHTML("<th>Patron Since</th>");
		$wgOut->addHTML("<th>Has Address</th>");
		$wgOut->addHTML("</tr>");
		
		foreach ($patrons as $patron) {
			$patronId = $patron['patreon_id'];
			$name = $this->escapeHtml($patron['name']);
			$tier = $this->escapeHtml($patron['tier']);
			$status = $this->makeNiceStatus($patron['status']);
			$lifetime = '$' . number_format($patron['lifetimePledgeCents'] / 100, 2);
			$pledgeType = $patron['pledgeCadence'];
			$pledgeStart = $patron['startDate'];
			
			if ($pledgeType == 1)
				$pledgeType = "Monthly";
			else if ($pledgeType == 12)
				$pledgeType = "Annual";
			else {
				$pledgeType = $this->escapeHtml($pledgeType);
				$pledgeType = "Every $pledgeType Months";
			}
			
			$hasAddress = "Yes";
			if ($patron['addressName'] == "" || $patron['addressLine1'] == "" || $patron['addressCountry'] == "") $hasAddress = "NO";
			
			$checkbox = "<input type='checkbox' name='patronids[]' class='uesppatPatronRowCheckbox' value='$patronId'/>";
			
			$wgOut->addHTML("<tr>");
			$wgOut->addHTML("<td>$checkbox</td>");
			$wgOut->addHTML("<td>$name</td>");
			$wgOut->addHTML("<td>$tier</td>");
			$wgOut->addHTML("<td>$status</td>");
			$wgOut->addHTML("<td>$pledgeType</td>");
			$wgOut->addHTML("<td>$lifetime</td>");
			$wgOut->addHTML("<td>$pledgeStart</td>");
			$wgOut->addHTML("<td>$hasAddress</td>");
			$wgOut->addHTML("</tr>");
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
	
	
	private function showMainMenu() {
		global $wgOut;
		
		if (!$this->hasPermission("view")) {
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$viewLink = SpecialUespPatreon::getLink("list");
		$linkLink = SpecialUespPatreon::getLink("link");
		$newLink = SpecialUespPatreon::getLink("shownew");
		$tierChangeLink = SpecialUespPatreon::getLink("tierchange");
		$wikiLink = SpecialUespPatreon::getLink("showwiki");
		
		$wgOut->addHTML("<ul>");
		$wgOut->addHTML("<li><a href='$viewLink'>View Current Patrons</a></li>");
		$wgOut->addHTML("<li><a href='$newLink'>Show New Patrons</a></li>");
		$wgOut->addHTML("<li><a href='$wikiLink'>View Wiki Patreon List</a></li>");
		$wgOut->addHTML("<li><a href='$tierChangeLink'>Show Tier Changes</a></li>");
		$wgOut->addHTML("<li><a href='$linkLink'>Link to Patreon Account</a></li>");
		
		if ($this->hasPermission("shipment")) {
			$shipLink = SpecialUespPatreon::getLink("viewship");
			$wgOut->addHTML("<li><a href='$shipLink'>View Shipments</a></li>");
		}
		
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
	
	
	private function createNewShipments() {
		$index = 1;
		
		foreach ($this->inputPatronIds as $patronId) {
			$patron = $this->patrons[$patronId];
			
			$shipment = array();
			$shipment['id'] = $index++;
			$shipment['__isnew'] = true;
			
			if ($patron == null) {
				$shipment['__isbad'] = true;
				$shipment['patreon_id'] = -1;
				$shipment['name'] = "Unknown Patron #$patronId";
				$shipment['email'] = "";
				$shipment['tier'] = "";
				$shipment['status'] = "Unknown";
				$shipment['addressName'] = "";
				$shipment['addressLine1'] = "";
				$shipment['addressLine2'] = "";
				$shipment['addressCity'] = "";
				$shipment['addressState'] = "";
				$shipment['addressZip'] = "";
				$shipment['addressCountry'] = "";
				$shipment['addressPhone'] = "";
				
				$shipment['orderNumber'] = "Other" . $this->orderSuffix . "-" . str_pad(strval($this->orderIndex['Other']++), 3, '0', STR_PAD_LEFT);
				//$shipment['orderSku'] = "UESPOther" . $this->orderSuffix;
				$shipment['orderSku'] = $this->makeOrderSku($patron);
				$shipment['shipMethod'] = "";
				
			}
			else {
				$shipment['patreon_id'] = $patron['patreon_id'];;
				$shipment['name'] = $patron['name'];
				$shipment['email'] = $patron['email'];
				$shipment['tier'] = $patron['tier'];
				$shipment['status'] = $patron['status'];
				$shipment['addressName'] = $patron['addressName'];
				$shipment['addressLine1'] = $patron['addressLine1'];
				$shipment['addressLine2'] = $patron['addressLine2'];
				$shipment['addressCity'] = $patron['addressCity'];
				$shipment['addressState'] = $patron['addressState'];
				$shipment['addressZip'] = $patron['addressZip'];
				$shipment['addressCountry'] = $patron['addressCountry'];
				$shipment['addressPhone'] = $patron['addressPhone'];
				
				$shipment['orderNumber'] = $patron['tier']. $this->orderSuffix . "-" . str_pad(strval($this->orderIndex[$patron['tier']]++), 3, '0', STR_PAD_LEFT);
				//$shipment['orderSku'] = "UESP" . $patron['tier'] . $this->orderSuffix;
				$shipment['orderSku'] = $this->makeOrderSku($patron);
				$shipment['shipMethod'] = "";
				
				if ($shipment['addressName'] == "" || $shipment['addressLine1'] == "" || $shipment['addressCountry'] == "" || $shipment['tier'] == "") $shipment['__isbad'] = true;
			}
			
			$this->shipments[] = $shipment;
		}
		
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
		$this->addBreadcrumb("Show All", $this->getLink('viewship'));
		$this->addBreadcrumb("Show Unprocessed", $this->getLink('viewship', 'onlyunprocess=1'));
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
	
	
	private function outputShipmentTable() {
		global $wgOut;
		
		$wgOut->addHTML("<table class='wikitable sortable jquery-tablesorter' id='uesppatViewShipments'>");
		$wgOut->addHTML("<thead><tr>");
		$wgOut->addHTML("<th>#</th>");
		$wgOut->addHTML("<th class='unsortable'>Process</th>");
		$wgOut->addHTML("<th>Created On</th>");
		$wgOut->addHTML("<th>Order #</th>");
		$wgOut->addHTML("<th>SKU</th>");
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
		$wgOut->addHTML("</tr></thead><tbody>");
		$index = 1;
		
		foreach ($this->shipments as $shipment) {
			$id = $shipment['id'];
			
			$isProcessed = intval($shipment['isProcessed']);
			
			$createDate = $this->escapeHtml($shipment['createDate']);
			$orderNumber = $this->escapeHtml($shipment['orderNumber']);
			$orderSku = $this->escapeHtml($shipment['orderSku']);
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
			
			if ($isProcessed)
				$isProcessed = "Yes";
			else
				$isProcessed = "";
			
			$wgOut->addHTML("<tr>");
			$wgOut->addHTML("<td>$index</td>");
			$wgOut->addHTML("<td>$isProcessed</td>");
			$wgOut->addHTML("<td>$createDate</td>");
			$wgOut->addHTML("<td>$orderNumber</td>");
			$wgOut->addHTML("<td>$orderSku</td>");
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
			$wgOut->addHTML("</tr>");
			
			++$index;
		}
		
		$wgOut->addHTML("</tbody></table>");
	}
	
	
	private function saveNewShipment() {
		global $wgOut;
		
		if (!$this->hasPermission("shipment")) {
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$req = $this->getRequest();
		$patronIds = $req->getArray("patreon_id");
		$orderNumbers = $req->getArray("orderNumber");
		$orderSkus = $req->getArray("orderSku");
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
		
		$db = wfGetDB(DB_MASTER);
		$count = 0;
		$errorCount = 0;
		
		foreach ($patronIds as $i => $patronId) {
			$orderNumber = $orderNumbers[$i];
			$orderSku = $orderSkus[$i];
			$shipMethod = $shipMethods[$i];
			$addressName = $addressNames[$i];
			$addressLine1 = $addressLine1s[$i];
			$addressLine2 = $addressLine2s[$i];
			$addressCity = $addressCities[$i];
			$addressState = $addressStates[$i];
			$addressZip = $addressZips[$i];
			$addressCountry = $addressCountries[$i];
			$addressPhone = $addressPhones[$i];
			$email = $emails[$i];
			
			$result = $db->insert("patreon_shipment", [
					"patreon_id" => $patronId,
					"orderNumber" => $orderNumber,
					"orderSku" => $orderSku,
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
				++$count;
			else
				++$errorCount;
			
		}
		
		$wgOut->addHTML("Saved $count new shipments with $errorCount errors!");
		$wgOut->addHTML("<p/>");
		$viewLink = $this->getLink("viewship");
		$wgOut->addHTML("<a href='$viewLink'>View Shipments</a>");
		
		return true;
	}
	
	
	private function showCreateShipment() {
		global $wgOut;
		
		if (!$this->hasPermission("shipment")) {
			$wgOut->addHTML("Permission Denied!");
			return false;
		}
		
		$this->loadInfo();
		$this->loadPatronDataDB(false, false);
		
		$this->createNewShipments();
		
		$count = count($this->inputPatronIds);
		$wgOut->addHTML("Created new shipment with $count patrons! ");
		$wgOut->addHTML("Edit below shipments as needed and save to update shipment data. Invalid shipments will not be saved. ");
		
		$formLink = $this->getLink("savenewshipment"); 
		$wgOut->addHTML("<form method='post' id='uesppatSaveNewShipmentForm' action='$formLink' onsubmit='uesppatOnSaveNewShipments()'>");
		$wgOut->addHTML("<input type='submit' value='Save Shipments'>");
		$wgOut->addHTML("</form>");
		
		$this->outputCreateShipmentTable();
	}
	
	
	private function outputCreateShipmentTable() {
		global $wgOut;
		
		$wgOut->addHTML("<table class='wikitable sortable jquery-tablesorter' id='uesppatCreateShipments'>");
		$wgOut->addHTML("<thead><tr>");
		$wgOut->addHTML("<th>#</th>");
		$wgOut->addHTML("<th>Name</th>");
		$wgOut->addHTML("<th>Tier</th>");
		$wgOut->addHTML("<th>Status</th>");
		$wgOut->addHTML("<th>Order #</th>");
		$wgOut->addHTML("<th>SKU</th>");
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
			$shipMethod = $this->escapeHtml($shipment['shipMethod']);
			
			$class = "";
			if ($shipment['__isbad']) $class = "uesppatBadShipment";
			
			$wgOut->addHTML("<tr class='$class' patronid='$patronId' shipmentid='$id'>");
			$wgOut->addHTML("<td>$index</td>");
			$wgOut->addHTML("<td>$name</td>");
			$wgOut->addHTML("<td>$tier</td>");
			$wgOut->addHTML("<td>$status</td>");
			$wgOut->addHTML("<td>$orderNumber</td>");
			$wgOut->addHTML("<td>$orderSku</td>");
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
		$wgOut->addHTML("</tr></thead><tbody>");
		$wgOut->addHTML("</tbody></table>");
	}
	
	
	private function _default() {
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
			case 'viewship':
				$this->showShipments();
				break;
			case 'createship':
				$this->showCreateShipment();
				break;
			case 'savenewshipment':
				$this->saveNewShipment();
				break;
			default:
				$this->_default();
				break;
		}
	}
	
};
