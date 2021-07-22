<?php

if(!class_exists('BC_CF7_Retrieve_Password')){
    final class BC_CF7_Retrieve_Password {

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private static $instance = null;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public static function get_instance($file = ''){
            if(null !== self::$instance){
                return self::$instance;
            }
            if('' === $file){
                wp_die(__('File doesn&#8217;t exist?'));
            }
            if(!is_file($file)){
                wp_die(sprintf(__('File &#8220;%s&#8221; doesn&#8217;t exist?'), $file));
            }
            self::$instance = new self($file);
            return self::$instance;
    	}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private $file = '';

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __clone(){}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __construct($file = ''){
            $this->file = $file;
            add_action('bc_cf7_types_loaded', [$this, 'bc_cf7_types_loaded']);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function is_type($contact_form = null){
            return bc_cf7()->is_type('retrieve-password', $contact_form);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_types_loaded(){
            add_action('wpcf7_before_send_mail', [$this, 'wpcf7_before_send_mail'], 10, 3);
            add_filter('do_shortcode_tag', [$this, 'do_shortcode_tag'], 10, 4);
            add_filter('wpcf7_posted_data', [$this, 'wpcf7_posted_data'], 15);
            add_filter('wpcf7_validate_email*', [$this, 'wpcf7_validate_email'], 11, 2);
            add_filter('wpcf7_validate_text*', [$this, 'wpcf7_validate_text'], 11, 2);
            $this->fields = ['user_email', 'user_login'];
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function do_shortcode_tag($output, $tag, $attr, $m){
			if('contact-form-7' !== $tag){
                return $output;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if(null === $contact_form){
                return $output;
            }
            if(!$this->is_type($contact_form)){
                return $output;
            }
            $tags = wp_list_pluck($contact_form->scan_form_tags(), 'type', 'name');
            $missing = [];
            if(!isset($tags['user_email']) and !isset($tags['user_login'])){
                $missing[] = 'user_login';
            }
            if($missing){
                $error = current_user_can('manage_options') ? sprintf(__('Missing parameter(s): %s'), implode(', ', $missing)) . '.' : __('Something went wrong.');
                return '<div class="alert alert-danger" role="alert">' . $error . '</div>';
            }
            if(isset($tags['user_email']) and isset($tags['user_login'])){
                $error = current_user_can('manage_options') ? sprintf(__('Invalid parameter(s): %s'), __('Duplicated username or email address.')) : __('Something went wrong.');
                return '<div class="alert alert-danger" role="alert">' . $error . '</div>';
            }
            $invalid = [];
            if(isset($tags['user_email']) and $tags['user_email'] !== 'email*'){
                $invalid[] = 'user_email';
            }
            if(isset($tags['user_login']) and $tags['user_login'] !== 'text*'){
                $invalid[] = 'user_login';
            }
            if($invalid){
                $error = current_user_can('manage_options') ? sprintf(__('Invalid parameter(s): %s'), implode(', ', $invalid)) . '.' : __('Something went wrong.');
                return '<div class="alert alert-danger" role="alert">' . $error . '</div>';
            }
            if(is_user_logged_in()){
                $error = __('You are logged in already. No need to register again!');
                $error = bc_first_p($error);
                return '<div class="alert alert-warning" role="alert">' . $error . '</div>';
            }
            return $output;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_before_send_mail($contact_form, &$abort, $submission){
            if(!$this->is_type($contact_form)){
                return;
            }
            if(!$submission->is('init')){
                return; // prevent conflicts with other plugins
            }
            $abort = true; // prevent mail_sent and mail_failed actions
            $user_email = bc_cf7()->get_posted_data('user_email');
            $user_login = bc_cf7()->get_posted_data('user_login');
            if('' === $user_login){
                $user_login = $user_email;
            }
            $result = retrieve_password($user_login);
            if(is_wp_error($result)){
                $message = $result->get_error_message();
                $submission->set_response(wp_strip_all_tags($message));
                $submission->set_status('aborted'); // try to prevent conflicts with other plugins
                return;
            }
            $message = sprintf(__('Check your email for the confirmation link, then visit the <a href="%s">login page</a>.'), wp_login_url());
            if(bc_cf7()->skip_mail($contact_form)){
                $submission->set_response(wp_strip_all_tags($message));
                $submission->set_status('mail_sent');
            } else {
                if(bc_cf7()->mail($contact_form)){
                    $message .= ' ' . $contact_form->message('mail_sent_ok');
                    $submission->set_response(wp_strip_all_tags($message));
                    $submission->set_status('mail_sent');
                } else {
                    $message .= ' ' . $contact_form->message('mail_sent_ng');
                    $submission->set_response(wp_strip_all_tags($message));
                    $submission->set_status('mail_failed');
                }
            }
            do_action('bc_cf7_retrieve_password', $user_login, $contact_form, $submission);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_posted_data($posted_data){
            if(!$this->is_type()){
                return $posted_data;
            }
            foreach($this->fields as $field){
        		if(isset($posted_data[$field])){
        			unset($posted_data[$field]);
        		}
        	}
        	return $posted_data;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_validate_email($result, $tag){
            if('user_email' !== $tag->name){
                return $result;
            }
            if(!$this->is_type()){
                return $result;
            }
            $user_email = bc_cf7()->get_posted_data('user_email');
            if(!email_exists($user_email)){
                $message = __('Unknown email address. Check again or try your username.');
                $message = bc_first_p($message);
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            return $result;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_validate_text($result, $tag){
            if($tag->name !== 'user_login'){
                return $result;
            }
            if(!$this->is_type()){
                return $result;
            }
            $user_login = bc_cf7()->get_posted_data('user_login');
            if(wpcf7_is_email($user_login)){
                $message = __('Unknown email address. Check again or try your username.');
                $user = get_user_by('email', $user_login);
            } else {
                $message = __('Unknown username. Check again or try your email address.');
                $user = get_user_by('login', $user_login);
            }
            if(!$user){
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            return $result;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
