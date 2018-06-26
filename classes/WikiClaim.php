<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Wiki Claim Class
 *
 * @author		Alex Smith
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

class WikiClaim {
	/**
	 * New Claim
	 *
	 * @var		constant
	 */
	const CLAIM_NEW = 0;

	/**
	 * Pending Claim
	 *
	 * @var		constant
	 */
	const CLAIM_PENDING = 1;

	/**
	 * Approved Claim
	 *
	 * @var		constant
	 */
	const CLAIM_APPROVED = 2;

	/**
	 * Denied Claim
	 *
	 * @var		constant
	 */
	const CLAIM_DENIED = 3;

	/**
	 * Inactive Claim
	 *
	 * @var		constant
	 */
	const CLAIM_INACTIVE = 4;

	/**
	 * Object Loaded?
	 *
	 * @var		boolean
	 */
	private $isLoaded = false;

	/**
	 * Claim Data
	 *
	 * @var		array
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
	 * @var		array
	 */
	private $questions = [];

	/**
	 * Claim Answers
	 *
	 * @var		array
	 */
	private $answers = [];

	/**
	 * Mediawiki User object for this claim.
	 *
	 * @var		object
	 */
	private $user = false;

	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	mixed	User or UserRightProxy
	 * @return	void
	 */
	public function __construct() {
		$config = \ConfigFactory::getDefaultInstance()->makeConfig('main');

		$this->settings['number_of_questions'] = $config->get('ClaimWikiNumberOfQuestions');
	}

	/**
	 * Create a new object from an User object.
	 *
	 * @access	public
	 * @param	mixed	User or UserRightProxy
	 * @return	mixed	WikiClaim or false on InvalidArgumentException.
	 */
	static public function newFromUser(User $user) {
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
	 * @access	public
	 * @param	array	Database Row
	 * @return	mixed	WikiClaim or false on error.
	 */
	static public function newFromRow($row) {
		$claim = new self;

		$claim->newFrom = 'row';

		$claim->load($row);

		if (!$claim->getId()) {
			return false;
		}

		return $claim;
	}

	/**
	 * Get all wiki claims.
	 *
	 * @access	public
	 * @param	integer	[Optional] Database start position.
	 * @param	integer	[Optional] Maximum claims to retrieve.
	 * @param	string	[Optional] Database field to sort by.
	 * @param	string	[Optional] Sort direction.
	 * @return	array	WikiClaim objects of [Claim ID => Object].
	 */
	static public function getClaims($start = 0, $maxClaims = 25, $sortKey = 'claim_timestamp', $sortDir = 'asc') {
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
				'ORDER BY' => 'wiki_claims.'.$sortKey.' '.($sortDir == 'desc' ? 'DESC' : 'ASC'),
				'OFFSET'	=> $start,
				'LIMIT'		=> $maxClaims
			]
		);

		$claims = [];
		while ($row = $result->fetchRow()) {
			$claim = WikiClaim::newFromRow($row);
			if ($claim !== false) {
				$claims[$claim->getId()] = $claim;
			}
		}

