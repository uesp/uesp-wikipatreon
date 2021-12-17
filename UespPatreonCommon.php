<?php

/*
 * Common functions for all UESP Patreon code.
 */


class UespPatreonCommon {
	
	public static $UESP_CAMPAIGN_ID = 2731208;
	
	
	public static function parsePatronDataForMember($responseData) {
		$result = array();
		
		if ($responseData == null || count($responseData) == 0) return $result;
		
		return $result;
	}
	
	
	public static function parsePatronData($responseData, $onlyActive, $includeFollowers) {
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
			else {		// Unknown relationship
			}
		}
		
		foreach ($data as $row) {
			$attr = $row['attributes'];
			$rel = $row['relationships'];
			
			if ($attr == null) continue;
			
			if ($onlyActive && $attr['patron_status'] != "active_patron") continue;
			if (!$includeFollowers && $attr['patron_status'] == "") continue;
			
			$patron = array();
			$patron['id'] = -1;
			$patron['patreon_id'] = -1;
			$patron['wikiuser_id'] = -1;
			$patron['access_token'] = "";
			$patron['refresh_token'] = "";
			$patron['token_expires'] = "";
			$patron['name'] = $attr['full_name'];
			$patron['email'] = $attr['email'];
			$patron['discord'] = "";
			$patron['tier'] = "";
			$patron['status'] = $attr['patron_status'];
			$patron['pledgeCadence'] = $attr['pledge_cadence'];
			$patron['note'] = $attr['note'];
			$patron['lifetimePledgeCents'] = $attr['lifetime_support_cents'];
			$patron['lastChargeDate'] = $attr['last_charge_date'];
			$patron['startDate'] = preg_replace('/(.*)T(.*)\+.*/', '\1 \2', $attr['pledge_relationship_start']);
			$patron['addressName'] = "";
			$patron['addressLine1'] = "";
			$patron['addressLine2'] = "";
			$patron['addressCity'] = "";
			$patron['addressState'] = "";
			$patron['addressZip'] = "";
			$patron['addressCountry'] = "";
			$patron['addressPhone'] = "";
			
			if ($rel['address'] != null && $rel['address']['data'] != null) {
				$id = $rel['address']['data']['id'];
				$addr = $addresses[$id];
				
				if ($addr != null) {
					$patron['addressName'] = $addr['addressee'];
					$patron['addressLine1'] = $addr['line_1'];
					$patron['addressLine2'] = $addr['line_2'];
					$patron['addressCity'] = $addr['city'];
					$patron['addressState'] = $addr['state'];
					$patron['addressZip'] = $addr['postal_code'];
					$patron['addressCountry'] = $addr['country'];
					$patron['addressPhone'] = $addr['phone_number'];
				}
			}
			
			if ($rel['currently_entitled_tiers'] != null && $rel['currently_entitled_tiers']['data'] != null && $rel['currently_entitled_tiers']['data'][0] != null) {
				$id = $rel['currently_entitled_tiers']['data'][0]['id'];
				
				if ($tiers[$id] != null) {
					$patron['tier'] = $tiers[$id]['title'];
					if ($patron['tier']== null) $patron['tier'] = "";
				}
			}
			
			if ($rel['user'] != null && $rel['user']['data'] != null) {
				$id = $rel['user']['data']['id'];
				$patron['patreon_id'] = $id;
				
				if ($users[$id] != null) {
					if ($users[$id]['social_connections'] != null && $users[$id]['social_connections']['discord'] != null && $users[$id]['social_connections']['discord']['user_id'] != null) {
						$patron['discord'] = $users[$id]['social_connections']['discord']['user_id'];
					}
				}
			}
			
			$result[] = $patron;
		}
		
		usort($result, array("UespPatreonCommon", "sortPatronsByStartDate"));
		
		return $result;
	}
	
	
	public static function sortPatronsByStartDate($a, $b) {
		return strcmp($a['startDate'], $b['startDate']);
	}
	
	
};