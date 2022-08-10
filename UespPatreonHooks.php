<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension and must be run from within MediaWiki.' );
}


require_once("/home/uesp/secrets/patreon.secrets");


global $wgSharedTables;
$wgSharedTables[] = 'patreon_user';


class UespPatreonHooks {
	
	
	/**
	 * UserGetDefaultOptions hook handler
	 * @param $defaultOptions Array of default preference keys and values
	 * @return bool
	 */
	public static function userGetDefaultOptions( &$defaultOptions ) {
		
		$defaultOptions['uesppatreon-shirtsize'] = 'Not Set';
		
		return true;
	}
	
	
	/**
	 * GetPreferences hook handler.
	 * @param $user User
	 * @param $preferences Array: Preference descriptions
	 * @return bool
	 */
	public static function getPreferences( $user, &$preferences ) 
	{
		$patreonId = SpecialUespPatreon::loadPatreonUserId();
		
		if ($patreonId <= 0) {
			$text = wfMessage( "uesppatreon-link" )->parse();
			$authorizationUrl = SpecialUespPatreon::getLink("redirect");
		}
		else {
			$text = wfMessage( "uesppatreon-unlink" )->parse();
			$authorizationUrl = SpecialUespPatreon::getLink("unlink");
		}
		
		$text = str_replace("https://patreon.com", $authorizationUrl, $text);
		
		$text .= wfMessage( "uesppatreon-linkmsg" )->parse(); 
		
		$preferences['uesppatreon-link'] =
			array(
				'type' => 'info',
				'label' => '&#160;',
				'default' => $text,
				'section' => 'uesppatreon/uesppatreon-link',
				'raw' => 1,
				'rawrow' => 1,
			);
			
		$patreonUser = SpecialUespPatreon::loadPatreonUser();
		
		if ($patreonId > 0 && $patreonUser != null)
		{
			$appCode = SpecialUespPatreon::loadPatreonAppCode();
			if ($appCode < 0) $appCode = "NO CODE AVAILABLE (code missing)";
			
			if ($patreonUser['lifetimePledgeCents'] < 0)
			{
				$appCode = "CODE NOT AVAILABLE</b> (usually available within the hour after subscribing for new patrons)<b>";
			}
			
			$text = wfMessage( "uesppatreon-appcodemsg" )->parse();
			$text = str_replace("APPCODE", $appCode, $text);
			
			$preferences['uesppatreon-appcode'] =
				array(
					'type' => 'info',
					'label' => '&#160;',
					'default' => $text,
					'section' => 'uesppatreon/uesppatreon-appcode',
					'raw' => 1,
					'rawrow' => 1,
				);
		}
		
		$text = "Reward options may be selected here in the future.";
		/*
		$preferences['uesppatreon-options'] =
			array(
				'type' => 'info',
				'label' => '&#160;',
				'default' => $text,
				'section' => 'uesppatreon/uesppatreon-options',
				'raw' => 1,
				'rawrow' => 1,
			); */
		
		$preferences['uesppatreon-shirtsize'] =
			array(
				'type' => 'select',
				'label' => 'Select your preferred shirt size:',
				'section' => 'uesppatreon/uesppatreon-options',
				'options' => [
						'Not Set' => 'Not Set',
						'Small' => 'Small',
						'Medium' => 'Medium',
						'Large' => 'Large',
						'X-Large' => 'X-Large',
						'XX-Large' => 'XX-Large',
						'XXX-Large' => 'XXX-Large',
						'XXXX-Large' => 'XXXX-Large',
				],
				'raw' => 1,
				'rawrow' => 1,
			);
		
		return true;
	}
	
	
 	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$sql = __DIR__ . '/sql';
		$updater->addExtensionUpdate( [ 'addTable', 'patreon_user', "$sql/patreon_user.sql", true ] );
		$updater->addExtensionUpdate( [ 'addTable', 'patreon_tierchange', "$sql/patreon_tierchange.sql", true ] );
		$updater->addExtensionUpdate( [ 'addTable', 'patreon_info', "$sql/patreon_info.sql", true ] );
		$updater->addExtensionUpdate( [ 'addTable', 'patreon_shipment', "$sql/patreon_shipments.sql", true ] );
		$updater->addExtensionUpdate( [ 'addTable', 'patreon_reward', "$sql/patreon_reward.sql", true ] );
		$updater->addExtensionUpdate( [ 'addTable', 'patreon_tag', "$sql/patreon_tag.sql", true ] );
		return true;
	}
	
};