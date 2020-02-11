<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Claim Wiki Log Entry
 *
 * @package   ClaimWiki
 * @author    Alex Smith
 * @copyright (c) 2015 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace ClaimWiki;

use User;

class ClaimLogEntry {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->DB = wfGetDB(DB_MASTER);
	}

	/**
	 * Set the wikiClaim object.
	 *
	 * @param wikiClaim $claim
	 *
	 * @return void
	 */
	public function setClaim(WikiClaim $claim) {
		$this->claim = $claim;
	}

	/**
	 * Set the User object.
	 *
	 * @param User $user
	 *
	 * @return void
	 */
	public function setActor(User $user) {
		$this->actor = $user;
	}

	/**
	 * Do Log Insert
	 *
	 * @return void
	 */
	public function insert() {
		$success = $this->DB->insert(
			'wiki_claims_log',
			[
				'claim_id'	=> $this->claim->getId(),
				'actor_id'	=> $this->actor->getId(),
				'status'	=> $this->claim->getStatus(),
				'timestamp'	=> time()
			],
			__METHOD__
		);

		return $success;
	}
}
