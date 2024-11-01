<?php

class TLSA_Plugin_Taxonomy {

	public function __construct() {
    }

	public function init() {
		add_action( 'init', array($this, 'create_taxonomies'));
	}
	
	function block_inserts($term, $taxonomy) {
		return ( TLSA_TAXONOMY_TYPE === $taxonomy )
			? new WP_Error( 'term_addition_blocked', __( 'You cannot add terms to this taxonomy' ) )
			: $term;
	}
	
	function create_taxonomies() {
		$args = array( 
            'hierarchical'                      => false,  
            'labels' => array(
                'name'                          => _x('Event Type', 'taxonomy general name' ),
                'singular_name'                 => _x('Event Type', 'taxonomy singular name'),
                'search_items'                  => __('Search Event Types'),
                'popular_items'                 => __('Popular Event Type'),
                'all_items'                     => __('All Event Types'),
                'edit_item'                     => __('Edit Event Type'),
                'edit_item'                     => __('Edit Event Type'),
                'update_item'                   => __('Update Event Type'),
                'add_new_item'                  => __('Add New Event Type'),
                'new_item_name'                 => __('New Event Type Name'),
                'separate_items_with_commas'    => __('Seperate Event Tyep with Commas'),
                'add_or_remove_items'           => __('Add or Remove Event Type'),
                'choose_from_most_used'         => __('Choose from Most Used Event Type')
            ),  
            'query_var'                         => true,  
            'rewrite'                           => array('slug' =>'log_type'),
			 'capabilities' 					=> array(
				'manage_terms' 					=> 'manage_options',
				'edit_terms' 					=> 'manage_options',
				'delete_terms' 					=> '',
				'assign_terms' 					=> 'manage_options'
			  ),
			  'show_tagcloud'					=> false,
			  'show_admin_column'				=> true,
        );
		
		if(!current_user_can('manage_options')) {
			$args['show_ui'] = false;
		}
		
        register_taxonomy( TLSA_TAXONOMY_TYPE, array( TLSA_POST_TYPE ), $args );		
		
		$this->add_terms();
		add_action('pre_insert_term', array ( $this, 'block_inserts' ), 0, 2);
		add_action(TLSA_TAXONOMY_TYPE . '_edit_form_fields', array($this,'edit_meta_field'), 10, 2 );
		add_action('edited_' . TLSA_TAXONOMY_TYPE, array($this,'save_meta_field'), 10, 2 ); 

		add_filter('manage_edit-' . TLSA_TAXONOMY_TYPE . '_columns', array($this, 'add_columns'));
		add_filter('manage_' . TLSA_TAXONOMY_TYPE . '_custom_column', array($this, 'add_column_content'),10,3);
		add_filter('manage_edit-' . TLSA_TAXONOMY_TYPE . '_sortable_columns', array($this, 'add_column_sorting') );
		add_action('pre_get_terms', array ($this, 'set_orderby'));
		add_action('get_terms_orderby', array ($this, 'sort_get_orderby'), 10,2);
	}
	
	function add_columns( $columns ) {
		unset($columns['slug']);
		$columns['enabled'] = 'Enabled';
		$columns['severity'] = 'Severity';
		return $columns;
	}
	
	function add_column_sorting( $columns ) {
		$columns['enabled'] = 'enabled';
		$columns['severity'] = 'severity';
		return $columns;
	}
	
	function sort_get_orderby($orderby, $args) {
		if(! is_admin() ) return;

		if ( $args['meta_key'] == 'severity' ||  $args['meta_key'] == 'enabled') {
			return 'meta_value';
		}
		return $orderby;
	}
	
	function set_orderby(\WP_Term_Query $query ) {
		if(! is_admin() ) return;

		$queryVars = $query->query_vars;
		if ($queryVars['orderby'] != 'severity' && $queryVars['orderby'] != 'enabled')
			return;
		
		$args = [
			'meta_key' => $queryVars['orderby'],
			'orderby' => 'meta_value',
		];

		$query->query_vars = array_merge($queryVars, $args);
	}

