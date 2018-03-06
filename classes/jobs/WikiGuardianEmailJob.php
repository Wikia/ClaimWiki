<?php
/**
 * Curse Inc.
 * ClaimWiki
 * WikiGuardianEmail Job Class
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		ClaimWiki
 * @link		http://www.curse.com/
 *
**/

class WikiGuardianEmailJob extends \SyncService\Job {
	/**
	 * Handles ivoking emails for inactive wiki guardians.
	 *
	 * @access	public
	 * @param	array
	 * @return	integer	exit value for this thread
	 */
	public function execute($args = []) {
		global $wgEmergencyContact, $wgSitename, $wgClaimWikiEmailTo, $wgClaimWikiEnabled, $dsSiteKey;
		$this->outputLine("Starting Wiki Guardian Email Job.\n");

		if (!$wgClaimWikiEnabled) {
			$this->outputLine("Claim Wiki not Enabled. Exiting.\n");
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

			$claim = WikiClaim::newFromUser($user);
			$redisEmailKey = wfWikiID().':guardianReminderEmail:timeSent:'.$user->getId();

			$cheevosUser = \Cheevos\Cheevos::getWikiPointLog([
				'user_id' => $user->getId(),
				'site_id' => ($dsSiteKey ? $dsSiteKey : null)
			]);
			if (isset($cheevosUser[0]) && $cheevosUser[0]->getUser_Id() == $user->getId()) {
				$timestamp = $cheevosUser[0]->getTimestamp();
				$oldTimestamp = time() - 5184000; //Thirty Days
				$emailReminderExpired = time() - 1296000; //Fifteen Days
			} else {
				// cant get timestamp
				continue;
			}

			try {
				$emailSent = $this->redis->get($redisEmailKey);
			} catch (RedisException $e) {
				wfDebug(__METHOD__.": Caught RedisException - ".$e->getMessage());
			}
			if ($emailSent > 0 && $emailSent > $emailReminderExpired) {
				$this->outputLine("SKIP - Reminder email already send to ".$user->getName()." and resend is on cool down.\n");
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
					$this->outputLine("SUCCESS - Reminder email send to ".current($address).".\n");
					try {
						$this->redis->set($redisEmailKey, time());
						$this->redis->expire($redisEmailKey, 1296000);
					} catch (RedisException $e) {
						$this->outputLine(__METHOD__.": Caught RedisException - ".$e->getMessage());
					}
				} else {
					$this->outputLine("ERROR - Failed to send a reminder email to ".current($address).".\n");
				}
			}
		}
		$this->outputLine("DONE");
		return 0;
	}

	/**
	 * Return cron schedule if applicable.
	 *
	 * @access	public
	 * @return	mixed	False for no schedule or an array of schedule information.
	 */
	static public function getSchedule() {
		return [
			[
				'minutes' => '0',
				'hours' => '0',
				'days' => '*',
				'months' => '*',
				'weekdays' => '*',
				'arguments' => []
			]
		];
	}
}
