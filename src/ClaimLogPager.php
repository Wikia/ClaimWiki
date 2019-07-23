<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Claim Wiki Log Pager
 *
 * @package   ClaimWiki
 * @author    Alex Smith
 * @copyright (c) 2015 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 **/

namespace ClaimWiki;

use Html;
use Linker;
use MWTimestamp;
use ReverseChronologicalPager;
use Title;
use User;

class ClaimLogPager extends ReverseChronologicalPager {
	/**
	 * Return query arguments.
	 *
	 * @return array
	 */
	public function getQueryInfo() {
		$query = [
			'tables'		=> [
				'wiki_claims_log',
				'wiki_claims'
			],
			'fields'		=> [
				'wiki_claims_log.*', 'wiki_claims.user_id'
			],
			'conds'			=> [],
			'options'		=> [
				'ORDER BY'	=> 'wiki_claims_log.timestamp DESC'
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
	 * @return string
	 */
	public function getIndexField() {
		return 'timestamp';
	}

	/**
	 * Return a formatted database row.
	 *
	 * @param mixed $row
	 *
	 * @return string
	 */
	public function formatRow($row) {
		$user = User::newFromId($row->user_id);
		$claim = WikiClaim::newFromUser($user);

		$actor = User::newFromId($row->actor_id);

		$wikiClaimsPage	= Title::newFromText('Special:WikiClaims');
		$wikiClaimsURL	= $wikiClaimsPage->getFullURL();

		$timestamp = new MWTimestamp($row->timestamp);

		return Html::rawElement(
			'li',
			[],
			wfMessage(
				"claim_log_row",
				"<a href='{$wikiClaimsURL}?do=view&amp;claim_id="
				. $row->claim_id . "'>#" . $row->claim_id . "</a>",
				Linker::userLink($claim->getUser()->getId(), $claim->getUser()->getName()),
				wfMessage('status_' . $row->status)->escaped(),
				Linker::userLink($actor->getId(), $actor->getName()),
				Linker::userToolLinks($actor->getId(), $actor->getName()),
				$timestamp->getHumanTimestamp()
			)->text()
		);
	}
}
