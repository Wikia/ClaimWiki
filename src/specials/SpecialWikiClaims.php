<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Wiki Claims Special Page
 *
 * @package   ClaimWiki
 * @author    Alex Smith
 * @copyright (c) 2013 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace ClaimWiki\Specials;

use ClaimWiki\ClaimLogPager;
use ClaimWiki\WikiClaim;
use ConfigFactory;
use Html;
use HydraCore;
use HydraCore\SpecialPage;
use MediaWiki\MediaWikiServices;
use Title;
use Twiggy\TwiggyService;
use User;

class SpecialWikiClaims extends SpecialPage {
	/**
	 * Output HTML
	 *
	 * @var string
	 */
	private $content;

	/**
	 * Template Engine
	 *
	 * @var TwiggyService
	 */
	private $twiggy;

	/**
	 * Main Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		global $wgClaimWikiEnabled;
		parent::__construct('WikiClaims', 'wiki_claims', $wgClaimWikiEnabled);
	}

	/**
	 * Main Executor
	 *
	 * @param string $subpage Sub page passed in the URL.
	 *
	 * @return void	[Outputs to screen]
	 */
	public function execute($subpage) {
		$config = ConfigFactory::getDefaultInstance()->makeConfig('main');
		$wgClaimWikiEnabled = $config->get('ClaimWikiEnabled');

		$this->checkPermissions();

		$this->twiggy = MediaWikiServices::getInstance()->getService('TwiggyService');

		$this->output->addModuleStyles(['ext.claimWiki.styles']);
		$this->output->addModules(['ext.claimWiki.scripts']);

		$this->setHeaders();

		if (!$wgClaimWikiEnabled) {
			$this->output->showErrorPage('wiki_claim_error', 'wiki_claim_disabled');
			return;
		}

		if ($subpage == 'log') {
			$this->showLog();
		} else {
			switch ($this->wgRequest->getVal('do')) {
				default:
				case 'claims':
					$this->wikiClaims();
					break;
				case 'approve':
					$this->approveClaim();
					break;
				case 'resume':
					$this->resumeClaim();
					break;
				case 'deny':
					$this->denyClaim();
					break;
				case 'pending':
					$this->pendingClaim();
					break;
				case 'delete':
					$this->deleteClaim();
					break;
				case 'inactive':
					$this->inactiveClaim();
					break;
				case 'view':
					$this->viewClaim();
					break;
			}
		}

		$this->output->addHTML($this->content);
	}

	/**
	 * Wiki Claims List
	 *
	 * @return void	[Outputs to screen]
	 */
	public function wikiClaims() {
		$start = $this->wgRequest->getInt('st');
		$itemsPerPage = 25;
		$cookieExpire = time() + 900;

		if ($this->wgRequest->getCookie('wikiClaimsSortKey') && !$this->wgRequest->getVal('sort')) {
			$sort = $this->wgRequest->getCookie('wikiClaimsSortKey');
		} else {
			$sort = $this->wgRequest->getVal('sort');
		}
		$sortKey = $sort;
		$this->wgRequest->response()->setcookie('wikiClaimsSortKey', $sortKey, $cookieExpire);

		$sortDir = $this->wgRequest->getVal('sort_dir');

		$claims = WikiClaim::getClaims($start, $itemsPerPage, $sortKey, $sortDir);
		$claimsCount = WikiClaim::getClaimsCount();

		if (($this->wgRequest->getCookie('wikiClaimsSortDir') == 'desc' && !$sortDir)
			|| strtolower($sortDir) == 'desc'
		) {
			$claims = array_reverse($claims);
			$sortDir = 'desc';
		} else {
			$sortDir = 'asc';
		}
		$this->wgRequest->response()->setcookie('wikiClaimsSortDir', $sortDir, $cookieExpire);

		$pagination = HydraCore::generatePaginationHtml($this->getFullTitle(), $claimsCount, $itemsPerPage, $start);

		$template = $this->twiggy->load('@ClaimWiki/claim_list.twig');
		$this->output->setPageTitle(wfMessage('wikiclaims'));
		$this->content = $template->render([
			'claims' => $claims,
			'pagination' => $pagination,
			'sortKey' => $sortKey,
			'sortDir' => $sortDir,
			'wikiClaimsPage' => SpecialPage::getTitleFor('WikiClaims'),
			'logUrl' => SpecialPage::getTitleFor('WikiClaims/log')->getFullURL()
		]);
	}

