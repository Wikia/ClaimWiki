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

		parent::__construct();
	}

	/**
	 * Sends reminder emails to inactive wiki guardians.
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		global $wgEmergencyContact, $wgSitename, $claimWikiEmailTo, $claimWikiEnabled;

		if (!$claimWikiEnabled) {
			return;
		}

		$this->DB = wfGetDB(DB_MASTER);

		$settings['file'] = SITE_DIR.'/LocalSettings.php';
		if (!class_exists('mouseHole')) {
			require_once(SITE_DIR.'/mouse/mouse.php');
		}

		$this->mouse = mouseHole::instance(['output' => 'mouseOutputOutput', 'config' => 'mouseConfigMediawiki', 'redis' => 'mouseCacheRedis'], $settings);
		$this->mouse->output->addTemplateFolder(CW_EXT_DIR.'/templates');

		$results = $this->DB->select(
			['wiki_claims'],
			['*'],
			"agreed = 1 AND status = ".intval(wikiClaim::CLAIM_APPROVED)." AND start_timestamp > 0 AND end_timestamp = 0",
			__METHOD__
		);

		while ($row = $results->fetchRow()) {
			$user = User::newFromId($row['user_id']);
			if (!$user->getId()) {
				continue;
			}
			$claim = new wikiClaim($user);

			$redisEmailKey = wfWikiID().':guardianReminderEmail:timeSent:'.$user->mId;

			$timestamp = wfTimestamp(TS_UNIX, $user->getTouched());
			$oldTimestamp = time() - 5184000; //Thirty Days
			$emailReminderExpired = time() - 1296000; //Fifteen Days

			$emailSent = $this->mouse->redis->get($redisEmailKey);
			if ($emailSent > 0 && $emailSent > $emailReminderExpired) {
				$this->mouse->output->sendLine("SKIP - Reminder email already send to ".$user->getName()." and resend is on cool down.", time());
				continue;
			}

			if ($timestamp <= $oldTimestamp) {
				//Send a reminder email.
				$this->templateClaimEmails = new TemplateClaimEmails;

				//@TODO: Use the built in UserMailer.
				if ($_SERVER['PHP_ENV'] != 'development') {
					$emailTo = $claim->getUser()->getName() . " <" . $claim->getUser()->getEmail() . ">";
					$emailSubject = 'Inactive Wiki Guardian Notification - ' . $wgSitename;
				} else {
					$emailTo = 'Hydra Testers' . " <wikitest@curse.com>";
					$emailSubject = '~~ DEVELOPMENT WIKI GUARDIAN EMAIL ~~ ' . $wgSitename;
				}

				$emailBody		= $this->templateClaimEmails->wikiGuardianInactive($user->mName, $wgSitename);

				$emailFrom		= $wgEmergencyContact;
				$emailHeaders	= "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: {$emailFrom}\r\nReply-To: {$emailFrom}\r\nCC: {$claimWikiEmailTo}\r\nX-Mailer: Hydra/1.0";

				$success = mail($emailTo, $emailSubject, $emailBody, $emailHeaders, "-f{$emailFrom}");
				if ($success) {
					$this->mouse->output->sendLine("SUCCESS - Reminder email send to {$emailTo}.", time());
					$this->mouse->redis->set($redisEmailKey, time());
					$this->mouse->redis->expire($redisEmailKey, 1296000);
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