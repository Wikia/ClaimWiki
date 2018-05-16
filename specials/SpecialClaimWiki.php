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

class SpecialClaimWiki extends HydraCore\SpecialPage {
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
		global $wgClaimWikiEnabled, $wgClaimWikiGuardianTotal;

		$this->checkPermissions();

		$this->redis = RedisCache::getClient('cache');
		$this->templateClaimWiki = new TemplateClaimWiki;
		$this->templateClaimEmails = new TemplateClaimEmails;

		$this->output->addModules('ext.claimWiki');

		$this->setHeaders();

		if (!$wgClaimWikiEnabled) {
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
			'status = '.intval(WikiClaim::CLAIM_APPROVED).' AND end_timestamp = 0',
			__METHOD__
		);
		$total = $result->fetchRow();
		if ($total['total'] >= $wgClaimWikiGuardianTotal) {
			$this->output->showErrorPage('wiki_claim_error', 'wiki_claim_maximum_guardians');
			return;
		}

		if ($this->wgUser->getEditCount() < 1){
			$this->output->showErrorPage('wiki_claim_error', 'wiki_claim_zero_contributions');
			return;
		}

		$this->claim = WikiClaim::newFromUser($this->getUser());

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
		global $dsSiteKey;

		$errors = [];

		if ($this->getRequest()->wasPosted() && $this->getRequest()->getVal('do') === 'save') {
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
			$errors = array_merge($errors, $_errors);
			if (!count($errors) && $this->claim->isAgreed()) {
				$success = $this->claim->save();

				if ($success) {
					global $wgClaimWikiEmailTo, $wgSitename, $wgPasswordSender, $wgPasswordSenderName, $dsSiteKey;

					try {
						$siteManagers = @unserialize($this->redis->hGet('dynamicsettings:siteInfo:'.$dsSiteKey, 'wiki_managers'));
					} catch (RedisException $e) {
						wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
					}
					$siteManager = false;
					$echoManagerIds = [];
					if (is_array($siteManagers) && count($siteManagers)) {
						foreach ($siteManagers as $key => $siteManager) {
							$user = User::newFromName($siteManager);
							$user->load();
							if ($user->getId()) {
								$siteManagers[$key] = $user;
								$echoManagerIds[] = $user->getId();
							} else {
								unset($siteManagers[$key]);
							}
						}
						$wikiManager = current($siteManagers);
					}

					if (is_array($siteManagers) && count($siteManagers)) {
						$wikiManager = current($siteManagers);

						$wikiManagerEmail = $wikiManager->getEmail();
						if (Sanitizer::validateEmail($wikiManagerEmail)) {
							$emailTo[] = new MailAddress($wikiManagerEmail, $wikiManager->getName());
						}

						EchoEvent::create(
							[
								'type'	=> 'wiki-claim',
								'title'	=> Title::newFromText('Special:WikiClaims'),
								'agent'	=> $this->claim->getUser(),
								'extra'	=> [
									'notifyAgent'	=> true,
									'claim_id'		=> $this->claim->getId(),
									'managers'		=> $echoManagerIds,
									'site_key'		=> $dsSiteKey,
									'site_name'		=> $wgSitename,
									'claim_url'		=> SpecialPage::getTitleFor('WikiClaims')->getFullURL(['do' => 'view', 'user_id' => $this->claim->getUser()->getId()])
								]
							]
						);
					}

					$emailTo[] = new MailAddress($wgClaimWikiEmailTo);

					$emailSubject = wfMessage('claim_wiki_email_subject', $this->claim->getUser()->getName())->text();

					$emailExtra = [
						'environment'	=> (!empty($_SERVER['PHP_ENV']) ? $_SERVER['PHP_ENV'] : $_SERVER['SERVER_NAME']),
						'user'			=> $this->wgUser,
						'claim'			=> $this->claim,
						'site_name'		=> $wgSitename
					];

					$from = new MailAddress($wgPasswordSender, $wgPasswordSenderName);

					$email = new UserMailer();
					$status = $email->send(
						$emailTo,
						$from,
						$emailSubject,
						[
							'text' => strip_tags($this->templateClaimEmails->claimWikiNotice($emailExtra)),
							'html' => $this->templateClaimEmails->claimWikiNotice($emailExtra)
						]
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
