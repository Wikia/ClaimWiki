<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Wiki Claims Skin
 *
 * @author		Alex Smith
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

class skin_wikiclaims {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $HMTL;

	/**
	 * Wiki Claims
	 *
	 * @access	public
	 * @param	array	Array of Claim Information
	 * @param	array	Pagination
	 * @param	string	Data sorting key
	 * @param	string	Data sorting direction
	 * @param	string	Search Term
	 * @return	string	Built HTML
	 */
	public function wikiClaims($claims, $pagination, $sortKey, $sortDir, $searchTerm) {
		global $wgOut, $wgUser, $wgRequest, $wgServer, $wgScriptPath;

		$wikiClaimsPage	= Title::newFromText('Special:WikiClaims');
		$wikiClaimsURL	= $wikiClaimsPage->getFullURL();

		$HTML = "
	<div>{$pagination}</div>
	<div class='search_bar'>
		<form method='get' action='{$wikiClaimsURL}'>
			<fieldset>
				<input type='hidden' name='section' value='list' />
				<input type='hidden' name='do' value='search' />
				<input type='text' name='list_search' value='".htmlentities($searchTerm, ENT_QUOTES)."' class='search_field' />
				<input type='submit' value='".wfMessage('list_search')."' class='button' />
				<a href='{$wikiClaimsURL}?do=resetSearch' class='button'>".wfMessage('list_reset')."</a>
			</fieldset>
		</form>
	</div>
	<div class='buttons'>
		<div class='legend approved'>
			<span class='swatch'></span> Approved
		</div><div class='legend denied'>
			<span class='swatch'></span> Denied
		</div><div class='legend pending'>
			<span class='swatch'></span> Pending
		</div>
	</div>
	<table id='claimlist'>
		<thead>
			<tr class='sortable' data-sort-dir='".($sortDir == 'desc' ? 'desc' : 'asc')."'>
				<th class='unsortable'>".wfMessage('claim_user')->escaped()."</th>
				<th".($sortKey == 'claim_timestamp' ? " data-selected='true'" : '')."><span data-sort='claim_timestamp'".($sortKey == 'claim_timestamp' ? " data-selected='true'" : '').">".wfMessage('claim_timestamp')->escaped()."</span></th>
				<th".($sortKey == 'start_timestamp' ? " data-selected='true'" : '')."><span data-sort='start_timestamp'".($sortKey == 'start_timestamp' ? " data-selected='true'" : '').">".wfMessage('start_timestamp')->escaped()."</span></th>
				<th".($sortKey == 'end_timestamp' ? " data-selected='true'" : '')."><span data-sort='end_timestamp'".($sortKey == 'end_timestamp' ? " data-selected='true'" : '').">".wfMessage('end_timestamp')->escaped()."</span></th>
				<th class='controls unsortable'>&nbsp;</th>
			</tr>
		</thead>
		<tbody>
		";
		if (count($claims)) {
			foreach ($claims as $claim) {
				$claim = $claim['claimObj'];
				$HTML .= "
				<tr class='".($claim->isApproved() === true ? 'approved' : null).($claim->isApproved() === false ? 'denied' : null).($claim->isPending() === true ? 'pending' : null)."'>
					<td><a href='{$wikiClaimsURL}?do=view&amp;user_id=".$claim->getUser()->getId()."'>".$claim->getUser()->getName()."</a></td>
					<td><span data-sort='claim_timestamp'".($sortKey == 'claim_timestamp' ? " data-selected='true'" : '').">".($claim->getTimestamp('claim') ? date('Y-m-d H:i e', $claim->getTimestamp('claim')) : wfMessage('never')->escaped())."</span></td>
					<td><span data-sort='start_timestamp'".($sortKey == 'start_timestamp' ? " data-selected='true'" : '').">".($claim->getTimestamp('start') ? date('Y-m-d H:i e', $claim->getTimestamp('start')) : wfMessage('never')->escaped())."</span></td>
					<td><span data-sort='end_timestamp'".($sortKey == 'end_timestamp' ? " data-selected='true'" : '').">".($claim->getTimestamp('end') ? date('Y-m-d H:i e', $claim->getTimestamp('end')) : wfMessage('never')->escaped())."</span></td>
					<td class='controls'>
						<div class='controls_container'>
							<img src='{$wgServer}{$wgScriptPath}/extensions/ClaimWiki/images/wikilist/tools.png'/>
							<span class='dropdown'>
								".($claim->isApproved() !== true ? "<a href='{$wikiClaimsURL}?do=approve&amp;user_id=".$claim->getUser()->getId()."' title='".wfMessage('approve_claim')->escaped()."'><img src='{$wgServer}{$wgScriptPath}/extensions/ClaimWiki/images/green_check.png'/>".wfMessage('approve_claim')->escaped()."</a>" : null)."
								".($claim->isApproved() === true ? "<a href='{$wikiClaimsURL}?do=end&amp;user_id=".$claim->getUser()->getId()."' title='".wfMessage('end_claim')->escaped()."'><img src='{$wgServer}{$wgScriptPath}/extensions/ClaimWiki/images/yellow_check.png'/>".wfMessage('end_claim')->escaped()."</a>" : null)."
								".($claim->isApproved() === null ? "<a href='{$wikiClaimsURL}?do=deny&amp;user_id=".$claim->getUser()->getId()."' title='".wfMessage('deny_claim')->escaped()."'><img src='{$wgServer}{$wgScriptPath}/extensions/ClaimWiki/images/red-x.png'/>".wfMessage('deny_claim')->escaped()."</a>" : null)."
								".($claim->isPending() === null ? "<a href='{$wikiClaimsURL}?do=pending&amp;user_id=".$claim->getUser()->getId()."' title='".wfMessage('pending_claim')->escaped()."'><img src='{$wgServer}{$wgScriptPath}/extensions/ClaimWiki/images/pending.png'/>".wfMessage('pending_claim')->escaped()."</a>" : null)."
								".($claim->isApproved() === null ? "<a href='{$wikiClaimsURL}?do=delete&amp;user_id=".$claim->getUser()->getId()."' title='".wfMessage('delete_claim')->escaped()."'><img src='{$wgServer}{$wgScriptPath}/extensions/ClaimWiki/images/delete.png'/>".wfMessage('delete_claim')->escaped()."</a>" : null)."
							</span>
						</div>
					</td>
				</tr>
";
			}
		} else {
			$HTML .= "
			<tr>
				<td colspan='5'>".wfMessage('no_claims_found')->text()."</td>
			</tr>
			";
		}
$HTML .= <<<HTML
		</tbody>
	</table>
HTML;

		$HTML .= $pagination;

		return $HTML;
	}

