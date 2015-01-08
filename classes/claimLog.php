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

class claimLog {
	/**
	 * Log Entries
	 *
	 * @var		array
	 */
	private $entries = [];

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
	 * Function Documentation
	 *
	 * @access	public
	 * @return	void
	 */
	public function load($start, $itemsPerPage) {
		$result = $this->DB->select(
			['wiki_claims_log'],
			['*'],
			[],
			__METHOD__,
			[
				'ORDER BY'	=> 'timestamp DESC',
				'OFFSET'	=> $start,
				'LIMIT'		=> $itemsPerPage
			]
		);

		while ($row = $result->fetchRow()) {
			$user = User::newFromId($row['user_id']);
			$row['claimObj'] = new wikiClaim($user);
			$this->entries[$row['lid']] = $row;
			$this->entries[$row['lid']][$row['claimObj']->getClaimId()] = $row;
		}
	}

	/**
	 * Return loaded entries.
	 *
	 * @access	public
	 * @return	void
	 */
	public function getEntries() {
		return $this->entries;
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
		$this->user = $user;
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
				'user_id'	=> $this->user->getId(),
				'status'	=> $this->claim->getStatus(),
				'timestamp'	=> time()
			],
			__METHOD__
		);

		return $success;
	}
}