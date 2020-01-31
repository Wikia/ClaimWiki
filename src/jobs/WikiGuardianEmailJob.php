<?php
/**
 * Curse Inc.
 * ClaimWiki
 * WikiGuardianEmail Job Class
 *
 * @package   ClaimWiki
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GPL-2.0-or-later
 * @link      https://gitlab.com/hydrawiki
**/

namespace ClaimWiki\Jobs;

use Cheevos\Cheevos;
use ClaimWiki\WikiClaim;
use MailAddress;
use MediaWiki\MediaWikiServices;
use RedisCache;
use Sanitizer;
use SyncService\Job;
use User;
use UserMailer;

class WikiGuardianEmailJob extends Job {
	/**
	 * Handles invoking emails for inactive wiki guardians.
	 *
	 * @param array $args
	 *
	 * @return integer	exit value for this thread
	 */
	public function execute($args = []) {
		global $wgEmergencyContact, $wgSitename, $wgClaimWikiEnabled, $dsSiteKey;
		$this->outputLine("Starting Wiki Guardian Email Job.\n");

		if (!$wgClaimWikiEnabled) {
			$this->outputLine("Claim Wiki not Enabled. Exiting.\n");
			return;
		}

		$this->DB = wfGetDB(DB_REPLICA);
		$redis = RedisCache::getClient('cache');
		$this->twiggy = MediaWikiServices::getInstance()->getService('TwiggyService');

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
			$redisEmailKey = wfWikiID() . ':guardianReminderEmail:timeSent:' . $user->getId();

			$cheevosUser = Cheevos::getWikiPointLog([
				'user_id' => $user->getId(),
				'site_id' => ($dsSiteKey ? $dsSiteKey : null)
			]);
			if (isset($cheevosUser[0]) && $cheevosUser[0]->getUser_Id() == $user->getId()) {
				$timestamp = $cheevosUser[0]->getTimestamp();
				 // Thirty Days
				$oldTimestamp = time() - 5184000;
				 // Fifteen Days
				$emailReminderExpired = time() - 1296000;
			} else {
				// cant get timestamp
				continue;
			}

			try {
				$emailSent = $redis->get($redisEmailKey);
			} catch (RedisException $e) {
				wfDebug(__METHOD__ . ": Caught RedisException - " . $e->getMessage());
			}
			if ($emailSent > 0 && $emailSent > $emailReminderExpired) {
				$this->outputLine(
					"SKIP - Reminder email already send to " . $user->getName() . " and resend is on cool down.\n"
				);
				continue;
			}

			if ($timestamp <= $oldTimestamp) {
				// Send a reminder email.
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

				$template = $this->twiggy->load('@ClaimWiki/claim_email_inactive.twig');
				$email = new UserMailer();
				$status = $email->send(
					$address,
					$from,
					$emailSubject,
					[
						'text' => strip_tags(
							$template->render(['username' => $user->getName(), 'sitename' => $wgSitename])
						),
						'html' => $template->render(['username' => $user->getName(), 'sitename' => $wgSitename])
					]
				);

				if ($status->isOK()) {
					$this->outputLine("SUCCESS - Reminder email send to " . current($address) . ".\n");
					try {
						$redis->set($redisEmailKey, time());
						$redis->expire($redisEmailKey, 1296000);
					} catch (RedisException $e) {
						$this->outputLine(__METHOD__ . ": Caught RedisException - " . $e->getMessage());
					}
				} else {
					$this->outputLine("ERROR - Failed to send a reminder email to " . current($address) . ".\n");
				}
			}
		}
		$this->outputLine("DONE");
		return 0;
	}

	/**
	 * Return cron schedule if applicable.
	 *
	 * @return mixed False for no schedule or an array of schedule information.
	 */
	public static function getSchedule() {
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
