<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Claim Wiki Special Page
 *
 * @package   ClaimWiki
 * @author    Alex Smith
 * @copyright (c) 2013 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace ClaimWiki\Specials;

use ClaimWiki\Templates\TemplateClaimEmails;
use ClaimWiki\Templates\TemplateClaimWiki;
use ClaimWiki\WikiClaim;
use ConfigFactory;
use HydraCore\SpecialPage;
use MailAddress;
use MediaWiki\MediaWikiServices;
use RedisCache;
use Reverb\Notification\NotificationBroadcast;
use Sanitizer;
use Title;
use User;
use UserMailer;

class SpecialClaimWiki extends SpecialPage {
	/**
	 * Output HTML
	 *
	 * @var string
	 */
	private $content;

	/**
	 * Main Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		parent::__construct('ClaimWiki', 'claim_wiki', false);
	}

	/**
	 * Main Executor
	 *
	 * @param string $subpage Sub page passed in the URL.
	 *
	 * @return void [Outputs to screen]
	 */
	public function execute($subpage) {
		$config = ConfigFactory::getDefaultInstance()->makeConfig('main');
		$wgClaimWikiEnabled = $config->get('ClaimWikiEnabled');
		$wgClaimWikiGuardianTotal = $config->get('ClaimWikiGuardianTotal');
		$wgClaimWikiEditThreshold = $config->get('ClaimWikiEditThreshold');

		$this->checkPermissions();

		$this->redis = RedisCache::getClient('cache');
		$this->templateClaimWiki = new TemplateClaimWiki;
		$this->templateClaimEmails = new TemplateClaimEmails;

		$this->output->addModuleStyles(['ext.claimWiki.styles']);
		$this->output->addModules(['ext.claimWiki.scripts']);

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
			[
				'status' => intval(WikiClaim::CLAIM_APPROVED),
				'end_timestamp' => 0
			],
			__METHOD__
		);
		$total = $result->fetchRow();
		if ($total['total'] >= $wgClaimWikiGuardianTotal) {
			$this->output->showErrorPage('wiki_claim_error', 'wiki_claim_maximum_guardians');
			return;
		}

		if ($this->wgUser->getEditCount() < $wgClaimWikiEditThreshold) {
			$this->output->showErrorPage('wiki_claim_error', 'wiki_claim_below_threshhold_contributions');
			return;
		}

		$this->claim = WikiClaim::newFromUser($this->getUser());
		$this->claimForm();
		$this->output->addHTML($this->content);
	}

	/**
	 * Claim Wiki Form
	 *
	 * @return void	[Outputs to screen]
	 */
	public function claimForm() {
		$errors = $this->claimSave();
		$this->output->setPageTitle(wfMessage('claim_this_wiki'));
		$this->content = $this->templateClaimWiki->claimForm($this->claim, $errors);
	}

	/**
	 * Saves submitted Claim Wiki Forms.
	 *
	 * @return array	Array of errors.
	 */
	private function claimSave() {
		global $dsSiteKey;

		$errors = [];

		if ($this->getRequest()->wasPosted() && $this->getRequest()->getVal('do') === 'save') {
			$questionKeys = $this->claim->getQuestionKeys();
			foreach ($questionKeys as $key) {
				$this->claim->setAnswer($key, trim($this->wgRequest->getVal($key)));
			}

			// Reset the claim timestamp if resubmitted.
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
						$siteManagers = unserialize(
							$this->redis->hGet('dynamicsettings:siteInfo:' . $dsSiteKey, 'wiki_managers')
						);
					} catch (RedisException $e) {
						wfDebug(__METHOD__ . ": Caught RedisException - " . $e->getMessage());
					}
					$siteManager = false;
					if (is_array($siteManagers) && count($siteManagers)) {
						foreach ($siteManagers as $key => $siteManager) {
							$user = User::newFromName($siteManager);
							$user->load();
							if ($user->getId()) {
								$siteManagers[$key] = $user;
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

						$mainConfig = MediaWikiServices::getInstance()->getMainConfig();
						$broadcast = NotificationBroadcast::newMulti(
							'-wiki-claim',
							$this->claim->getUser(),
							$siteManagers,
							[
								'url' => SpecialPage::getTitleFor('WikiClaims')->getFullURL([
									'do' => 'view', 'user_id' => $this->claim->getUser()->getId()
								]),
								'message' => [
									[
										'user_note',
										''
									],
									[
										1,
										$this->claim->getUser()->getName()
									],
									[
										2,
										$mainConfig->get('Sitename')
									]
								]
							]
						);
						if ($broadcast) {
							$broadcast->transmit();
						}
					}

					$emailTo[] = new MailAddress(
						$wgClaimWikiEmailTo,
						wfMessage('claimwikiteamemail_sender')->escaped()
					);

					$emailSubject = wfMessage('claim_wiki_email_subject', $this->claim->getUser()->getName())->text();

					$emailExtra = [
						'environment' => (!empty($_SERVER['PHP_ENV']) ? $_SERVER['PHP_ENV'] : $_SERVER['SERVER_NAME']),
						'user'        => $this->wgUser,
						'claim'       => $this->claim,
						'site_name'   => $wgSitename
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
				$this->output->redirect($page->getFullURL() . "?success=true");
				return;
			}
		}
		return $errors;
	}

	/**
	 * Return the group name for this special page.
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'claimwiki';
	}
}
