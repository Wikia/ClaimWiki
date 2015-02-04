<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Claim Log Class
 *
 * @author		Alex Smith
 * @copyright	(c) 2015 Curse Inc.
 * @license		All Rights Reserved
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

class claimLogPager extends ReverseChronologicalPager {
	/**
	 * Log Entries
	 *
	 * @var		array
	 */
	private $entries = [];

	/**
	 * Return query arguments.
	 *
	 * @access	public
	 * @return	array
	 */
	public function getQueryInfo() {
		$query = [
			'tables'		=> [
				'l' => 'wiki_claims_log',
				'c'	=> 'wiki_claims'
			],
			'fields'		=> [
				'l.*', 'c.user_id'
			],
			'conds'			=> [],
			'options'		=> [
				'ORDER BY'	=> 'timestamp DESC'
			],
			'join_conds'	=> [
				'wiki_claims' => [
					'INNER JOIN', 'wiki_claims.cid = wiki_claims_log.claim_id'
				]
			]
		];

		return $query;
	}

	/**
	 * Return index(sort) field
	 *
	 * @access	public
	 * @return	string
	 */
	function getIndexField() {
		return 'timestamp';
	}

	/**
	 * Return a formatted database row.
	 *
	 * @access	public
	 * @return	void
	 */
	public function formatRow($row) {
		$user = User::newFromId($row->user_id);
		$claim = new wikiClaim($user);

		$actor = User::newFromId($row->actor_id);

		$wikiClaimsPage	= Title::newFromText('Special:WikiClaims');
		$wikiClaimsURL	= $wikiClaimsPage->getFullURL();

		$timestamp = new MWTimestamp($row->timestamp);

		return Html::rawElement(
			'li',
			[],
			wfMessage(
				"claim_log_row",
				"<a href='{$wikiClaimsURL}?do=view&amp;user_id=".$claim->getUser()->getId()."'>#".$row->claim_id."</a>",
				Linker::userLink($claim->getUser()->getId(),$claim->getUser()->getName()),
				wfMessage('status_'.$row->status)->escaped(),
				Linker::userLink($actor->getId(), $actor->getName()),
				Linker::userToolLinks($actor->getId(), $actor->getName()),
				$timestamp->getHumanTimestamp()
			)->text()
		);
	}
}

class claimLogEntry {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		$this->DB = wfGetDB(DB_MASTER);
	}

	/**
	 * Set the wikiClaim object.
	 *
	 * @access	public
	 * @param	object	wikiClaim
	 * @return	void
	 */
	public function setClaim(wikiClaim $claim) {
		$this->claim = $claim;
	}

	/**
	 * Set the User object.
	 *
	 * @access	public
	 * @param	object	wikiClaim
	 * @return	void
	 */
	public function setActor(User $user) {
		$this->actor = $user;
	}

	/**
	 * Function Documentation
	 *
	 * @access	public
	 * @return	void
	 */
	public function insert() {
		$success = $this->DB->insert(
			'wiki_claims_log',
			[
				'claim_id'	=> $this->claim->getClaimId(),
				'actor_id'	=> $this->actor->getId(),
				'status'	=> $this->claim->getStatus(),
				'timestamp'	=> time()
			],
			__METHOD__
		);

		return $success;
	}
}