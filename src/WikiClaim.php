<?php
/**
 * Curse Inc.
 * Claim Wiki
 *
 * @package   ClaimWiki
 * @author    Alex Smith
 * @copyright (c) 2013 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
 */

namespace ClaimWiki;

use GlobalVarConfig;
use InvalidArgumentException;
use MailAddress;
use MediaWiki\MediaWikiServices;
use MWException;
use RequestContext;
use Sanitizer;
use SpecialPage;
use Title;
use User;
use UserMailer;

class WikiClaim {
	/**
	 * New Claim
	 */
	public const CLAIM_CREATED = -1;

	/**
	 * New Claim
	 */
	public const CLAIM_NEW = 0;

	/**
	 * Pending Claim
	 */
	public const CLAIM_PENDING = 1;

	/**
	 * Approved Claim
	 */
	public const CLAIM_APPROVED = 2;

	/**
	 * Denied Claim
	 */
	public const CLAIM_DENIED = 3;

	/**
	 * Inactive Claim
	 */
	public const CLAIM_INACTIVE = 4;

	/**
	 * Deleted Claim
	 */
	public const CLAIM_DELETED = 5;

	/**
	 * Object Loaded?
	 *
	 * @var bool
	 */
	private $isLoaded = false;

	/**
	 * Claim Data
	 *
	 * @var array
	 */
	private $data = [
		'cid'				=> 0,
		'user_id'			=> 0,
		'claim_timestamp'	=> 0,
		'start_timestamp'	=> 0,
		'end_timestamp'		=> 0,
		'agreed'			=> 0,
		'status'			=> -1
	];

	/**
	 * Claim Question
	 *
	 * @var array
	 */
	private $questions = [];

	/**
	 * Claim Answers
	 *
	 * @var array
	 */
	private $answers = [];

	/**
	 * MediaWiki User object for this claim.
	 *
	 * @var object
	 */
	private $user = false;

	/**
	 * Main Configuration
	 *
	 * @var GlobalVarConfig
	 */
	private $config;

	/** @var string */
	private $newFrom;

