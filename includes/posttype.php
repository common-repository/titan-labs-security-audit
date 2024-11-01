<?php

class TLSA_Plugin_PostTypes {

	protected $allowed_severities = array('critical', 'high', 'medium', 'low', 'info');
	protected $allowed_types = array('media', 'plugin', 'post', 'system', 'theme', 'user');

	public function __construct() {
    }

	public function init() {
		add_action('init', array($this, 'create_posttypes'));
		add_filter('all_admin_notices', array ($this, 'add_logo'));
		add_filter('manage_edit-' . TLSA_POST_TYPE . '_columns', array($this, 'add_columns'));
		add_filter('manage_' . TLSA_POST_TYPE . '_posts_custom_column', array($this, 'add_column_content'),10,3);
		add_filter('manage_edit-' . TLSA_POST_TYPE . '_sortable_columns', array($this, 'add_column_sorting'));
		add_action('pre_get_posts', array($this,'set_orderby'));
		add_action('restrict_manage_posts', array($this,'manage_posts'),10,1);
		add_filter('parse_query', array($this,'change_query'),10,1);
		add_filter('posts_clauses', array ($this,'set_clauses'), 10, 2);
		add_filter('bulk_actions-edit-' . TLSA_POST_TYPE, array($this,'add_bulk_actions'));
		add_filter('handle_bulk_actions-edit-' . TLSA_POST_TYPE, array($this,'bulk_action_handler'), 10, 3 );
		add_action('admin_notices', array($this, 'bulk_action_notices'));
	}
	
	function create_posttypes() {
		register_post_type( TLSA_POST_TYPE,
			array(
					'labels' 			=> array(
					'name' 				=> __( 'Security Audit' ),
					'singular_name' 	=> __( 'Audit Log' ),
					'all_items' 		=> __('View Log'),
				),
				'public' 				=> true,
				'has_archive' 			=> true,
				'rewrite' 				=> array('slug' => 'auditlog'),
				'show_in_rest'			=> false,
				'show_in_menu' 			=> current_user_can('manage_options'),
				'menu_icon' 			=> $this->get_icon(),
				'menu_position' 		=> 2,
				'capability_type'       =>  'post',
				'capabilities' => array(
					'create_posts' 		=> false,
					'delete_posts' 		=> false,
					'publish_posts' 	=> false,
				),
				'map_meta_cap' 			=> true,
				'supports' 				=> array('title', 'author', ),
				'taxonomies' 			=> array(TLSA_TAXONOMY_TYPE),
			)	
		);
	}
	
	function get_icon(){
		global $wp_version;
		if ( version_compare( $wp_version, '3.8', '>' ) ) {
			return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 205 205" width="20" height="20"><path id="Path 0" class="shp0" fill="black" d="M96.45 0.82C95.93 1.27 90.1 4.03 83.5 6.96C76.9 9.89 68.35 14.08 64.5 16.29C60.65 18.49 53 23.46 47.5 27.33C42 31.19 36.38 35.68 35 37.3C33.63 38.92 31.96 41.43 31.3 42.87C30.48 44.66 30.08 52.54 30.05 67.5L30.01 89.5C22.96 123.6 20.41 135.3 19.79 137.5C19.17 139.7 18.28 141.95 17.81 142.5C17.35 143.05 16.97 145.19 16.98 147.25C16.99 149.31 17.34 151.11 17.75 151.25C18.16 151.39 19.85 154.1 21.5 157.28C23.15 160.46 27.65 166.49 31.5 170.69C35.35 174.89 44.33 183.31 51.45 189.41C58.58 195.51 65.78 201.52 67.45 202.77C69.13 204.01 70.95 205.03 71.5 205.02C72.05 205.02 72.84 201.07 73.25 196.26C73.66 191.44 74.44 179.85 74.99 170.5C75.53 161.15 76.7 145.4 77.57 135.5C78.44 125.6 79.01 115.7 78.83 113.5C78.55 110.06 78.01 109.26 75 107.78C73.08 106.84 67 104.41 61.5 102.38C56 100.35 49.36 97.63 46.75 96.34L42 94C43.04 87.41 44.05 82.13 44.92 78L46.51 70.5L160.5 70.5C163.96 87.55 164.82 92.95 164.63 93.5C164.45 94.05 156.53 97.65 147.03 101.5C137.52 105.35 129.24 109.12 128.63 109.89C127.86 110.83 127.71 114.2 128.16 120.39C128.53 125.4 129.56 138.95 130.45 150.5C131.34 162.05 132.61 178.93 133.28 188C134.13 199.53 134.86 204.5 135.7 204.5C136.37 204.5 141.99 200.48 148.2 195.56C154.42 190.65 164.01 182.1 169.52 176.56C175.02 171.03 181.44 163.57 183.78 160C186.13 156.41 188.3 151.71 188.63 149.5C189.05 146.74 188.27 141.46 186.14 132.5C184.43 125.35 181.94 114.33 180.59 108C178.6 98.69 178.02 92.4 177.53 75C177.21 63.17 176.56 50.58 176.1 47C175.31 40.81 175.01 40.26 169.85 35.5C166.87 32.75 159.95 27.49 154.47 23.82C148.98 20.14 140.22 14.96 135 12.3C129.78 9.64 121.79 5.79 117.25 3.73C110.77 0.8 107.76 0 103.2 0C100.01 0 96.97 0.37 96.45 0.82Z" /></svg>');
		} else {
			return plugins_url('../assets/img/titan-icon.png', __FILE__); //'dashicons-shield'
		}
	}
	
