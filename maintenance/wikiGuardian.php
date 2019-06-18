<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Manual Runner for Wiki Guardian Email job.
 *
 * @author    Cameron Chunn
 * @copyright (c) 2017 Curse Inc.
 * @license   GNU General Public License v2.0 or later
 * @package   Claim Wiki
 * @link      https://gitlab.com/hydrawiki
**/

require_once dirname(__DIR__, 3) . "/maintenance/Maintenance.php";

class GuardianReminderEmail extends Maintenance {
	/**
	 * Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Queue wiki guardian email job
	 *
	 * @access public
	 * @return void
	 */
	public function execute() {
		$this->output('Queuing Wiki Guardian Email job');
		WikiGuardianEmailJob::queue();
	}
}

$maintClass = 'GuardianReminderEmail';
require_once RUN_MAINTENANCE_IF_MAIN;
