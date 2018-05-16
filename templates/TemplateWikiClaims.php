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

class TemplateWikiClaims {
	/**
	 * Wiki Claims
	 *
	 * @access	public
	 * @param	array	Array of Claim Information
	 * @param	array	Pagination
	 * @param	string	Data sorting key
	 * @param	string	Data sorting direction
	 * @return	string	Built HTML
	 */
	public function wikiClaims($claims, $pagination, $sortKey, $sortDir) {
		$wikiClaimsPage	= SpecialPage::getTitleFor('WikiClaims');
		$wikiClaimsURL	= $wikiClaimsPage->getFullURL();
		
		$html = "
	<div>{$pagination}</div>
	<div class='buttons'>
		<div class='legend approved'>
			<span class='swatch'></span> ".wfMessage('claim_legend_approved')->escaped()."
		</div>
		<div class='legend denied'>
			<span class='swatch'></span> ".wfMessage('claim_legend_denied')->escaped()."
		</div>
		<div class='legend pending'>
			<span class='swatch'></span> ".wfMessage('claim_legend_pending')->escaped()."
		</div>
		<div class='legend inactive'>
			<span class='swatch'></span> ".wfMessage('claim_legend_inactive')->escaped()."
		</div>
		<a href='".SpecialPage::getTitleFor('WikiClaims/log')->getFullURL()."' class='mw-ui-button'>".wfMessage('claim_log')->escaped()."</a>
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
			foreach ($claims as $claimId => $claim) {
				$html .= "
				<tr class='".($claim->isApproved() ? 'approved' : null).($claim->isDenied() ? 'denied' : null).($claim->isPending() ? 'pending' : null).($claim->isInactive() ? 'inactive' : null)."'>
					<td><a href='".$wikiClaimsPage->getFullURL(['do' => 'view', 'user_id' => $claim->getUser()->getId()])."'>".$claim->getUser()->getName()."</a></td>
					<td><span data-sort='claim_timestamp'".($sortKey == 'claim_timestamp' ? " data-selected='true'" : '').">".($claim->getTimestamp('claim') ? date('Y-m-d H:i e', $claim->getTimestamp('claim')) : wfMessage('never')->escaped())."</span></td>
					<td><span data-sort='start_timestamp'".($sortKey == 'start_timestamp' ? " data-selected='true'" : '').">".($claim->getTimestamp('start') ? date('Y-m-d H:i e', $claim->getTimestamp('start')) : wfMessage('never')->escaped())."</span></td>
					<td><span data-sort='end_timestamp'".($sortKey == 'end_timestamp' ? " data-selected='true'" : '').">".($claim->getTimestamp('end') ? date('Y-m-d H:i e', $claim->getTimestamp('end')) : wfMessage('never')->escaped())."</span></td>
					<td class='controls'>
						<div class='controls_container'>
							<img src='".wfExpandUrl('/extensions/ClaimWiki/images/wikilist/tools.png')."'/>
							<span class='dropdown'>
								".($claim->isNew() || $claim->isPending() || $claim->isDenied() ? "<a href='".$wikiClaimsPage->getFullURL(['do' => 'approve', 'user_id' => $claim->getUser()->getId()])."' title='".wfMessage('approve_claim')->escaped()."'><img src='".wfExpandUrl('/extensions/ClaimWiki/images/green_check.png')."'/>".wfMessage('approve_claim')->escaped()."</a>" : null)."
								".($claim->isInactive() ? "<a href='".$wikiClaimsPage->getFullURL(['do' => 'resume', 'user_id' => $claim->getUser()->getId()])."' title='".wfMessage('resume_claim')->escaped()."'><img src='".wfExpandUrl('/extensions/ClaimWiki/images/green_check.png')."'/>".wfMessage('resume_claim')->escaped()."</a>" : null)."
								".($claim->isApproved() ? "<a href='".$wikiClaimsPage->getFullURL(['do' => 'inactive', 'user_id' => $claim->getUser()->getId()])."' title='".wfMessage('mark_inactive')->escaped()."'><img src='".wfExpandUrl('/extensions/ClaimWiki/images/yellow_check.png')."'/>".wfMessage('mark_inactive')->escaped()."</a>" : null)."
								".(!$claim->isDenied() ? "<a href='".$wikiClaimsPage->getFullURL(['do' => 'deny', 'user_id' => $claim->getUser()->getId()])."' title='".wfMessage('deny_claim')->escaped()."'><img src='".wfExpandUrl('/extensions/ClaimWiki/images/red-x.png')."'/>".wfMessage('deny_claim')->escaped()."</a>" : null)."
								".($claim->isNew() ? "<a href='".$wikiClaimsPage->getFullURL(['do' => 'pending', 'user_id' => $claim->getUser()->getId()])."' title='".wfMessage('pending_claim')->escaped()."'><img src='".wfExpandUrl('/extensions/ClaimWiki/images/pending.png')."'/>".wfMessage('pending_claim')->escaped()."</a>" : null)."
								".($claim->isNew() || $claim->isInactive() || $claim->isDenied() ? "<a href='".$wikiClaimsPage->getFullURL(['do' => 'delete', 'user_id' => $claim->getUser()->getId()])."' title='".wfMessage('delete_claim')->escaped()."'><img src='".wfExpandUrl('/extensions/ClaimWiki/images/delete.png')."'/>".wfMessage('delete_claim')->escaped()."</a>" : null)."
							</span>
						</div>
					</td>
				</tr>
";
			}
		} else {
			$html .= "
			<tr>
				<td colspan='5'>".wfMessage('no_claims_found')->text()."</td>
			</tr>
			";
		}
		$html .= "
		</tbody>
	</table>";

		$html .= $pagination;

		return $html;
	}

	/**
	 * Claim View
	 *
	 * @access	public
	 * @param	array	Array of Claim Information
	 * @return	string	Built HTML
	 */
	public function viewClaim($claim) {
		$wikiContributionsPage	= Title::newFromText('Special:Contributions');
		$wikiContributionsURL	= $wikiContributionsPage->getFullURL();

		$answers = $claim->getAnswers();
		$html = "
		<div id='claim_wiki_form'>
			<h3>User Name: <span class='plain'>".$claim->getUser()->getName()."</span></h3>
			<h3>Email: <a href='mailto:".$claim->getUser()->getEmail()."?subject=".urlencode(wfMessage('claim_questions'))."'><span class='plain'>".$claim->getUser()->getEmail()."</span></a></h3>";
		foreach ($answers as $questionKey => $answer) {
			$html .= "<h3>".wfMessage($questionKey)->text()."</h3>
			<p>{$answer}</p>";
		}
		$html .= "
			<a href='{$wikiContributionsURL}/".$claim->getUser()->getName()."' target='_blank'>".wfMessage('claim_user_contributions')->escaped()."</a><br />
			<a  href='".$claim->getUser()->getUserPage()->getFullURL()."'>View User Page for ".$claim->getUser()->getName()."</a>
		</div>";

		return $html;
	}
}
