<?php
/**
 * Curse Inc.
 * Claim Wiki
 * EchoWikiClaimFormatter Class
 *
 * @author  Alexia E. Smith
 * @license GPLv2
 * @package Claim Wiki
 * @link    https://gitlab.com/hydrawiki
**/

class EchoWikiClaimFormatter extends EchoModelFormatter {
	/**
	 * @param $event EchoEvent
	 * @param $param string
	 * @param $message Message
	 * @param $user User
	 */
	protected function processParam($event, $param, $message, $user) {
		$extra = $event->getExtra();
		switch ($param) {
			default:
				parent::processParam($event, $param, $message, $user);
				break;
		}
	}

	/**
	 * Helper function for getLink()
	 *
	 * @param  EchoEvent $event
	 * @param  User      $user        The user receiving the notification
	 * @param  String    $destination The destination type for the link
	 * @return Array including target and query parameters
	 */
	protected function getLinkParams($event, $user, $destination) {
		$target = null;
		$query = [];
		// Set up link parameters based on the destination (or pass to parent)
		switch ($destination) {
			default:
				return parent::getLinkParams($event, $user, $destination);
		}

		return [$target, $query];
	}
}
