<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Claim Wiki Hooks
 *
 * @author 		Alex Smith
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

class ClaimWikiHooks {
	/**
	 * Handle special on extension registration bits.
	 *
	 * @access	public
	 * @return	void
	 */
	static public function onRegistration() {
		global $wgGroupPermissions, $wgClaimWikiEmailTo, $wgwgClaimWikiEnabled, $wgEchoNotifications;

		if (!isset($wgwgClaimWikiEnabled)) {
			$wgwgClaimWikiEnabled = true;
		}

		$wgGroupPermissions['wiki_guardian'] = $wgGroupPermissions['sysop'];

		if (!isset($wgClaimWikiEmailTo) || !is_bool($wgwgClaimWikiEnabled)) {
			$wgClaimWikiEmailTo = $wgEmergencyContact;
		}
	}

	/**
	 * Add this extensions Echo notifications.
	 *
	 * @access	public
	 * @param	array	See $wgEchoNotifications in Extension:Echo.
	 * @param	array	See $wgEchoNotificationCategories in Extension:Echo.
	 * @param	array	See $wgEchoNotificationIcons in Extension:Echo.
	 * @return	boolean	True
	 */
	static public function onBeforeCreateEchoEvent(&$wgEchoNotifications, &$wgEchoNotificationCategories, &$wgEchoNotificationIcons) {
		global $wgDefaultUserOptions;

		$wgDefaultUserOptions['echo-subscriptions-web-wiki-claims'] = true;
		$wgDefaultUserOptions['echo-subscriptions-email-wiki-claims'] = true;

		$wgEchoNotificationCategories['wiki-claims'] = [
			'priority' => 1,
			'no-dismiss' => ['web'],
			'tooltip' => 'echo-pref-tooltip-wiki-claims',
		];

		$wgEchoNotifications['wiki-claim'] = [
			EchoAttributeManager::ATTR_LOCATORS => [
				['EchoUserLocator::locateFromEventExtra', ['managers']],
			],
			'primary-link' => ['message' => 'wiki-claim-notification', 'destination' => 'wikiclaims'],
			'category' => 'wiki-claims',
			'group' => 'neutral',
			'section' => 'alert',
			'presentation-model' => 'EchoWikiClaimPresentationModel',
			// Legacy formatting system
			'formatter-class' => 'EchoWikiClaimFormatter',
			'title-message' => 'notification-header-wiki-claim',
			'title-params' => ['agent'],
			'email-subject-message' => 'notification-header-wiki-claim',
			'email-subject-params' => ['agent', 'gender', 'site_name'],
			'email-body-batch-message' => 'notification-email-body-wiki-claim',
			'email-body-batch-params' => ['agent'],
			'icon' => 'wiki-claim'
		];

		$wgEchoNotificationIcons['wiki-claim'] = ['path' => "ClaimWiki/images/notification.png"];
	}

	/**
	 * Add resource loader modules.
	 *
	 * @access	public
	 * @param	object	Mediawiki Output Object
	 * @param	object	Mediawiki Skin Object
	 * @return	boolean True
	 */
	static public function onBeforePageDisplay(OutputPage &$output, Skin &$skin) {
		global $wgClaimWikiEnabled;

		if (!$wgClaimWikiEnabled) {
			return true;
		}

		$output->addModules('ext.claimWiki');

		return true;
	}

	/**
	* Claim Wiki Side Bar
	*
	* @access      public
	* @param       object  Skin Object
	* @param       array   Array of bar contents to modify.
	* @return      boolean True - Must return true or the site will break.
	*/
	static public function onSkinBuildSidebar(Skin $skin, &$bar) {
		global $wgClaimWikiEnabled, $wgClaimWikiGuardianTotal;

		if (!$wgClaimWikiEnabled) {
			return true;
		}

		$DB = wfGetDB(DB_MASTER);
		$result = $DB->select(
			'wiki_claims',
			['COUNT(*) as total'],
			'status = '.intval(WikiClaim::CLAIM_APPROVED).' AND end_timestamp = 0',
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
	 * @access	public
	 * @param	object	User or UserRightsProxy object changed.
	 * @return	boolean	True
	 */
	static public function onUserRights($user, array $add, array $remove) {
		if (!$user instanceOf User || !$user->getId()) {
			return true;
		}

		if (in_array('wiki_guardian', $remove)) {
			$claim = WikiClaim::newFromUser($user);
			if ($claim !== false) {
				$claim->delete();
			}
		}
		return true;
	}

	/**
	 * Add sysop to effective groups when the user has wiki_guardian.
	 *
	 * @access	public
	 * @param	object	User
	 * @param	array	"Actual" user groups that should reflect the rows in the database.
	 * @return	void
	 */
	static public function onUserEffectiveGroups(&$user, &$aUserGroups) {
		if (in_array('wiki_guardian', $aUserGroups)) {
			$aUserGroups[] = 'sysop';
		}
		return true;
	}

	/**
	 * Setups and Modifies Database Information
	 *
	 * @access	public
	 * @param	object	[Optional] DatabaseUpdater Object
	 * @return	boolean	true
	 */
	static public function onLoadExtensionSchemaUpdates(DatabaseUpdater $updater = null) {
		$extDir = __DIR__;

		//Tables
		//2015-01-08
		$updater->addExtensionUpdate(['addTable', 'wiki_claims', "{$extDir}/install/sql/claimwiki_table_wiki_claims.sql", true]);
		$updater->addExtensionUpdate(['addTable', 'wiki_claims_answers', "{$extDir}/install/sql/claimwiki_table_wiki_claims_answers.sql", true]);
		$updater->addExtensionUpdate(['addTable', 'wiki_claims_log', "{$extDir}/install/sql/claimwiki_table_wiki_claims_log.sql", true]);

		return true;
	}
}
