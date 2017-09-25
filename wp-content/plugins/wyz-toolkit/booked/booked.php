<?php

define('BOOKED_VERSION', '1.9.12');
define('BOOKED_FA_VERSION','4.6.3');
define('BOOKED_FA_ID','fcc8474e79');
define('BOOKED_WELCOME_SCREEN', get_option('booked_welcome_screen',true));
define('BOOKED_DEMO_MODE', get_option('booked_demo_mode',false));
define('BOOKED_PLUGIN_URL', untrailingslashit(plugin_dir_url( __FILE__ )) );
define('BOOKED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define('BOOKED_STYLESHEET_DIR', get_stylesheet_directory());
define('BOOKED_PLUGIN_TEMPLATES_DIR', plugin_dir_path( __FILE__ ) . '/templates/');
define('BOOKED_AJAX_INCLUDES_DIR', plugin_dir_path( __FILE__ ) . '/includes/ajax/');


// Booked Mailer Actions (Added in v1.9.0)
add_action( 'booked_confirmation_email', 'booked_mailer', 10, 3 );
add_action( 'booked_admin_confirmation_email', 'booked_mailer', 10, 5 );
add_action( 'booked_reminder_email', 'booked_mailer', 10, 3 );
add_action( 'booked_admin_reminder_email', 'booked_mailer', 10, 5 );
add_action( 'booked_cancellation_email', 'booked_mailer', 10, 3 );
add_action( 'booked_admin_cancellation_email', 'booked_mailer', 10, 5 );
add_action( 'booked_approved_email', 'booked_mailer', 10, 3 );
add_action( 'booked_registration_email', 'booked_mailer', 10, 3 );

if(!class_exists('booked_plugin')) {
	class booked_plugin {
		/**
		 * Construct the plugin object
		 */
		public function __construct() {

			$this->booked_screens = apply_filters('booked_admin_booked_screens', array('booked-pending','booked-appointments','booked-settings','booked-welcome'));

			require_once(sprintf("%s/post-types/booked_appointments.php", BOOKED_PLUGIN_DIR));
			$booked_appointments_post_type = new booked_appointments_post_type();

			require_once(sprintf("%s/includes/general-functions.php", BOOKED_PLUGIN_DIR));
			require_once(sprintf("%s/includes/shortcodes.php", BOOKED_PLUGIN_DIR));
			require_once(sprintf("%s/includes/widgets.php", BOOKED_PLUGIN_DIR));
			require_once(sprintf("%s/includes/ajax/init.php", BOOKED_PLUGIN_DIR));
			require_once(sprintf("%s/includes/ajax/init_admin.php", BOOKED_PLUGIN_DIR));

			require_once( BOOKED_PLUGIN_DIR . '/addons/booked-frontend-agents/booked-frontend-agents.php' );
			require_once( BOOKED_PLUGIN_DIR . '/addons/booked-woocommerce-payments/booked-woocommerce-payments.php' );
			
			$booked_ajax = new Booked_AJAX();
			$booked_admin_ajax = new Booked_Admin_AJAX();

			add_action('init', array(&$this, 'admin_init'), 9);
			add_action('admin_menu', array(&$this, 'add_menu'));
			add_action('admin_bar_menu', array(&$this, 'add_admin_bar_menu'), 65);
			
			add_action('the_posts', array(&$this, 'add_to_calendar_check'));
			
			add_action('admin_enqueue_scripts', array(&$this, 'admin_styles'));
			add_action('wp_enqueue_scripts', array(&$this, 'frontend_scripts'));
			add_action('admin_enqueue_scripts', array(&$this, 'admin_scripts'));
			add_action('manage_users_custom_column', array(&$this, 'booked_add_custom_user_columns'), 15, 3);



			add_filter('manage_users_columns', array(&$this, 'booked_add_user_columns'), 15, 1);
			add_filter('user_contactmethods', array(&$this, 'booked_phone_numbers'));
			
			add_action('booked_profile_tabs', array(&$this, 'booked_profile_tabs'));
			add_action('booked_profile_tab_content', array(&$this, 'booked_profile_tab_content'));
			add_action('wp_enqueue_scripts', array(&$this, 'front_end_scripts'),1);
			
			add_action('admin_menu', array(&$this, 'booked_add_pending_appt_bubble' ));
			add_action('admin_notices', array(&$this, 'booked_pending_notice' ));
			add_action('admin_notices', array(&$this, 'booked_no_profile_page_notice' ));
			add_action('parent_file', array(&$this, 'booked_tax_menu_correction'));

			add_action( 'booked_custom_calendars_add_form_fields', array(&$this, 'booked_calendars_add_custom_fields'), 10, 2 );
			add_action( 'booked_custom_calendars_edit_form_fields', array(&$this, 'booked_calendars_edit_custom_fields'), 10, 2 );
			add_action( 'create_booked_custom_calendars', array(&$this, 'booked_save_calendars_custom_fields'), 10, 2 );
			add_action( 'edited_booked_custom_calendars', array(&$this, 'booked_save_calendars_custom_fields'), 10, 2 );

			add_action('init', array(&$this, 'init'),10);
			
			// Prevent WooCommerce from Redirecting "Booking Agents" to the My Account page.
			add_filter('woocommerce_prevent_admin_access', array(&$this, 'booked_wc_check_admin_access'));
			
			// Allow other plugins/themes to apply Booked capabilities to other user roles
			//add_filter( 'booked_user_roles', array(&$this,'booked_user_roles_filter') );
			
			// Email Reminders (Added in v1.8.0)
			add_filter( 'cron_schedules', array(&$this,'cron_schedules'));
			add_action( 'booked_send_admin_reminders', array($this, 'admin_reminders'), 20 );
			add_action( 'booked_send_user_reminders', array($this, 'user_reminders'), 20 );
			
			$user_email_content = get_option('booked_reminder_email',false);
			$user_email_subject = get_option('booked_reminder_email_subject',false);
			
			if ($user_email_content && $user_email_subject):
				if ( !wp_next_scheduled('booked_send_user_reminders') ):
					wp_schedule_event( time(),'booked_everyfive','booked_send_user_reminders' );
			    endif;
			else:
				wp_clear_scheduled_hook( 'booked_send_user_reminders' );
			endif;
			
			$admin_email_content = get_option('booked_admin_reminder_email',false);
			$admin_email_subject = get_option('booked_admin_reminder_email_subject',false);
			
			if ($admin_email_content && $admin_email_subject):
				if ( !wp_next_scheduled('booked_send_admin_reminders') ):
					wp_schedule_event(time(),'booked_everyfive','booked_send_admin_reminders');
			    endif;
			else:
				wp_clear_scheduled_hook('booked_send_admin_reminders');
			endif;

		}
		
		public static function admin_reminders(){
			
			$admin_reminder_buffer = get_option('booked_admin_reminder_buffer',30);
			$start_timestamp = current_time('timestamp');
			$end_timestamp = strtotime(date_i18n('Y-m-d H:i:s',current_time('timestamp')).' + '.$admin_reminder_buffer.' minutes');
			
			$args = array(
				'post_type' => 'booked_appointments',
				'posts_per_page' => -1,
				'post_status' => array('publish','future'),
				'meta_query' => array(
					array(
						'key'     => '_appointment_timestamp',
						'value'   => array( $start_timestamp, $end_timestamp ),
						'compare' => 'BETWEEN',
					)
				)
			);

			$bookedAppointments = new WP_Query($args);
			if($bookedAppointments->have_posts()):
				while ($bookedAppointments->have_posts()):
					
					$bookedAppointments->the_post();
					global $post;
					
					$appt_id = $post->ID;
					$reminder_sent = get_post_meta($appt_id,'_appointment_admin_reminder_sent',true);
					
					if (!$reminder_sent):
					
						$title = get_post_meta($appt_id,'_appointment_title',true);
						$timeslot = get_post_meta($appt_id,'_appointment_timeslot',true);
						$timestamp = get_post_meta($appt_id,'_appointment_timestamp',true);
						$cf_meta_value = get_post_meta($appt_id,'_cf_meta_value',true);
						$timeslots = explode('-',$timeslot);
						$time_format = get_option('time_format');
						$date_format = get_option('date_format');
						$hide_end_times = get_option('booked_hide_end_times',false);
						
						$timestamp_start = strtotime(date_i18n('Y-m-d',$timestamp).' '.$timeslots[0]);
						$timestamp_end = strtotime(date_i18n('Y-m-d',$timestamp).' '.$timeslots[1]);
						$current_timestamp = current_time('timestamp');
						
						if ($timeslots[0] == '0000' && $timeslots[1] == '2400'):
							$timeslotText = esc_html__('All day','wyzi-business-finder');
						else :
							$timeslotText = date_i18n($time_format,$timestamp_start).(!$hide_end_times ? '&ndash;'.date_i18n($time_format,$timestamp_end) : '');
						endif;
						
						$appt = get_post( $appt_id );
						$appt_author = $appt->post_author;
						
						$guest_name = get_post_meta($appt_id,'_appointment_guest_name',true);
						$guest_surname = get_post_meta($appt_id,'_appointment_guest_surname',true);
						$guest_surname = ( $guest_surname ? $guest_surname : false );
						$guest_name = ( $guest_surname ? $guest_name . ' ' . $guest_surname : $guest_name );
						$guest_email = get_post_meta($appt_id,'_appointment_guest_email',true);
						
						if ($guest_name):
							$user_name = $guest_name;
							$email = $guest_email;
						else:
							$user_name = booked_get_name( $appt_author );
							$user_data = get_userdata( $appt_author );
							$email = $user_data->user_email;
						endif;
					
						$appointment_calendar_id = get_the_terms( $appt_id,'booked_custom_calendars' );
						if (!empty($appointment_calendar_id)):
							foreach($appointment_calendar_id as $calendar):
								$calendar_id = $calendar->term_id;
								break;
							endforeach;
						else:
							$calendar_id = false;
						endif;
								
						if (!empty($calendar_id)): $calendar_term = get_term_by('id',$calendar_id,'booked_custom_calendars'); $calendar_name = $calendar_term->name; else: $calendar_name = false; endif;
						
						$day_name = date('D',$timestamp);
						$timeslotText = apply_filters('booked_emailed_timeslot_text',$timeslotText,$timestamp_start,$timeslot,$calendar_id);
						
						// Send an email reminder
						$email_content = get_option('booked_admin_reminder_email',false);
						$email_subject = get_option('booked_admin_reminder_email_subject',false);
						if ($email_content && $email_subject):
							$admin_email = booked_which_admin_to_send_email($calendar_id);
							$tokens = array('%name%','%date%','%time%','%customfields%','%calendar%','%email%','%title%');
							$replacements = array($user_name,date_i18n($date_format,$timestamp),$timeslotText,$cf_meta_value,$calendar_name,$email,$title);
							$email_content = htmlentities(str_replace($tokens,$replacements,$email_content), ENT_QUOTES | ENT_IGNORE, "UTF-8");
							$email_content = html_entity_decode($email_content, ENT_QUOTES | ENT_IGNORE, "UTF-8");
							$email_subject = str_replace($tokens,$replacements,$email_subject);
							update_post_meta($appt_id,'_appointment_admin_reminder_sent',true);
							do_action( 'booked_admin_reminder_email', $admin_email, $email_subject, $email_content, $email, $user_name );
						endif;

					endif;
					
				endwhile;
	
			endif;
			
			wp_reset_postdata();
			
		}
		
		public static function user_reminders(){
			
			$user_reminder_buffer = get_option('booked_reminder_buffer',30);
			
			$start_timestamp = current_time('timestamp');
			$end_timestamp = strtotime(date_i18n('Y-m-d H:i:s',current_time('timestamp')).' + '.$user_reminder_buffer.' minutes');
			
			$args = array(
				'post_type' => 'booked_appointments',
				'posts_per_page' => -1,
				'post_status' => array('publish','future'),
				'meta_query' => array(
					array(
						'key'     => '_appointment_timestamp',
						'value'   => array( $start_timestamp, $end_timestamp ),
						'compare' => 'BETWEEN',
					)
				)
			);

			$bookedAppointments = new WP_Query($args);
			if($bookedAppointments->have_posts()):
				while ($bookedAppointments->have_posts()):
					
					$bookedAppointments->the_post();
					global $post;
					
					$appt_id = $post->ID;
					$reminder_sent = get_post_meta($appt_id,'_appointment_user_reminder_sent',true);
					
					$send_mail = true;
					if ( !$reminder_sent && apply_filters( 'booked_prepare_sending_reminder', true, $appt_id ) ):
					
						$title = get_post_meta($appt_id,'_appointment_title',true);
						$timeslot = get_post_meta($appt_id,'_appointment_timeslot',true);
						$timestamp = get_post_meta($appt_id,'_appointment_timestamp',true);
						$cf_meta_value = get_post_meta($appt_id,'_cf_meta_value',true);
						$timeslots = explode('-',$timeslot);
						$time_format = get_option('time_format');
						$date_format = get_option('date_format');
						$hide_end_times = get_option('booked_hide_end_times',false);
						
						$timestamp_start = strtotime(date_i18n('Y-m-d',$timestamp).' '.$timeslots[0]);
						$timestamp_end = strtotime(date_i18n('Y-m-d',$timestamp).' '.$timeslots[1]);
						$current_timestamp = current_time('timestamp');
						
						if ($timeslots[0] == '0000' && $timeslots[1] == '2400'):
							$timeslotText = esc_html__('All day','wyzi-business-finder');
						else :
							$timeslotText = date_i18n($time_format,$timestamp_start).(!$hide_end_times ? '&ndash;'.date_i18n($time_format,$timestamp_end) : '');
						endif;
						
						$appt = get_post( $appt_id );
						$appt_author = $appt->post_author;
						
						$guest_name = get_post_meta($appt_id,'_appointment_guest_name',true);
						$guest_surname = get_post_meta($appt_id,'_appointment_guest_surname',true);
						$guest_surname = ( $guest_surname ? $guest_surname : false );
						$guest_name = ( $guest_surname ? $guest_name . ' ' . $guest_surname : $guest_name );
						$guest_email = get_post_meta($appt_id,'_appointment_guest_email',true);
						
						if ($guest_name):
							$user_name = $guest_name;
							$email = $guest_email;
						else:
							$user_name = booked_get_name( $appt_author );
							$user_data = get_userdata( $appt_author );
							$email = $user_data->user_email;
						endif;
						
						$appointment_calendar_id = get_the_terms( $appt_id,'booked_custom_calendars' );
						if (!empty($appointment_calendar_id)):
							foreach($appointment_calendar_id as $calendar):
								$calendar_id = $calendar->term_id;
								break;
							endforeach;
						else:
							$calendar_id = false;
						endif;
								
						if (!empty($calendar_id)): $calendar_term = get_term_by('id',$calendar_id,'booked_custom_calendars'); $calendar_name = $calendar_term->name; else: $calendar_name = false; endif;
						
						// Send an email reminder
						$email_content = get_option('booked_reminder_email',false);
						$email_subject = get_option('booked_reminder_email_subject',false);
						
						if ($email_content && $email_subject):
							$tokens = array('%name%','%date%','%time%','%customfields%','%calendar%','%email%','%title%');
							$replacements = array($user_name,date_i18n($date_format,$timestamp),$timeslotText,$cf_meta_value,$calendar_name,$email,$title);
							$email_content = htmlentities(str_replace($tokens,$replacements,$email_content), ENT_QUOTES | ENT_IGNORE, "UTF-8");
							$email_content = html_entity_decode($email_content, ENT_QUOTES | ENT_IGNORE, "UTF-8");
							$email_subject = str_replace($tokens,$replacements,$email_subject);
							update_post_meta($appt_id,'_appointment_user_reminder_sent',true);
							do_action( 'booked_reminder_email', $email, $email_subject, $email_content );
						endif;

					endif;
					
				endwhile;
	
			endif;
			
			wp_reset_postdata();
			
		}
		
		public static function cron_schedules( $schedules ) {
			$schedules['booked_everyfive'] = array(
				'interval' => 60 * 5,
				'display' => esc_html__('Every Five Minutes', 'wyzi-business-finder')
			);
	
			return $schedules;
		}
		
		public static function activate() {
			set_transient( '_booked_welcome_screen_activation_redirect', true, 30 );
		}

		public function booked_wc_check_admin_access( $redirect_to ) {
			$booked_current_user = wp_get_current_user();
			if( is_array( $booked_current_user->roles ) && in_array( 'booked_booking_agent', $booked_current_user->roles ) ) {
				return false;
  			}
  			return $redirect_to;
		}

		public function admin_init() {
			
			if (isset($_POST['booked_export_appointments_csv'])):
				include('includes/export-csv.php');	
			endif;
			
			/*$booked_redirect_non_admins = get_option('booked_redirect_non_admins',false);
			
			// Redirect non-admin users
			if ($booked_redirect_non_admins):	
				if (!current_user_can('edit_booked_appointments') && !defined( 'DOING_AJAX' )){
					
					$booked_profile_page = booked_get_profile_page();

					if ($booked_profile_page):
						$redirect_url = get_permalink($booked_profile_page);
					else:
						$redirect_url = home_url();
					endif;
					
					wp_redirect( $redirect_url );
					exit;
					
				}
			endif;*/
			
			// Set up the settings for this plugin
			require_once(sprintf("%s/includes/admin-functions.php", BOOKED_PLUGIN_DIR));
			require_once(sprintf("%s/includes/dashboard-widget.php", BOOKED_PLUGIN_DIR));
			$this->init_settings();
			
			// Welcome Screen Redirect
			if ( !get_transient( '_booked_welcome_screen_activation_redirect' ) ) {
				return;
  			}
  			
  			delete_transient( '_booked_welcome_screen_activation_redirect' );
  			
  			if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
  				return;
  			}
  			
  			if (BOOKED_WELCOME_SCREEN):
  				wp_safe_redirect( add_query_arg( array( 'page' => 'booked-welcome' ), admin_url( 'admin.php' ) ) );
  				exit;
  			endif;
  			// END Welcome Screen Redirect
  			
  			return;

		} // END public static function activate
		
		public function booked_curl($url){
			
			if ( function_exists('curl_init') ):
			
				$ch = curl_init();
				$timeout = 5;
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
				$data = curl_exec($ch);
				curl_close($ch);
				if ($data):
					return $data;
				else:
					return false;
				endif;

			else:
			
				return false;
			
			endif;
			
		}
		
		public function booked_profile_tabs($default_tabs){
			
			foreach($default_tabs as $slug => $name):
				echo '<li'.($name['class'] ? ' class="'.$name['class'].'"' : '').'><a href="#'.$slug.'"><i class="fa '.$name['fa-icon'].'"></i>'.$name['title'].'</a></li>';
			endforeach;
			
		}
		
		public function booked_profile_tab_content($default_tabs){
			
			foreach($default_tabs as $slug => $name):
				echo '<div id="profile-'.$slug.'" class="booked-tab-content bookedClearFix">';
					call_user_func('booked_profile_content_'.$slug);
				echo '</div>';
			endforeach;
			
		}

		public function init() {
			
			// Hide the Admin Bar from subscribers.
		    $booked_current_user = wp_get_current_user();
			if ( isset($booked_current_user->roles[0]) && $booked_current_user->roles[0]=='subscriber' ) {
				add_filter('show_admin_bar', '__return_false');
			}
			
			// Include the Booked functions file.
			require_once(sprintf("%s/includes/functions.php", BOOKED_PLUGIN_DIR));

			// Start a session if none is started yet.
			if( !session_id() ) {
		        session_start();
		    }
		    
		    // Check to see if the plugin was updated.
			$current_version = get_option('booked_version_check','1.6.20');
			if ( version_compare( $current_version, BOOKED_VERSION ) < 0 && !BOOKED_DEMO_MODE):
				update_option('booked_version_check',BOOKED_VERSION);
				set_transient( '_booked_welcome_screen_activation_redirect', true, 60 );
				set_transient('booked_show_new_tags',true,60*60*24*15);
			else:
				update_option('booked_version_check',BOOKED_VERSION);
			endif;
		    
		}
		
		public function add_to_calendar_check($posts){

		    if (empty($posts)):
		    	return $posts;
		    endif;
		 
		    $found = false;
		 
			if ( 'off' != get_option( 'wyz_users_can_booking' ) ){
		    	$found = true;
		    } else {
		 
				foreach ($posts as $post):
				
					$profile_shortcode = stripos($post->post_content, '[booked-profile');
					$appts_shortcode = stripos($post->post_content, '[booked-appointments');
					
					if ( $profile_shortcode !== false || $appts_shortcode !== false):
						$found = true;
						break;
					endif;
				
				endforeach;
		    }
		    
		    if ($found && !get_option('booked_hide_google_link',false)):
		        add_action('wp_footer', array(&$this, 'add_to_calendar_code' ));
			endif;
		    return $posts;
		    
		}
		
		public function add_to_calendar_code(){
			if ( is_page( 'user-account' ) ) return;
			?><script type="text/javascript">(function () {
		        if (window.addtocalendar)if(typeof window.addtocalendar.start == "function")return;
		        if (window.ifaddtocalendar == undefined) { window.ifaddtocalendar = 1;
		            var d = document, s = d.createElement('script'), g = 'getElementsByTagName';
		            s.type = 'text/javascript';s.charset = 'UTF-8';s.async = true;
		            s.src = '<?php echo BOOKED_PLUGIN_URL; ?>/js/atc.min.js';
		            var h = d[g]('body')[0];h.appendChild(s); }})();
			</script><?php
				
		}

		static function plugin_settings() {
			$plugin_options = array(
				'booked_login_redirect_page',
				'booked_custom_login_message',
				'booked_appointment_redirect_type',
				'booked_appointment_success_redirect_page',
				'booked_registration_name_requirements',
				'booked_hide_admin_bar_menu',
				'booked_timeslot_intervals',
				'booked_appointment_buffer',
				'booked_appointment_limit',
				'booked_cancellation_buffer',
				'booked_new_appointment_default',
				'booked_prevent_appointments_before',
				'booked_prevent_appointments_after',
				'booked_booking_type',
				'booked_require_guest_email_address',
				'booked_hide_default_calendar',
				'booked_hide_unavailable_timeslots',
				'booked_hide_google_link',
				'booked_hide_weekends',
				'booked_dont_allow_user_cancellations',
				'booked_show_only_titles',
				'booked_hide_end_times',
				'booked_hide_available_timeslots',
				'booked_public_appointments',
				'booked_redirect_non_admins',
				'booked_light_color',
				'booked_dark_color',
				'booked_button_color',
				'booked_email_logo',
				'booked_default_email_user',
				'booked_email_force_sender',
				'booked_reminder_buffer',
				'booked_admin_reminder_buffer',
				'booked_reminder_email',
				'booked_admin_reminder_email',
				'booked_reminder_email_subject',
				'booked_admin_reminder_email_subject',
				'booked_registration_email_subject',
				'booked_registration_email_content',
				'booked_approval_email_content',
				'booked_approval_email_subject',
				'booked_cancellation_email_content',
				'booked_cancellation_email_subject',
				'booked_appt_confirmation_email_content',
				'booked_appt_confirmation_email_subject',
				'booked_admin_appointment_email_content',
				'booked_admin_appointment_email_subject',
				'booked_admin_cancellation_email_content',
				'booked_admin_cancellation_email_subject'
			);

			return $plugin_options;
		}

		public function init_settings() {
			$plugin_options = $this->plugin_settings();
			foreach($plugin_options as $option_name) {
				register_setting('booked_plugin-group', $option_name);
			}
		}


		public function booked_phone_numbers($profile_fields) {
			$profile_fields['booked_phone'] = esc_html__('Phone Number','wyzi-business-finder');
			return $profile_fields;
		}


		/**********************
		ADD MENUS FUNCTION
		**********************/

		public function add_menu() {
			add_menu_page( esc_html__('Appointments','wyzi-business-finder'), esc_html__('Appointments','wyzi-business-finder'), 'edit_booked_appointments', 'booked-appointments', array(&$this, 'admin_calendar'), 'dashicons-calendar-alt', 58 );
			add_submenu_page('booked-appointments', esc_html__('Pending','wyzi-business-finder'), esc_html__('Pending','wyzi-business-finder'), 'edit_booked_appointments', 'booked-pending', array(&$this, 'admin_pending_list'));
			add_submenu_page('booked-appointments', esc_html__('Calendars','wyzi-business-finder'), esc_html__('Calendars','wyzi-business-finder'), 'manage_booked_options', 'edit-tags.php?taxonomy=booked_custom_calendars');
			add_submenu_page('booked-appointments', esc_html__('Settings','wyzi-business-finder'), esc_html__('Settings','wyzi-business-finder'), 'edit_booked_appointments', 'booked-settings', array(&$this, 'plugin_settings_page'));
			add_submenu_page('booked-appointments', esc_html__('What\'s New?','wyzi-business-finder'), esc_html__('What\'s New?','wyzi-business-finder'), 'manage_booked_options', 'booked-welcome', array(&$this, 'booked_welcome_content'));
		}
			
		public function add_admin_bar_menu() {
			
			$hide_menu = get_option('booked_hide_admin_bar_menu',false);
			
			if (!$hide_menu):
			
				global $wp_admin_bar;
				
				$wp_admin_bar->add_menu(array('id' => 'booked', 'title' => '<span class="ab-icon"></span>'.esc_html__('Appointments','wyzi-business-finder'), 'href' => get_admin_url().'admin.php?page=booked-appointments'));
				$wp_admin_bar->add_menu(array('parent' => 'booked', 'title' => esc_html__('Appointments','wyzi-business-finder'), 'id' => 'booked-appointments', 'href' => get_admin_url().'admin.php?page=booked-appointments'));
				$wp_admin_bar->add_menu(array('parent' => 'booked', 'title' => esc_html__('Pending','wyzi-business-finder'), 'id' => 'booked-pending', 'href' => get_admin_url().'admin.php?page=booked-pending'));
				if (current_user_can('manage_booked_options')):
					$wp_admin_bar->add_menu(array('parent' => 'booked', 'title' => esc_html__('Calendars','wyzi-business-finder'), 'id' => 'booked-calendars', 'href' => get_admin_url().'edit-tags.php?taxonomy=booked_custom_calendars'));
				endif;
				$wp_admin_bar->add_menu(array('parent' => 'booked', 'title' => esc_html__('Settings','wyzi-business-finder'), 'id' => 'booked-settings', 'href' => get_admin_url().'admin.php?page=booked-settings'));

			endif;
			
		}
		
		public function booked_welcome_content(){
			include(sprintf("%s/templates/welcome.php", BOOKED_PLUGIN_DIR));
		}

		// Move Taxonomy (custom calendars) to Appointments Menu
		public function booked_tax_menu_correction($parent_file) {
			global $current_screen;
			$taxonomy = $current_screen->taxonomy;
			if ($taxonomy == 'booked_custom_calendars')
				$parent_file = 'booked-appointments';
			return $parent_file;
		}

		// Booked Settings
		public function plugin_settings_page() {
			if(!current_user_can('edit_booked_appointments')) {
				wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wyzi-business-finder'));
			}
			include(sprintf("%s/templates/settings.php", BOOKED_PLUGIN_DIR));
		}

		// Booked Pending Appointments List
		public function admin_pending_list() {
			if(!current_user_can('edit_booked_appointments')) {
				wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wyzi-business-finder'));
			}
			include(sprintf("%s/templates/pending-list.php", BOOKED_PLUGIN_DIR));
		}

		// Booked Appointment Calendar
		public function admin_calendar() {
			if(!current_user_can('edit_booked_appointments')) {
				wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wyzi-business-finder'));
			}
			include(sprintf("%s/templates/admin-calendar.php", BOOKED_PLUGIN_DIR));
		}

		// Add Pending Appointments Bubble
		public function booked_add_pending_appt_bubble() {

			global $submenu;

			$pending = booked_pending_appts_count();

			foreach ( $submenu as $key => $value ) :
				if ( $key == 'booked-appointments' ) :
					if ( $pending ) { $submenu[$key][1][0] .= " <span style='position:relative; top:1px; margin:-2px 0 0 2px' class='update-plugins count-$pending' title='$pending'><span style='padding:0 6px 0 4px; min-width:7px; text-align:center;' class='update-count'>" . $pending . "</span></span>"; }
					return;
				endif;
			endforeach;

		}
		
		public function booked_no_profile_page_notice() {

			if (current_user_can('manage_booked_options')):

				$booked_booking_type = get_option('booked_booking_type','registered');
				$booked_redirect_type =  get_option('booked_appointment_redirect_type',false);
				$booked_profile_page = booked_get_profile_page();
				$page = (isset($_GET['page']) ? $page = $_GET['page'] : $page = false);
				
				if ($booked_booking_type == 'registered' && $booked_redirect_type == 'booked-profile' && !$booked_profile_page && $page != 'booked-welcome'):

					echo '<div class="update-nag" style="line-height:28px; border-left-color:#DB5933;">';
						echo sprintf(esc_html__( 'You need to create a page with the %s shortcode. It is required with your current settings.','wyzi-business-finder' ),'<code>[booked-profile]</code>').'&nbsp;&nbsp;&nbsp;<a href="'.get_admin_url().'post-new.php?post_type=page" class="button-primary">'.esc_html__('Create a Page','wyzi-business-finder').'</a>&nbsp;&nbsp;<a href="'.get_admin_url().'admin.php?page=booked-settings" class="button">'.esc_html__('Change Settings','wyzi-business-finder').'</a>';
					echo '</div>';

				endif;

			endif;

		}

		public function booked_pending_notice() {

			if (current_user_can('edit_booked_appointments')):

				$pending = booked_pending_appts_count();
				$page = (isset($_GET['page']) ? $page = $_GET['page'] : $page = false);
				if ($pending && $page != 'booked-pending' && $page != 'booked-welcome'):

					echo '<div class="update-nag" style="line-height:28px">';
						echo sprintf( _n( 'There is %s pending appointment.', 'There are %s pending appointments.', $pending, 'wyzi-business-finder' ), $pending ).'&nbsp;&nbsp;&nbsp;<a href="'.get_admin_url().'admin.php?page=booked-pending" class="button">'._n('View Pending Appointment','View Pending Appointments',$pending,'wyzi-business-finder').'</a>';
					echo '</div>';

				endif;

			endif;

		}

		/**********************
		ADD USER FIELD TO CALENDAR TAXONOMY PAGE
		**********************/
		public function booked_calendars_add_custom_fields($tag) {

			?><div class="form-field">
				<label for="term_meta[notifications_user_id]"><?php esc_html_e('Assign this calendar to','wyzi-business-finder'); ?>:</label>
				<select name="term_meta[notifications_user_id]" id="term_meta[notifications_user_id]">
				<option value=""><?php esc_html_e('Default','wyzi-business-finder'); ?></option><?php

					$all_users = get_users();
					$allowed_users = array();
					foreach ( $all_users as $user ):
						$wp_user = new WP_User($user->ID);
						if ( !in_array( 'subscriber', $wp_user->roles ) ):
							array_push($allowed_users, $user);
						endif;
					endforeach;

					if(!empty($allowed_users)) :
						foreach($allowed_users as $u) :
							$user_id = $u->ID;
							$username = $u->data->user_login;
							$email = $u->data->user_email; ?>
							<option value="<?php echo $email; ?>"><?php echo $email; ?> (<?php echo $username; ?>)</option><?php
						endforeach;
					endif;

				?></select>
				<p><?php esc_html_e('This will use your setting from the Booked Settings panel by default.','wyzi-business-finder'); ?></p>
			</div><?php

		}

		public function booked_calendars_edit_custom_fields($tag) {

			$t_id = $tag->term_id;
			$term_meta = get_option( "taxonomy_$t_id" );
			$selected_value = $term_meta['notifications_user_id'];

			$all_users = get_users();
			$allowed_users = array();
			foreach ( $all_users as $user ):
				$wp_user = new WP_User($user->ID);
				if ( !in_array( 'subscriber', $wp_user->roles ) ):
					array_push($allowed_users, $user);
				endif;
			endforeach; ?>

			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="term_meta[notifications_user_id]"><?php esc_html_e('Assign this calendar to','wyzi-business-finder'); ?>:</label>
				</th>
				<td>
					<select name="term_meta[notifications_user_id]" id="term_meta[notifications_user_id]">
						<option value=""><?php esc_html_e('Default','wyzi-business-finder'); ?></option>
						<?php if(!empty($allowed_users)) :
							foreach($allowed_users as $u) :
								$user_id = $u->ID;
								$username = $u->data->user_login;
								$email = $u->data->user_email; ?>
								<option value="<?php echo $email; ?>"<?php echo ($selected_value == $email ? ' selected="selected"' : ''); ?>><?php echo $email; ?> (<?php echo $username; ?>)</option>
							<?php endforeach;

						endif; ?>
					</select><br>
					<span class="description"><?php esc_html_e('This will use your setting from the Booked Settings panel by default.'); ?></span>
				</td>
			</tr><?php
		}

		/**********************
		SAVE USER FIELD FROM CALENDAR TAXONOMY PAGE
		**********************/
		public function booked_save_calendars_custom_fields( $term_id ) {
			if ( isset( $_POST['term_meta'] ) ) {
				$t_id = $term_id;
				$term_meta = get_option( "taxonomy_$t_id" );
				$cat_keys = array_keys( $_POST['term_meta'] );
				foreach ( $cat_keys as $key ) {
					if ( isset ( $_POST['term_meta'][$key] ) ) {
						$term_meta[$key] = $_POST['term_meta'][$key];
					}
				}
				update_option( "taxonomy_$t_id", $term_meta );
			}
		}

		/**********************
		ADD USER COLUMN FOR APPOINTMENT COUNTS
		**********************/

		public function booked_add_user_columns( $defaults ) {
			$defaults['booked_appointments'] = esc_html__('Appointments', 'wyzi-business-finder');
			return $defaults;
		}
		public function booked_add_custom_user_columns($value, $column_name, $id) {
			if( $column_name == 'booked_appointments' ) {

				$args = array(
					'posts_per_page'   	=> -1,
					'meta_key'   	   	=> '_appointment_timestamp',
					'orderby'			=> 'meta_value_num',
					'order'            	=> 'ASC',
					'meta_query' => array(
						array(
							'key'     => '_appointment_timestamp',
							'value'   => strtotime(date_i18n('Y-m-d H:i:s')),
							'compare' => '>=',
						),
					),
					'author'		   	=> $id,
					'post_type'        	=> 'booked_appointments',
					'post_status'      	=> array('publish','future'),
					'suppress_filters'	=> true );

				$appointments = get_posts($args);
				$count = count($appointments);

				$appointments = array_slice($appointments, 0, 5);
				$time_format = get_option('time_format');
				$date_format = get_option('date_format');

				ob_start();

				if ($count){

					echo '<strong>'.$count.' '._n('Upcoming Appointment','Upcoming Appointments',$count,'wyzi-business-finder').':</strong>';

					echo '<span style="font-size:12px;">';

					foreach($appointments as $appointment):
						$timeslot = get_post_meta($appointment->ID, '_appointment_timeslot',true);
						$timeslot = explode('-',$timeslot);
						$timestamp = get_post_meta($appointment->ID, '_appointment_timestamp',true);
						echo '<br>' . date_i18n($date_format,$timestamp) . ' @ ' . date_i18n($time_format,strtotime($timeslot[0])) . '&ndash;' . date_i18n($time_format,strtotime($timeslot[1]));
					endforeach;

					if ($count > 5):
						$diff = $count - 5;
						echo '<br>...'.esc_html__('and','wyzi-business-finder').' '.$diff.' '.esc_html__('more','wyzi-business-finder');
					endif;

					echo '</span>';

				}

				return ob_get_clean();
			}

		}

		public function frontend_scripts() {

			$user_page = is_page( 'user-account' ) && isset( $_GET['business-calendar'] );

			if ( $user_page ):
				wp_enqueue_script('booked-serialize', BOOKED_PLUGIN_URL . '/js/jquery.serialize.js', array(), BOOKED_VERSION);
			
				wp_enqueue_media();
				wp_enqueue_script('wp-color-picker');
				wp_enqueue_script('jquery-ui');
				wp_enqueue_script('jquery-ui-sortable');
				wp_enqueue_script('jquery-ui-datepicker');
				wp_enqueue_script('booked-font-awesome', '//use.fontawesome.com/'.BOOKED_FA_ID.'.js', array(), BOOKED_FA_VERSION);
				wp_enqueue_script('spin-js', BOOKED_PLUGIN_URL . '/js/spin.min.js', array(), '2.0.1');
				wp_enqueue_script('spin-jquery', BOOKED_PLUGIN_URL . '/js/spin.jquery.js', array(), '2.0.1');
				wp_enqueue_script('booked-chosen', BOOKED_PLUGIN_URL . '/js/chosen/chosen.jquery.min.js', array(), '1.2.0');
				wp_enqueue_script('booked-fitvids', BOOKED_PLUGIN_URL . '/js/fitvids.js', array(), '1.1');
				wp_enqueue_script('booked-tooltipster', BOOKED_PLUGIN_URL . '/js/tooltipster/js/jquery.tooltipster.min.js', array(), '3.3.0', true);
				wp_enqueue_script('booked-calendar-popup', BOOKED_PLUGIN_URL . '/js/jquery.bookedCalendarPopup.js', array(), BOOKED_VERSION);
				wp_register_script('booked-admin', BOOKED_PLUGIN_URL . '/js/admin-functions.js', array(), BOOKED_VERSION);
				
				$booked_js_vars = array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'ajaxRequests' => array(),
					'i18n_slot' => esc_html__('Space Available','wyzi-business-finder'),
					'i18n_slots' => esc_html__('Spaces Available','wyzi-business-finder'),
					'i18n_add' => esc_html__('Add','wyzi-business-finder'),
					'i18n_time_error' => esc_html__('The "End Time" needs to be later than the "Start Time".','wyzi-business-finder'),
					'i18n_bulk_add_confirm' => esc_html__('Are you sure you want to add those bulk time slots?','wyzi-business-finder'),
					'i18n_all_fields_required' => esc_html__('All fields are required.','wyzi-business-finder'),
					'i18n_single_add_confirm' => esc_html__('You are about to add the following time slot(s)','wyzi-business-finder'),
					'i18n_to' => esc_html__('to','wyzi-business-finder'),
					'i18n_please_wait' => esc_html__('Please wait ...','wyzi-business-finder'),
					'i18n_all_day' => esc_html__('All day','wyzi-business-finder'),
					'i18n_choose_customer' => esc_html__('Please choose a customer.','wyzi-business-finder'),
					'i18n_fill_out_required_fields' => esc_html__('Please fill out all required fields.','wyzi-business-finder'),
					'i18n_confirm_ts_delete' => esc_html__('Are you sure you want to delete this time slot?','wyzi-business-finder'),
					'i18n_confirm_cts_delete' => esc_html__('Are you sure you want to delete this custom time slot?','wyzi-business-finder'),
					'i18n_confirm_appt_delete' => esc_html__('Are you sure you want to cancel this appointment?','wyzi-business-finder'),
					'i18n_appt_required_fields' => esc_html__('A name, email address and password are required.','wyzi-business-finder'),
					'i18n_appt_required_guest_fields' => esc_html__('A name is required.','wyzi-business-finder'),
					'i18n_appt_required_guest_fields_surname' => esc_html__('A first and last name are required.','wyzi-business-finder'),
					'i18n_appt_required_guest_fields_all' => esc_html__('A first name, last name and email address are required.','wyzi-business-finder'),
					'i18n_appt_required_guest_fields_name_email' => esc_html__('A name and an email address are required.','wyzi-business-finder'),
					'i18n_confirm_appt_approve' => esc_html__('Are you sure you want to approve this appointment?','wyzi-business-finder'),
					'i18n_confirm_appt_approve_all' => esc_html__('Are you sure you want to approve ALL pending appointments?','wyzi-business-finder'),
					'i18n_confirm_appt_delete_all' => esc_html__('Are you sure you want to delete ALL pending appointments?','wyzi-business-finder'),
					'i18n_confirm_appt_delete_past' => esc_html__('Are you sure you want to delete all PASSED pending appointments?','wyzi-business-finder'),
				);			
				
				wp_localize_script('booked-admin', 'booked_js_vars', $booked_js_vars );
				wp_enqueue_script('booked-admin');

				wp_enqueue_style('booked-tooltipster', 	BOOKED_PLUGIN_URL . '/js/tooltipster/css/tooltipster.css', array(), '3.3.0');
				wp_enqueue_style('booked-tooltipster-theme', 	BOOKED_PLUGIN_URL . '/js/tooltipster/css/themes/tooltipster-light.css', array(), '3.3.0');
				wp_enqueue_style('chosen', BOOKED_PLUGIN_URL . '/js/chosen/chosen.min.css', array(), '1.2.0');
				wp_enqueue_style('booked-animations', BOOKED_PLUGIN_URL . '/css/animations.css', array(), BOOKED_VERSION);
				wp_enqueue_style('booked-admin', BOOKED_PLUGIN_URL . '/css/admin-styles.css', array(), BOOKED_VERSION);
				
			endif;
			
		}


		// --------- ADMIN SCRIPTS/STYLES --------- //

		public function admin_scripts() {

			$current_page = (isset($_GET['page']) ? $_GET['page'] : false);
			$user_page = is_page( 'user-account' ) && isset( $_GET['business-calendar'] );
			$screen = get_current_screen();

			// Gonna need jQuery
			wp_enqueue_script('jquery');
			
			// For Serializing Arrays
			if ($current_page == 'booked-settings' || $screen->id == 'dashboard' || $user_page ):
				wp_enqueue_script('booked-serialize', BOOKED_PLUGIN_URL . '/js/jquery.serialize.js', array(), BOOKED_VERSION);
			endif;

			// Load the rest of the stuff!
			if (in_array($current_page,$this->booked_screens) || $screen->id == 'dashboard' || $user_page ):
			
				wp_enqueue_media();
				wp_enqueue_script('wp-color-picker');
				wp_enqueue_script('jquery-ui');
				wp_enqueue_script('jquery-ui-sortable');
				wp_enqueue_script('jquery-ui-datepicker');
				wp_enqueue_script('booked-font-awesome', '//use.fontawesome.com/'.BOOKED_FA_ID.'.js', array(), BOOKED_FA_VERSION);
				wp_enqueue_script('spin-js', BOOKED_PLUGIN_URL . '/js/spin.min.js', array(), '2.0.1');
				wp_enqueue_script('spin-jquery', BOOKED_PLUGIN_URL . '/js/spin.jquery.js', array(), '2.0.1');
				wp_enqueue_script('booked-chosen', BOOKED_PLUGIN_URL . '/js/chosen/chosen.jquery.min.js', array(), '1.2.0');
				wp_enqueue_script('booked-fitvids', BOOKED_PLUGIN_URL . '/js/fitvids.js', array(), '1.1');
				wp_enqueue_script('booked-tooltipster', BOOKED_PLUGIN_URL . '/js/tooltipster/js/jquery.tooltipster.min.js', array(), '3.3.0', true);
				wp_enqueue_script('booked-calendar-popup', BOOKED_PLUGIN_URL . '/js/jquery.bookedCalendarPopup.js', array(), BOOKED_VERSION);
				wp_register_script('booked-admin', BOOKED_PLUGIN_URL . '/js/admin-functions.js', array(), BOOKED_VERSION);
				
				$booked_js_vars = array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'ajaxRequests' => array(),
					'i18n_slot' => esc_html__('Space Available','wyzi-business-finder'),
					'i18n_slots' => esc_html__('Spaces Available','wyzi-business-finder'),
					'i18n_add' => esc_html__('Add','wyzi-business-finder'),
					'i18n_time_error' => esc_html__('The "End Time" needs to be later than the "Start Time".','wyzi-business-finder'),
					'i18n_bulk_add_confirm' => esc_html__('Are you sure you want to add those bulk time slots?','wyzi-business-finder'),
					'i18n_all_fields_required' => esc_html__('All fields are required.','wyzi-business-finder'),
					'i18n_single_add_confirm' => esc_html__('You are about to add the following time slot(s)','wyzi-business-finder'),
					'i18n_to' => esc_html__('to','wyzi-business-finder'),
					'i18n_please_wait' => esc_html__('Please wait ...','wyzi-business-finder'),
					'i18n_all_day' => esc_html__('All day','wyzi-business-finder'),
					'i18n_choose_customer' => esc_html__('Please choose a customer.','wyzi-business-finder'),
					'i18n_fill_out_required_fields' => esc_html__('Please fill out all required fields.','wyzi-business-finder'),
					'i18n_confirm_ts_delete' => esc_html__('Are you sure you want to delete this time slot?','wyzi-business-finder'),
					'i18n_confirm_cts_delete' => esc_html__('Are you sure you want to delete this custom time slot?','wyzi-business-finder'),
					'i18n_confirm_appt_delete' => esc_html__('Are you sure you want to cancel this appointment?','wyzi-business-finder'),
					'i18n_appt_required_fields' => esc_html__('A name, email address and password are required.','wyzi-business-finder'),
					'i18n_appt_required_guest_fields' => esc_html__('A name is required.','wyzi-business-finder'),
					'i18n_appt_required_guest_fields_surname' => esc_html__('A first and last name are required.','wyzi-business-finder'),
					'i18n_appt_required_guest_fields_all' => esc_html__('A first name, last name and email address are required.','wyzi-business-finder'),
					'i18n_appt_required_guest_fields_name_email' => esc_html__('A name and an email address are required.','wyzi-business-finder'),
					'i18n_confirm_appt_approve' => esc_html__('Are you sure you want to approve this appointment?','wyzi-business-finder'),
					'i18n_confirm_appt_approve_all' => esc_html__('Are you sure you want to approve ALL pending appointments?','wyzi-business-finder'),
					'i18n_confirm_appt_delete_all' => esc_html__('Are you sure you want to delete ALL pending appointments?','wyzi-business-finder'),
					'i18n_confirm_appt_delete_past' => esc_html__('Are you sure you want to delete all PASSED pending appointments?','wyzi-business-finder'),
				);			
				
				wp_localize_script('booked-admin', 'booked_js_vars', $booked_js_vars );
				wp_enqueue_script('booked-admin');
				
			endif;
			
		}

		public function admin_styles() {
			
			$current_page = (isset($_GET['page']) ? $_GET['page'] : false);
			$screen = get_current_screen();
			
			if (in_array($current_page,$this->booked_screens) || $screen->id == 'dashboard'):
				wp_enqueue_style('wp-color-picker');
				wp_enqueue_style('booked-tooltipster', 	BOOKED_PLUGIN_URL . '/js/tooltipster/css/tooltipster.css', array(), '3.3.0');
				wp_enqueue_style('booked-tooltipster-theme', 	BOOKED_PLUGIN_URL . '/js/tooltipster/css/themes/tooltipster-light.css', array(), '3.3.0');
				wp_enqueue_style('chosen', BOOKED_PLUGIN_URL . '/js/chosen/chosen.min.css', array(), '1.2.0');
				wp_enqueue_style('booked-animations', BOOKED_PLUGIN_URL . '/css/animations.css', array(), BOOKED_VERSION);
				wp_enqueue_style('booked-admin', BOOKED_PLUGIN_URL . '/css/admin-styles.css', array(), BOOKED_VERSION);
			endif;
		
		}


		// --------- FRONT-END SCRIPTS/STYLES --------- //

		public function front_end_scripts() {
			global $post;
			global $business_data;
			if ( ( ! $post || ! has_shortcode( $post->post_content, 'booked-profile' ) ) &&  isset($_GET['business-calendar']) && ( '' == $business_data || ! $business_data->can_bookings ) )
				return;
			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-ui');
			wp_enqueue_script('jquery-ui-datepicker');
			wp_enqueue_script('booked-font-awesome', '//use.fontawesome.com/'.BOOKED_FA_ID.'.js', array(), BOOKED_FA_VERSION);
			wp_enqueue_script('booked-spin-js', 	BOOKED_PLUGIN_URL . '/js/spin.min.js', array(), '2.0.1', true);
			wp_enqueue_script('booked-spin-jquery', BOOKED_PLUGIN_URL . '/js/spin.jquery.js', array(), '2.0.1', true);
			wp_enqueue_script('booked-tooltipster', BOOKED_PLUGIN_URL . '/js/tooltipster/js/jquery.tooltipster.min.js', array(), '3.3.0', true);
			wp_register_script('booked-functions', BOOKED_PLUGIN_URL . '/js/functions.js', array(), BOOKED_VERSION, true);

			$booked_redirect_type = get_option('booked_appointment_redirect_type','booked-profile');
			$booked_detect_profile_page = booked_get_profile_page();
			
			if ($booked_redirect_type == 'booked-profile'):
				$profile_page = ($booked_detect_profile_page ? $booked_detect_profile_page : false);
			elseif ($booked_redirect_type == 'page'):
				$profile_page = get_option('booked_appointment_success_redirect_page',false);
			else:
				$profile_page = false;
			endif;
			
			$profile_page = ($profile_page ? get_permalink($profile_page) : false);

			$booked_js_vars = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'profilePage' => $profile_page,
				'publicAppointments' => get_option('booked_public_appointments',false),
				'i18n_confirm_appt_delete' => esc_html__('Are you sure you want to cancel this appointment?','wyzi-business-finder'),
				'i18n_please_wait' => esc_html__('Please wait ...','wyzi-business-finder'),
				'i18n_wrong_username_pass' => esc_html__('Wrong username/password combination.','wyzi-business-finder'),
				'i18n_fill_out_required_fields' => esc_html__('Please fill out all required fields.','wyzi-business-finder'),
				'i18n_guest_appt_required_fields' => esc_html__('Please enter your name to book an appointment.','wyzi-business-finder'),
				'i18n_appt_required_fields' => esc_html__('Please enter your name, your email address and choose a password to book an appointment.','wyzi-business-finder'),
				'i18n_appt_required_fields_guest' => esc_html__('Please fill in all "Information" fields.','wyzi-business-finder'),
				'i18n_password_reset' => esc_html__('Please check your email for instructions on resetting your password.','wyzi-business-finder'),
				'i18n_password_reset_error' => esc_html__('That username or email is not recognized.','wyzi-business-finder'),
			);
			wp_localize_script( 'booked-functions', 'booked_js_vars', $booked_js_vars );
			wp_enqueue_script('booked-functions');
			
		}

		public static function front_end_styles() {
			wp_enqueue_style('booked-tooltipster', 	BOOKED_PLUGIN_URL . '/js/tooltipster/css/tooltipster.css', array(), '3.3.0');
			wp_enqueue_style('booked-tooltipster-theme', 	BOOKED_PLUGIN_URL . '/js/tooltipster/css/themes/tooltipster-light.css', array(), '3.3.0');
			wp_enqueue_style('booked-animations', 	BOOKED_PLUGIN_URL . '/css/animations.css', array(), BOOKED_VERSION);
			wp_enqueue_style('booked-styles', 		BOOKED_PLUGIN_URL . '/css/styles.css', array(), BOOKED_VERSION);
			wp_enqueue_style('booked-responsive', 	BOOKED_PLUGIN_URL . '/css/responsive.css', array(), BOOKED_VERSION);
		}

		public static function front_end_color_theme() {
			if (!isset($_GET['print'])):
				$colors_pattern_file = BOOKED_PLUGIN_DIR . '/css/color-theme.php';
				if ( !file_exists($colors_pattern_file) ) {
					return;
				}
			
				ob_start();
				include(esc_attr($colors_pattern_file));
				$booked_color_css = ob_get_clean();
				
				$compressed_booked_color_css = booked_compress_css( $booked_color_css );
				
				echo '<style type="text/css" media="screen">';
					echo $compressed_booked_color_css;
				echo '</style>';
				
			endif;
		}
		
		public static function booked_user_roles_filter( $booked_user_roles ) {
			return $booked_user_roles;
		}

	} // END class booked_plugin
} // END if(!class_exists('booked_plugin'))

