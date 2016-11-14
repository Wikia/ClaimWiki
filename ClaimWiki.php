<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Claim Wiki Mediawiki Settings
 *
 * @author 		Alex Smith
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'ClaimWiki' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['ClaimWiki'] = __DIR__.'/i18n';
	$wgExtensionMessagesFiles['SpecialClaimWiki'] = __DIR__."/ClaimWiki.alias.php";
	/* wfWarn(
		'Deprecated PHP entry point used for AbuseFilter extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the AbuseFilter extension requires MediaWiki 1.25+' );
}
