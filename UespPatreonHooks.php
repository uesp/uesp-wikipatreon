<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension and must be run from within MediaWiki.' );
}


class UespPatreonHooks {
	
	
	/**
	 * UserGetDefaultOptions hook handler
	 * @param $defaultOptions Array of default preference keys and values
	 * @return bool
	 */
	public static function userGetDefaultOptions( &$defaultOptions ) {
		return true;
	}
	
	
	/**
	 * GetPreferences hook handler.
	 * @param $user User
	 * @param $preferences Array: Preference descriptions
	 * @return bool
	 */
	public static function getPreferences( $user, &$preferences ) {
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
				
		$preferences['uesppatreon-options'] =
			array(
				'type' => 'info',
				'label' => '&#160;',
				'default' => $text,
				'section' => 'uesppatreon/uesppatreon-options',
				'raw' => 1,
				'rawrow' => 1,
			);
		
		return true;
	}
	
	
 	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$sql = __DIR__ . '/sql';
		$schema = "$sql/patreon_user.sql";
		$updater->addExtensionUpdate( [ 'addTable', 'patreon_user', $schema, true ] );
		return true;
	}    
	
};