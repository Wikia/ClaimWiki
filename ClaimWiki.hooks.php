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
	 * Setups and Modifies Database Information
	 *
	 * @access	public
	 * @param	object	[Optional] DatabaseUpdater Object
	 * @return	boolean	true
	 */
	static public function onLoadExtensionSchemaUpdates($updater = null) {
		$extDir = __DIR__;

		if ($updater === null) {
			//Fresh Installation
			global $wgExtNewTables, $wgExtNewFields, $wgExtPGNewFields, $wgExtPGAlteredFields, $wgExtNewIndexes, $wgDBtype;
			//Tables
			//2013-08-21
			$wgExtNewTables[]	= array('wiki_claims', "{$extDir}/install/sql/claimwiki_table_wiki_claims.sql");
			$wgExtNewTables[]	= array('wiki_claims_answers', "{$extDir}/install/sql/claimwiki_table_wiki_claims_answers.sql");
		} else {
			//Tables
			//2013-08-21
			$updater->addExtensionUpdate(array('addTable', 'wiki_claims', "{$extDir}/install/sql/claimwiki_table_wiki_claims.sql", true));
			$updater->addExtensionUpdate(array('addTable', 'wiki_claims_answers', "{$extDir}/install/sql/claimwiki_table_wiki_claims_answers.sql", true));
		}
		return true;
	}
}
?>