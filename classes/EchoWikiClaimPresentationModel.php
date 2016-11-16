<?php
/**
 * Curse Inc.
 * Claim Wiki
 * EchoWikiClaimPresentationModel Class
 *
 * @author		Alexia E. Smith
 * @license		GPLv2
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

class EchoWikiClaimPresentationModel extends EchoEventPresentationModel {
	public function getIconType() {
		return 'wiki-claim';
	}

	public function getHeaderMessage() {
		$siteName = $this->event->getExtraParam('site_name');
		
		$msg = $this->getMessageWithAgent("notification-header-wiki-claim");
		$msg->plaintextParams($this->event->getExtraParam('site_name'));
		return $msg;
	}

	public function getBodyMessage() {
		$reason = $this->event->getExtraParam( 'reason' );
		return $reason ? $this->msg( 'notification-body-user-rights' )->params( $reason ) : false;
	}

	private function getLocalizedGroupNames( $names ) {
		return array_map( function( $name ) {
			$msg = $this->msg( 'group-' . $name );
			return $msg->isBlank() ? $name : $msg->text();
		}, $names );
	}

	public function getPrimaryLink() {
		return [
			'url' => $this->event->getExtraParam('claim_url'),
			'label' => $this->msg('echo-learn-more')->text()
		];
	}

	public function getSecondaryLinks() {
		return [$this->getAgentLink()];
	}
}
