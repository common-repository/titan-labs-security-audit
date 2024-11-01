<?php

class TLSA_Plugin_Logger {

	protected $current_plugins 		= array();
	protected $current_themes 		= array();
	protected $post_types_disregard = array(TLSA_POST_TYPE, 'attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'custom_css', 'auto-draft');
	protected $saved_post 			= null;

	public function logEvent($event, $type, $message, $user = null){
		
		if (!$user) {
			$current_user = wp_get_current_user();
			if ( (! $current_user->ID) && $event == '1001') return; // don't log empty logouts
		} else {
			$current_user = $user;
		}
		$userName = $current_user->user_login;

		$term = get_term_by('name', $event , TLSA_TAXONOMY_TYPE);
		if ($term) {
			$enabled  = get_term_meta( $term->term_id, 'enabled', true );
			$severity = get_term_meta( $term->term_id, 'severity', true );
			
			if ($enabled && $enabled == 'true') {
				
				$id = wp_insert_post(array(
					'post_type'		=> TLSA_POST_TYPE,
					'post_title'    => $term->description,
					'post_status'   => 'publish',
					'post_author'   => $current_user->ID,
					'meta_input'   	=> array(
						'severity' 	=> $severity,
						'ip'		=> $this->get_the_user_ip(),
						'type'		=> $type,
						'message'	=> $message,
					),
					'tax_input'    => array(
						TLSA_TAXONOMY_TYPE => array ( $term->name )
					),
				));
				
				wp_set_object_terms( $id , array ( $term->name ), TLSA_TAXONOMY_TYPE );
				
				$options = get_option('tlsa_settings');

				$sysLogEnabled = $options['syslogEnabled'][0];
				if ($sysLogEnabled == 'yes') {
					wp_schedule_single_event( time() + 30, 'tsla_send_syslog', array( $id ) ); 
				}
			}
		}
	}
	
	public function logUserEvent( $user_id, $event ){
		$this->logUserEventByUser(get_userdata( $user_id ), $event);
	}
	
	public function logUserEventByUser( $user, $event ){
		$roles 		= is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles;
		$message 	= "Username={$user->user_login}, first name={$user->user_firstname}, last name={$user->user_lastname}, email={$user->user_email}, roles=[{$roles}]";

		$this->logEvent($event, 'User', $message);
	}
	
	public function logPluginEventByUser($event, $plugin, $plugin_path, $file = null ){
		$message 		= '';
		if ($file) {
			$message 	= "File={$file}, ";
		}

		$message 		= "Plugin={$plugin['Name']}, version={$plugin['Version']}, uri={$plugin['PluginURI']}, path={$plugin_path}";
		$this->logEvent($event, 'Plugin', $message);
	}

	public function logThemeEventByUser($event, $theme, $file = null){
		$message 		= '';
		if ($file) {
			$message 	= "File={$file}, ";
		}
		
		$message 		.= "Theme={$theme['Name']}, version={$theme['Version']}, uri={$theme['ThemeURI']}, path={$theme->get_stylesheet_directory_uri()}";
		$this->logEvent($event, 'Theme', $message);
	}
	
	public function logMediaEventByUser($event, $attachment_id){
		$src		= wp_get_attachment_url($attachment_id);
		$message	= 'File=' . basename($src) . ', path=' . dirname($src);
		$this->logEvent($event, 'Media', $message);
	}

	public function logPostEventByUser($event, $post_id){
		$post = get_post( $post_id );

		if (!in_array($post->post_type, $this->post_types_disregard, true)) {
			if ($event === '5002' && ($post->post_title === 'auto-draft' || $post->post_title === 'Auto Draft')) { // Ignore wordpress backend tidy events
				return;
			}
			
			$message = "Id={$post->ID}, type={$post->post_type}, title={$post->post_title}, status={$post->post_status}, date=" . get_the_time('d/m/Y H:i:s', $post->ID) . ", slug=" . get_permalink($post->ID);
			$this->logEvent($event, 'Post', $message);
		}
	}
	
	public function init() {
		add_action('tsla_send_syslog', array($this, 'send_syslog'), 10, 1);
		add_action('admin_init', array($this, 'adminInit'));
		add_action('pre_post_update', array($this, 'savePreUpdatePost'), 10, 2);

		add_action('wp_login', function($userName, $user) { $this->logEvent('1000', 'User', '', $user); }, 10, 2); 								// 1000 Successful user login
		add_action('clear_auth_cookie', function() { $this->logEvent('1001', 'User', '' ); }, 10, 0); 			 								// 1001 User is logging out
		add_action('wp_login_failed', array($this, 'loginFailed'), 10, 1);																		// 1002 Login failed, 1003 Login failed / non existing user
		//add_action('wp_login_blocked', function($userName) { $this->logEvent('1004', 'User', $userName); }, 10, 1);							// 1004 Login blocked
		add_action('password_reset', function($user, $password) { $this->logEvent('1005', 'User', '', $user); }, 10, 2 );						// 1005 Password reset
		add_action('user_register', function ($user_id) { $this->logUserEvent($user_id, (is_user_logged_in() ? '1011' : '1010')); });			// 1010 New user registered, 1011 New user created
		add_action('delete_user', function($user_id) { $this->logUserEvent( $user_id, '1012'); });												// 1012 User deleted
		add_action('profile_update', array( $this, 'userUpdated' ), 10, 2 );																	// 1020,1023,1024,1025,1026
		add_action('set_user_role', array( $this, 'roleChanged' ), 10, 3 );																		// 1027 User changed another user's role
		add_action('edit_user_profile', array( $this, 'editProfile' ), 10, 1 ); 																// 1028 User opened another user' profile
		add_action('shutdown', array($this, 'adminShutdown'));																					// 2000,2003,2004,3000,3003,3004 Plugin/theme install/remove/update
		add_action('activate_plugin', function($plugin) { $this->pluginChange($plugin, '2001'); }, 10 ,1);										// 2001 Plugin activated
		add_action('deactivate_plugin', function($plugin) { $this->pluginChange($plugin, '2002'); }, 10 ,1);									// 2002 Plugin deactivated
		add_action('switch_theme', function($new_name, $new_theme, $old_theme) { $this->logThemeEventByUser('3001', $new_theme); }, 10, 3);		// 3001 Theme activated
		add_action('delete_attachment', function($attachment_id) { $this->logMediaEventByUser('4000', $attachment_id); }, 10, 1);				// 4000 Media deleted
		add_action('add_attachment', array($this, 'mediaUploaded'), 10, 1);																		// 4001 Media uploaded
		add_action('wp_trash_post', function($post_id) { $this->logPostEventByUser('5000', $post_id); }, 10, 1);								// 5000 User moved a post to trash
		add_action('untrash_post', function($post_id) { $this->logPostEventByUser('5001', $post_id); }, 10, 1);									// 5001 User restored a post from trash
		add_action('delete_post', function($post_id) { $this->logPostEventByUser('5002', $post_id); }, 10, 1);									// 5002 User permanently removed a post from bin
		add_action('save_post', array($this, 'postSave'), 10, 3);																				// 5010,5011,5012,5013
		add_action('automatic_updates_complete', array($this, 'autoUpdate'), 10, 1);															// 9000 WordPress was updated (automatic)
		add_filter('template_redirect', array($this, 'nonExistPage'));																			// 9001 Non existent page was requested
		add_action('update_option_admin_email', array($this, 'adminEmail'), 10, 3 );															// 9004 admin email changed
	}
		
	public function adminInit(){
		$this->current_plugins 	= get_plugins();
		$this->current_themes  	= wp_get_themes();
		$post_array   			= filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
		$get_array    			= filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
		$server_array 			= filter_input_array(INPUT_SERVER);
		$action  			 	= isset($post_array['action']) ? wp_unslash($post_array['action']) : false;
		$actype 				= '';

		if (isset($get_array['action']) && $get_array['action']==='do-core-upgrade' && isset($post_array['version'])) {
			$before = get_bloginfo('version');
			$after 	= $post_array['version'];
			
			if ($before != $after) {
				$message = "Version before={$before}, after={$after}";
				$this->logEvent('9000', 'System', $message);
			}
		}

		if ($action ==='edit-theme-plugin-file') {
			$nonce   			= isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : false;
			$file    			= isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : false;
			$referer 			= isset($_POST['_wp_http_referer'] ) ? sanitize_text_field(wp_unslash($_POST['_wp_http_referer'])) : false;
			$referer 			= remove_query_arg(array('file', 'theme', 'plugin', 'Submit'), $referer);
			$referer 			= basename($referer, '.php');
			
			if ($referer === 'plugin-editor' && wp_verify_nonce($nonce, 'edit-plugin_' . $file)) {
				$plugin_name	= isset($_POST['plugin']) ? sanitize_text_field(wp_unslash( $_POST['plugin'])) : false;
				$plugin			= $this->current_plugins[$plugin_name];
				$this->logPluginEventByUser('2010', $plugin, plugins_url() . '/' . $plugin_name, $file);
			
			} elseif ($referer === 'theme-editor') {
				$stylesheet 	= isset($_POST['theme']) ? sanitize_text_field( wp_unslash( $_POST['theme'])) : false;
				if (! wp_verify_nonce($nonce, 'edit-theme_' . $stylesheet . '_' . $file)) {
					return;
				}
				$theme 	= $this->current_themes[$stylesheet];
				$this->logThemeEventByUser('3010', $theme, $file);
			}
		}
		
		if (!current_user_can('manage_options') && isset($post_array['_wpnonce'] ) && !wp_verify_nonce($post_array['_wpnonce'], 'update')) {
			return;
		}
		if (!empty($server_array['SCRIPT_NAME'])) {
			$actype = basename($server_array['SCRIPT_NAME'], '.php' );
		}

		// Registration?
		if ($actype === 'options' && wp_verify_nonce($post_array['_wpnonce'], 'general-options') && (get_option('users_can_register') xor isset($post_array['users_can_register']))) {
			$prev 	= get_option( 'users_can_register' ) ? 'enabled' : 'disabled';
			$new 	= isset($post_array['users_can_register']) ? 'enabled' : 'disabled';

			if ($prev !== $new) {
				$this->logEvent('9002', 'System', "Setting={$new}");
			}
		}
		// Default role?
		if ($actype === 'options' && wp_verify_nonce($post_array['_wpnonce'], 'general-options') && !empty($post_array['default_role'])) {
			$prev 	= get_option('default_role');
			$new 	= trim($post_array['default_role']);
			
			if ($prev != $new) {
				$this->logEvent('9003', 'System', "Setting={$new}");
			}
		}
	}
	
	public function adminEmail($old_value, $value, $option) {
		if (!empty( $old_value ) && !empty( $value) && !empty( $option ) && $option === 'admin_email') {
			if ( $old_value != $value ) {
				$this->logEvent('9004', 'System', "Setting={$value}");
			}
		}
	}
	
	public function autoUpdate($update_results) {
		if (isset($update_results['core'][0])) {
			$auto   = $update_results['core'][0];
			$before = get_bloginfo( 'version' );

			$message = "Version before={$before}, after={$auto->item->version}, type=automatic";
			$this->logEvent('9000', 'System', $message);
		}
	}

	public function loginFailed($username) {
		$user  = get_user_by( 'login', $username );
		if ( $user ) {
			$this->logEvent('1002', 'User', '', $user);
		} else {
			$this->logEvent('1003', 'User', $username);
		}
	}
	
	public function userUpdated( $user_id, $old_userdata ) {
		$new_user = get_userdata( $user_id );

		if ( $old_userdata->display_name !== $new_user->display_name ) {
			$this->logUserEventByUser($new_user, '1020');
		}

		if ( $old_userdata->user_pass !== $new_user->user_pass ) {
			$this->logUserEventByUser($new_user, (get_current_user_id() === $user_id ? '1023' : '1024'));
		}
		
		if ( $old_userdata->user_email !== $new_user->user_email ) {
			$this->logUserEventByUser($new_user, (get_current_user_id() === $user_id ? '1025' : '1026'));
		}
	}
	
	public function roleChanged( $user_id, $new_role, $old_roles ){
		$user		= get_userdata( $user_id );
		$new_roles 	= $user->roles;

		if ( $old_roles !== $new_roles ) {
			$this->logUserEventByUser($user, '1027');
		}
	}
	
	public function editProfile( $user ){
		$current_user = wp_get_current_user();

		if ( $user && $current_user && ( $user->ID !== $current_user->ID ) && !isset( $_GET['updated'] ) ) {
			$this->logUserEventByUser($user, '1028');
		}
	}
	
	public function adminShutdown(){
		$post_array  	= filter_input_array( INPUT_POST, FILTER_SANITIZE_STRING );
		$get_array   	= filter_input_array( INPUT_GET, FILTER_SANITIZE_STRING );
		$script_name 	= isset($_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field(wp_unslash($_SERVER['SCRIPT_NAME'])) : false;
		$action 		= '';
		$action_type 	= '';
		
		if (isset($get_array['action']) && $get_array['action'] != '-1') {
			$action = $get_array['action'];
		} elseif (isset( $post_array['action']) && $post_array['action'] != '-1') {
			$action = $post_array['action'];
		}

		if (isset($get_array['action2']) && $get_array['action2'] != '-1') {
			$action = $get_array['action2'];
		} elseif (isset($post_array['action2']) && $post_array['action2'] != '-1') {
			$action = $post_array['action2'];
		}
		
		if (!empty($script_name)) {
			$action_type = basename( $script_name, '.php' );
		}
		$is_plugins = 'plugins' === $action_type;

		// New Plugin(s)
		if (in_array($action, array('install-plugin', 'upload-plugin')) && current_user_can('install_plugins')) {
			$new_plugins 	= get_plugins();
			$plugin 		= array_values(array_diff(array_keys($new_plugins), array_keys($this->current_plugins)));
			
			if (count($plugin) == 1) {
				$this->logPluginEventByUser('2000', $new_plugins[$plugin[0]], plugins_url() . '/' . $plugin[0]);
			}
		}
		
		// Delete plugin(s)
		if ( in_array($action, array('delete-plugin')) && current_user_can('delete_plugins') && isset($post_array['plugin'])) {
			$old_plugin	= $this->current_plugins[$post_array['plugin']];

			$this->logPluginEventByUser('2003', $old_plugin, plugins_url() . '/' . $post_array['plugin']);
		}
		
		// Update plugin(s)
		if ( in_array( $action, array( 'upgrade-plugin', 'update-plugin', 'update-selected' ) ) && current_user_can( 'update_plugins' ) ) {

			$plugins = array();

			if (isset($get_array['plugins'])) {
				$plugins = explode(',', $get_array['plugins']);
			} elseif (isset($get_array['plugin'])) {
				$plugins[] = $get_array['plugin'];
			}

			if ( isset($post_array['plugins'])) {
				$plugins = explode(',', $post_array['plugins']);
			} elseif (isset($post_array['plugin'])) {
				$plugins[] = $post_array['plugin'];
			}
			
			if (isset($plugins)) {
				foreach ($plugins as $plugin_file) {
					$plugin	= $this->current_plugins[$plugin_file];
					$this->logPluginEventByUser('2004', $plugin, plugins_url() . '/' . $plugin_file);
				}
			}
		
		}
	
		// New theme
		if (in_array($action, array('install-theme', 'upload-theme')) && current_user_can('install_themes')) {
			$new_themes = array_diff( wp_get_themes(), $this->current_themes );
			foreach ($new_themes as $name => $theme) {
				$this->logThemeEventByUser('3000', $theme);
			}
		}
		
		// Delete theme
		if (in_array($action, array('delete-theme')) && current_user_can('install_themes')) {
			$removed_themes = $this->current_themes;
			foreach ( $removed_themes as $i => $theme ) {
				if ( file_exists( $theme->get_template_directory() ) ) {
					unset( $removed_themes[ $i ] );
				}
			}
			foreach ( $removed_themes as $index => $theme ) {
				$this->logThemeEventByUser('3003', $theme);
			}
		}
		
		// Update theme(s)
		if (in_array($action, array('upgrade-theme', 'update-theme', 'update-selected-themes')) && current_user_can('install_themes')) {
			
			$themes = array();

			if (isset($get_array['slug']) || isset($get_array['theme'])) {
				$themes[] = isset($get_array['slug']) ? $get_array['slug'] : $get_array['theme'];
			} elseif (isset($get_array['themes'])) {
				$themes = explode(',', $get_array['themes']);
			}

			if (isset($post_array['slug']) || isset($post_array['theme'])) {
				$themes[] = isset($post_array['slug']) ? $post_array['slug'] : $post_array['theme'];
			} elseif (isset($post_array['themes'])) {
				$themes = explode(',', $post_array['themes']);
			}
			
			if (isset($themes)) {
				foreach ($themes as $theme_name) {
					$theme = wp_get_theme($theme_name);
					$this->logThemeEventByUser('3004', $theme);
				}
			}
		}
	}
	
	public function pluginChange($plugin, $event){
		if (in_array($plugin, $this->current_plugins)) {
			$new_plugin	= $this->current_plugins[$plugin];
			$this->logPluginEventByUser($event, $new_plugin, plugins_url() . '/' . $plugin);
		}
	}

	public function themeChange($new_name, $new_theme, $old_theme){
		$this->logThemeEventByUser('3001', $new_theme);
	}
	
	public function mediaUploaded( $attachment_id ) {
		$posted_array 	= filter_input_array( INPUT_POST, FILTER_SANITIZE_STRING );
		$action 		= isset($posted_array['action']) ? $posted_array['action'] : '';

		if ($action !== 'upload-theme' && $action !== 'upload-plugin') {
			$this->logMediaEventByUser('4001', $attachment_id);
		}
	}

	public function savePreUpdatePost($post_id, $data) {
		$post = get_post($post_id);
		
		if (! empty($post) && $post instanceof WP_Post && $post->post_type !== TLSA_POST_TYPE) {
			$this->saved_post  = $post;
		}
	}

	public function postSave($post_id, $post, $update){
		
		if ((!$update) || in_array($post->post_type, $this->post_types_disregard, true) || empty($post->post_type) || wp_is_post_autosave($post_id)) {
			return;
		}
		
		// https://github.com/WordPress/gutenberg/issues/15094
		if (defined('REST_REQUEST') && REST_REQUEST) {
			$this->logPostSave($post);
			set_transient( 'tlsa_posted_flag', 'done', 10 );
			return;
		} else {
			if (get_transient('tlsa_posted_flag')===false) {
				$this->logPostSave($post);
			}
		}
	}
	
	public function logPostSave($post){
		$event = '';

		if ($this->saved_post->comment_status !== $post->comment_status) {
			$event = $post->comment_status === 'open' ? '5020' : '5021';
			$this->logPostEventByUser($event, $post->ID);
		}
		
		if ($this->saved_post->ping_status !== $post->ping_status) {
			$event = $post->ping_status === 'open' ? '5022' : '5023';
			$this->logPostEventByUser($event, $post->ID);
		}
		
		$oldVis = 'public';
		$newVis = 'public';
		$event  = '5030';

		if ($this->saved_post->post_password) {
			$oldVis = 'password';
		} elseif ($this->saved_post->post_status === 'private') {
			$oldVis = 'private';
		}
		
		if ($post->post_password) {
			$newVis = 'password';
			$event  = '5032';
		} elseif ($post->post_status === 'private') {
			$newVis = 'private';
			$event  = '5031';
		}
		
		if ($oldVis !== $newVis){
			$this->logPostEventByUser($event, $post->ID);
		}

		$event  = '';
		switch ($post->post_status ) {
			case 'publish':
				$event = '5010';
				break;
			case 'draft':
				$event = '5011';
				break;
			case 'future':
				$event = '5012';
				break;
			case 'pending':
				$event = '5013';
				break;
		}
		if (!empty($event)) {
			$this->logPostEventByUser($event, $post->ID);
		}
	}
	
	public function nonExistPage(){
		global $wp_query;
		
		if (! $wp_query->is_404) {
			return;
		}

		$requested = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_STRING);
		if (! empty($requested)) {
			$url_404 = home_url() . $requested;
		} else{
			return;
		}

		$url_404 = untrailingslashit($url_404);
		$message = "Requested page={$url_404}";
		$this->logEvent('9001', 'System', $message);
	}
	
	public function get_the_user_ip() {
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			//check ip from share internet
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			//to check ip is pass from proxy
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}
	
	public function send_syslog( $id) {
		$post = get_post( $id );
		if ($post) {
			$authorName = '';
			$author = get_user_by('id', $post->post_author);
			if ($author) {
				$authorName = $author->user_login;
			}

			$ip 		= get_post_meta($id, 'ip', true);
			$severity 	= get_post_meta($id, 'severity', true);
			$extra 		= get_post_meta($id, 'message', true);
			$terms 		= get_the_terms( $post->ID, TLSA_TAXONOMY_TYPE);
			$event 		= $terms[0]->name;
			$options 	= get_option('tlsa_settings');
			$message 	= $post->post_title;
			$data 		= "";
			
			if ($options['syslogCEF'][0] == 'yes'){
				$data 	= "src={$ip} suid={$options['syslogDataId']} suser={$authorName}";
				if (!empty($extra)) {
					$extra 	= str_replace('=',':', $extra);
					$data 	.=  " msg={$extra}";
				}
			} else {
				$data 	= "src=\"{$ip}\" suid=\"{$options['syslogDataId']}\" suser=\"{$authorName}\" msg=\"{$extra}\"";
				if (!empty($extra)) $data .=  " msg=\"{$extra}\"";
			}
			
			$syslog = new TLSA_Plugin_Syslog(
				$options['syslogHost'],
				$options['syslogPort'],
				$options['syslogDataId'],
				$options['syslogProtocol'][0],
				$options['syslogCEF'][0]
			);
			$syslog->sendMessage(get_site_url(), $message, $event, $severity, $data);
		}
	}
	
}