	function add_logo (){
		$screen = get_current_screen();
		if ( is_admin() && $screen->post_type != TLSA_POST_TYPE) return;

		$img = plugins_url('../assets/img/titan-labs-logo.png', __FILE__);
		echo "<div class='tlsa-header-image'><a href='https://titan-labs.co.uk/' title='Visit Titan Labs - '><img src='{$img}' alt='Titan Labs' /></a></div>";
	}
	
	function add_columns( $columns ) {
		$new_columns = array(
			'logdate'							=> 'Date',
			'taxonomy-' . TLSA_TAXONOMY_TYPE	=> 'Event Type',
			'severity'							=> 'Severity',
			'logauthor'							=> 'User',
			'ip' 								=> 'IP',
			'type'								=> 'Type',
			'logtitle' 							=> 'Title',
			'message'							=> 'Message',
		);
		
		$options = get_option('tlsa_settings');
		$sysLogEnabled = $options['syslogEnabled'][0];
		
		if ($sysLogEnabled == 'yes') {
			$new_columns = array_merge(array('cb' => '<input type="checkbox" />'), $new_columns);
		}

		return $new_columns;
	}
	
	function add_column_content($column_name, $post_id){

		$result = '';
		
		if ($column_name == 'ip' || $column_name == 'severity' || $column_name == 'type' || $column_name == 'message') {
			$result = get_post_meta($post_id, $column_name, true);
		}
		
		if ($column_name == 'logdate') {
			$result = get_the_time( 'd/m/Y H:i:s', $post_id );
		}
		
		if ($column_name == 'logtitle') {
			$post 				= get_post($post_id);
			$result				= $post->post_title;
		}

		if ($column_name == 'logType') {
			$terms 				= get_the_terms( $post_id, TLSA_TAXONOMY_TYPE );
			
			if ($terms) {
				$term 			= array_shift($terms);
				$result			= $term->name;
			}
		}

		if ($column_name == 'logauthor') {
			$post 				= get_post($post_id);
			$user_profile_url 	= get_edit_user_link($post->post_author);
			$avatar 			= get_avatar($post->post_author, 24);;
			$user 				= get_userdata($post->post_author);

			if ($user) {
				global $wp_roles;
				
				$user_roles 	= $user->roles;
				$user_role 		= array_shift($user_roles);
				$user_role_name = $wp_roles->roles[ $user_role ]['name'];
				$result 		= "{$avatar} <a href='{$user_profile_url}'>{$user->display_name}</a> {$user_role_name}";
			} else {
				$result = $avatar;
			}
		}
		
		if ($column_name == 'severity') {
			$icon  = '';
			
			switch ($result) {
				case 'info':
					$icon = 'info low';
					break;
				case 'low':
					$icon = 'shield low';
					break;
				case 'medium':
					$icon = 'shield medium';
					break;
				case 'high':
					$icon = 'shield high';
					break;
				case 'critical':
					$icon = 'warning high';
					break;
			}
			$result = "<span class='dashicons dashicons-{$icon}'></span>";
		}
		
		echo $result;
	}

