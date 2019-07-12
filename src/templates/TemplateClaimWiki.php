<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Claim Wiki Skin
 *
 * @package   ClaimWiki
 * @author    Alex Smith
 * @copyright (c) 2013 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace ClaimWiki\Templates;

use Title;

class TemplateClaimWiki {
	/**
	 * Claim Wiki Form
	 *
	 * @param array $claim  Array of claim information
	 * @param array $errors Form Errors
	 *
	 * @return string Built HTML
	 */
	public function claimForm($claim, $errors) {
		global $wgRequest, $wgSitename;

		$html = '';
		if (is_array($errors) && count($errors)) {
			$mainPage		= new Title();
			$mainPageURL	= $mainPage->getFullURL();
			foreach ($errors as $value) {
				$html .= "<div class='errorbox'>" . $value . "</div><br style='clear: both;'/>";
			}
			$html .= "<a href='{$mainPageURL}'>" . wfMessage('returnto', $wgSitename)->escaped() . "</a>";
		} else {
			if ($wgRequest->getVal('success') == 'true' || ($claim->isAgreed() && $claim->getTimestamp('claim') > 0)) {
				$mainPage		= new Title();
				$mainPageURL	= $mainPage->getFullURL();
				if ($claim->isDenied()) {
					$html .= "<div class='errorbox'>" . wfMessage('claim_denied') . "</div><br style='clear: both;'/>
					<a href='{$mainPageURL}'>" . wfMessage('returnto', $wgSitename)->escaped() . "</a>";
				} else {
					$html .= "<div class='successbox'>" . wfMessage('claim_successful') . "</div><br style='clear: both;'/>
					<a href='{$mainPageURL}'>" . wfMessage('returnto', $wgSitename)->escaped() . "</a>";
				}
			} else {
				$html .= "<p>" . $claim->getGuidelinesText() . "</p>
				<form id='claim_wiki_form' method='post' action='?do=save'>
					<fieldset>
						<h3>User Name: <span class='plain'>" . $claim->getUser()->getName() . "</span></h3>
						<h3>Email: <span class='plain'>" . $claim->getUser()->getEmail() . "</span></h3>";
				$questions = $claim->getQuestions();
				foreach ($questions as $key => $question) {
					$html .= (isset($errors[$key]) ? '<span class="error">' . $errors[$key] . '</span>' : '') . "
						<label for='{$key}' class='label_above'><h3>" . $question['text'] . "</h3></label>
						<textarea id='{$key}' name='{$key}' type='text'/>" . htmlentities($question['answer'], ENT_QUOTES) . "</textarea>
					";
				}
				$html .=
						(isset($errors['agreement']) ? '<br/><span class="error">' . $errors['agreement'] . '</span>' : '') . "
						<p>" . $claim->getAgreementText() . "</p>
						<label for='agreement'><input id='agreement' name='agreement' type='checkbox' value='agreed'" . ($claim->isAgreed() ? " checked='checked'" : null) . "/> " . wfMessage('claim_agree') . "</label>
					</fieldset>
					<fieldset class='submit'>
						<input id='user_id' name='user_id' type='hidden' value='" . $claim->getUser()->getId() . "'/>
						<input id='wiki_submit' name='wiki_submit' type='submit' value='" . wfMessage('send_claim') . "'/>
					</fieldset>
				</form>";
			}
		}
		return $html;
	}
}
