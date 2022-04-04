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
 */

namespace ClaimWiki\Jobs;

use Cheevos\Cheevos;
use ClaimWiki\WikiClaim;
use Job;
use JobSpecification;
use MailAddress;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use Psr\Log\LoggerInterface;
use Redis;
use RedisCache;
use RedisConnRef;
use Sanitizer;
use Title;
use Twiggy\TwiggyService;
use UserMailer;
use WikiMap;
use Wikimedia\Rdbms\ILoadBalancer;

class WikiGuardianEmailJob extends Job {
	/** @var ILoadBalancer */
	private $lb;
	/** @var TwiggyService */
	private $twiggy;
	/** @var UserFactory */
	private $userFactory;
	/** @var RedisConnRef|Redis|bool */
	private $redis;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(
		$command,
		$params,
		ILoadBalancer $lb,
		UserFactory $userFactory,
		TwiggyService $twiggy,
		$redis,
		LoggerInterface $logger
	) {
		parent::__construct(
			$command,
			$params
		);
		$this->lb = $lb;
		$this->twiggy = $twiggy;
		$this->userFactory = $userFactory;
		$this->redis = $redis;
		$this->logger = $logger;
	}

	/**
	 * Queue a new job.
	 *
	 * @param array $parameters Named arguments passed by the command that queued this job.
	 *
	 * @return void
	 */
	public static function queue( array $parameters = [] ) {
		$job = new JobSpecification( __CLASS__, $parameters );
		MediaWikiServices::getInstance()->getJobQueueGroup()->push( $job );
	}

	public static function newInstance( ?Title $title, array $params ): self {
		$services = MediaWikiServices::getInstance();
		$lb = $services->getDBLoadBalancer();
		$twiggy = $services->getService( 'TwiggyService' );
		$userFactory = $services->getUserFactory();
		$redis = RedisCache::getClient( 'cache' );
		$logger = LoggerFactory::getInstance( __CLASS__ );

		return new self( $title, $params, $lb, $userFactory, $twiggy, $redis, $logger );
	}

	/**
	 * Handles invoking emails for inactive wiki guardians.
	 *
	 * @return bool Success
	 */
	public function run() {
		global $wgEmergencyContact, $wgSitename, $wgClaimWikiEnabled, $dsSiteKey;

		if ( !$wgClaimWikiEnabled ) {
			return true;
		}

		$db = $this->lb->getConnectionRef( DB_REPLICA );

		$results = $db->select(
			[ 'wiki_claims' ],
			[ '*' ],
			[
				'agreed' => 1,
				'status' => WikiClaim::CLAIM_APPROVED,
				'start_timestamp > 0',
				'end_timestamp' => 0,
			],
			__METHOD__
		);

		while ( $row = $results->fetchRow() ) {
			$address = [];

			$user = $this->userFactory->newFromId( (int)$row['user_id'] );
			if ( !$user->getId() ) {
				continue;
			}

			$claim = WikiClaim::newFromUser( $user );
			$redisEmailKey = WikiMap::getCurrentWikiId() . ':guardianReminderEmail:timeSent:' . $user->getId();

			$cheevosUser = Cheevos::getWikiPointLog(
				[
					'site_id' => ( $dsSiteKey ?: null ),
				],
				$user
			);
			if ( isset( $cheevosUser[0] ) && $cheevosUser[0]->getUser_Id() ) {
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
				$emailSent = $this->redis->get( $redisEmailKey );
			} catch ( RedisException $e ) {
				$this->logger->error(
					__METHOD__ . ": Caught RedisException - " . $e->getMessage()
				);
			}
			if ( $emailSent > 0 && $emailSent > $emailReminderExpired ) {
				continue;
			}

			if ( $timestamp <= $oldTimestamp ) {
				// Send a reminder email.
				if ( $_SERVER['PHP_ENV'] != 'development' ) {
					$ownerEmail = $claim->getUser()->getEmail();
					if ( Sanitizer::validateEmail( $ownerEmail ) ) {
						$address[] = new MailAddress( $ownerEmail, $claim->getUser()->getName() );
					}
					$emailSubject = 'Inactive Wiki Guardian Notification - ' . $wgSitename;
				} else {
					$address[] = new MailAddress( 'platform-l@fandom.com', 'Fandom' );
					$emailSubject = '~~ DEVELOPMENT WIKI GUARDIAN EMAIL ~~ ' . $wgSitename;
				}

				$from = new MailAddress( $wgEmergencyContact );
				$address[] = $from;

				$template = $this->twiggy->load( '@ClaimWiki/claim_email_inactive.twig' );
				$email = new UserMailer();
				$status = $email->send(
					$address,
					$from,
					$emailSubject,
					[
						'text' => strip_tags(
							$template->render(
								[ 'username' => $user->getName(), 'sitename' => $wgSitename ] )
						),
						'html' => $template->render(
							[ 'username' => $user->getName(), 'sitename' => $wgSitename ] ),
					]
				);

				if ( $status->isOK() ) {
					try {
						$this->redis->set( $redisEmailKey, time() );
						$this->redis->expire( $redisEmailKey, 1296000 );
					} catch ( RedisException $e ) {
						$this->logger->error(
							__METHOD__ . ": Caught RedisException - " . $e->getMessage()
						);
					}
				}
			}
		}

		return true;
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
				'arguments' => [],
			],
		];
	}
}
