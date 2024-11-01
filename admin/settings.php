<?php

class TLSA_Plugin_Admin_Settings {

	public function __construct() {
    }

	public function init() {
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}

		add_action('admin_enqueue_scripts', array($this,'enqueueAdmin'));
		add_action('admin_menu', array($this, 'adminMenu'), 1);
		add_action('admin_init', array( $this, 'setupSectionsAndFields' ) );
		add_action('admin_post_testSyslog', array($this, 'testSyslog'));
	}
	
    public function enqueueAdmin() {
    	$screen = get_current_screen();

    	if (($screen->base == 'edit-tags' && $screen->taxonomy  == TLSA_TAXONOMY_TYPE) ||
			($screen->base == TLSA_POST_TYPE . '_page_tlsa_settings') ||
			($screen->base == 'dashboard') ||
			($screen->base == 'edit' && $screen->post_type == TLSA_POST_TYPE ))
		{
			wp_enqueue_style('tlsa_admin_css', plugins_url('../assets/css/admin.css', __FILE__), null, '1.0');
		}
    }

	public function adminMenu(){
		add_submenu_page('edit.php?post_type=' . TLSA_POST_TYPE, 'Security Audit', 'Settings', 'manage_options', 'tlsa_settings', array($this, 'tslaSettingsPage'), 1);

		global $submenu;
		$url = 'https://titan-labs.co.uk/';
		$submenu['edit.php?post_type=' . TLSA_POST_TYPE][] = array('Help', 'manage_options', $url);
	}

	public function setupSectionsAndFields() {
		add_settings_section( 'general_section', 'General', array( $this, 'sectionCallback' ), 'tlsa_settings' );
		add_settings_section( 'history_section', 'History', array( $this, 'sectionCallback' ), 'tlsa_settings' );
		add_settings_section( 'syslog_section', 'Syslog', array( $this, 'sectionCallback' ), 'tlsa_settings' );
		
		$fields = array(
			array(
        		'uid' => 'showLogin',
        		'label' => 'Show Login Page Warning',
        		'section' => 'general_section',
        		'type' => 'radio',
        		'options' => array(
        			'no' => 'Disabled',
        			'yes' => 'Enabled'
        		),
				'helper' => 'Show a security warning message on the login page.',
				'supplemental' => '',
                'default' => array( 'no' )
        	),
			array(
        		'uid' => 'showDashboard',
        		'label' => 'Show Dashboard Summary',
        		'section' => 'general_section',
        		'type' => 'radio',
        		'options' => array(
        			'no' => 'Disabled',
        			'yes' => 'Enabled'
        		),
				'helper' => 'Show a recent log summary on the dashboard.',
				'supplemental' => '',
                'default' => array( 'no' )
        	),
			array(
        		'uid' => 'histDuration',
        		'label' => 'History duration',
        		'section' => 'history_section',
        		'type' => 'radio',
        		'options' => array(
        			'0' => 'Keep all history',
        			'7' => '7 days',
        			'30' => '30 days',
        			'60' => '60 days'
        		),
				'helper' => 'Select duration to keep logs.',
				'supplemental' => '',
                'default' => array( '30' )
        	),
			array(
        		'uid' => 'syslogEnabled',
        		'label' => 'Broadcasting',
        		'section' => 'syslog_section',
        		'type' => 'radio',
        		'options' => array(
        			'no' => 'Disabled',
        			'yes' => 'Enabled'
        		),
				'helper' => 'Choose if Syslog broadcasts are enabled or not.',
				'supplemental' => '',
                'default' => array( 'no' )
        	),
			array(
        		'uid' => 'syslogCEF',
        		'label' => 'CEF Format',
        		'section' => 'syslog_section',
        		'type' => 'radio',
        		'options' => array(
        			'no' => 'Standard Syslog',
        			'yes' => 'CEF via Syslog'
        		),
				'helper' => 'Choose if Syslog broadcasts use CEF Format.',
				'supplemental' => '',
                'default' => array( 'yes' )
        	),
			array(
				'uid' => 'syslogHost',
				'label' => 'Host Address',
				'section' => 'syslog_section',
				'type' => 'text',
				'options' => false,
				'placeholder' => 'IP Address or Host',
				'helper' => 'Enter your syslog server IP address or host name',
				'supplemental' => 'Titan Lab syslog server is 51.132.242.180',
				'default' => '51.132.242.180'
			),
			array(
				'uid' => 'syslogPort',
				'label' => 'Port',
				'section' => 'syslog_section',
				'type' => 'text',
				'options' => false,
				'placeholder' => 'Port Number',
				'helper' => 'Enter your syslog server port number',
				'supplemental' => 'Titan Lab test server is 514 ',
				'default' => '514 '
			),
			array(
				'uid' => 'syslogDataId',
				'label' => 'Data Id',
				'section' => 'syslog_section',
				'type' => 'text',
				'options' => false,
				'placeholder' => 'Data Id',
				'helper' => 'Enter your syslog Data Id',
				'supplemental' => 'e.g. for Loggly your_customer_token@Loggly_PEN',
				'default' => ''
			),
			array(
        		'uid' => 'syslogProtocol',
        		'label' => 'Protocol',
        		'section' => 'syslog_section',
        		'type' => 'radio',
        		'options' => array(
        			'udp' => 'UDP',
        			'tcp' => 'TCP',
        			'tls' => 'TCP/TLS',
        			'ssl' => 'TCP/SSL'
        		),
				'helper' => 'Specify the protocol',
				'supplemental' => 'UDP, TCP or TCP encrypted (TLS/SSL)',
                'default' => array( 'tcp' )
        	)
		);

		$args = array( 'type' => 'array', 'sanitize_callback' => array( $this, 'validate_settings' ),  );
		register_setting('tlsa_settings', 'tlsa_settings', $args );

		foreach( $fields as $field ){
			add_settings_field( $field['uid'], $field['label'], array( $this, 'fieldCallback' ), 'tlsa_settings', $field['section'], $field );
		}
	}
	
	function validate_settings( $input ) {
		foreach( $input as $key => $value ) {
			if( isset( $input[$key] ) && ! is_array( $input[$key] ) ) {
				$input[$key] = sanitize_text_field( $input[ $key ] );
			}
		}

	 return $input;
	} 	
	
	public function sectionCallback($arguments) {
		switch( $arguments['id'] ) {
			case 'general_section':
				echo 'Configure general logging settings.';
				break;
			case 'history_section':
				echo 'For how long would you like to keep the logged events?';
				break;
			case 'syslog_section':
				echo 'Please enter your syslog settings, verify they are working by sending a test broadcast.';
				break;
		}
	}
	
	public function fieldCallback( $arguments ) {
		$options 	= get_option('tlsa_settings');
		$value 		= $arguments['default'];


		if ($options && array_key_exists( $arguments['uid'], $options)) {
			$value = $options[$arguments['uid'] ];
		}
		
		switch( $arguments['type'] ){
			case 'text':
				printf( '<input name="tlsa_settings[%1$s]" id="%1$s" type="%2$s" class="regular-text" placeholder="%3$s" value="%4$s" />', 
					$arguments['uid'], $arguments['type'], $arguments['placeholder'], esc_attr( $value ) );
				break;
			case 'radio':
            case 'checkbox':
                if( ! empty ( $arguments['options'] ) && is_array( $arguments['options'] ) ){
                    $options_markup = '';
                    $iterator = 0;
                    foreach( $arguments['options'] as $key => $label ){
                        $iterator++;
                        $options_markup .= sprintf( '<label for="tlsa_settings[%1$s]_%6$s"><input id="tlsa_settings[%1$s]_%6$s" name="tlsa_settings[%1$s][]" type="%2$s" value="%3$s" %4$s />%5$s</label>&nbsp;&nbsp;', 
							$arguments['uid'], $arguments['type'], $key, checked( $value[ array_search( $key, $value, true ) ], $key, false ), $label, $iterator );
                    }
                    printf( '<fieldset>%s</fieldset>', $options_markup );
                }
                break;
		}

		if( $helper = $arguments['helper'] ){
			printf( '<span class="helper"> %s</span>', $helper );
		}

		if( $supplimental = $arguments['supplemental'] ){
			printf( '<p class="description">%s</p>', $supplimental );
		}
	}

	public function testSyslog() { 
		if ( ! wp_verify_nonce( $_POST[ 'testSyslog_nonce' ], 'testSyslog' ) )
            die( 'Invalid nonce.' );

        if ( ! isset ( $_POST['_wp_http_referer'] ) )
            die( 'Missing target.' );

		if (!function_exists('socket_create')) 
            die( 'Sockets not enabled!' );

		$_SESSION['syslog_test'] = '0';
		
		$options = get_option('tlsa_settings');
		$syslog = new TLSA_Plugin_Syslog(
			$options['syslogHost'],
			$options['syslogPort'],
			$options['syslogDataId'],
			$options['syslogProtocol'][0],
			$options['syslogCEF'][0]
		);
		global $current_user; wp_get_current_user();

		$result = $syslog->testSettings(
			$current_user->user_login, 
			get_site_url()
		);
		
		if ($result == 1)
			$_SESSION['syslog_test'] = '1';
		
        $url = urldecode( $_POST['_wp_http_referer'] );
        wp_safe_redirect( $url );
        exit;
	}

	public function tslaSettingsPage() { 
		$redirect = urlencode( remove_query_arg( 'sys', $_SERVER['REQUEST_URI'] ) );
		$redirect = urlencode( $_SERVER['REQUEST_URI'] );

		?><div class="wrap">
			<h2>Settings</h2>
			<?php
				if (isset($_SESSION['syslog_test'])) {
					if ('1' === $_SESSION['syslog_test']) {
						$this->adminNotice('Syslog test OK.', TRUE);
					} else {
						$this->adminNotice('Syslog test FAILED!', FALSE);
					}
					unset($_SESSION['syslog_test']);
				}
				else if ( isset( $_GET['settings-updated'] ) && sanitize_text_field($_GET['settings-updated']) ){
					  $this->adminNotice('Settings Updated.', TRUE);
				} 
			?>
			<form method="post" action="options.php">
				<?php
					settings_fields( 'tlsa_settings' );
					do_settings_sections( 'tlsa_settings' );
					?><input type="button" value="Test Broadcast" class="button button-default" onclick="jQuery('#tsla_syslog_form').submit();" style="float:left;margin:20px 10px 0 0;"><?php
					submit_button();
				?>
			</form>
			<form action="<?php echo admin_url('admin-post.php'); ?>" method="post" style="float:left;" id="tsla_syslog_form">
				<?php wp_nonce_field( 'testSyslog', 'testSyslog' . '_nonce', FALSE ); ?>
				<input type="hidden" name="_wp_http_referer" value="<?php echo $redirect; ?>">
				<input type="hidden" name="action" value="testSyslog">
			</form>

		</div> <?php
	}

	public function adminNotice($messaage, $outcome) { 
		$className = ($outcome === TRUE) ? "notice-success" : "notice-error";
	?>
        <div class="notice <?php echo $className ?> is-dismissible">
            <p><?php echo $messaage ?></p>
        </div>
	<?php
    }
}