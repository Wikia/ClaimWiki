<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Claim Wiki Hooks
 *
 * @package   ClaimWiki
 * @author    Alex Smith
 * @copyright (c) 2013 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace ClaimWiki;

use ConfigFactory;
use DatabaseUpdater;
use MediaWiki\MediaWikiServices;
use OutputPage;
use Skin;
use SpecialPage;
use Title;
use User;

class Hooks {
	/**
	 * Handle special on extension registration bits.
	 *
	 * @return void
	 */
	public static function onRegistration() {
		global $wgGroupPermissions, $wgClaimWikiEmailTo, $wgClaimWikiEnabled,
		$wgEmergencyContact, $wgTwiggyAllowedPHPFunctions;

		if (!isset($wgClaimWikiEnabled)) {
			$wgClaimWikiEnabled = true;
		}

		$wgGroupPermissions['wiki_guardian'] = $wgGroupPermissions['sysop'];

		if (!isset($wgClaimWikiEmailTo) || !is_bool($wgClaimWikiEnabled)) {
			$wgClaimWikiEmailTo = $wgEmergencyContact;
		}

		$twiggyAllowedPHPFunctions = [
			"strtoupper",
			"strtolower",
			"ucwords",
			"is_array",
			"count",
			"wfExpandUrl",
			"date",
			"urlencode"
		];
		$wgTwiggyAllowedPHPFunctions = array_merge($wgTwiggyAllowedPHPFunctions, $twiggyAllowedPHPFunctions);
	}

	/**
	 * Add resource loader modules.
	 *
	 * @param object $output MediaWiki Output Object
	 * @param object $skin   MediaWiki Skin Object
	 *
	 * @return boolean True
	 */
	public static function onBeforePageDisplay(OutputPage &$output, Skin &$skin) {
		global $wgClaimWikiEnabled;

		if (!$wgClaimWikiEnabled) {
			return true;
		}

		$output->addModules('ext.claimWiki.styles');

		return true;
	}

	/**
	 * Claim Wiki Side Bar
	 *
	 * @param object $skin Skin Object
	 * @param array  $bar  Array of bar contents to modify.
	 *
	 * @return bool	True - Must return true or the site will break.
	 */
	public static function onSkinBuildSidebar(Skin $skin, &$bar) {
		$config = ConfigFactory::getDefaultInstance()->makeConfig('main');
		$wgClaimWikiEnabled = $config->get('ClaimWikiEnabled');
		$wgClaimWikiGuardianTotal = $config->get('ClaimWikiGuardianTotal');

		if (!$wgClaimWikiEnabled) {
			return true;
		}

		$DB = wfGetDB(DB_REPLICA);
		$result = $DB->select(
			'wiki_claims',
			['COUNT(*) as total'],
			[
				'status' => intval(WikiClaim::CLAIM_APPROVED),
				'end_timestamp' => 0
			],
			__METHOD__
		);

		$total = $result->fetchRow();

		if ($total['total'] < $wgClaimWikiGuardianTotal) {
			$page = Title::newFromText('Special:ClaimWiki');

			$claimSidebarContent = "<div class='claimSidebar'><a href='" . $page->getFullURL() . "'>&nbsp;</a></div>";
			$_bar['claimWiki'] = $claimSidebarContent;
			$bar = array_merge($_bar, $bar);
		}

		return true;
	}

	/**
	 * Detect changes to the user groups and update users in the wiki_guardian group as needed.
	 *
	 * @param object $user    User or UserRightsProxy object changed.
	 * @param array  $add
	 * @param array  $remove
	 * @param array  ...$args all additional arguments
	 *
	 * @return boolean	True
	 */
	public static function onUserGroupsChanged($user, array $add, array $remove, ...$args) {
		if (!$user instanceof User || !$user->getId()) {
			return true;
		}
		if (in_array('wiki_guardian', $remove)) {
			$claim = WikiClaim::newFromUser($user);
			if ($claim !== false && $claim->isLoaded()) {
				$claim->setDeleted();
				$claim->save();
			}
		}
		return true;
	}

	/**
	 * Add sysop to effective groups when the user has wiki_guardian.
	 *
	 * @param object $user        User
	 * @param array  $aUserGroups "Actual" user groups that should reflect the rows in the database.
	 *
	 * @return void
	 */
	public static function onUserEffectiveGroups(&$user, &$aUserGroups) {
		if (in_array('wiki_guardian', $aUserGroups)) {
			$aUserGroups[] = 'sysop';
		}
		return true;
	}

	/**
	 * Setups and Modifies Database Information
	 *
	 * @param object $updater [Optional] DatabaseUpdater Object
	 *
	 * @return boolean	true
	 */
	public static function onLoadExtensionSchemaUpdates(DatabaseUpdater $updater = null) {
		$extDir = __DIR__ . '/../';

		// Tables
		// 2015-01-08
		$updater->addExtensionUpdate([
			'addTable',
			'wiki_claims',
			"{$extDir}/install/sql/claimwiki_table_wiki_claims.sql", true
		]);
		$updater->addExtensionUpdate([
			'addTable',
			'wiki_claims_answers',
			"{$extDir}/install/sql/claimwiki_table_wiki_claims_answers.sql", true
		]);
		$updater->addExtensionUpdate([
			'addTable',
			'wiki_claims_log',
			"{$extDir}/install/sql/claimwiki_table_wiki_claims_log.sql", true
		]);

		return true;
	}

	/**
	 * Setup Twig Templates
	 *
	 * @param SpecialPage $special
	 * @param string      $subPage
	 *
	 * @return void
	 */
	public static function onSpecialPageBeforeExecute(SpecialPage $special, $subPage) {
		$twig = MediaWikiServices::getInstance()->getService('TwiggyService');
		$twig->setTemplateLocation('ClaimWiki', __DIR__ . '/../resources/templates');
	}
}