	/**
	 * Claim View
	 *
	 * @access	public
	 * @param	array	Array of Claim Information
	 * @return	string	Built HTML
	 */
	public function viewClaim($claim) {
		global $wgServer, $wgScriptPath;

		$wikiContributionsPage	= Title::newFromText('Special:Contributions');
		$wikiContributionsURL	= $wikiContributionsPage->getFullURL();

		$answers = $claim->getAnswers();
		$HTML .= "
		<div id='claim_wiki_form'>
			<h3>User Name: <span class='plain'>".$claim->getUser()->getName()."</span></h3>
			<h3>Email: <a href='mailto:".$claim->getUser()->getEmail()."?subject=".urlencode(wfMessage('claim_questions'))."'><span class='plain'>".$claim->getUser()->getEmail()."</span></a></h3>";
		foreach ($answers as $questionKey => $answer) {
			$HTML .= "<h3>".wfMessage($questionKey)->text()."</h3>
			<p>{$answer}</p>";
		}
		$HTML .= "
			<a href='{$wikiContributionsURL}/".$claim->getUser()->getName()."' target='_blank'>".wfMessage('claim_user_contributions')->escaped()."</a><br />
			<a  href='".$claim->getUser()->getUserPage()->getFullURL()."'>View User Page for ".$claim->getUser()->getName()."</a>
		</div>";

		return $HTML;
	}
}
?>