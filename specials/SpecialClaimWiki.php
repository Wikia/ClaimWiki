<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Claim Wiki Special Page
 *
 * @author		Alex Smith
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

class SpecialClaimWiki extends SpecialPage {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $content;

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		global $wgRequest, $wgUser, $wgOut;

		parent::__construct('ClaimWiki', 'wiki_claims', false);

		$this->wgRequest	= $wgRequest;
		$this->wgUser		= $wgUser;
		$this->output		= $this->getOutput();

		if (!defined('DS_EXT_DIR')) {
			define('DS_EXT_DIR', dirname(__DIR__));
		}

		//HOW CAN YOU FAIL SO BADLY, MEDIAWIKI?
		$this->DB = wfGetDB(DB_MASTER);
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
	 */
	public function execute($subpage) {
		global $wgSitename, $claimWikiEnabled, $claimWikiGuardianTotal;
		if (!defined('CW_EXT_DIR')) {
			define('CW_EXT_DIR', dirname(__DIR__));
		}
		if (!defined('SITE_DIR')) {
			define('SITE_DIR', dirname(dirname(dirname(__DIR__))));
		}

		$this->checkPermissions();

		$this->mouse = mouseNest::getMouse();
		$this->mouse->output->addTemplateFolder(CW_EXT_DIR.'/templates');

		$this->output->addModules('ext.claimWiki');

		$this->setHeaders();

		if (!$claimWikiEnabled) {
			$this->output->showErrorPage('wiki_claim_error', 'wiki_claim_disabled');
			return;
		}

		if (in_array('wiki_guardian', $this->wgUser->getGroups())) {
			$this->output->showErrorPage('wiki_claim_error', 'wiki_claim_already_guardian');
			return;
		}

		$result = $this->DB->select(
			'wiki_claims',
			['COUNT(*) as total'],
			'status = '.intval(wikiClaim::CLAIM_APPROVED).' AND end_timestamp = 0',
			__METHOD__
		);
		$total = $result->fetchRow();
		if ($total['total'] >= $claimWikiGuardianTotal) {
			$this->output->showErrorPage('wiki_claim_error', 'wiki_claim_maximum_guardians');
			return;
		}

		if ($this->wgUser->getEditCount() < 1){
			$this->output->showErrorPage('wiki_claim_error', 'wiki_claim_zero_contributions');
			return;
		}

		$this->claim = new wikiClaim($this->wgUser);

		$this->claimForm();

		$this->output->addHTML($this->content);
	}

	/**
	 * Claim Wiki Form
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function claimForm() {
		$errors = $this->claimSave();

		$this->output->setPageTitle(wfMessage('claim_this_wiki'));
		$this->mouse->output->loadTemplate('claimwiki');
		$this->content = $this->mouse->output->claimwiki->claimForm($this->claim, $errors);
	}

	/**
	 * Saves submitted Claim Wiki Forms.
	 *
	 * @access	private
	 * @return	array	Array of errors.
	 */
	private function claimSave() {
		if ($_GET['do'] == 'save') {
			$questionKeys = $this->claim->getQuestionKeys();
			foreach ($questionKeys as $key) {
				$this->claim->setAnswer($key, trim($this->wgRequest->getVal($key)));
			}

			//Reset the claim timestamp if resubmitted.
			$this->claim->setTimestamp(time(), 'claim');

			if ($this->wgRequest->getVal('agreement') == 'agreed') {
				$this->claim->setAgreed();
			} else {
				$errors['agreement'] = wfMessage('claim_agree_error')->escaped();
			}

			$_errors = $this->claim->getErrors();
			if (is_array($_errors)) {
				$errors = array_merge($errors, $_errors);
			}
			if (!is_array($errors) && $this->claim->isAgreed()) {
				$success = $this->claim->save();

				if ($success) {
					global $claimWikiEmailTo, $wgSitename, $wgPasswordSender, $wgPasswordSenderName, $dsSiteKey;
					$this->mouse->output->loadTemplate('claimemails');

					$emailTo[] = $claimWikiEmailTo;

					$siteManagers = @unserialize($this->mouse->redis->hget('dynamicsettings:siteInfo:'.$dsSiteKey, 'wiki_managers'));
					$siteManager = false;
					if (is_array($siteManagers) && count($siteManagers)) {
						$siteManager = current($siteManagers);
						$user = User::newFromName($siteManager);
						$user->load();
						if ($user->getId()) {
							$wikiManager = $user->getEmail();
						}
					}

					if (filter_var($wikiManager, FILTER_VALIDATE_EMAIL)) {
						$emailTo[] = $wikiManager;
					}
					$emailTo = implode(', ', $emailTo);
					$emailSubject = wfMessage('claim_wiki_email_subject', $this->claim->getUser()->getName())->text();

					$emailExtra = [
						'environment'	=> (!empty($_SERVER['PHP_ENV']) ? $_SERVER['PHP_ENV'] : $_SERVER['SERVER_NAME']),
						'user'			=> $this->wgUser,
						'claim'			=> $this->claim,
						'site_name'		=> $wgSitename
					];
					$emailBody		= $this->mouse->output->claimemails->claimWikiNotice($emailExtra);

					$emailFrom		= $wgPasswordSenderName." <{$wgPasswordSender}>";
					$emailHeaders	= "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: {$emailFrom}\r\nReply-To: {$emailFrom}\r\nX-Mailer: Hydra/1.0";

					$success = mail($emailTo, $emailSubject, $emailBody, $emailHeaders, '-f'.$emailFrom);

					return true;
				} else {
					return false;
				}

				$page = Title::newFromText('Special:ClaimWiki');
				$this->output->redirect($page->getFullURL()."?success=true");
				return;
			}
		}
		return $errors;
	}

	public function getGroupName() {
		return 'claimwiki';
	}
}
