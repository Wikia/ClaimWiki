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

class SpecialClaimWiki extends Curse\SpecialPage {
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
		parent::__construct('ClaimWiki', 'claim_wiki', false);
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

		$this->checkPermissions();

		$this->redis = RedisCache::getClient('cache');
		$this->templateClaimWiki = new TemplateClaimWiki;
		$this->templateClaimEmails = new TemplateClaimEmails;

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
		$this->content = $this->templateClaimWiki->claimForm($this->claim, $errors);
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

					$emailTo[] = new MailAddress($claimWikiEmailTo);

					try {
						$siteManagers = @unserialize($this->redis->hGet('dynamicsettings:siteInfo:'.$dsSiteKey, 'wiki_managers'));
					} catch (RedisException $e) {
						wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
					}
					$siteManager = false;
					if (is_array($siteManagers) && count($siteManagers)) {
						$siteManager = current($siteManagers);
						$user = User::newFromName($siteManager);
						$user->load();
						if ($user->getId()) {
							$wikiManager = $user;
							unset($user);
						}
					}

					$wikiManagerEmail = $wikiManager->getEmail(); //This is done to prevent calling "GetEmail" hooks multiple times.
					if (Sanitizer::validateEmail($wikiManagerEmail)) {
						$emailTo[] = new MailAddress($wikiManagerEmail, $wikiManager->getName());
					}
					$emailSubject = wfMessage('claim_wiki_email_subject', $this->claim->getUser()->getName())->text();

					$emailExtra = [
						'environment'	=> (!empty($_SERVER['PHP_ENV']) ? $_SERVER['PHP_ENV'] : $_SERVER['SERVER_NAME']),
						'user'			=> $this->wgUser,
						'claim'			=> $this->claim,
						'site_name'		=> $wgSitename
					];

					$emailSubject = wfMessage('claim_status_email_subject', wfMessage('subject_' . $status)->text())->text();

					$from = new MailAddress($wgPasswordSender, $wgPasswordSenderName);

					$email = new UserMailer();
					$status = $email->send(
						$address,
						$from,
						$emailSubject,
						$this->templateClaimEmails->claimWikiNotice($emailExtra);
					);

					if ($status->isOK()) {
						return true;
					}
					return false;
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

	/**
	 * Return the group name for this special page.
	 *
	 * @access	protected
	 * @return	string
	 */
	protected function getGroupName() {
		return 'claimwiki';
	}
}