	/**
	 * Wiki Claim View
	 *
	 * @return void	[Outputs to screen]
	 */
	public function viewClaim() {
		$this->loadClaim(true);
		if (!$this->claim) {
			return;
		}

		$this->output->setPageTitle(wfMessage('view_claim') . ' - ' . $this->claim->getUser()->getName());
		$this->output->addBacklinkSubtitle($this->getPageTitle());
		$template = $this->twiggy->load('@ClaimWiki/claim_view.twig');
		$this->content = $template->render([
			'claim' => $this->claim,
			'wikiContributionsURL' => Title::newFromText('Special:Contributions')->getFullURL()
		]);
	}

	/**
	 * Show Claim Log
	 *
	 * @return void
	 */
	public function showLog() {
		$pager = new ClaimLogPager($this->getContext(), []);

		$body = $pager->getBody();

		$this->content .= "<div id='contentSub'><span>" . wfMessage('back_to_wiki_claims')->parse() . "</span></div>";

		if ($body) {
			$this->content .= $pager->getNavigationBar()
				. Html::rawElement('ul', [], $body)
				. $pager->getNavigationBar();
		} else {
			$this->content .= wfMessage('no_log_entries_found')->escaped();
		}

		$this->output->setPageTitle(wfMessage('claim_log')->escaped());
	}

	/**
	 * Approve Claim
	 *
	 * @return void
	 */
	public function approveClaim() {
		$this->loadClaim();
		if (!$this->claim) {
			return;
		}

		$this->claim->setApproved();
		$this->claim->setTimestamp(time(), 'start');
		$this->claim->setTimestamp(0, 'end');
		$this->claim->save();
		$this->claim->getUser()->addGroup('wiki_guardian');

		$this->claim->sendNotification('approved', $this->getUser());

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Resume Claim
	 *
	 * @return void
	 */
	public function resumeClaim() {
		$this->loadClaim();
		if (!$this->claim) {
			return;
		}

		$this->claim->setApproved();
		$this->claim->setTimestamp(0, 'end');
		$this->claim->save();
		$this->claim->getUser()->addGroup('wiki_guardian');

		$this->claim->sendNotification('resumed', $this->getUser());

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Deny Claim
	 *
	 * @return void
	 */
	public function denyClaim() {
		$this->loadClaim();
		if (!$this->claim) {
			return;
		}

		$this->claim->setDenied();
		$this->claim->save();
		$this->claim->getUser()->removeGroup('wiki_guardian');

		$this->claim->sendNotification('denied', $this->getUser());

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Pending Claim
	 *
	 * @return void
	 */
	public function pendingClaim() {
		$this->loadClaim();
		if (!$this->claim) {
			return;
		}

		$this->claim->setPending();
		$this->claim->save();
		$this->claim->getUser()->removeGroup('wiki_guardian');

		$this->claim->sendNotification('pending', $this->getUser());

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Inactive Claim
	 *
	 * @return void
	 */
	public function inactiveClaim() {
		$this->loadClaim();
		if (!$this->claim) {
			return;
		}

		$this->claim->setInactive();
		$this->claim->setTimestamp(time(), 'end');
		$this->claim->save();
		$this->claim->getUser()->removeGroup('wiki_guardian');

		$this->claim->sendNotification('inactive', $this->getUser());

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Delete Claim
	 *
	 * @return void
	 */
	public function deleteClaim() {
		$this->loadClaim();
		if (!$this->claim) {
			return;
		}

		$this->claim->setDeleted();
		$this->claim->setTimestamp(time(), 'end');
		$this->claim->save();
		$this->claim->getUser()->removeGroup('wiki_guardian');

		$this->claim->sendNotification('deleted', $this->getUser());

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Load Claim
	 *
	 * @param boolean $allowDeleted
	 *
	 * @return object	Loaded wikiClaim object.
	 */
	private function loadClaim($allowDeleted = false) {
		$userId = $this->wgRequest->getInt('user_id');
		$user = User::newFromId($userId);
		if (!$user->getId()) {
			$this->output->showErrorPage('wiki_claim_error', 'view_claim_bad_user_id');
			return false;
		}

		$this->claim = WikiClaim::newFromUser($user, $allowDeleted);
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
