<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Guardian Reminder Email Class
 *
 * @author		Alex Smith
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

require_once(dirname(dirname(dirname(__DIR__)))."/maintenance/Maintenance.php");

class guardianReminderEmail extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
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

		parent::__construct();
	}

	/**
	 * Sends reminder emails to inactive wiki guardians.
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		global $wgEmergencyContact, $wgSitename;
		$this->DB = wfGetDB(DB_MASTER);

		$results = $this->DB->select(
			['wiki_claims'],
			['*'],
			"agreed = 1 AND approved = 1 AND start_timestamp > 0 AND end_timestamp = 0",
			__METHOD__
		);

		while ($row = $results->fetchRow()) {
			$user = User::newFromId($row['user_id']);
			if (!$user->getId()) {
				continue;
			}
			$claim = new wikiClaim($user);

			$timestamp = wfTimestamp(TS_UNIX, $user->getTouched());
			$oldTimestamp = time() - 2592000;
			if ($timestamp <= $oldTimestamp) {
				//Send a reminder email.
				$this->mouse->output->loadTemplate('dsemails');

				$emailTo		= $claim->getUser()->getName()." <".$claim->getUser()->getEmail().">";
				$emailSubject	= 'Inactive Wiki Guardian Notification - '.$wgSitename;

				$emailBody		= $this->mouse->output->dsemails->wikiGuardianInactive($row, $wgSitename);

				$emailFrom		= $wgEmergencyContact;
				$emailHeaders	= "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: {$emailFrom}\r\nReply-To: {$emailFrom}\r\nX-Mailer: Hydra/1.0";

				$success = mail($emailTo, $emailSubject, $emailBody, $emailHeaders, "-f{$emailFrom}");
				if ($success) {
					$this->mouse->output->sendLine("SUCCESS - Reminder email send to {$emailTo}.", time());
				} else {
					$this->mouse->output->sendLine("ERROR - Failed to send a reminder email to {$emailTo}.", time());
				}
			}
		}
	}
}

$maintClass = 'guardianReminderEmail';
require_once(RUN_MAINTENANCE_IF_MAIN);
?>