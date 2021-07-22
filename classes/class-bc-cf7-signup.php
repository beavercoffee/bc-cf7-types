<?php

if(!class_exists('BC_CF7_Signup')){
    final class BC_CF7_Signup {

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

    	private function get_role($contact_form = null){
            $role = bc_cf7()->pref('bc_default_role', $contact_form);
            if('' === $role){
                return get_option('default_role');
            }
            if(!wp_roles()->is_role($role)){
                return get_option('default_role');
            }
            return $role;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function is_type($contact_form = null){
            return bc_cf7()->is_type('signup', $contact_form);
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
            add_filter('wpcf7_validate_password*', [$this, 'wpcf7_validate_password'], 11, 2);
            add_filter('wpcf7_validate_text*', [$this, 'wpcf7_validate_text'], 11, 2);
            $this->fields = ['user_email', 'user_login', 'user_password', 'user_password_confirm'];
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
            if(!isset($tags['user_email'])){
                $missing[] = 'user_email';
            }
            if(isset($tags['user_password_confirm']) and !isset($tags['user_password'])){
                $missing[] = 'user_password';
            }
            if($missing){
                $error = current_user_can('manage_options') ? sprintf(__('Missing parameter(s): %s'), implode(', ', $missing)) . '.' : __('Something went wrong.');
                return '<div class="alert alert-danger" role="alert">' . $error . '</div>';
            }
            $invalid = [];
            if(isset($tags['user_email']) and $tags['user_email'] !== 'email*'){
                $invalid[] = 'user_email';
            }
            if(isset($tags['user_login']) and $tags['user_login'] !== 'text*'){
                $invalid[] = 'user_login';
            }
            if(isset($tags['user_password']) and $tags['user_password'] !== 'password*'){
                $invalid[] = 'user_password';
            }
            if(isset($tags['user_password_confirm']) and $tags['user_password_confirm'] !== 'password*'){
                $invalid[] = 'user_password_confirm';
            }
            if($invalid){
                $error = current_user_can('manage_options') ? sprintf(__('Invalid parameter(s): %s'), implode(', ', $invalid)) . '.' : __('Something went wrong.');
                return '<div class="alert alert-danger" role="alert">' . $error . '</div>';
            }
            if(is_user_logged_in() and !current_user_can('create_users')){
                $error = __('Sorry, you are not allowed to create new users.') . ' ' . __('You need a higher level of permission.');
                return '<div class="alert alert-danger" role="alert">' . $error . '</div>';
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
            $user_password = bc_cf7()->get_posted_data('user_password');
            if('' === $user_login){
                $user_login = $user_email;
            }
            $generated_password = false;
            if('' === $user_password){
                $generated_password = true;
                $user_password = wp_generate_password(12, false);
            }
            $user_id = wp_insert_user([
                'role' => $this->get_role($contact_form),
                'user_email' => wp_slash($user_email),
                'user_login' => wp_slash($user_login),
                'user_pass' => $user_password,
            ]);
            if(is_wp_error($user_id)){
                $message = $user_id->get_error_message();
                $submission->set_response(wp_strip_all_tags($message));
                $submission->set_status('aborted'); // try to prevent conflicts with other plugins
                return;
            }
            $message = sprintf(__('Registration complete. Please check your email, then visit the <a href="%s">login page</a>.'), wp_login_url());
            if(!$generated_password){
                $message = bc_first_p($message);
            }
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
            $notify = bc_cf7()->pref('bc_notify', $contact_form);
            switch($notify){
                case 'admin':
                    wp_new_user_notification($user_id, null, 'admin');
                    break;
                case 'both':
                    if($generated_password){
                        wp_new_user_notification($user_id, null, 'both');
                    } else {
                        wp_new_user_notification($user_id, null, 'admin');
                    }
                    break;
                case 'user':
                    if($generated_password){
                        wp_new_user_notification($user_id, null, 'admin');
                    }
                    break;
            }
            bc_cf7()->update($contact_form, $submission, 'user', $user_id);
            do_action('bc_cf7_signup', $user_id, $contact_form, $submission);
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
            if(email_exists($user_email)){
                $message = __('<strong>Error</strong>: This email is already registered. Please choose another one.');
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            if(username_exists($user_email)){
                $message = __('<strong>Error</strong>: This username is already registered. Please choose another one.');
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            return $result;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_validate_password($result, $tag){
            if(!in_array($tag->name, ['user_password', 'user_password_confirm'])){
                return $result;
            }
            if(!$this->is_type()){
                return $result;
            }
            $user_password = bc_cf7()->get_posted_data('user_password');
            $user_password_confirm = bc_cf7()->get_posted_data('user_password_confirm');
            switch($tag->name){
                case 'user_password':
                    if(false !== strpos(wp_unslash($user_password), '\\')){
                        $message = __('<strong>Error</strong>: Passwords may not contain the character "\\".');
                        $result->invalidate($tag, wp_strip_all_tags($message));
                        return $result;
                    }
                    break;
                case 'user_password_confirm':
                    if($user_password !== $user_password_confirm){
                        $message = __('<strong>Error</strong>: Passwords don&#8217;t match. Please enter the same password in both password fields.');
                        $result->invalidate($tag, wp_strip_all_tags($message));
                        return $result;
                    }
                    break;
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
            if(!validate_username($user_login)){
                $message = __('<strong>Error</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.');
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            $illegal_user_logins = (array) apply_filters('illegal_user_logins', ['admin']);
            if(in_array(strtolower($user_login), array_map('strtolower', $illegal_user_logins), true)){
                $message = __('<strong>Error</strong>: Sorry, that username is not allowed.');
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            if(username_exists($user_login)){
                $message = __('<strong>Error</strong>: This username is already registered. Please choose another one.');
                $result->invalidate($tag, wp_strip_all_tags($message));
                return $result;
            }
            if(wpcf7_is_email($user_login)){
                if(email_exists($user_login)){
                    $message = __('<strong>Error</strong>: This email is already registered. Please choose another one.');
                    $result->invalidate($tag, wp_strip_all_tags($message));
                    return $result;
                }
            }
            return $result;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
