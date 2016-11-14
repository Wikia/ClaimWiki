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

class GuardianReminderEmail extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
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

		$this->redis = RedisCache::getClient('cache');

		$this->templateClaimEmails = new TemplateClaimEmails;

		$results = $this->DB->select(
			['wiki_claims'],
			['*'],
			[
				'agreed' => 1,
				'status' => intval(WikiClaim::CLAIM_APPROVED),
				'start_timestamp > 0',
				'end_timestamp' => 0
			],
			__METHOD__
		);

		while ($row = $results->fetchRow()) {
			$address = [];

			$user = User::newFromId($row['user_id']);
			if (!$user->getId()) {
				continue;
			}
			$claim = new WikiClaim($user);

			$redisEmailKey = wfWikiID().':guardianReminderEmail:timeSent:'.$user->getId();

			$timestamp = wfTimestamp(TS_UNIX, $user->getDBTouched());
			$oldTimestamp = time() - 5184000; //Thirty Days
			$emailReminderExpired = time() - 1296000; //Fifteen Days

			try {
				$emailSent = $this->redis->get($redisEmailKey);
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
			if ($emailSent > 0 && $emailSent > $emailReminderExpired) {
				$this->output("SKIP - Reminder email already send to ".$user->getName()." and resend is on cool down.\n");
				continue;
			}

			if ($timestamp <= $oldTimestamp) {
				//Send a reminder email.
				if ($_SERVER['PHP_ENV'] != 'development') {
					$ownerEmail = $claim->getUser()->getEmail();
					if (Sanitizer::validateEmail($ownerEmail)) {
						$address[] = new MailAddress($ownerEmail, $claim->getUser()->getName());
					}
					$emailSubject = 'Inactive Wiki Guardian Notification - ' . $wgSitename;
				} else {
					$address[] = new MailAddress("wikitest@curse.com", 'Hydra Testers');
					$emailSubject = '~~ DEVELOPMENT WIKI GUARDIAN EMAIL ~~ ' . $wgSitename;
				}

				$from = new MailAddress($wgEmergencyContact);
				$address[] = $from;

				$email = new UserMailer();
				$status = $email->send(
					$address,
					$from,
					$emailSubject,
					[
						'text' => strip_tags($this->templateClaimEmails->wikiGuardianInactive($user->getName(), $wgSitename)),
						'html' => $this->templateClaimEmails->wikiGuardianInactive($user->getName(), $wgSitename)
					]
				);

				if ($status->isOK()) {
					$this->output("SUCCESS - Reminder email send to ".current($address).".\n");
					try {
						$this->redis->set($redisEmailKey, time());
						$this->redis->expire($redisEmailKey, 1296000);
					} catch (RedisException $e) {
						$this->output(__METHOD__.": Caught RedisException - ".$e->getMessage());
					}
				} else {
					$this->output("ERROR - Failed to send a reminder email to ".current($address).".\n");
				}
			}
		}
	}
}

$maintClass = 'GuardianReminderEmail';
require_once(RUN_MAINTENANCE_IF_MAIN);
?>