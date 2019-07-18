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

use ClaimWiki\WikiClaim;
use ConfigFactory;
use GlobalVarConfig;
use HydraCore\SpecialPage;
use MailAddress;
use MediaWiki\MediaWikiServices;
use Title;
use Twiggy\TwiggyService;
use UserMailer;

class SpecialClaimWiki extends SpecialPage {
	/**
	 * Template Engine
	 *
	 * @var TwiggyService
	 */
	private $twiggy;

	/**
	 * Main Configuration
	 *
	 * @var GlobalVarConfig
	 */
	private $config;

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
		$this->checkPermissions();

		$this->config = ConfigFactory::getDefaultInstance()->makeConfig('main');
		$this->twiggy = MediaWikiServices::getInstance()->getService('TwiggyService');

		$this->output->addModuleStyles(['ext.claimWiki.styles']);
		$this->output->addModules(['ext.claimWiki.scripts']);

		$this->setHeaders();

		$errors = $this->checkForClaimErrors();
		if ($errors) {
			$this->output->showErrorPage(...$errors);
			return;
		}

		$this->claim = WikiClaim::newFromUser($this->getUser());
		$this->render();
	}

	/**
	 * Render the page output
	 *
	 * @return mixed
	 */
	private function render() {
		$this->output->setPageTitle(wfMessage('claim_this_wiki'));
		$errors = [];

		// Display claim status
		if ($this->claim->getStatus() >= 0) {
			$wgSiteName  = $this->config->get('Sitename');
			$mainPage    = new Title();
			$mainPageURL = $mainPage->getFullURL();
			$template = $this->twiggy->load('@ClaimWiki/claim_status.twig');
			return $this->output->addHTML($template->render([
				'claim' => $this->claim,
				'wgSiteName' => $wgSiteName,
				'mainPageURL' => $mainPageURL
			]));
		}

		// Saving claim
		if ($this->getRequest()->wasPosted() && $this->getRequest()->getVal('do') === 'save') {
			$errors = $this->validateRequest($errors);
			// if no errors save claim and redirect to success
			if (!$errors) {
				$this->claimSave();
				$page = Title::newFromText('Special:ClaimWiki');
				return $this->output->redirect($page->getFullURL() . "?success=true");
			}
		}

		// Show form
		$template = $this->twiggy->load('@ClaimWiki/claim_form.twig');
		return $this->output->addHTML($template->render(['claim' => $this->claim, 'errors' => $errors]));
	}

	/**
	 * Save submitted Claim Wiki Form
	 *
	 * @return void
	 */
	private function claimSave() {
		$this->claim->setNew();
		$success = $this->claim->save();

		if ($success) {
			$this->claim->sendNotification('created', $this->getUser());
			$this->sendClaimCreatedEmail();
		}
	}

	/**
	 * Send Claim Created Email
	 *
	 * @return void
	 */
	private function sendClaimCreatedEmail() {
		$wgClaimWikiEmailTo = $this->config->get('ClaimWikiEmailTo');
		$wgSitename = $this->config->get('Sitename');
		$wgPasswordSender = $this->config->get('PasswordSender');
		$wgPasswordSenderName = $this->config->get('PasswordSenderName');

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

		$page = Title::newFromText('Special:WikiClaims');
		$template = $this->twiggy->load('@ClaimWiki/claim_email_created.twig');
		return $email->send(
			$emailTo,
			$from,
			$emailSubject,
			[
				'text' => strip_tags($template->render(['emailExtra' => $emailExtra, 'page' => $page])),
				'html' => $template->render(['emailExtra' => $emailExtra, 'page' => $page])
			]
		);
	}

	/**
	 * Check request for errors
	 *
	 * @param array $errors
	 *
	 * @return array
	 */
	private function validateRequest($errors) {
		$request = $this->getRequest();
		$questionKeys = $this->claim->getQuestionKeys();
		// check for agreement
		$this->claim->setTimestamp(time(), 'claim');

		if ($request->getVal('agreement') == 'agreed') {
			$this->claim->setAgreed();
		} else {
			$errors['agreement'] = wfMessage('claim_agree_error')->escaped();
		}
		// check that all required questions have answers
		array_walk($questionKeys, function ($key) use ($request) {
			$this->claim->setAnswer($key, trim($request->getVal($key)));
		});
		return array_merge($errors, $this->claim->getErrors());
	}

	/**
	 * Check for common claim errors
	 *
	 * @return boolean
	 */
	private function checkForClaimErrors() {
		$wgClaimWikiEnabled = $this->config->get('ClaimWikiEnabled');
		$wgClaimWikiGuardianTotal = $this->config->get('ClaimWikiGuardianTotal');
		$wgClaimWikiEditThreshold = $this->config->get('ClaimWikiEditThreshold');

		if (!$wgClaimWikiEnabled) {
			return ['wiki_claim_error', 'wiki_claim_disabled'];
		}

		if (in_array('wiki_guardian', $this->wgUser->getGroups())) {
			return ['wiki_claim_error', 'wiki_claim_already_guardian'];
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
			return ['wiki_claim_error', 'wiki_claim_maximum_guardians'];
		}

		if ($this->wgUser->getEditCount() < $wgClaimWikiEditThreshold) {
			return ['wiki_claim_error', 'wiki_claim_below_threshhold_contributions'];
		}

		return false;
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
