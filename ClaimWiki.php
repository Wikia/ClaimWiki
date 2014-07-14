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

/******************************************/
/* Credits                                */
/******************************************/
$wgExtensionCredits['specialpage'][] = array(
	'path'				=> __FILE__,
	'name'				=> 'Claim Wiki',
	'author'			=> 'Alexia E. Smith, Curse Inc&copy;',
	'descriptionmsg'	=> 'claimwiki_description',
	'version'			=> '1.0' //Must be a string or Mediawiki will turn it into an integer.
);

/******************************************/
/* Language Strings, Page Aliases, Hooks  */
/******************************************/
$extDir = __DIR__;

$wgAvailableRights[] = 'claim_wiki';

$wgExtensionMessagesFiles['ClaimWiki']				= "{$extDir}/ClaimWiki.i18n.php";

$wgAutoloadClasses['ClaimWikiHooks']				= "{$extDir}/ClaimWiki.hooks.php";
$wgAutoloadClasses['SpecialClaimWiki']				= "{$extDir}/specials/SpecialClaimWiki.php";
$wgAutoloadClasses['SpecialWikiClaims']				= "{$extDir}/specials/SpecialWikiClaims.php";
$wgAutoloadClasses['wikiClaim']						= "{$extDir}/classes/wikiClaim.php";

$wgSpecialPages['ClaimWiki']						= 'SpecialClaimWiki';
$wgSpecialPages['WikiClaims']						= 'SpecialWikiClaims';

$wgSpecialPageGroups['ClaimWiki']					= 'claimwiki';
$wgSpecialPageGroups['WikiClaims']					= 'claimwiki';

$wgHooks['BeforePageDisplay'][]						= 'ClaimWikiHooks::onBeforePageDisplay';
$wgHooks['SkinBuildSidebar'][]                      = 'ClaimWikiHooks::onSkinBuildSidebar';
$wgHooks['LoadExtensionSchemaUpdates'][]			= 'ClaimWikiHooks::onLoadExtensionSchemaUpdates';

$wgResourceModules['ext.claimWiki'] = array(
	'localBasePath'	=> __DIR__,
	'remoteExtPath'	=> 'ClaimWiki',
	'scripts'		=> array('js/listSorter.js'),
	'styles'		=> array('css/claimwiki.css')
);

/******************************************/
/* Settings and Permissions               */
/******************************************/
//Is the system enabled?
if (!isset($claimWikiEnabled)) {
	$claimWikiEnabled = true;
}

//Number of questions on the form.  This can be overrided and custom language strings added.
if (!isset($claimWikiNumberOfQuestions)) {
	$claimWikiNumberOfQuestions = 4;
}

//Who should receive emails when a new claim is submitted?
if (!isset($claimWikiEmailTo)) {
	$claimWikiEmailTo = $wgEmergencyContact;
}

//The tag line that will show up at the bottom of approval and denial emails.
if (!isset($claimWikiEmailSignature)) {
	$claimWikiEmailSignature = 'The Wiki Team';
}

//Number of wiki guardians that are approved before the side bar button disappears.
if (!isset($claimWikiGuardianTotal)) {
	$claimWikiGuardianTotal = 1;
}

$wgGroupPermissions['user']['claim_wiki']			= true;
$wgGroupPermissions['bureaucrat']['wiki_claims']	= true;

$wgGroupPermissions['wiki_guardian'] = $wgGroupPermissions['sysop'];

$wgMessagesDirs['ClaimWiki'] = __DIR__ . '/i18n';
