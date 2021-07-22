<?php
/*
Author: Beaver Coffee
Author URI: https://beaver.coffee
Description: A collection of useful types for Contact Form 7.
Domain Path:
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true
Plugin Name: BC CF7 Types
Plugin URI: https://github.com/beavercoffee/bc-cf7-types
Requires at least: 5.7
Requires PHP: 5.6
Text Domain: bc-cf7-types
Version: 1.7.20
*/

if(defined('ABSPATH')){
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-edit-post.php');
    BC_CF7_Edit_Post::get_instance(__FILE__);
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-edit-user.php');
    BC_CF7_Edit_User::get_instance(__FILE__);
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-login.php');
    BC_CF7_Login::get_instance(__FILE__);
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-logout.php');
    BC_CF7_Logout::get_instance(__FILE__);
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-payment-intent.php');
    BC_CF7_Payment_Intent::get_instance(__FILE__);
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-retrieve-password.php');
    BC_CF7_Retrieve_Password::get_instance(__FILE__);
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-signup.php');
    BC_CF7_Signup::get_instance(__FILE__);
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-cf7-types.php');
    BC_CF7_Types::get_instance(__FILE__);
    require_once(plugin_dir_path(__FILE__) . 'classes/class-bc-payment-intent.php');
}
