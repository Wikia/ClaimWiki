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

class wikiClaim {
	/**
	 * Production Environment Domain
	 *
	 * @var		constant
	 */
	const CLAIM_NEW = 0;

	/**
	 * Staging Environment Domain
	 *
	 * @var		constant
	 */
	const CLAIM_PENDING = 1;

	/**
	 * Development Environment Domain
	 *
	 * @var		constant
	 */
	const CLAIM_APPROVED = 2;

	/**
	 * Local Environment Domain
	 *
	 * @var		constant
	 */
	const CLAIM_DENIED = 3;

	/**
	 * Redirect Domain
	 *
	 * @var		constant
	 */
	const CLAIM_INACTIVE = 4;

	/**
	 * Claim Data
	 *
	 * @var		array
	 */
	private $data = [];

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
	private $user;

	/**
	 * Mediawiki DB object for this claim.
	 *
	 * @var		object
	 */
	private $DB;

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct($user) {
		global $claimWikiNumberOfQuestions;

		if (!$user instanceof User || !$user->getId()) {
			throw new MWException('Invalid user passed to '.__METHOD__.'.');
		}

		$this->user = $user;

		$this->data['user_id'] = $this->user->getId();

		$this->settings['number_of_questions'] = $claimWikiNumberOfQuestions;

		if (!defined('SITE_DIR')) {
			define('SITE_DIR', dirname(dirname(dirname(__DIR__))));
		}

		$this->DB = wfGetDB(DB_MASTER);

		$this->init();
	}

	/**
	 * Initialize the object.
	 *
	 * @access	public
	 * @return	void
	 */
	public function init() {
		$result = $this->DB->select(
			'wiki_claims',
			array('*'),
			'user_id = '.intval($this->data['user_id']),
			__METHOD__
		);
		$data = $result->fetchRow();

		if ($data['cid'] > 0) {
			//Load existing data.
			$this->data = $data;

			//Load existing answers.
			$result = $this->DB->select(
				'wiki_claims_answers',
				array('*'),
				'claim_id = '.intval($data['cid']),
				__METHOD__
			);
			while ($row = $result->fetchRow()) {
				$this->setAnswer($row['question_key'], $row['answer']);
			}
		}
	}

	/**
	 * Returns the claim identification number from the database.
	 *
	 * @access	public
	 * @return	string	Claim ID
	 */
	public function getClaimId() {
		return $this->data['cid'];
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
				'answer'	=> $this->answers[$key]
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
	 * Are the terms accepted by this user?
	 *
	 * @access	public
	 * @return	boolean	Agreed to the terms?
	 */
	public function isAgreed() {
		return $this->data['agreed'];
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
	 * Set the approved status on this claim.
	 * True - Approved
	 * False - Denied
	 * Null - Undecided
	 *
	 * @access	public
	 * @param	boolean	[Optional] Approval status for this claim.  Defaults to true.
	 * @return	void
	 */
	public function setApproved($approved = true) {
		if ($approved === null) {
			$this->data['approved'] = null;
		} elseif ($approved === true) {
			$this->data['approved'] = 1;
			$this->data['pending'] = null;
		} elseif ($approved === false) {
			$this->data['approved'] = 0;
			$this->data['pending'] = null;
		} else {
			$this->data['approved'] = null;
		}
	}

	/**
	 * Is this claim approved?
	 *
	 * @access	public
	 * @return	mixed	Boolean true or false for approval, null if neither decision has been made.
	 */
	public function isApproved() {
		if ($this->data['approved'] == 1) {
			return true;
		} elseif ($this->data['approved'] == 0) {
			return false;
		} else {
			return null;
		}
	}

	/**
	 * Is this claim denied?
	 *
	 * @access	public
	 * @return	mixed	Boolean true or false for denied, null if neither decision has been made.
	 */
	public function isDenied() {
		if ($this->data['approved'] == 1) {
			return false;
		} elseif ($this->data['approved'] == 0) {
			return true;
		} else {
			return null;
		}
	}

	/**
	 * Set the pending status on this claim.
	 * True - pending
	 *
	 * @access	public
	 * @param	boolean	[Optional] pending status for this claim.  Defaults to true.
	 * @return	void
	 */
	public function setPending($approved = true) {
		if ($approved === null) {
			$this->data['pending'] = null;
		} elseif ($approved === true) {
			$this->data['pending'] = 1;
			$this->data['approved'] = null;
		} elseif ($approved === false) {
			$this->data['pending'] = 0;
		} else {
			$this->data['pending'] = null;
		}
	}

	/**
	 * Is this claim pending?
	 *
	 * @access	public
	 * @return	mixed	Boolean true or false for pending, null if neither decision has been made.
	 */
	public function isPending() {
		if ($this->data['pending'] === null) {
			return null;
		} elseif ($this->data['pending'] == 1) {
			return true;
		} elseif ($this->data['pending'] == 0) {
			return false;
		} else {
			return null;
		}
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
	 * @return	mixed	Boolean false for no errors or an array of errors of $questionKey => $message.
	 */
	public function getErrors() {
		$keys = $this->getQuestionKeys();
		$errors = false;
		foreach ($keys as $key) {
			if (empty($this->answers[$key])) {
				$errors[$key] = wfMessage($key.'_error');
			}
		}
		return $errors;
	}

	/**
	 * Save data to the database.
	 *
	 * @access	public
	 * @return	boolean	Successful Save.
	 */
	public function save() {
		//Do a transactional save.
		$this->DB->begin();
		if ($this->data['cid'] > 0) {
			$_data = $this->data;
			unset($_data['cid']);
			//Do an update
			$success = $this->DB->update(
				'wiki_claims',
				$_data,
				array('cid' => $this->data['cid']),
				__METHOD__
			);
			unset($_data);
		} else {
			//Do an insert
			$success = $this->DB->insert(
				'wiki_claims',
				$this->data,
				__METHOD__
			);
		}

		//Roll back if there was an error.
		if (!$success) {
			$this->DB->rollback();
			return false;
		} else {
			if (!$this->data['cid']) {
				$this->data['cid'] = $this->DB->insertId();
			}
			$this->DB->commit();
		}

		$this->DB->delete(
			'wiki_claims_answers',
			array('claim_id' => $this->data['cid']),
			__METHOD__
		);

		foreach ($this->answers as $key => $answer) {
			$answerData = [
				'claim_id'		=> $this->data['cid'],
				'question_key'	=> $key,
				'answer'		=> $answer
			];
			$this->DB->insert(
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
		//Do a transactional save.
		$this->DB->begin();
		if ($this->data['cid'] > 0) {
			//Do an update
			$success = $this->DB->delete(
				'wiki_claims',
				array('cid' => $this->data['cid']),
				__METHOD__
			);
		}

		//Roll back if there was an error.
		if (!$success) {
			$this->DB->rollback();
			return false;
		} else {
			$this->DB->commit();
		}

		$this->DB->delete(
			'wiki_claims_answers',
			array('claim_id' => $this->data['cid']),
			__METHOD__
		);

		$this->data = [];
		$this->answers = [];

		return true;
	}
}
?>