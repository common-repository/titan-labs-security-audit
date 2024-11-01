<?php

class TLSA_Plugin_Dashboard {


	public function __construct() {
    }

	public function init() {
		$options 		= get_option('tlsa_settings');
		$widgetEnabled 	= $options['showDashboard'][0];

		if ($widgetEnabled == 'yes') {
			add_action('wp_dashboard_setup', array ($this, 'addWidget'));
		}
	}
	
	function addWidget(){
		if (current_user_can('manage_options')) {
			wp_add_dashboard_widget('tlsa_summary_widget','Security Audit Summary', array($this, 'widgetFunction'));
		}
	}
	
	function widgetFunction(){
	?>
		<div id='tlsa_summary_widget'>
			<h3>Recent Events</h3>
				<table>
					<thead>
						<tr>
							<th width='25%'>Date</th>
							<th width='15%'>User</th>
							<th width='60%'>Event</th>
						</tr>
					</thead>
					<tbody>
						<?php $this->recentEvents(); ?>
					</tbody>
				</table>
		</div>
	<?php
	}
	
	function recentEvents() {
		$recent_posts = wp_get_recent_posts(array('numberposts' => 10, 'post_type' => TLSA_POST_TYPE));
		
		foreach( $recent_posts as $post ){
			echo '<tr><td>'. get_the_time( 'd/m/Y H:i:s', $post['ID'] ) . '</td>'
			. '<td>' . get_the_author_meta( 'display_name', $post['post_author'] ) . '</td>'
			. '<td>' . $post['post_title'] . '</td></tr>';
		}
	}
}