	function add_column_sorting( $columns ) {
		$columns['logdate'] 						= 'date';
		$columns['logauthor'] 						= 'author';
		$columns['logtitle'] 						= 'title';
		$columns['taxonomy-' . TLSA_TAXONOMY_TYPE] 	= 'taxonomy-'  . TLSA_TAXONOMY_TYPE;
		$columns['ip'] 								= 'ip';
		$columns['severity'] 						= 'severity';
		$columns['author'] 							= 'author';
		$columns['type'] 							= 'type';
		return $columns;
	}

	function set_orderby( $query ) {
	  if( ! is_admin() || ! $query->is_main_query() && in_array ( $query->get('post_type'), array(TLSA_POST_TYPE) ) ) {
		return;
	  }

	  $order = $query->get('orderby');
	  if ('ip' == $order || 'severity' == $order || 'type' == $order) {
		$query->set( 'orderby', 'meta_value' );
		$query->set( 'meta_key', $order );
	  }
	}
	
	// Add filters
	function manage_posts($post_type){
		if ($post_type == TLSA_POST_TYPE) {
			
			// Taxonomy
			$info_taxonomy = get_taxonomy(TLSA_TAXONOMY_TYPE);
			wp_dropdown_categories(array(
				'show_option_all' => sprintf( __( 'All %s', 'textdomain' ), $info_taxonomy->label ),
				'taxonomy'        => TLSA_TAXONOMY_TYPE,
				'name'            => TLSA_TAXONOMY_TYPE,
				'orderby'         => 'name',
				'selected'        => isset($_GET[TLSA_TAXONOMY_TYPE]) ? sanitize_text_field($_GET[TLSA_TAXONOMY_TYPE]) : '',
				'show_count'      => false,
				'hide_empty'      => true,
			));
			
			// Severity
			if (isset( $_GET['filter-by-severity'] ) && in_array(sanitize_key($_GET['filter-by-severity']), $this->allowed_severities)) {
				$selectedSeverity = sanitize_key($_GET['filter-by-severity']);
			} else {
				$selectedSeverity = "-1";
			}
			
			echo '<select class="" id="filter-by-severity" name="filter-by-severity">';
			echo sprintf('<option value="-1">%1$s</option>', __('All Severities', 'your-text-domain'));
			echo sprintf('<option value="info" %1$s>Information</option>', ($selectedSeverity=="info"?"selected":"") );
			echo sprintf('<option value="low" %1$s>Low</option>', ($selectedSeverity=="low"?"selected":"") );
			echo sprintf('<option value="medium" %1$s>Medium</option>', ($selectedSeverity=="medium"?"selected":"") );
			echo sprintf('<option value="high" %1$s>High</option>', ($selectedSeverity=="high"?"selected":"") );
			echo sprintf('<option value="critical" %1$s>Critical</option>', ($selectedSeverity=="critical"?"selected":"") );
			echo '</select>';
			
			// User (Author)
			wp_dropdown_users(array(
				'show_option_all'   => 'All Authors',
				'show_option_none'  => false,
				'name'         	 	=> 'author',
				'selected'      	=> !empty($_GET['author']) && absint($_GET['author']) != 0  ? absint($_GET['author']) : 0,
				'include_selected'  => false,
			));

			// Type
			if (isset( $_GET['filter-by-type'] ) && in_array(sanitize_key($_GET['filter-by-type']), $this->allowed_types)) {
				$selectedType = sanitize_key($_GET['filter-by-type']);
			} else {
				$selectedType = "-1";
			}
			
			echo '<select class="" id="filter-by-type" name="filter-by-type">';
			echo sprintf('<option value="-1">%1$s</option>', __('All Types', 'your-text-domain'));
			echo sprintf('<option value="Media" %1$s>Media</option>', ($selectedType=="media"?"selected":"") );
			echo sprintf('<option value="Plugin" %1$s>Plugin</option>', ($selectedType=="plugin"?"selected":"") );
			echo sprintf('<option value="Post" %1$s>Post</option>', ($selectedType=="post"?"selected":"") );
			echo sprintf('<option value="System" %1$s>System</option>', ($selectedType=="system"?"selected":"") );
			echo sprintf('<option value="Theme" %1$s>Theme</option>', ($selectedType=="theme"?"selected":"") );
			echo sprintf('<option value="User" %1$s>User</option>', ($selectedType=="user"?"selected":"") );
			echo '</select>';
		};		
	}
	