function booked_mailer($to,$subject,$message,$from_email = false,$from_name = false){
	
	add_filter( 'wp_mail_content_type', 'booked_set_html_content_type' );
	
	$booked_email_logo = get_option('booked_email_logo');
	if ($booked_email_logo):
		$logo = apply_filters( 'booked_email_logo_html', '<img src="'.$booked_email_logo.'" style="max-width:100%; height:auto; display:block; margin:10px 0 20px;">' );
	else :
		$logo = apply_filters( 'booked_email_logo_html', '' );
	endif;

	$link_color = get_option('booked_button_color','#56C477');
	$force_sender = get_option('booked_email_force_sender',false);

	if ( $force_sender ):
		$sender_email = get_option('admin_email');
		$from_email = $sender_email;
		$from_name = false;
	endif;

	if ( file_exists( get_stylesheet_directory() . '/booked/email-template.html' ) ):
		$template = file_get_contents( get_stylesheet_directory() . '/booked/email-template.html', true );
	elseif ( file_exists( get_template_directory() . '/booked/email-template.html' ) ):
		$template = file_get_contents( get_template_directory() . '/booked/email-template.html', true );
	else:
		$template = file_get_contents( untrailingslashit( BOOKED_PLUGIN_DIR ) . '/includes/email-templates/default.html', true );
	endif;

	$filter = array('%content%','%logo%','%link_color%');
	$replace = array(wpautop($message),$logo,$link_color);
	if ( $from_email ):
		$headers[] = 'From: ' . ( $from_name ? $from_name . ' <' . $from_email . '>' : $from_email );
	endif;
	$headers[] = 'Content-Type: text/html; charset=UTF-8';
	$message = str_replace($filter, $replace, $template);

	wp_mail($to,$subject,$message,$headers);

	remove_filter( 'wp_mail_content_type', 'booked_set_html_content_type' );

}

