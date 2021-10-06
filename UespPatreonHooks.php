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
		
			// If this is set any setting of "Large" will not be stored in the database
		//$defaultOptions['uesppatreon-shirtsize'] = 'Large';
		
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
				//'default' => 'Large',	// Doesn't seem to work
				'section' => 'uesppatreon/uesppatreon-options',
				'options' => [
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
		return true;
	}    
	
};