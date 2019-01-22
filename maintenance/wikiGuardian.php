<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Manual Runner for Wiki Guardian Email job.
 *
 * @author		Cameron Chunn
 * @copyright	(c) 2017 Curse Inc.
 * @license		All Rights Reserved
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

require_once(dirname(__DIR__, 3)."/maintenance/Maintenance.php");

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
	 * Queue wiki guardian email job
	 *
	 * @access	public
	 * @return	void
	 */
	public function execute() {
		$this->output('Queuing Wiki Guardian Email job');
		WikiGuardianEmailJob::queue();
	}
}

$maintClass = 'GuardianReminderEmail';
require_once(RUN_MAINTENANCE_IF_MAIN);
?>