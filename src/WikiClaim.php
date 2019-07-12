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
 **/

namespace ClaimWiki;

use ConfigFactory;
use InvalidArgumentException;
use MWException;
use User;

class WikiClaim {
	/**
	 * New Claim
	 *
	 * @var constant
	 */
	const CLAIM_NEW = 0;

	/**
	 * Pending Claim
	 *
	 * @var constant
	 */
	const CLAIM_PENDING = 1;

	/**
	 * Approved Claim
	 *
	 * @var constant
	 */
	const CLAIM_APPROVED = 2;

	/**
	 * Denied Claim
	 *
	 * @var constant
	 */
	const CLAIM_DENIED = 3;

	/**
	 * Inactive Claim
	 *
	 * @var constant
	 */
	const CLAIM_INACTIVE = 4;

	/**
	 * Object Loaded?
	 *
	 * @var boolean
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
		'status'			=> 0
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
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$config = ConfigFactory::getDefaultInstance()->makeConfig('main');

		$this->settings['number_of_questions'] = $config->get('ClaimWikiNumberOfQuestions');
	}

	/**
	 * Create a new object from an User object.
	 *
	 * @param mixed $user User or UserRightProxy
	 *
	 * @return mixed WikiClaim or false on InvalidArgumentException.
	 */
	public static function newFromUser(User $user) {
		$claim = new self;

		if (!$user->getId()) {
			return false;
		}

		$claim->setUser($user);

		$claim->newFrom = 'user';

		$claim->load();

		return $claim;
	}

	/**
	 * Load a new object from a database row.
	 *
	 * @param array $row Database Row
	 *
	 * @return mixed WikiClaim or false on error.
	 */
	public static function newFromRow($row) {
		$claim = new self;

		$claim->newFrom = 'row';

		$claim->load($row);

		if (!$claim->getId()) {
			return false;
		}

		return $claim;
	}

	/**
	 * Get count of wiki claims.
	 *
	 * @return integer	Count of WikiClaim objects
	 */
	public static function getClaimsCount() {
		$db = wfGetDB(DB_MASTER);

		$result = $db->selectField(
			'wiki_claims',
			'COUNT(*)',
			[
			],
			__METHOD__
		);

		return (int)$result;
	}

	/**
	 * Get all wiki claims.
	 *
	 * @param integer $start     [Optional] Database start position.
	 * @param integer $maxClaims [Optional] Maximum claims to retrieve.
	 * @param string  $sortKey   [Optional] Database field to sort by.
	 * @param string  $sortDir   [Optional] Sort direction.
	 *
	 * @return array	WikiClaim objects of [Claim ID => Object].
	 */
	public static function getClaims($start = 0, $maxClaims = 25, $sortKey = 'claim_timestamp', $sortDir = 'asc') {
		$db = wfGetDB(DB_MASTER);

		$sortKeys = ['claim_timestamp', 'start_timestamp', 'end_timestamp'];
		if (!in_array($sortKey, $sortKeys)) {
			$sortKey = 'claim_timestamp';
		}

		$result = $db->select(
			[
				'wiki_claims'
			],
			[
				'wiki_claims.*'
			],
			[],
			__METHOD__,
			[
				'ORDER BY' => 'wiki_claims.' . $sortKey . ' ' . ($sortDir == 'desc' ? 'DESC' : 'ASC'),
				'OFFSET'	=> $start,
				'LIMIT'		=> $maxClaims
			]
		);

		$claims = [];
		while ($row = $result->fetchRow()) {
			$claim = self::newFromRow($row);
			if ($claim !== false) {
				$claims[$claim->getId()] = $claim;
			}
		}

		return $claims;
	}

