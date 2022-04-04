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
 */

namespace ClaimWiki\Specials;

use ClaimWiki\WikiClaim;
use Config;
use GlobalVarConfig;
use HydraCore\SpecialPage;
use MediaWiki\User\UserGroupManager;
use Title;
use Twiggy\TwiggyService;
use Wikimedia\Rdbms\ILoadBalancer;

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
	 * @var WikiClaim|false|mixed
	 */
	private $claim;

	/** @var ILoadBalancer */
	private $lb;

	/** @var UserGroupManager */
	private $groupManager;

	/**
	 * Main Constructor
	 *
	 * @return void
	 */
	public function __construct(
		ILoadBalancer $lb,
		Config $config,
		UserGroupManager $groupManager,
		TwiggyService $twiggy
	) {
		parent::__construct( 'ClaimWiki', 'claim_wiki', false );
		$this->lb = $lb;
		$this->config = $config;
		$this->twiggy = $twiggy;
		$this->groupManager = $groupManager;
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

		$this->getOutput()->addModuleStyles( [ 'ext.claimWiki.styles' ] );
		$this->getOutput()->addModules( [ 'ext.claimWiki.scripts' ] );

		$this->setHeaders();

		$errors = $this->checkForClaimErrors();
		if ( !empty( $errors ) ) {
			$this->getOutput()->showErrorPage( ...$errors );
			return;
		}

		$this->claim = WikiClaim::newFromUser( $this->getUser() );
		$this->render();
	}

	/**
	 * Render the page output
	 *
	 * @return mixed
	 */
	private function render(): void {
		$this->getOutput()->setPageTitle( wfMessage( 'claim_this_wiki' ) );
		$errors = [];

		// Display claim status
		if ( $this->claim->getStatus() >= 0 ) {
			$wgSiteName = $this->config->get( 'Sitename' );
			$mainPage = Title::newMainPage();
			$mainPageURL = $mainPage->getFullURL();
			$template = $this->twiggy->load( '@ClaimWiki/claim_status.twig' );
			$this->getOutput()->addHTML(
				$template->render( [
					'claim' => $this->claim,
					'errors' => $errors,
					'wgSiteName' => $wgSiteName,
					'mainPageURL' => $mainPageURL,
				] )
			);
			return;
		}

		// Saving claim
		if ( $this->getRequest()->wasPosted() && $this->getRequest()->getVal( 'do' ) === 'save' ) {
			$errors = $this->validateRequest( $errors );
			// if no errors save claim and redirect to success
			if ( !$errors ) {
				$this->claimSave();
				$page = Title::newFromText( 'Special:ClaimWiki' );
				$this->getOutput()->redirect( $page->getFullURL() . "?success=true" );
				return;
			}
		}

		// Show form
		$template = $this->twiggy->load( '@ClaimWiki/claim_form.twig' );
		$this->getOutput()->addHTML( $template->render( [ 'claim' => $this->claim, 'errors' => $errors ] ) );
	}

	/**
	 * Save submitted Claim Wiki Form
	 *
	 * @return void
	 */
	private function claimSave() {
		$this->claim->setNew();
		$success = $this->claim->save();

		if ( $success ) {
			$this->claim->sendNotification( 'created', $this->getUser() );
		}
	}

	/**
	 * Check request for errors
	 *
	 * @param array $errors
	 *
	 * @return array
	 */
	private function validateRequest( $errors ) {
		$request = $this->getRequest();
		$questionKeys = $this->claim->getQuestionKeys();
		// check for agreement
		$this->claim->setTimestamp( time() );

		if ( $request->getVal( 'agreement' ) == 'agreed' ) {
			$this->claim->setAgreed();
		} else {
			$errors['agreement'] = wfMessage( 'claim_agree_error' )->escaped();
		}
		// check that all required questions have answers
		array_walk( $questionKeys, function ( $key ) use ( $request ) {
			$this->claim->setAnswer( $key, trim( $request->getVal( $key ) ) );
		} );
		return array_merge( $errors, $this->claim->getErrors() );
	}

	/**
	 * Check for common claim errors
	 * @return string[]
	 */
	private function checkForClaimErrors(): array {
		$wgClaimWikiEnabled = $this->config->get( 'ClaimWikiEnabled' );
		$wgClaimWikiGuardianTotal = $this->config->get( 'ClaimWikiGuardianTotal' );
		$wgClaimWikiEditThreshold = $this->config->get( 'ClaimWikiEditThreshold' );

		if ( !$wgClaimWikiEnabled ) {
			return [ 'wiki_claim_error', 'wiki_claim_disabled' ];
		}

		$userGroups = $this->groupManager->getUserGroups( $this->getUser() );
		if ( in_array( 'wiki_guardian', $userGroups ) ) {
			return [ 'wiki_claim_error', 'wiki_claim_already_guardian' ];
		}

		$db = $this->lb->getConnectionRef( DB_REPLICA );
		$result = $db->select(
			'wiki_claims',
			[ 'COUNT(*) as total' ],
			[
				'status' => WikiClaim::CLAIM_APPROVED,
				'end_timestamp' => 0,
			],
			__METHOD__
		);
		$total = $result->fetchRow();

		if ( $total['total'] >= $wgClaimWikiGuardianTotal ) {
			return [ 'wiki_claim_error', 'wiki_claim_maximum_guardians' ];
		}

		if ( $this->getUser()->getEditCount() < $wgClaimWikiEditThreshold ) {
			return [ 'wiki_claim_error', 'wiki_claim_below_threshhold_contributions' ];
		}

		$block = $this->getUser()->getBlock();
		if ( $block && $block->isSitewide() ) {
			return [ 'wiki_claim_error', 'wiki_claim_user_blocked' ];
		}
		return [];
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
