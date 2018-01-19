<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Claim Wiki Emails Template
 *
 * @author		Alex Smith
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

class TemplateClaimEmails {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $HMTL;

	/**
	 * Claim Wiki Notice
	 *
	 * @access	public
	 * @param	array	Extra information for email body template.
	 * @return	string	Built HTML
	 */
	public function claimWikiNotice($emailExtra = []) {
		$page = Title::newFromText('Special:WikiClaims');

		$HTML = '';
		$HTML .= "~*~*~*~*~*~*~*~ ".strtoupper($emailExtra['environment'])." ~*~*~*~*~*~*~*~<br/><br/>"
				.$emailExtra['user']->getName()." submitted a claim to administrate {$emailExtra['site_name']} at ".date('c', $emailExtra['claim']->getTimestamp('claim')).".<br/>
				Please visit <a href='".$page->getFullURL()."'>the wiki claims page</a> to approve or deny this claim.";
		return $HTML;
	}

	/**
	 * Claim Wiki Status
	 *
	 * @access	public
	 * @param	string	Status email type to send.
	 * @param	array	Extra information for email body template.
	 * @return	string	Built HTML
	 */
	public function claimStatusNotice($status, $emailExtra) {
		global $wgEmergencyContact, $wgClaimWikiEmailSignature;

		$page = Title::newFromText('Project:Admin_noticeboard');

		$HTML = '';

		if ($status == "pending") {
			$HTML .= "Dear ".$emailExtra['claim']->getUser()->getName().",<br/><br/>
					Thank you very much for your recent application to become the Wiki Guardian for this project. We very much appreciate your enthusiasm for the project, but feel that at this moment in time there is either not enough activity to warrant having an administrator for now, or that we would really like to see more of a contribution history from you before accepting your request. The admin tools that are granted by this position are generally used very infrequently and do not confer any type of status or rank on the wiki, merely the ability to perform certain custodial tasks and we generally like to choose individuals who have demonstrated a continued interest in contributing to the project at all levels.<br/><br/>
					For now, we have kept your application on file and would ask that you contact a Curse staff member on their userpage or e-mail ".$wgEmergencyContact." after continuing to contribute to this project in the meantime. If you have need of an administrator in the meantime, please post on the <a href='".$page->getFullURL()."'>Admin Noticeboard</a> and we will be happy to assist!<br/><br />
					Thanks,<br/>
					--{$wgClaimWikiEmailSignature}";
		} elseif ($status == "approved") {
			$HTML .= "Dear ".$emailExtra['claim']->getUser()->getName().",<br/><br/>
					We’re happy to say that your Claim-a-Wiki application has been accepted! After reviewing your responses, we are confident that you are going to be a welcome addition to this wiki and Gamepedia in general. We assume you’re pretty up to speed with the basics, but remember that you have now been granted the technical ability to perform certain special actions on this wiki. This includes the ability to block users from editing, protect pages from editing, delete pages, rename pages without restriction, and use certain other tools. We ask that you use these tools in the pursuit of excellence, and never for spiteful or personal reasons. If you ever have any questions, comments, concerns, or any type of issue you’re not sure how to handle please feel free to contact the wiki team either via email at wiki@curse.com or by leaving a message on a wiki administrator's talk page. Be sure to also check out the Administrators Guide (https://help.gamepedia.com/Administrators_guide).<br/><br/>
					If you are not already on it, we would also like to invite you to join us on the Gamepedia Slack. Slack is a messaging platform for teams, and joining our Slack team is a great way for you, as an active editor, to keep in touch with our staff as well as other active editors and admins. You can visit https://gamepedia.com/slackinvite to request access.<br/><br/>
					Congratulations, and welcome!<br/><br/>
					--{$wgClaimWikiEmailSignature}";
		} elseif ($status == "denied") {
			$HTML .= "Dear ".$emailExtra['claim']->getUser()->getName().",<br/><br/>
					After reviewing your Claim-a-Wiki application, we must unfortunately decline your application. It’s nothing personal, but for one reason or another we felt that you were not eligible to be elevated to an administrator level on this project. The most common reason for a wiki claim denial is a lack of edit history. If you are still interested, you are welcome to apply again, although please note that your previous application will still be on file and so it may be in your interest to wait a short while and gain some more experience on-wiki before trying again. If you would like to contact us directly about your application, feel free to e-mail us at wiki@curse.com or leave a message on a wiki administrator's talk page.<br/>
					Thank you for your interest,<br/><br/>
					--{$wgClaimWikiEmailSignature}";
		} elseif ($status == "inactive") {
			$HTML .= "Dear ".$emailExtra['claim']->getUser()->getName().",<br/><br/>
					Your status as Wiki Guardian has been removed due to inactivity. Please contact a wiki administrator if you wish to reinstate your status.<br/><br/>
					--{$wgClaimWikiEmailSignature}";
		} elseif ($status == "resumed") {
			$HTML .= "Dear ".$emailExtra['claim']->getUser()->getName().",<br/><br/>
					Your status as a Wiki Guardian has been restored after its removal for inactivity. <br/><br/>
					--{$wgClaimWikiEmailSignature}";
		}
		return $HTML;
	}

	/**
	 * Wiki Guardian Inactive
	 *
	 * @access	public
	 * @param	object	The wikiClaim object.
	 * @param	string	Wiki Name
	 * @return	string	Built HTML
	 */
	public function wikiGuardianInactive($userName, $wikiName) {
		$HTML = "Dear {$userName},<br/><br/>
				Your status as Wiki Guardian on ".$wikiName." will be removed soon, as we’ve noticed your inactivity. Please visit the wiki and resume contributing to retain your status. If it has already been removed, contact a wiki administrator if you wish to restore your status.";
		return $HTML;
	}
}