	/** @var array */
	private $settings;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$services = MediaWikiServices::getInstance();
		$this->config = $services->getMainConfig();
		$this->lb = $services->getDBLoadBalancer();
		$this->settings['number_of_questions'] = $this->config->get( 'ClaimWikiNumberOfQuestions' );
	}

	/**
	 * Create a new object from an User object.
	 *
	 * @param mixed $user User or UserRightProxy
	 * @param bool $allowDeleted
	 *
	 * @return mixed WikiClaim or false on InvalidArgumentException.
	 */
	public static function newFromUser( User $user, $allowDeleted = false ) {
		$claim = new self;

		if ( !$user->getId() ) {
			return false;
		}

		$claim->setUser( $user );

		$claim->newFrom = 'user';

		$claim->load( null, $allowDeleted );

		return $claim;
	}

	/**
	 * Load a new object from a database row.
	 *
	 * @param array $row Database Row
	 * @param bool $allowDeleted
	 *
	 * @return mixed WikiClaim or false on error.
	 */
	public static function newFromRow( $row, $allowDeleted = false ) {
		$claim = new self;

		$claim->newFrom = 'row';

		$claim->load( $row, $allowDeleted );

		if ( !$claim->getId() ) {
			return false;
		}

		return $claim;
	}

	/**
	 * Load a new object from a claim id.
	 * @return WikiClaim|false WikiClaim or false on error.
	 */
	public static function newFromID( int $cid, ?bool $allowDeleted = false ) {
		$claim = new self;

		$claim->newFrom = 'id';

		$claim->setId( $cid );

		$claim->load( null, $allowDeleted );

		if ( !$claim->isLoaded() ) {
			return false;
		}

		return $claim;
	}

	/**
	 * Get count of wiki claims.
	 *
	 * @return int Count of WikiClaim objects
	 */
	public static function getClaimsCount(): int {
		$db = wfGetDB( DB_REPLICA );

		$result = $db->selectField(
			'wiki_claims',
			'COUNT(*)',
			[ 'status != 5' ],
			__METHOD__
		);

		return (int)$result;
	}

	/**
	 * Get all wiki claims.
	 *
	 * @param int $start [Optional] Database start position.
	 * @param int $maxClaims [Optional] Maximum claims to retrieve.
	 * @param string $sortKey [Optional] Database field to sort by.
	 * @param string $sortDir [Optional] Sort direction.
	 *
	 * @return array WikiClaim objects of [Claim ID => Object].
	 */
	public static function getClaims( $start = 0, $maxClaims = 25, $sortKey = 'claim_timestamp', $sortDir = 'asc' ) {
		$db = wfGetDB( DB_REPLICA );

		$sortKeys = [ 'claim_timestamp', 'start_timestamp', 'end_timestamp' ];
		if ( !in_array( $sortKey, $sortKeys ) ) {
			$sortKey = 'claim_timestamp';
		}

		$result = $db->select(
			[
				'wiki_claims'
			],
			[
				'wiki_claims.*'
			],
			[ 'status != 5' ],
			__METHOD__,
			[
				'ORDER BY' => 'wiki_claims.' . $sortKey . ' ' . ( $sortDir == 'desc' ? 'DESC' : 'ASC' ),
				'OFFSET'	=> $start,
				'LIMIT'		=> $maxClaims
			]
		);

		$claims = [];
		while ( $row = $result->fetchRow() ) {
			$claim = self::newFromRow( $row );
			if ( $claim !== false ) {
				$claims[$claim->getId()] = $claim;
			}
		}

		return $claims;
	}

	/**
	 * Load the object.
	 *
	 * @param array|null $row Raw database row.
	 * @param bool $allowDeleted
	 *
	 * @return bool Success
	 */
	private function load( $row = null, $allowDeleted = false ) {
		$db = wfGetDB( DB_REPLICA );
		if ( !$this->isLoaded ) {
			if ( $this->newFrom != 'row' ) {
				switch ( $this->newFrom ) {
					case 'id':
						$where = [
							'cid' => $this->getId()
						];
						break;
					case 'user':
						$where = [
							'user_id' => $this->getUser()->getId()
						];
						break;
				}

				// Allow deleted claims to be loaded.
				if ( !$allowDeleted ) {
					$where[] = 'status != 5';
				}

				$result = $db->select(
					[ 'wiki_claims' ],
					[ 'wiki_claims.*' ],
					$where,
					__METHOD__,
					[
						'ORDER BY' => 'wiki_claims.claim_timestamp DESC'
					]
				);
				$row = $result->fetchRow();
			}
			if ( $row && $row['cid'] > 0 && $row['user_id'] > 0 ) {
				// Load existing data.
				$this->data = array_intersect_key( $row, $this->data );
				$this->data['status'] = intval( $this->data['status'] );

				$this->user = User::newFromId( $row['user_id'] );

				// Load existing answers.
				$result = $db->select(
					'wiki_claims_answers',
					[ '*' ],
					[
						'claim_id' => intval( $row['cid'] )
					],
					__METHOD__
				);

				while ( $row = $result->fetchRow() ) {
					$this->setAnswer( $row['question_key'], $row['answer'] );
				}

				$this->isLoaded = true;

				return true;
			}
		}

		return false;
	}

	/**
	 * Save data to the database.
	 *
	 * @return bool Successful Save.
	 */
	public function save() {
		$db = wfGetDB( DB_PRIMARY );

		if ( !$this->data['user_id'] ) {
			throw new MWException( __METHOD__ . ': Attempted to save a wiki claim without a valid user ID.' );
		}

		// Do a transactional save.
		$db->startAtomic( __METHOD__ );
		if ( $this->data['cid'] > 0 ) {
			$_data = $this->data;
			unset( $_data['cid'] );
			// Do an update
			$success = $db->update(
				'wiki_claims',
				$_data,
				[ 'cid' => $this->data['cid'] ],
				__METHOD__
			);
			unset( $_data );
		} else {
			// Do an insert
			$success = $db->insert(
				'wiki_claims',
				$this->data,
				__METHOD__
			);
		}

		// Roll back if there was an error.
		if ( !$success ) {
			$db->cancelAtomic( __METHOD__ );
			return false;
		} else {
			if ( !isset( $this->data['cid'] ) || !$this->data['cid'] ) {
				$this->data['cid'] = $db->insertId();
			}

			$db->endAtomic( __METHOD__ );

			$logEntry = new ClaimLogEntry( $this->lb );
			$logEntry->setClaim( $this );
			$logEntry->setActor( RequestContext::getMain()->getUser() );
			$logEntry->insert();
		}

		$db->delete(
			'wiki_claims_answers',
			[ 'claim_id' => $this->data['cid'] ],
			__METHOD__
		);

		foreach ( $this->answers as $key => $answer ) {
			$answerData = [
				'claim_id'		=> $this->data['cid'],
				'question_key'	=> $key,
				'answer'		=> $answer
			];
			$db->insert(
				'wiki_claims_answers',
				$answerData,
				__METHOD__
			);
		}
		return true;
	}

	/**
	 * Returns the claim identification number from the database.
	 *
	 * @return string Claim ID
	 */
	public function getId() {
		return $this->data['cid'];
	}

	/**
	 * Returns the claim identification number from the database.
	 *
	 * @param int $cid
	 *
	 * @return string void
	 */
	public function setId( $cid ) {
		$this->data['cid'] = $cid;
	}

	/**
	 * Set the User object.
	 *
	 * @param object $user MediaWiki User Object
	 *
	 * @throws object InvalidArgumentException
	 *
	 * @return void
	 */
	public function setUser( User $user ) {
		if ( !$user->getId() ) {
			throw new InvalidArgumentException( __METHOD__ . ': Invalid user given.' );
		}
		$this->user = $user;
		$this->data['user_id'] = $user->getId();
	}

	/**
	 * Returns the User object.
	 *
	 * @return object MediaWiki User Object
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Returns the loaded questions with filled in information.
	 *
	 * @return array Multidimensional array of questions with question text and possible previously entered answer.
	 */
	public function getQuestions() {
		$keys = $this->getQuestionKeys();
		foreach ( $keys as $key ) {
			$this->questions[$key] = [
				'text'		=> wfMessage( $key ),
				'answer'	=> $this->answers[$key] ?? null
			];
		}
		return $this->questions;
	}

	/**
	 * Returns the question keys.
	 *
	 * @return array Question Keys
	 */
	public function getQuestionKeys() {
		for ( $i = 0; $i < $this->settings['number_of_questions']; $i++ ) {
			$keys[] = 'wiki_claim_question_' . $i;
		}
		return $keys;
	}

	/**
	 * Returns the agreement text for the wiki claim terms.
	 *
	 * @return string Terms and Agreement Text.
	 */
	public function getAgreementText() {
		return wfMessage( 'wiki_claim_agreement' )->plain();
	}

	/**
	 * Returns the guidelines text for the wiki claim terms.
	 *
	 * @return string Guidelines Text.
	 */
	public function getGuidelinesText() {
		return wfMessage( 'wiki_claim_more_info' )->parseAsBlock();
	}

	/**
	 * Return the status code for this claim.
	 *
	 * @return int Status Code
	 */
	public function getStatus() {
		return intval( $this->data['status'] );
	}

	/**
	 * Are the terms accepted by this user?
	 *
	 * @return bool Agreed to the terms?
	 */
	public function isAgreed() {
		return boolval( $this->data['agreed'] );
	}

	/**
	 * Set that the wiki claim terms have been agreed to.
	 *
	 * @param bool $agreed [Optional] Agreed to the terms or not.  Defaults to true.
	 *
	 * @return void
	 */
	public function setAgreed( $agreed = true ) {
		$this->data['agreed'] = ( $agreed ? 1 : 0 );
	}

	/**
	 * Set the new status on this claim.
	 *
	 * @return void
	 */
	public function setNew() {
		$this->data['status'] = self::CLAIM_NEW;
	}

	/**
	 * Is this claim new?
	 *
	 * @return bool
	 */
	public function isNew() {
		return $this->data['status'] === self::CLAIM_NEW;
	}

	/**
	 * Set the pending status on this claim.
	 *
	 * @return void
	 */
	public function setPending() {
		$this->data['status'] = self::CLAIM_PENDING;
	}

	/**
	 * Is this claim pending?
	 *
	 * @return bool
	 */
	public function isPending() {
		return $this->data['status'] === self::CLAIM_PENDING;
	}

	/**
	 * Set the approved status on this claim.
	 *
	 * @return void
	 */
	public function setApproved() {
		$this->data['status'] = self::CLAIM_APPROVED;
	}

	/**
	 * Is this claim approved?
	 *
	 * @return bool
	 */
	public function isApproved() {
		return $this->data['status'] === self::CLAIM_APPROVED;
	}

	/**
	 * Set the denied status on this claim.
	 *
	 * @return void
	 */
	public function setDenied() {
		$this->data['status'] = self::CLAIM_DENIED;
	}

	/**
	 * Is this claim denied?
	 *
	 * @return bool
	 */
	public function isDenied() {
		return $this->data['status'] === self::CLAIM_DENIED;
	}

	/**
	 * Set the inactive status on this claim.
	 *
	 * @return void
	 */
	public function setInactive() {
		$this->data['status'] = self::CLAIM_INACTIVE;
	}

	/**
	 * Is this claim inactive?
	 *
	 * @return bool
	 */
	public function isInactive() {
		return $this->data['status'] === self::CLAIM_INACTIVE;
	}

	/**
	 * Set the deleted status on this claim.
	 *
	 * @return void
	 */
	public function setDeleted() {
		$this->data['status'] = self::CLAIM_DELETED;
	}

	/**
	 * Is this claim deleted?
	 *
	 * @return bool
	 */
	public function isDeleted() {
		return $this->data['status'] === self::CLAIM_DELETED;
	}

	/**
	 * Determine if the claim is loaded from DB
	 *
	 * @return bool
	 */
	public function isLoaded() {
		return $this->isLoaded;
	}

	/**
	 * Return the language key for the current status
	 *
	 * @return string
	 */
	public function getStatusKey() {
		switch ( $this->data['status'] ) {
			case self::CLAIM_PENDING:
				return 'claim_legend_pending';
			case self::CLAIM_APPROVED:
				return 'claim_legend_approved';
			case self::CLAIM_DENIED:
				return 'claim_legend_denied';
			case self::CLAIM_INACTIVE:
				return 'claim_legend_inactive';
			default:
				return 'claim_legend_created';
		}
	}

	/**
	 * Sets an answer for the provided question key.
	 *
	 * @param string $key Question Key
	 * @param mixed $answer Question Answer
	 *
	 * @return void
	 */
	public function setAnswer( $key, $answer ) {
		if ( is_bool( $answer ) ) {
			$answer = intval( $answer );
		}
		$this->answers[$key] = $answer;
		ksort( $this->answers );
	}

	/**
	 * Returns the answer array.
	 *
	 * @return array Array of $questionKey => $answer;
	 */
	public function getAnswers() {
		return $this->answers;
	}

	/**
	 * Set a timestamp for this claim.
	 *
	 * @param int $timestamp Epoch based timestamp.
	 * @param string $type [Optional] Which timestamp to set.  Valid values: claim, start, and end.
	 *
	 * @return void
	 */
	public function setTimestamp( $timestamp, $type = 'claim' ) {
		switch ( $type ) {
			default:
			case 'claim':
				$this->data['claim_timestamp'] = intval( $timestamp );
				break;
			case 'start':
				$this->data['start_timestamp'] = intval( $timestamp );
				break;
			case 'end':
				$this->data['end_timestamp'] = intval( $timestamp );
				break;
		}
	}

	/**
	 * Return a timestamp for this claim.
	 *
	 * @param string $type [Optional] Which timestamp to get.  Valid values: claim, start, and end.
	 *
	 * @return int Epoch based timestamp
	 */
	public function getTimestamp( $type = 'claim' ) {
		switch ( $type ) {
			default:
			case 'claim':
				return intval( $this->data['claim_timestamp'] );
			case 'start':
				return intval( $this->data['start_timestamp'] );
			case 'end':
				return intval( $this->data['end_timestamp'] );
		}
	}

	/**
	 * Get the formatted Date of a claim
	 *
	 * @return string
	 */
	public function getClaimDate() {
		return date( 'c', $this->getTimestamp( 'claim' ) );
	}

	/**
	 * Figures out what answers are not answers and return a list of errors.
	 *
	 * @return array An array of errors of $questionKey => $message.  The array will be empty for no errors.
	 */
	public function getErrors() {
		$keys = $this->getQuestionKeys();
		$errors = [];
		foreach ( $keys as $key ) {
			if ( empty( $this->answers[$key] ) ) {
				$errors[$key] = wfMessage( $key . '_error' )->escaped();
			}
		}
		return $errors;
	}

	/**
	 * Send Email Notification
	 *
	 * @param string $status
	 * @param User $performer
	 *
	 * @return void
	 */
	public function sendNotification( $status, $performer ) {
		$wgEmergencyContact = $this->config->get( 'EmergencyContact' );

		$from = new MailAddress( $wgEmergencyContact );
		$claimWikiEmail = new MailAddress( $this->config->get( 'ClaimWikiEmailTo' ) );

		// Handle the Wiki Manager notification
		$claimUrl = SpecialPage::getTitleFor( 'WikiClaims' )->getFullURL(
			[
				'do' => 'view', 'claim_id' => $this->getId()
			]
		);

		$fromUserTitle = Title::makeTitle( NS_USER, $this->getUser()->getName() );
		$performerUserTitle = Title::makeTitle( NS_USER, $performer );

		$emailBody = wfMessage(
			'long-header-user-moderation-wiki-claim-' . $status,
			$this->getUser()->getName(),
			$this->config->get( 'Sitename' ),
			$performer->getName(),
			$claimUrl,
			$fromUserTitle->getFullURL(),
			$performerUserTitle->getFullURL()
		)->parse();

		$email = new UserMailer();
		$email->send(
			[ $claimWikiEmail ],
			$from,
			wfMessage( 'claim_wiki_email_subject', $performer->getName() )->parse(),
			[
				'text' => $emailBody,
				'html' => $emailBody
			]
		);

		// no message is sent to the user for created or deleted actions
		if ( $status == 'created' || $status == 'deleted' ) {
			return;
		}

		// Handle user notification
		$claimUrl = SpecialPage::getTitleFor( 'ClaimWiki' )->getFullURL();
		$emailBody = wfMessage(
			'long-header-user-account-wiki-claim-' . $status,
			$this->getUser()->getName(),
			$this->config->get( 'Sitename' ),
			$performer->getName(),
			$claimUrl,
			$fromUserTitle->getFullURL(),
			$performerUserTitle->getFullURL()
		)->parse();

		$ownerEmail = $this->getUser()->getEmail();
		if ( Sanitizer::validateEmail( $ownerEmail ) ) {
			$address[] = new MailAddress( $ownerEmail, $this->getUser()->getName() );

			$email = new UserMailer();
			$email->send(
				$address,
				$claimWikiEmail,
				wfMessage( 'claim_status_email_subject', $status )->parse(),
				[
					'text' => $emailBody,
					'html' => $emailBody
				]
			);
		}
	}
}
