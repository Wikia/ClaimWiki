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
 */

namespace ClaimWiki\Specials;

use ClaimWiki\ClaimLogPager;
use ClaimWiki\WikiClaim;
use Html;
use HydraCore;
use HydraCore\SpecialPage;
use MediaWiki\MediaWikiServices;
use Title;
use Twiggy\TwiggyService;

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
	 * Wiki Claim
	 *
	 * @var WikiClaim
	 */
	private $claim = null;

	public function __construct() {
		parent::__construct( 'WikiClaims', 'wiki_claims' );
		$this->twiggy = MediaWikiServices::getInstance()->getService( 'TwiggyService' );
	}

	/**
	 * Main Executor
	 *
	 * @param string $subpage Sub page passed in the URL.
	 *
	 * @return void [Outputs to screen]
	 */
	public function execute( $subpage ) {
		$this->checkPermissions();

		$output = $this->getOutput();
		$output->addModuleStyles( [ 'ext.claimWiki.styles' ] );
		$output->addModules( [ 'ext.claimWiki.scripts' ] );

		$this->setHeaders();
		$output = $this->getOutput();

		if ( $subpage == 'log' ) {
			$this->showLog();
		} else {
			switch ( $this->getRequest()->getVal( 'do' ) ) {
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

		$output->addHTML( $this->content );
	}

	/**
	 * Wiki Claims List
	 *
	 * @return void [Outputs to screen]
	 */
	public function wikiClaims() {
		global $wgExtensionAssetsPath;

		$request = $this->getRequest();
		$start = $request->getInt( 'st' );
		$itemsPerPage = 25;
		$cookieExpire = time() + 900;

		if ( $request->getCookie( 'wikiClaimsSortKey' ) && !$request->getVal( 'sort' ) ) {
			$sort = $request->getCookie( 'wikiClaimsSortKey' );
		} else {
			$sort = $request->getVal( 'sort' );
		}
		$sortKey = $sort;
		$request->response()->setcookie( 'wikiClaimsSortKey', $sortKey, $cookieExpire );

		$sortDir = $request->getVal( 'sort_dir' );

		$claims = WikiClaim::getClaims( $start, $itemsPerPage, $sortKey, $sortDir );
		$claimsCount = WikiClaim::getClaimsCount();

		if ( ( $request->getCookie( 'wikiClaimsSortDir' ) == 'desc' && !$sortDir )
			 || strtolower( $sortDir ) == 'desc'
		) {
			$claims = array_reverse( $claims );
			$sortDir = 'desc';
		} else {
			$sortDir = 'asc';
		}
		$request->response()->setcookie( 'wikiClaimsSortDir', $sortDir, $cookieExpire );

		$pagination = HydraCore::generatePaginationHtml( $this->getFullTitle(), $claimsCount, $itemsPerPage, $start );

		$template = $this->twiggy->load( '@ClaimWiki/claim_list.twig' );
		$this->getOutput()->setPageTitle( wfMessage( 'wikiclaims' ) );
		$this->content = $template->render( [
			'claims' => $claims,
			'pagination' => $pagination,
			'sortKey' => $sortKey,
			'sortDir' => $sortDir,
			'wgExtensionAssetsPath' => $wgExtensionAssetsPath,
			'wikiClaimsPage' => SpecialPage::getTitleFor( 'WikiClaims' ),
			'logUrl' => SpecialPage::getTitleFor( 'WikiClaims/log' )->getFullURL(),
		] );
	}

	public function viewClaim() {
		$claimId = $this->getRequest()->getInt( 'claim_id' );
		$output = $this->getOutput();
		$this->claim = WikiClaim::newFromID( $claimId, true );
		if ( !$this->claim ) {
			$output->addBacklinkSubtitle( $this->getPageTitle() );
			$this->content = wfMessage( 'claim_not_found' )->plain();
			return;
		}

		$output->setPageTitle( wfMessage( 'view_claim' ) . ' - ' . $this->claim->getUser()->getName() );
		$output->addBacklinkSubtitle( $this->getPageTitle() );
		$template = $this->twiggy->load( '@ClaimWiki/claim_view.twig' );
		$this->content = $template->render( [
			'claim' => $this->claim,
			'wikiContributionsURL' => Title::newFromText( 'Special:Contributions' )->getFullURL(),
		] );
	}

	public function showLog() {
		$pager = new ClaimLogPager( $this->getContext() );

		$body = $pager->getBody();

		$this->content .= "<div id='contentSub'><span>" . wfMessage( 'back_to_wiki_claims' )->parse() . "</span></div>";

		if ( $body ) {
			$this->content .= $pager->getNavigationBar()
							  . Html::rawElement( 'ul', [], $body )
							  . $pager->getNavigationBar();
		} else {
			$this->content .= wfMessage( 'no_log_entries_found' )->escaped();
		}

		$this->getOutput()->setPageTitle( wfMessage( 'claim_log' )->escaped() );
	}

	/**
	 * Approve Claim
	 *
	 * @return void
	 */
	public function approveClaim() {
		$this->loadClaim();
		if ( !$this->claim ) {
			return;
		}

		$this->claim->setApproved();
		$this->claim->setTimestamp( time(), 'start' );
		$this->claim->setTimestamp( 0, 'end' );
		$this->claim->save();
		$this->claim->getUser()->addGroup( 'wiki_guardian' );

		$this->claim->sendNotification( 'approved', $this->getUser() );

		$page = Title::newFromText( 'Special:WikiClaims' );
		$this->getOutput()->redirect( $page->getFullURL() );
	}

	/**
	 * Resume Claim
	 *
	 * @return void
	 */
	public function resumeClaim() {
		$this->loadClaim();
		if ( !$this->claim ) {
			return;
		}

		$this->claim->setApproved();
		$this->claim->setTimestamp( 0, 'end' );
		$this->claim->save();
		$this->claim->getUser()->addGroup( 'wiki_guardian' );

		$this->claim->sendNotification( 'resumed', $this->getUser() );

		$page = Title::newFromText( 'Special:WikiClaims' );
		$this->getOutput()->redirect( $page->getFullURL() );
	}

	/**
	 * Deny Claim
	 *
	 * @return void
	 */
	public function denyClaim() {
		$this->loadClaim();
		if ( !$this->claim ) {
			return;
		}

		$this->claim->setDenied();
		$this->claim->save();
		$this->claim->getUser()->removeGroup( 'wiki_guardian' );

		$this->claim->sendNotification( 'denied', $this->getUser() );

		$page = Title::newFromText( 'Special:WikiClaims' );
		$this->getOutput()->redirect( $page->getFullURL() );
	}

	/**
	 * Pending Claim
	 *
	 * @return void
	 */
	public function pendingClaim() {
		$this->loadClaim();
		if ( !$this->claim ) {
			return;
		}

		$this->claim->setPending();
		$this->claim->save();
		$this->claim->getUser()->removeGroup( 'wiki_guardian' );

		$this->claim->sendNotification( 'pending', $this->getUser() );

		$page = Title::newFromText( 'Special:WikiClaims' );
		$output->redirect( $page->getFullURL() );
	}

	/**
	 * Inactive Claim
	 *
	 * @return void
	 */
	public function inactiveClaim() {
		$this->loadClaim();
		if ( !$this->claim ) {
			return;
		}

		$this->claim->setInactive();
		$this->claim->setTimestamp( time(), 'end' );
		$this->claim->save();
		$this->claim->getUser()->removeGroup( 'wiki_guardian' );

		$this->claim->sendNotification( 'inactive', $this->getUser() );

		$page = Title::newFromText( 'Special:WikiClaims' );
		$output->redirect( $page->getFullURL() );
	}

	public function deleteClaim(): void {
		$this->loadClaim();
		if ( !$this->claim ) {
			return;
		}

		$this->claim->setDeleted();
		$this->claim->setTimestamp( time(), 'end' );
		$this->claim->save();
		$this->claim->getUser()->removeGroup( 'wiki_guardian' );

		$this->claim->sendNotification( 'deleted', $this->getUser() );

		$page = Title::newFromText( 'Special:WikiClaims' );
		$this->getOutput()->redirect( $page->getFullURL() );
	}

	private function loadClaim(): void {
		$userId = $this->getRequest()->getInt( 'user_id' );
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromId( $userId );
		if ( !$user->getId() ) {
			$this->getOutput()->showErrorPage( 'wiki_claim_error', 'view_claim_bad_user_id' );
			return;
		}

		$this->claim = WikiClaim::newFromUser( $user );
	}

	protected function getGroupName() {
		return 'claimwiki';
	}
}