function booked_set_html_content_type() {
	return 'text/html';
}

if(class_exists('booked_plugin')) {

	// Activation Hook
	//register_activation_hook(__FILE__, array('booked_plugin', 'activate'));

	// Initiate the Booked Class
	$booked_plugin = new booked_plugin();

	// Add a link to the settings page onto the plugin page
	if(isset($booked_plugin)) {
		
		// Add the settings link to the plugins page
		function booked_custom_links($links) {
			
			$custom_links[] = '<a href="admin.php?page=booked-settings">'.esc_html__('Settings','wyzi-business-finder').'</a>';
			$custom_links[] = '<a href="'.trailingslashit(get_admin_url()).'admin.php?page=booked-welcome">'.esc_html__('What\'s New?','wyzi-business-finder').'</a>';
			
			foreach($custom_links as $custom_link):
				array_unshift($links, $custom_link);
			endforeach;
			
			return $links;
			
		}

		$plugin = plugin_basename(__FILE__);
		add_filter("plugin_action_links_$plugin", 'booked_custom_links');

		// Load the Front-End Styles and Color Settings
		add_action('wp_enqueue_scripts', array('booked_plugin', 'front_end_styles'));
		add_action('wp_enqueue_scripts', array('booked_plugin', 'front_end_color_theme'));
		
	}
}
