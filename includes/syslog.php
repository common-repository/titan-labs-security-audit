<?php

class TLSA_Plugin_Syslog {

	private $remote_ip;
	private $remote_port;
	private $data_id;
	private $protocol;
	private $cef;

	public function __construct($ip, $port, $data, $prot, $cef) {
		$this->remote_ip 	= $ip;
		$this->remote_port	= $port;
		$this->data_id		= $data;
		$this->protocol		= $prot;
		$this->cef			= $cef;
    }

	public function testSettings($userName, $siteName) {
		
		$message		= "This is a test message from wordpress via {$this->protocol}.";
		$data 			= "suser=\"{$userName}\"";
		
		return $this->sendMessage($siteName, $message, '9000', 'high', $data);
	}
	
	public function sendMessage($siteName, $message, $event, $severity, $data){

		$protocolPrefix = ($this->protocol === 'tcp') ? '' : "{$this->protocol}://";
		$facility_code 	= 1;
		$severity_level = 5;
		
		switch ($severity){
			case 'info':
				$severity_level = ($this->cef == 'yes') ? 0 : 6;
				break;
			case 'low':
				$severity_level = ($this->cef == 'yes') ? 3 : 4;
				break;
			case 'medium':
				$severity_level = 5;
				break;
			case 'high':
				$severity_level = ($this->cef == 'yes') ? 7 : 1;
				break;
			case 'critical':
				$severity_level = ($this->cef == 'yes') ? 10 : 2;
				break;
		}

		$date			= "";
		$syslog_message = "";
		$sent			= 0;
		$result			= 0;
		$version		= TSLA_PLUGIN_VERSION;
		
		if ($this->cef == 'yes') {
			$date			= date('M d Y H:i:s e');
			$syslog_message = "{$date} {$siteName} CEF:0|TitanLabs|TLSAPlugin|{$version}|{$event}|{$message}|{$severity_level}|{$data}";
		} else {
			$date			= date(DATE_ISO8601);
			$priority 		= ($facility_code * 8) + $severity_level;
			$syslog_message = "<{$priority}>1 {$date} {$siteName} TLSAPlugin {$version} {$event} [{$this->data_id} {$data}] {$message}";
		}

		$fp = fsockopen("{$protocolPrefix}{$this->remote_ip}", $this->remote_port, $errno, $errstr, 30) ;
		if ($fp) {
			$sent = fwrite($fp, $syslog_message);
			fclose($fp);
		}
		if ($sent != 0 && $sent != FALSE) {
			$result = 1;
		}
		
		return $result;
	}
	
}