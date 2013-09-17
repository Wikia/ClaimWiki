<?php
/**
 * Curse Inc.
 * Claim Wiki
 * Claim Wiki Emails Template
 *
 * @author		Alex Smith
 * @copyright	(c) 2013 Curse Inc.
 * @license		All Rights Reserved
 * @package		Claim Wiki
 * @link		http://www.curse.com/
 *
**/

class skin_claimemails {
	/**
	 * Output HTML
	 *
	 * @var		string
	 */
	private $HMTL;

	/**
	 * Claim Wiki Notice
	 *
	 * @access	public
	 * @param	array	Extra information for email body template.
	 * @return	string	Built HTML
	 */
	public function claimWikiNotice($emailExtra = array()) {
		$page = Title::newFromText('Special:WikiClaims');

		$HTML .= "~*~*~*~*~*~*~*~ ".strtoupper($emailExtra['environment'])." ~*~*~*~*~*~*~*~<br/>
<br/>
".$emailExtra['user']->getName()." submitted a claim to administrate {$emailExtra['site_name']} at ".date('c', $emailExtra['claim']->getTimestamp('claim')).".<br/>
Please visit <a href='".$page->getFullURL()."'>the wiki claims page</a> to approve or deny this claim.<br/>
<pre style='font: 10px/5px monospace;'>                                                      
                        ``                            
                     `                                
                     .                                
                     ,                                
                      ,     `                         
                      ::::,  ,                        
                       `::::`:                        
                       `.::::::                       
                   ,.,::::::::::`                     
                    .,:::::::::::                     
                    ,::::::::::`::                    
                 .,,:::::,,:::::,:`                   
                   ,::::   ,,,``.:::                  
                  ,:::,    ,,,,  `,``..`  `           
                 `:::,,   ,,,,`,` .:::`   `           
                 :::,,,       ,````   ,,,.            
                ,: ,,,,        ,,                     
                :  .,,,`                              
               `,  `,``           ::                  
               `    ,,,,          ``                  
                    ,,,,.                             
                    ,,,,,.      .                     
                     ,,,,,,,,,,,`                     
                     ,,,...,,,,,.`                    
                `     ,.....,,,,,,,,                  
              .,      ,,..,.,,,,,,,,,`                
             ,,,       ,,.,...,,,,.`,:  ,:.           
            `,,,        ,.,...,::,,,  : ,:,           
            ,,,.        ,,,..:::,,,,`    `            
            ,,,,        ,,,,::::,,,::  `              
           `,,,,        ,,,::::,,,,::                 
           `,,,,,       ,,:::::.,::::.                
           `,,,,,.     ,,:::::,.,::::,                
            :,,,,,,,,,,,,::::::,:::::,                
            ::,,,,,,,,,,:::::::::::::,                
            `:::,,,,,,,,:::::::::::::,                
             :::::,,,,,,:::::::::::::                 
              ,::::::,,,:::::::::::::                 
               ,:::::::,::::::::::::                  
                `::::::::::::::::::`                  
                  `:::::::::::::::                    
                      `,:::::::,`                     
                        `:::,`                        
                         `:`                          
                          ,                           


          :    ; `:  .: ;,,,;. .....:   ;;            
          :    ;  ., ;  ;    ; .`   ,  ,`,`           
          :,,,,;   ,;   ;    : .::::.  ;  ;           
          :    ;   `:   ;    ; .`  ,` ,;;;;.          
          :    ;   `:   ;;;;:  .`   : ;    ;          

</pre>";

		return $HTML;
	}

	/**
	 * Claim Wiki Status
	 *
	 * @access	public
	 * @param	array	Extra information for email body template.
	 * @return	string	Built HTML
	 */
	public function claimStatusNotice($emailExtra = array()) {
		global $defaultPortal, $wgEmergencyContact, $claimWikiEmailSignature;
		if ($emailExtra['claim']->isApproved() === true) {
			$HTML .= "Dear ".$emailExtra['claim']->getUser()->getName().",<br/>
<br/>
We’re happy to say that your Claim-a-Wiki application has been accepted! After reviewing your responses, we are confident that you are going to be a welcome addition to this wiki and ".str_replace(['http://', 'https://'], '', $defaultPortal)." in general.  We assume you’re pretty up to speed with the basics, but remember that you have now been granted the technical ability to perform certain special actions on this wiki.  This includes the ability to block users from editing, protect pages from editing, delete pages, rename pages without restriction, and use certain other tools.  We ask that you use these tools in the pursuit of excellence, and never for spiteful or personal reasons.  If you ever have any questions, comments, concerns, or any type of issue you’re not sure how to handle please feel free to contact the wiki team either via email at ".$wgEmergencyContact." or by leaving a message on a wiki administrator's talk page.<br/>
<br/>
Congratulations, and welcome!<br/>
<br/>
--{$claimWikiEmailSignature}";
		} elseif ($emailExtra['claim']->isApproved() === false) {
			$HTML .= "Dear ".$emailExtra['claim']->getUser()->getName().",<br/>
<br/>
After reviewing your Claim-a-Wiki application we must unfortunately decline your application.  It’s nothing personal, but for one reason or another we felt that you were not eligible to be elevated to an administrator level on this project.  If you are still interested you are welcome to apply again although please note that your previous application will still be on file and so it may be in your interest to wait a short while and gain some more experience on-wiki before trying again. If you would like to contact us directly about your application, feel free to e-mail us at ".$wgEmergencyContact." or leave a message on a wiki administrator's talk page.<br/>
<br/>
Thank you for your interest,<br/>
<br/>
--{$claimWikiEmailSignature}";
		} elseif ($emailExtra['claim']->isApproved() === null) {
			$HTML .= "Dear ".$emailExtra['claim']->getUser()->getName().",<br/>
<br/>
Your status as Wiki Guardian has been removed due to inactivity.  Please contact a wiki administrator if you wish to reinstate your status.<br/>
<br/>
--{$claimWikiEmailSignature}";
		}
		return $HTML;
	}

	/**
	 * Wiki Guardian Inactive
	 *
	 * @access	public
	 * @param	object	The wikiClaim object.
	 * @param	string	Wiki Name
	 * @return	string	Built HTML
	 */
	public function wikiGuardianInactive($userClaimRow, $wikiName) {
		$HTML .= "Dear ".$userClaimRow['user_name'].",<br/>
<br/>
Your status as Wiki Guardian on ".$wikiName." will be removed soon due to inactivity.  Please visit the wiki to retain your status or contact a wiki administrator if you wish to reinstate your status if it has already been removed.";

		return $HTML;
	}
}
?>