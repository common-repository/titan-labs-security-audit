<?php
/*
Plugin Name: Titan Labs Security Audit Plugin
Description: Identify WordPress security concerns before they become an issue. Similar to Windows Event Log and Linux Syslog. Optional integration with Titan View. Use the Log Viewer included to see all the security messages.
Version: 1.0.0
Author: Titan Labs
Author URI: https://titan-labs.co.uk/
License: GPLv2
*/

define( "TSLA_PLUGIN_VERSION", "1.0.0");
define( "TLSA_POST_TYPE", "tlsa_audit");
define( "TLSA_TAXONOMY_TYPE", "tlsa_log_type");

class TLSA_Plugin {

    public function __construct() {
    }
	
	public function init() {
		
		require_once plugin_dir_path( __FILE__ ) . 'includes/syslog.php';

		require_once plugin_dir_path( __FILE__ ) . 'includes/taxonomy.php';
		$tax = new TLSA_Plugin_Taxonomy();
		$tax->init();

		require_once plugin_dir_path( __FILE__ ) . 'includes/posttype.php';
		$ctp = new TLSA_Plugin_PostTypes();
		$ctp->init();

		require_once plugin_dir_path( __FILE__ ) . 'includes/messages.php';
		$msg = new TLSA_Plugin_Messages();
		$msg->init();

		require_once plugin_dir_path( __FILE__ ) . 'includes/logger.php';
		$log = new TLSA_Plugin_Logger();
		$log->init();

		if (is_admin()) {
			require_once plugin_dir_path( __FILE__ ) . 'admin/settings.php';
			$admin = new TLSA_Plugin_Admin_Settings();
			$admin->init();
			
			require_once plugin_dir_path( __FILE__) . 'includes/dashboard.php';
			$dashboard = new TLSA_Plugin_Dashboard();
			$dashboard->init();
		}

		register_activation_hook(__FILE__, array($this, 'pluginActivation'));
		register_deactivation_hook(__FILE__, array($this, 'plugDeactivation'));
		add_action('tsla_cleanup', array($this, 'purgeEvents'));
		add_action('wp_head', array($this,'insertHeader'));
	}

	public function pluginActivation(){
		wp_schedule_event( time(), 'hourly', 'tsla_cleanup' );
	}
	
	public function plugDeactivation() {
		wp_clear_scheduled_hook( 'tsla_cleanup' );
	}
	
	function purgeEvents() {
		$options = get_option('tlsa_settings');
		$duration = $options['histDuration'][0];

		if ($duration === '0') {
			return;
		}

		$args = array(
			'fields'         => 'ids',
			'post_type'      => array( TLSA_POST_TYPE ),
			'posts_per_page' => '-1',
			'date_query'     => array('column'  => 'post_date', 'before' => '-' . $duration . ' days')
        );
		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				wp_delete_post(get_the_ID(),true);
			}    
		} else {
			return false;
		}
		die();
		wp_reset_postdata();
	}
	
	public function insertHeader() {
		?>  <meta name="generator" content="Powered by Titan Labs Security Audit Plugin - Identify WordPress security concerns before they become an issue."/> <?php
	}
}

$tlsaPlugin = new TLSA_Plugin();
$tlsaPlugin->init();