	function add_column_content($content,$column_name,$term_id){
		$value = get_term_meta( $term_id, $column_name, true );
		$icon  = '';
		$content = $value;
		
		if ($column_name == 'enabled') {
			$icon = ($value == 'true') ? 'yes' : 'no-alt';
		}
		else if ($column_name = 'severity') {
			switch ($value) {
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
		}
		
		if ($icon != '') {
			$content = "<span class='dashicons dashicons-{$icon}'></span>";
		} else {
			$content = $value;
		}
		
		return $content;
	}

	function edit_meta_field($term, $taxonomy) {
		$enabled = get_term_meta( $term->term_id, 'enabled', true );
		$severity = get_term_meta( $term->term_id, 'severity', true );
    ?>
        <tr class="form-field">
			<th scope="row" valign="top"><label for="enabled"><?php _e( 'Enabled' ); ?></label></th>
			<td>
				<input type="hidden" value="false" name="enabled">
				<input type="checkbox" <?php echo ($enabled=="true" ? ' checked="checked" ' : ''); ?> value="true" name="enabled" />
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="severity"><?php _e( 'Severity' ); ?></label></th>
			<td><select class="postform" id="severity" name="severity">
					<option value="info" <?php echo ($severity=="info" ? 'selected' : '')?>><?php _e( 'Information'); ?></option>
					<option value="low" <?php echo ($severity=="low" ? 'selected' : '')?>><?php _e( 'Low'); ?></option>
					<option value="medium" <?php echo ($severity=="medium" ? 'selected' : '')?>><?php _e( 'Medium'); ?></option>
					<option value="high" <?php echo ($severity=="high" ? 'selected' : '')?>><?php _e( 'High'); ?></option>
					<option value="critical" <?php echo ($severity=="critical" ? 'selected' : '')?>><?php _e( 'Critical'); ?></option>
				</select>
			</td>
    </tr>
    <?php
    }
	
	function save_meta_field( $term_id ) {
        if ( isset( $_POST['enabled'] ) ) {
			update_term_meta( $term_id, 'enabled', sanitize_title( $_POST['enabled'] ) );
		}
        if ( isset( $_POST['severity'] ) ) {
			update_term_meta( $term_id, 'severity', sanitize_title( $_POST['severity'] ) );
		}
    }  
	
	function add_terms() {
		$this->add_term('1000', 'true', 'low', 'User logged in');
		$this->add_term('1001', 'true', 'low', 'User logged out');
		$this->add_term('1002', 'true', 'medium', 'Login failed');
		$this->add_term('1003', 'true', 'low', 'Login failed, Invalid user');
		//$this->add_term('1004', 'true', 'medium', 'Login blocked');
		$this->add_term('1005', 'true', 'medium', 'Password reset');
		$this->add_term('1010', 'true', 'high', 'New user registered');
		$this->add_term('1011', 'true', 'critical', 'New user created');
		$this->add_term('1012', 'true', 'critical', 'User deleted');
		$this->add_term('1020', 'true', 'low', 'User changed display name');
		$this->add_term('1023', 'true', 'high', 'User changed password');
		$this->add_term('1024', 'true', 'high', 'User changed another user\'s password');
		$this->add_term('1025', 'true', 'medium', 'User changed email address');
		$this->add_term('1026', 'true', 'medium', 'User changed another user\'s email address');
		$this->add_term('1027', 'true', 'critical', 'User changed another user\'s role');
		$this->add_term('1028', 'true', 'info', 'User opened another user\'s profile');

		$this->add_term('2000', 'true', 'critical', 'User installed a plugin');
		$this->add_term('2001', 'true', 'high', 'User activated a plugin');
		$this->add_term('2002', 'true', 'high', 'User deactivated a plugin');
		$this->add_term('2003', 'true', 'high', 'User uninstalled a plugin');
		$this->add_term('2004', 'true', 'low', 'User updated a plugin');
		$this->add_term('2010', 'true', 'high', 'User updated a plugin file using the editor');

		$this->add_term('3000', 'true', 'critical', 'User installed a theme');
		$this->add_term('3001', 'true', 'high', 'User activated a theme');
		$this->add_term('3003', 'true', 'high', 'User uninstalled a theme');
		$this->add_term('3004', 'true', 'low', 'User updated a theme');
		$this->add_term('3010', 'true', 'high', 'User updated a theme file via the editor');

		$this->add_term('4000', 'true', 'medium', 'User deleted a media file');
		$this->add_term('4001', 'true', 'medium', 'User uploaded a media file');

		$this->add_term('5000', 'true', 'medium', 'User moved a post to bin');
		$this->add_term('5001', 'true', 'low', 'User restored a post from bin');
		$this->add_term('5002', 'true', 'medium', 'User permanently removed a post from bin');
		$this->add_term('5010', 'true', 'low', 'User published a post');
		$this->add_term('5011', 'true', 'info', 'User created a new draft');
		$this->add_term('5012', 'true', 'low', 'User scheduled a post');
		$this->add_term('5013', 'true', 'info', 'User submitted a post for review');
		$this->add_term('5020', 'true', 'low', 'User enabled post comments');
		$this->add_term('5021', 'true', 'low', 'User disabled post comments');
		$this->add_term('5022', 'true', 'low', 'User enabled post trackbacks');
		$this->add_term('5023', 'true', 'low', 'User disabled post trackbacks');
		$this->add_term('5030', 'true', 'medium', 'User changed post visbility to public');
		$this->add_term('5031', 'true', 'medium', 'User changed post visbility to private');
		$this->add_term('5032', 'true', 'medium', 'User changed post visbility to password protected');
		
		$this->add_term('9000', 'true', 'medium', 'WordPress was updated');
		$this->add_term('9001', 'true', 'info', 'Non existent page was requested');
		$this->add_term('9002', 'true', 'critical', 'WordPress setting "Anyone can register" changed');
		$this->add_term('9003', 'true', 'critical', 'WordPress setting "New User Default Role" changed');
		$this->add_term('9004', 'true', 'critical', 'WordPress setting "Administration Email Address" changed');
	}
	
	function add_term($name, $enabled, $severity, $description){
		if(!term_exists($name, TLSA_TAXONOMY_TYPE)) {
			$term = wp_insert_term($name, TLSA_TAXONOMY_TYPE,
			   array(
				 'description' => $description,
				 'slug'        => $name,
			   )
			);
			update_term_meta($term['term_id'], 'enabled', $enabled);
			update_term_meta($term['term_id'], 'severity', $severity);
		}		
	}
}
