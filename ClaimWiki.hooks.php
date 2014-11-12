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
	 * Add resource loader modules.
	 *
	 * @access	public
	 * @param	object	Mediawiki Output Object
	 * @param	object	Mediawiki Skin Object
	 * @return	boolean True
	 */
	static public function onBeforePageDisplay(&$output, &$skin) {
		global $claimWikiEnabled;

		if (!$claimWikiEnabled) {
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
		global $claimWikiEnabled, $claimWikiGuardianTotal;

		if (!$claimWikiEnabled) {
			return true;
		}

		$DB = wfGetDB(DB_MASTER);
		$result = $DB->select(
			'wiki_claims',
			['COUNT(*) as total'],
			'approved = 1 AND end_timestamp = 0',
			__METHOD__
		);
		$total = $result->fetchRow();
		if ($total['total'] >= $claimWikiGuardianTotal) {
			return true;
		}

		$page = Title::newFromText('Special:ClaimWiki');

		$claimSidebarContent = "<div class='claimSidebar'><a href='".$page->getFullURL()."'>&nbsp;</a></div>";
		$_bar['claimWiki'] = $claimSidebarContent;
		$bar = array_merge($_bar, $bar);

		return true;
	}

	/**
	 * Detect changes to the user groups and update users in the wiki_guardian group as needed.
	 *
	 * @access	public
	 * @param	object	User or UserRightsProxy object changed.
	 * @return	boolean	True
	 */
	public function onUserRights($user, array $add, array $remove) {
		if (!$user instanceOf User || !$user->getId()) {
			return true;
		}

		if (in_array('wiki_guardian', $remove)) {
			$claim = new wikiClaim($user);
			if (!$claim) {
				return true;
			}
			$claim->delete();
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
	static public function onLoadExtensionSchemaUpdates($updater = null) {
		$extDir = __DIR__;

		//Tables
		//2013-08-21
		$updater->addExtensionUpdate(array('addTable', 'wiki_claims', "{$extDir}/install/sql/claimwiki_table_wiki_claims.sql", true));
		$updater->addExtensionUpdate(array('addTable', 'wiki_claims_answers', "{$extDir}/install/sql/claimwiki_table_wiki_claims_answers.sql", true));

		$updater->addExtensionUpdate(['addField', 'wiki_claims', 'pending', "{$extDir}/upgrade/sql/claimwiki_upgrade_wiki_claims_add_pending.sql", true]);

		return true;
	}
}
?>