		return $claims;
	}

	/**
	 * Load the object.
	 *
	 * @access	private
	 * @param	array	Raw database row.
	 * @return	boolean	Success
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
				//Load existing data.
				$this->data = $row;
				$this->data['status'] = intval($this->data['status']);

				$this->user = User::newFromId($row['user_id']);

				//Load existing answers.
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
	 * @access	public
	 * @return	boolean	Successful Save.
	 */
	public function save() {
		$db = wfGetDB(DB_MASTER);

		if (!$this->data['user_id']) {
			throw new MWException(__METHOD__.': Attempted to save a wiki claim without a valid user ID.');
		}

		//Do a transactional save.
		$dbPending = $db->writesOrCallbacksPending();
		if (!$dbPending) {
			$db->begin();
		}
		if ($this->data['cid'] > 0) {
			$_data = $this->data;
			unset($_data['cid']);
			//Do an update
			$success = $db->update(
				'wiki_claims',
				$_data,
				['cid' => $this->data['cid']],
				__METHOD__
			);
			unset($_data);
		} else {
			//Do an insert
			$success = $db->insert(
				'wiki_claims',
				$this->data,
				__METHOD__
			);
		}

		//Roll back if there was an error.
		if (!$success) {
			if (!$dbPending) {
				$db->rollback();
			}

			return false;
		} else {
			if (!isset($this->data['cid']) || !$this->data['cid']) {
				$this->data['cid'] = $db->insertId();
			}
			if (!$dbPending) {
				$db->commit();
			}
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
	 * @access	public
	 * @return	boolean	Successful Deletion.
	 */
	public function delete() {
		$db = wfGetDB(DB_MASTER);

		//Do a transactional save.
		$dbPending = $db->writesOrCallbacksPending();
		if (!$dbPending) {
			$db->begin();
		}
		if ($this->data['cid'] > 0) {
			//Do an update
			$success = $db->delete(
				'wiki_claims',
				['cid' => $this->data['cid']],
				__METHOD__
			);
		}

		//Roll back if there was an error.
		if (!$success) {
			if (!$dbPending) {
				$db->rollback();
			}
			return false;
		} else {
			if (!$dbPending) {
				$db->commit();
			}
		}

		$db->delete(
			'wiki_claims_answers',
			['claim_id' => $this->data['cid']],
			__METHOD__
		);

		$this->data = [];
		$this->answers = [];

		return true;
	}

	/**
	 * Returns the claim identification number from the database.
	 *
	 * @access	public
	 * @return	string	Claim ID
	 */
	public function getId() {
		return $this->data['cid'];
	}

	/**
	 * Set the User object.
	 *
	 * @access	public
	 * @param	object	Mediawiki User Object
	 * @throws	object	InvalidArgumentException
	 */
	public function setUser(User $user) {
		if (!$user->getId()) {
			throw new InvalidArgumentException(__METHOD__.': Invalid user given.');
		}
		$this->user = $user;
		$this->data['user_id'] = $user->getId();
	}

	/**
	 * Returns the User object.
	 *
	 * @access	public
	 * @return	object	Mediawiki User Object
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Returns the loaded questions with filled in information.
	 *
	 * @access	public
	 * @return	array	Multidimensional array of questions with question text and possible previously entered answer.
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
	 * @access	public
	 * @return	array	Question Keys
	 */
	public function getQuestionKeys() {
		for ($i=0; $i < $this->settings['number_of_questions']; $i++) {
			$keys[] = 'wiki_claim_question_'.$i;
		}
		return $keys;
	}

	/**
	 * Returns the agreement text for the wiki claim terms.
	 *
	 * @access	public
	 * @return	string	Terms and Agreement Text.
	 */
	public function getAgreementText() {
		return wfMessage('wiki_claim_agreement')->plain();
	}

	/**
	 * Returns the guidelines text for the wiki claim terms.
	 *
	 * @access	public
	 * @return	string	Guidlines Text.
	 */
	public function getGuidelinesText() {
		return wfMessage('wiki_claim_more_info')->parseAsBlock();
	}

	/**
	 * Return the status code for this claim.
	 *
	 * @access	public
	 * @return	integer	Status Code
	 */
	public function getStatus() {
		return intval($this->data['status']);
	}

	/**
	 * Are the terms accepted by this user?
	 *
	 * @access	public
	 * @return	boolean	Agreed to the terms?
	 */
	public function isAgreed() {
		return boolval($this->data['agreed']);
	}

	/**
	 * Set that the wiki claim terms have been agreed to.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Agreed to the terms or not.  Defaults to true.
	 * @return	void
	 */
	public function setAgreed($agreed = true) {
		$this->data['agreed'] = ($agreed ? 1 : 0);
	}

	/**
	 * Set the new status on this claim.
	 *
	 * @access	public
	 * @return	void
	 */
	public function setNew() {
		$this->data['status'] = self::CLAIM_NEW;
	}

	/**
	 * Is this claim new?
	 *
	 * @access	public
	 * @return	boolean
	 */
	public function isNew() {
		return $this->data['status'] === self::CLAIM_NEW;
	}

	/**
	 * Set the pending status on this claim.
	 *
	 * @access	public
	 * @return	void
	 */
	public function setPending() {
		$this->data['status'] = self::CLAIM_PENDING;
	}

	/**
	 * Is this claim pending?
	 *
	 * @access	public
	 * @return	boolean
	 */
	public function isPending() {
		return $this->data['status'] === self::CLAIM_PENDING;
	}

	/**
	 * Set the approved status on this claim.
	 *
	 * @access	public
	 * @return	void
	 */
	public function setApproved() {
		$this->data['status'] = self::CLAIM_APPROVED;
	}

	/**
	 * Is this claim approved?
	 *
	 * @access	public
	 * @return	boolean
	 */
	public function isApproved() {
		return $this->data['status'] === self::CLAIM_APPROVED;
	}

	/**
	 * Set the denied status on this claim.
	 *
	 * @access	public
	 * @return	void
	 */
	public function setDenied() {
		$this->data['status'] = self::CLAIM_DENIED;
	}

	/**
	 * Is this claim denied?
	 *
	 * @access	public
	 * @return	boolean
	 */
	public function isDenied() {
		return $this->data['status'] === self::CLAIM_DENIED;
	}

	/**
	 * Set the inactive status on this claim.
	 *
	 * @access	public
	 * @return	void
	 */
	public function setInactive() {
		$this->data['status'] = self::CLAIM_INACTIVE;
	}

	/**
	 * Is this claim inactive?
	 *
	 * @access	public
	 * @return	boolean
	 */
	public function isInactive() {
		return $this->data['status'] === self::CLAIM_INACTIVE;
	}

	/**
	 * Sets an answer for the provided question key.
	 *
	 * @access	public
	 * @param	string	Question Key
	 * @param	mixed	Question Answer
	 * @return	void
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
	 * @access	public
	 * @param	string	Question Key
	 * @return	array	Array of $questionKey => $answer;
	 */
	public function getAnswers() {
		return $this->answers;
	}

	/**
	 * Set a timestamp for this claim.
	 *
	 * @access	public
	 * @param	integer	Epoch based timestamp.
	 * @param	string	[Optional] Which timestamp to set.  Valid values: claim, start, and end.
	 * @return	void
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
	 * @access	public
	 * @param	string	[Optional] Which timestamp to get.  Valid values: claim, start, and end.
	 * @return	integer	Epoch based timestamp
	 */
	public function getTimestamp($type = 'claim') {
		switch ($type) {
			default:
			case 'claim':
				return intval($this->data['claim_timestamp']);
				break;
			case 'start':
				return intval($this->data['start_timestamp']);
				break;
			case 'end':
				return intval($this->data['end_timestamp']);
				break;
		}
	}

	/**
	 * Figures out what answers are not answers and return a list of errors.
	 *
	 * @access	public
	 * @return	array	An array of errors of $questionKey => $message.  The array will be empty for no errors.
	 */
	public function getErrors() {
		$keys = $this->getQuestionKeys();
		$errors = [];
		foreach ($keys as $key) {
			if (empty($this->answers[$key])) {
				$errors[$key] = wfMessage($key.'_error');
			}
		}
		return $errors;
	}
}