	/**
	 * Load the object.
	 *
	 * @param array $row Raw database row.
	 *
	 * @return boolean	Success
	 */
	private function load($row = null) {
		$db = wfGetDB(DB_MASTER);

		if (!$this->isLoaded) {
			if ($this->newFrom != 'row') {
				switch ($this->newFrom) {
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

				$result = $db->select(
					['wiki_claims'],
					['wiki_claims.*'],
					$where,
					__METHOD__
				);
				$row = $result->fetchRow();
			}

			if ($row['cid'] > 0 && $row['user_id'] > 0) {
				// Load existing data.
				$this->data = $row;
				$this->data['status'] = intval($this->data['status']);

				$this->user = User::newFromId($row['user_id']);

				// Load existing answers.
				$result = $db->select(
					'wiki_claims_answers',
					['*'],
					[
						'claim_id' => intval($row['cid'])
					],
					__METHOD__
				);

				while ($row = $result->fetchRow()) {
					$this->setAnswer($row['question_key'], $row['answer']);
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
	 * @return boolean	Successful Save.
	 */
	public function save() {
		$db = wfGetDB(DB_MASTER);

		if (!$this->data['user_id']) {
			throw new MWException(__METHOD__ . ': Attempted to save a wiki claim without a valid user ID.');
		}

		// Do a transactional save.
		$db->startAtomic(__METHOD__);
		if ($this->data['cid'] > 0) {
			$_data = $this->data;
			unset($_data['cid']);
			// Do an update
			$success = $db->update(
				'wiki_claims',
				$_data,
				['cid' => $this->data['cid']],
				__METHOD__
			);
			unset($_data);
		} else {
			// Do an insert
			$success = $db->insert(
				'wiki_claims',
				$this->data,
				__METHOD__
			);
		}

		// Roll back if there was an error.
		if (!$success) {
			$db->cancelAtomic(__METHOD__);
			return false;
		} else {
			if (!isset($this->data['cid']) || !$this->data['cid']) {
				$this->data['cid'] = $db->insertId();
			}

			$db->endAtomic(__METHOD__);

			global $wgUser;
			$logEntry = new ClaimLogEntry();
			$logEntry->setClaim($this);
			$logEntry->setActor($wgUser);
			$logEntry->insert();
		}

		$db->delete(
			'wiki_claims_answers',
			['claim_id' => $this->data['cid']],
			__METHOD__
		);

		foreach ($this->answers as $key => $answer) {
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
	 * Deletes from the database and clears the object.
	 *
	 * @return boolean	Successful Deletion.
	 */
	public function delete() {
		$db = wfGetDB(DB_MASTER);

		$success = false;

		// Do a transactional save.
		$db->startAtomic(__METHOD__);

		if ($this->data['cid'] > 0) {
			// Do an update
			$success = $db->delete(
				'wiki_claims',
				['cid' => $this->data['cid']],
				__METHOD__
			);
		}

		// Roll back if there was an error.
		if (!$success) {
			$db->cancelAtomic(__METHOD__);
		} else {
			$success = true;

			$db->delete(
				'wiki_claims_answers',
				['claim_id' => $this->data['cid']],
				__METHOD__
			);

			$this->data = [];
			$this->answers = [];
			$db->endAtomic(__METHOD__);
		}

		return $success;
	}

	/**
	 * Returns the claim identification number from the database.
	 *
	 * @return string	Claim ID
	 */
	public function getId() {
		return $this->data['cid'];
	}

	/**
	 * Set the User object.
	 *
	 * @param object $user MediaWiki User Object
	 *
	 * @throws object	InvalidArgumentException
	 *
	 * @return void
	 */
	public function setUser(User $user) {
		if (!$user->getId()) {
			throw new InvalidArgumentException(__METHOD__ . ': Invalid user given.');
		}
		$this->user = $user;
		$this->data['user_id'] = $user->getId();
	}

	/**
	 * Returns the User object.
	 *
	 * @return object	MediaWiki User Object
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Returns the loaded questions with filled in information.
	 *
	 * @return array	Multidimensional array of questions with question text and possible previously entered answer.
	 */
	public function getQuestions() {
		$keys = $this->getQuestionKeys();
		foreach ($keys as $key) {
			$this->questions[$key] = [
				'text'		=> wfMessage($key),
				'answer'	=> isset($this->answers[$key]) ? $this->answers[$key] : null
			];
		}
		return $this->questions;
	}

	/**
	 * Returns the question keys.
	 *
	 * @return array	Question Keys
	 */
	public function getQuestionKeys() {
		for ($i = 0; $i < $this->settings['number_of_questions']; $i++) {
			$keys[] = 'wiki_claim_question_' . $i;
		}
		return $keys;
	}

	/**
	 * Returns the agreement text for the wiki claim terms.
	 *
	 * @return string	Terms and Agreement Text.
	 */
	public function getAgreementText() {
		return wfMessage('wiki_claim_agreement')->plain();
	}

	/**
	 * Returns the guidelines text for the wiki claim terms.
	 *
	 * @return string	Guidelines Text.
	 */
	public function getGuidelinesText() {
		return wfMessage('wiki_claim_more_info')->parseAsBlock();
	}

	/**
	 * Return the status code for this claim.
	 *
	 * @return integer	Status Code
	 */
	public function getStatus() {
		return intval($this->data['status']);
	}

	/**
	 * Are the terms accepted by this user?
	 *
	 * @return boolean	Agreed to the terms?
	 */
	public function isAgreed() {
		return boolval($this->data['agreed']);
	}

	/**
	 * Set that the wiki claim terms have been agreed to.
	 *
	 * @param boolean $agreed [Optional] Agreed to the terms or not.  Defaults to true.
	 *
	 * @return void
	 */
	public function setAgreed($agreed = true) {
		$this->data['agreed'] = ($agreed ? 1 : 0);
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
	 * @return boolean
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
	 * @return boolean
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
	 * @return boolean
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
	 * @return boolean
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
	 * @return boolean
	 */
	public function isInactive() {
		return $this->data['status'] === self::CLAIM_INACTIVE;
	}

	/**
	 * Sets an answer for the provided question key.
	 *
	 * @param string $key    Question Key
	 * @param mixed  $answer Question Answer
	 *
	 * @return void
	 */
	public function setAnswer($key, $answer) {
		if (is_bool($answer)) {
			$answer = intval($answer);
		}
		$this->answers[$key] = $answer;
		ksort($this->answers);
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
	 * @param integer $timestamp Epoch based timestamp.
	 * @param string  $type      [Optional] Which timestamp to set.  Valid values: claim, start, and end.
	 *
	 * @return void
	 */
	public function setTimestamp($timestamp, $type = 'claim') {
		switch ($type) {
			default:
			case 'claim':
				$this->data['claim_timestamp'] = intval($timestamp);
				break;
			case 'start':
				$this->data['start_timestamp'] = intval($timestamp);
				break;
			case 'end':
				$this->data['end_timestamp'] = intval($timestamp);
				break;
		}
	}

	/**
	 * Return a timestamp for this claim.
	 *
	 * @param string $type [Optional] Which timestamp to get.  Valid values: claim, start, and end.
	 *
	 * @return integer Epoch based timestamp
	 */
	public function getTimestamp($type = 'claim') {
		switch ($type) {
			default:
			case 'claim':
				return intval($this->data['claim_timestamp']);
			case 'start':
				return intval($this->data['start_timestamp']);
			case 'end':
				return intval($this->data['end_timestamp']);
		}
	}

	/**
	 * Figures out what answers are not answers and return a list of errors.
	 *
	 * @return array	An array of errors of $questionKey => $message.  The array will be empty for no errors.
	 */
	public function getErrors() {
		$keys = $this->getQuestionKeys();
		$errors = [];
		foreach ($keys as $key) {
			if (empty($this->answers[$key])) {
				$errors[$key] = wfMessage($key . '_error');
			}
		}
		return $errors;
	}
}
