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

class SpecialWikiClaims extends SpecialPage {
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

		parent::__construct('WikiClaims');

		$this->wgRequest	= $wgRequest;
		$this->wgUser		= $wgUser;
		$this->output		= $this->getOutput();

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
		global $wgSitename, $claimWikiEnabled;
		if (!$this->wgUser->isAllowed('wiki_claims')) {
			throw new PermissionsError('wiki_claims');
			return;
		}
		if (!defined('CW_EXT_DIR')) {
			define('CW_EXT_DIR', dirname(__DIR__));
		}
		if (!defined('SITE_DIR')) {
			define('SITE_DIR', dirname(dirname(dirname(__DIR__))));
		}

		if (!class_exists('mouseHole')) {
			require_once(SITE_DIR.'/mouse/mouse.php');
		}
		$this->mouse = mouseHole::instance(array('output' => 'mouseOutputOutput'), array());
		$this->mouse->output->addTemplateFolder(CW_EXT_DIR.'/templates');

		$this->output->addModules('ext.claimWiki');

		$this->setHeaders();

		if (!$claimWikiEnabled) {
			$this->output->showErrorPage('wiki_claim_error', 'wiki_claim_disabled');
			return;
		}

		switch ($this->wgRequest->getVal('do')) {
			default:
			case 'claims':
				$this->wikiClaims();
				break;
			case 'approve':
				$this->approveClaim();
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

			$pagination = $this->mouse->output->generatePagination(count($claims), $itemsPerPage, $start);
			$pagination = $this->mouse->output->paginationTemplate($pagination);
			$claims = array_slice($claims, $start, $itemsPerPage, true);
		}

		$this->output->setPageTitle(wfMessage('wikiclaims'));
		$this->mouse->output->loadTemplate('wikiclaims');
		$this->content = $this->mouse->output->wikiclaims->wikiClaims($claims, $pagination, $sortKey, $sortDir, $searchTerm);
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
		$this->mouse->output->loadTemplate('wikiclaims');
		$this->content = $this->mouse->output->wikiclaims->viewClaim($this->claim);
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
		$this->mouse->output->loadTemplate('claimemails');

		$emailTo		= $this->claim->getUser()->getName()." <".$this->claim->getUser()->getEmail().">";

		$emailSubject	= wfMessage('claim_status_email_subject', wfMessage('subject_'.$status)->text())->text();

		$emailExtra		= [
			'user'			=> $this->wgUser,
			'claim'			=> $this->claim,
			'site_name'		=> $wgSitename
		];
		$emailBody		= $this->mouse->output->claimemails->claimStatusNotice($emailExtra);

		$emailFrom		= $this->wgUser->getEmail();
		$emailHeaders	= "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: {$emailFrom}\r\nReply-To: {$emailFrom}\r\nX-Mailer: Hydra/1.0";

		$success = mail($emailTo, $emailSubject, $emailBody, $emailHeaders, "-f{$emailFrom}");

		return $success;
	}

	/**
	 * Hides special page from SpecialPages special page.
	 *
	 * @access	public
	 * @return	boolean	False
	 */
	public function isListed() {
		if (!$this->wgUser->isAllowed('wiki_claims')) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Lets others determine that this special page is restricted.
	 *
	 * @access	public
	 * @return	boolean	True
	 */
	public function isRestricted() {
		return true;
	}
}
?>