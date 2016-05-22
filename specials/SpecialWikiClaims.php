<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Wiki Claims Special Page
 *
 * @author		Alex Smith
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

class SpecialWikiClaims extends Curse\SpecialPage {
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
		parent::__construct('WikiClaims', 'wiki_claims');
	}

	/**
	 * Main Executor
	 *
	 * @access	public
	 * @param	string	Sub page passed in the URL.
	 * @return	void	[Outputs to screen]
	 */
	public function execute($subpage) {
		global $wgSitename, $claimWikiEnabled;

		$this->templateWikiClaims = new TemplateWikiClaims;
		$this->templateClaimEmails = new TemplateClaimEmails;

		$this->output->addModules(['ext.claimWiki']);

		$this->setHeaders();

		if (!$claimWikiEnabled) {
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
				case 'end':
					$this->endClaim();
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
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function wikiClaims() {
		$start = $this->wgRequest->getInt('st');
		$itemsPerPage = 100;
		$cond = array('c.user_id = u.user_id');

		$result = $this->DB->select(
			array('c' => 'wiki_claims', 'u' => 'user'),
			array('c.user_id', 'u.user_name', 'c.claim_timestamp', 'c.start_timestamp', 'c.end_timestamp'),
			$cond,
			__METHOD__,
			array('ORDER BY' => 'c.claim_timestamp ASC')
		);
		while ($row = $result->fetchRow()) {
			$user = User::newFromId($row['user_id']);
			$row['claimObj'] = new wikiClaim($user);
			$claims[$row['claimObj']->getClaimId()] = $row;
		}

		if (count($claims)) {
			if ($this->wgRequest->getCookie('wikiClaimsSortKey') && !$this->wgRequest->getVal('sort')) {
				$sort = $this->wgRequest->getCookie('wikiClaimsSortKey');
			} else {
				$sort = $this->wgRequest->getVal('sort');
			}
			if (array_key_exists($sort, reset($claims))) {
				$sortKey = $sort;
			} else {
				$sortKey = 'claim_timestamp';
			}
			$this->wgRequest->response()->setcookie('wikiClaimsSortKey', $sortKey, $cookieExpire);

			$sorter = array();
			foreach ($claims as $key => $info) {
				$sorter[$key] = $info[$sortKey];
			}
			natcasesort($sorter);

			$sortedArray = array();
			foreach ($sorter as $key => $value) {
				$sortedArray[$key] = $claims[$key];
			}
			$claims = $sortedArray;

			$sortDir = $this->wgRequest->getVal('sort_dir');
			if (($this->wgRequest->getCookie('wikiClaimsSortDir') == 'desc' && !$sortDir) || strtolower($sortDir) == 'desc') {
				$claims = array_reverse($claims);
				$sortDir = 'desc';
			} else {
				$sortDir = 'asc';
			}
			$this->wgRequest->response()->setcookie('wikiClaimsSortDir', $sortDir, $cookieExpire);


			$searchTerm = $this->wgRequest->getVal('list_search');
			if (($this->wgRequest->getVal('do') == 'search' && !empty($searchTerm))) {
				$searchKey = array('user_name');
				$searchTerm = mb_strtolower($searchTerm, 'UTF-8');
				$found = array();
				foreach ($claims as $key => $info) {
					foreach ($searchKey as $sKey) {
						if (is_array($info[$sKey])) {
							$_temp = mb_strtolower(implode(',', $info[$sKey]), 'UTF-8');
						} else {
							$_temp = mb_strtolower($info[$sKey], 'UTF-8');
						}
						if (strpos($_temp, $searchTerm) !== false) {
							$found[$key] = $info;
						}
					}
				}
				$claims = $found;
			}

			$pagination = Curse::generatePaginationHtml(count($claims), $itemsPerPage, $start);
				$claims = array_slice($claims, $start, $itemsPerPage, true);
		}

		$this->output->setPageTitle(wfMessage('wikiclaims'));
		$this->content = $this->templateWikiClaims->wikiClaims($claims, $pagination, $sortKey, $sortDir, $searchTerm);
	}

	/**
	 * Wiki Claim View
	 *
	 * @access	public
	 * @return	void	[Outputs to screen]
	 */
	public function viewClaim() {
		$this->loadClaim();
		if (!$this->claim) {
			return;
		}

		$this->output->setPageTitle(wfMessage('view_claim').' - '.$this->claim->getUser()->getName());
		$this->content = $this->templateWikiClaims->viewClaim($this->claim);
	}

	/**
	 * Show Claim Log
	 *
	 * @access	public
	 * @return	void
	 */
	public function showLog() {
		$start = $this->wgRequest->getVal('start');
		$itemsPerPage = 50;

		$pager = new claimLogPager($this->getContext(), []);

		$body = $pager->getBody();

		$this->content .= "<div id='contentSub'><span>".wfMessage('back_to_wiki_claims')->parse()."</span></div>";

		if ($body) {
			$this->content .= $pager->getNavigationBar().Html::rawElement('ul', [], $body).$pager->getNavigationBar();
		} else {
			$this->content .= wfMessage('no_log_entries_found')->escaped();
		}

		$this->output->setPageTitle(wfMessage('claim_log')->escaped());
	}

	/**
	 * Approve Claim
	 *
	 * @access	public
	 * @return	void
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

		$this->sendEmail('approved');

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Resume Claim
	 *
	 * @access	public
	 * @return	void
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

		$this->sendEmail('resumed');

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Deny Claim
	 *
	 * @access	public
	 * @return	void
	 */
	public function denyClaim() {
		$this->loadClaim();
		if (!$this->claim) {
			return;
		}

		$this->claim->setDenied();
		$this->claim->save();
		$this->claim->getUser()->removeGroup('wiki_guardian');

		$this->sendEmail('denied');

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Pending Claim
	 *
	 * @access	public
	 * @return	void
	 */
	public function pendingClaim() {
		$this->loadClaim();
		if (!$this->claim) {
			return;
		}

		$this->claim->setPending();
		$this->claim->save();
		$this->claim->getUser()->removeGroup('wiki_guardian');

		$this->sendEmail('pending');

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * End Claim
	 *
	 * @access	public
	 * @return	void
	 */
	public function endClaim() {
		$this->loadClaim();
		if (!$this->claim) {
			return;
		}

		$this->claim->setNew();
		$this->claim->setTimestamp(time(), 'end');
		$this->claim->save();
		$this->claim->getUser()->removeGroup('wiki_guardian');

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Inactive Claim
	 *
	 * @access	public
	 * @return	void
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

		$this->sendEmail('inactive');

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Delete Claim
	 *
	 * @access	public
	 * @return	void
	 */
	public function deleteClaim() {
		$this->loadClaim();
		if (!$this->claim) {
			return;
		}

		$this->claim->getUser()->removeGroup('wiki_guardian');
		$this->claim->delete();

		$page = Title::newFromText('Special:WikiClaims');
		$this->output->redirect($page->getFullURL());
	}

	/**
	 * Load Claim
	 *
	 * @access	private
	 * @return	object	Loaded wikiClaim object.
	 */
	private function loadClaim() {
		$userId = $this->wgRequest->getInt('user_id');
		$user = User::newFromId($userId);
		if (!$user->getId()) {
			$this->output->showErrorPage('wiki_claim_error', 'view_claim_bad_user_id');
			return false;
		}

		$this->claim = new wikiClaim($user);
	}

	/**
	 * Send a claim status email.
	 *
	 * @access	private
	 * @param	boolean	Approved/Denied
	 * @return	void
	 */
	private function sendEmail($status) {
		if ($_SERVER['PHP_ENV'] != 'development') {
			$ownerEmail = $this->claim->getUser()->getEmail();
			if (Sanitizer::validateEmail($ownerEmail)) {
				$address[] = new MailAddress($ownerEmail, $this->claim->getUser()->getName());
			}
			$emailSubject = wfMessage('claim_status_email_subject', wfMessage('subject_' . $status)->text())->text();

			//Copy the approver/denier on the email.
			$adminEmail = $this->wgUser->getEmail();
			if (Sanitizer::validateEmail($adminEmail)) {
				$address[] = new MailAddress($adminEmail, $this->wgUser->getName());
			}
		} else {
			$emailTo = 'Hydra Testers' . " <wikitest@curse.com>";
			$address[] = new MailAddress("wikitest@curse.com", 'Hydra Testers');
			$emailSubject = wfMessage('claim_status_email_subject_dev', wfMessage('subject_' . $status)->text())->text();
		}

		$emailExtra		= [
			'user'			=> $this->wgUser,
			'claim'			=> $this->claim
		];

		$from = new MailAddress($wgEmergencyContact);
		$address[] = $from;

		$email = new UserMailer();
		$status = $email->send(
			$address,
			$from,
			$emailSubject,
			$this->templateClaimEmails->claimStatusNotice($status, $emailExtra);
		);

		if ($status->isOK()) {
			return true;
		}
		return false;
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
