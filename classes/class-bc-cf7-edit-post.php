<?php

if(!class_exists('BC_CF7_Edit_Post')){
    final class BC_CF7_Edit_Post {

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

        private $file = '', $post_id = 0;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __clone(){}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __construct($file = ''){
            $this->file = $file;
            add_action('bc_cf7_types_loaded', [$this, 'bc_cf7_types_loaded']);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private function get_post_id($contact_form = null, $submission = null){
            if(null === $contact_form){
                $contact_form = wpcf7_get_current_contact_form();
            }
            if(null === $contact_form){
                return new WP_Error('bc_error', __('The requested contact form was not found.', 'contact-form-7'));
            }
            $type = bc_cf7()->type($contact_form);
            if('' === $type){
                return new WP_Error('bc_error', sprintf(__('Missing parameter(s): %s'), 'bc_type') . '.');
            }
            if(!$this->is_type($contact_form)){
                return new WP_Error('bc_error', sprintf(__('%1$s is not of type %2$s.'), $type, 'edit-post'));
            }
            $missing = [];
            if(null === $submission){
                $submission = WPCF7_Submission::get_instance();
            }
            if(null === $submission){
                $nonce = null;
                $post_id = $contact_form->shortcode_attr('bc_post_id');
            } else {
                $nonce = $submission->get_posted_data('bc_nonce');
                if(null === $nonce){
                    $missing[] = 'bc_nonce';
                }
                $post_id = $submission->get_posted_data('bc_post_id');
            }
            if(null === $post_id){
                $missing[] = 'bc_post_id';
            }
            if($missing){
                return new WP_Error('bc_error', sprintf(__('Missing parameter(s): %s'), implode(', ', $missing)) . '.');
            }
            if(null !== $nonce and !wp_verify_nonce($nonce, 'bc-edit-post_' . $post_id)){
                $message = __('The link you followed has expired.');
                $message .=  ' ' . bc_last_p(__('An error has occurred. Please reload the page and try again.'));
                return new WP_Error('bc_error', $message);
            }
            $post_id = $this->sanitize_post_id($post_id);
            if(0 === $post_id){
                return new WP_Error('bc_error', __('Invalid post ID.'));
            }
            if(!current_user_can('edit_post', $post_id)){
                if('post' === get_post_type($post_id)){
                    $message = __('Sorry, you are not allowed to edit this post.');
                } else {
                    $message = __('Sorry, you are not allowed to edit this item.');
                }
                $message .=  ' ' . __('You need a higher level of permission.');
                return new WP_Error('bc_error', $message);
			}
            if('trash' === get_post_status($post_id)){
                return new WP_Error('bc_error', __('You can&#8217;t edit this item because it is in the Trash. Please restore it and try again.'));
            }
            return $post_id;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function is_type($contact_form = null){
            return bc_cf7()->is_type('edit-post', $contact_form);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function output($post_id, $attr, $content, $tag){
            global $post;
            $post = get_post($post_id);
            setup_postdata($post);
            $output = wpcf7_contact_form_tag_func($attr, $content, $tag);
            wp_reset_postdata();
            return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function sanitize_post_id($post_id){
            $post = null;
            if(is_numeric($post_id)){
                $post = get_post($post_id);
            } else {
                if('current' === $post_id){
                    if(in_the_loop()){
                        $post = get_post();
                    }
                }
            }
            if(null === $post){
                return 0;
            }
            return $post->ID;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_free_text_value($value, $tag){
            if('' !== $value){
                return $value;
            }
            if(!$this->is_type()){
                return $value;
            }
            $post_id = $this->get_post_id();
            if(is_wp_error($post_id)){
                return $value;
            }
            return get_post_meta($post_id, $tag->name . '_free_text', true);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_types_loaded(){
            add_action('wpcf7_before_send_mail', [$this, 'wpcf7_before_send_mail'], 10, 3);
            add_filter('bc_cf7_free_text_value', [$this, 'bc_cf7_free_text_value'], 10, 2);
            add_filter('do_shortcode_tag', [$this, 'do_shortcode_tag'], 10, 4);
            add_filter('shortcode_atts_wpcf7', [$this, 'shortcode_atts_wpcf7'], 10, 3);
            add_filter('wpcf7_feedback_response', [$this, 'wpcf7_feedback_response'], 15, 2);
            add_filter('wpcf7_form_hidden_fields', [$this, 'wpcf7_form_hidden_fields'], 15);
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
            $post_id = $this->get_post_id();
            if(is_wp_error($post_id)){
                return '<div class="alert alert-danger" role="alert">' . $post_id->get_error_message() . '</div>';
            }
            $content = isset($m[5]) ? $m[5] : null;
            $output = $this->output($post_id, $attr, $content, $tag);
            return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function shortcode_atts_wpcf7($out, $pairs, $atts){
            if(isset($atts['bc_post_id'])){
                $out['bc_post_id'] = $atts['bc_post_id'];
            }
            return $out;
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
            $post_id = $this->get_post_id($contact_form, $submission);
            if(is_wp_error($post_id)){
                $submission->set_response($post_id->get_error_message());
                $submission->set_status('aborted'); // try to prevent conflicts with other plugins
                return;
            }
            $this->post_id = $post_id;
            $response = 'post' === get_post_type($post_id) ? __('Post updated.') : __('Item updated.');
            if(bc_cf7()->skip_mail($contact_form)){
                $submission->set_response($response);
                $submission->set_status('mail_sent');
            } else {
                if(bc_cf7()->mail($contact_form)){
                    $submission->set_response($response . ' ' . $contact_form->message('mail_sent_ok'));
                    $submission->set_status('mail_sent');
                } else {
                    $submission->set_response($response . ' ' . $contact_form->message('mail_sent_ng'));
                    $submission->set_status('mail_failed');
                }
            }
            bc_cf7()->update($contact_form, $submission, 'post', $post_id);
            do_action('bc_cf7_edit_post', $post_id, $contact_form, $submission);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_feedback_response($response, $result){
            if(0 !== $this->post_id){
                if(isset($response['bc_uniqid']) and '' !== $response['bc_uniqid']){
                    $uniqid = get_post_meta($this->post_id, 'bc_uniqid', true);
                    if('' !== $uniqid){
                        $response['bc_uniqid'] = $uniqid;
                    }
                }
            }
            return $response;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_form_hidden_fields($hidden_fields){
            if(!$this->is_type()){
                return $hidden_fields;
            }
            $post_id = $this->get_post_id();
            if(is_wp_error($post_id)){
                return $hidden_fields;
            }
            $hidden_fields['bc_post_id'] = $post_id;
            $hidden_fields['bc_nonce'] = wp_create_nonce('bc-edit-post_' . $post_id);
            if(isset($hidden_fields['bc_uniqid'])){
                $uniqid = get_post_meta($post_id, 'bc_uniqid', true);
                if('' !== $uniqid){
                    $hidden_fields['bc_uniqid'] = $uniqid;
                }
            }
            return $hidden_fields;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
