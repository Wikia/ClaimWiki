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
		$this->event->getExtraParam();
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
		return array(
			'url' => SpecialPage::getTitleFor( 'Listgrouprights' )->getLocalURL(),
			'label' => $this->msg( 'echo-learn-more' )->text()
		);
	}

	public function getSecondaryLinks() {
		return array( $this->getAgentLink(), $this->getLogLink() );
	}

	private function getLogLink() {
		$affectedUserPage = User::newFromId( $this->event->getExtraParam( 'user' ) )->getUserPage();
		$query = array(
			'type' => 'rights',
			'page' => $affectedUserPage->getPrefixedText(),
			'user' => $this->event->getAgent()->getName(),
		);
		return array(
			'label' => $this->msg( 'echo-log' )->text(),
			'url' => SpecialPage::getTitleFor( 'Log' )->getFullURL( $query ),
			'description' => '',
			'icon' => false,
			'prioritized' => true,
		);
	}
}
