<?php

class TLSA_Plugin_Messages {


	public function __construct() {
    }

	public function init() {
		$options 		= get_option('tlsa_settings');
		$loginEnabled 	= $options['showLogin'][0];

		if ($loginEnabled == 'yes') {
			add_filter( 'login_message', array( $this, 'loginMessage' ), 10, 1 );
		}
	}
	
	public function loginMessage($message){
		$message .= '<p class="message">For security monitoring purposes, a record of all of your actions and changes within WordPress will be recorded in a log. This also includes the IP address used to access this site.</p>';
		return $message;
	}
	
}