	// Filtering by taxonomy / severity
	function change_query($query){
		
		global $pagenow;
		$q_vars    = &$query->query_vars;

		if ((!is_admin()) || ($pagenow != 'edit.php') || (!isset($q_vars['post_type'])) || ($q_vars['post_type'] != TLSA_POST_TYPE)) {
			return;
		}
		
		// Taxonomy
		if (isset($q_vars[TLSA_TAXONOMY_TYPE]) && is_numeric($q_vars[TLSA_TAXONOMY_TYPE]) && $q_vars[TLSA_TAXONOMY_TYPE] != 0 ) {
			$term = get_term_by('id', $q_vars[TLSA_TAXONOMY_TYPE], TLSA_TAXONOMY_TYPE);
			if ($term) {
				$q_vars[TLSA_TAXONOMY_TYPE] = $term->slug;
			}
		}		

		// Severity
		if (isset( $_GET['filter-by-severity'] ) && in_array(sanitize_key($_GET['filter-by-severity']), $this->allowed_severities)) {
			$severity_name = sanitize_key($_GET['filter-by-severity']);
			$query->query_vars['meta_key'] = 'severity';
			$query->query_vars['meta_value'] = $severity_name;
			$query->query_vars['meta_compare'] = '=';
		}		

		// Type
		if (isset( $_GET['filter-by-type'] ) && in_array(sanitize_key($_GET['filter-by-type']), $this->allowed_types)) {
			$type_name = sanitize_key($_GET['filter-by-type']);
			$query->query_vars['meta_key'] = 'type';
			$query->query_vars['meta_value'] = $type_name;
			$query->query_vars['meta_compare'] = '=';
		}		
	}

	// Sorting by taxonomy
	function set_clauses($clauses, $query){
		global $pagenow;
		global $wpdb;
		$q_vars    = &$query->query_vars;
		
        if(is_admin() && $pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == TLSA_POST_TYPE 
			&& isset($query->query['orderby']) 
			&& $query->query['orderby'] == 'taxonomy-' . TLSA_TAXONOMY_TYPE
			&& (!isset($q_vars[TLSA_TAXONOMY_TYPE]))){
			
            $clauses['join'] .= "
				LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID={$wpdb->term_relationships}.object_id
				LEFT OUTER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)
				LEFT OUTER JOIN {$wpdb->terms} USING (term_id)";
            $clauses['where'] .= "AND (taxonomy = '" . TLSA_TAXONOMY_TYPE . "' OR taxonomy IS NULL)";
            $clauses['groupby'] = "object_id";
            $clauses['orderby'] = "GROUP_CONCAT({$wpdb->terms}.name ORDER BY name ASC)";
            if(strtoupper($query->get('order')) == 'ASC'){
                $clauses['orderby'] .= 'ASC';
            } else{
                $clauses['orderby'] .= 'DESC';
            }
        }

        return $clauses;		
	}
	
	// Bulk re-send option
	function add_bulk_actions( $bulk_array ) {
		unset( $bulk_array[ 'edit' ] );
		
		$options = get_option('tlsa_settings');
		$sysLogEnabled = $options['syslogEnabled'][0];
		
		if ($sysLogEnabled == 'yes') {
			$bulk_array['tlsa_resend'] = 'Re-send SysLog';
		}
		
		return $bulk_array;
	}
	
	// Bulk re-send callback, re-queue the entries
	function bulk_action_handler($redirect, $doaction, $object_ids){
		foreach ( $object_ids as $post_id ) {
			wp_schedule_single_event( time() + 30, 'tsla_send_syslog', array( $post_id )); 
		}
		$redirect = add_query_arg('tlsa_syslog_requeued', count( $object_ids ), $redirect );
		return $redirect;
	}

	// Display summary of bulk action as an admin notice
	function bulk_action_notices(){
		if (! empty($_REQUEST['tlsa_syslog_requeued'])) {
			$num = intval($_REQUEST['tlsa_syslog_requeued']);
			echo "<div id='message' class='updated notice is-dismissible'><p>{$num} log(s) queued for SysLog send.</p></div>";
		}
	}
}