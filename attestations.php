<?php
/*
Plugin Name: HDA Attestations
Description: Attestation management for Historical Dance Association
Version:     0.1
Author:      Rostislav I. Kondratenko
License:     WTFPL
License URI: http://www.wtfpl.net/about/
Domain Path: /languages
Text Domain: attestations
*
* DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
*                    Version 2, December 2004
*
* Copyright (C) 2004 Sam Hocevar <sam@hocevar.net>
*
* Everyone is permitted to copy and distribute verbatim or modified
* copies of this license document, and changing it is allowed as long
* as the name is changed.
*
*            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
*   TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
*
*  0. You just DO WHAT THE FUCK YOU WANT TO.
*/
defined('ABSPATH') or die('');
require_once (dirname(__FILE__) . '/includes/functions.php');

function atttestations_install() {
    $the_page_title = 'Аттестация';
    $the_page_name = 'attestations';
    delete_option('attestations_page_title');
    add_option('attestations_page_title', $the_page_name, '', 'yes');
    delete_option('attestations_page_name');
    add_option('attestations_page_name', $the_page_name, '', 'yes');
    delete_option('attestations_page_id');
    add_option('attestations_page_id', '0', '', 'yes');
    $the_page = get_page_by_title($the_page_name);
    if (!$the_page) {
        $_p = array();
        $_p['post_title'] = $the_page_title;
        $_p['post_name'] = $the_page_name;
        $_p['post_content'] = 'Главная страница аттестации';
        $_p['post_status'] = 'publish';
        $_p['post_type'] = 'page';
        $_p['comment_status'] = 'closed';
        $_p['ping_status'] = 'closed';
        $_p['post_category'] = array(1);
        $the_page_id = wp_insert_post($_p);
    } else {
        $the_page->post_status = 'publish';
        $the_page_id = wp_update_post($the_page);
    }
    delete_option('attestations_page_id');
    add_option('attestations_page_id', $the_page_id);
}
register_activation_hook(__FILE__, 'atttestations_install');

function atttestations_remove() {
    $the_page_id = get_option('attestations_page_id');
    if ($the_page_id) {
        wp_delete_post($the_page_id, true);
    }
    delete_option('attestations_page_title');
    delete_option('attestations_page_name');
    delete_option('attestations_page_id');
}
register_deactivation_hook(__FILE__, 'atttestations_remove');

function attestations_css() {
    wp_register_style('attestations_css', plugins_url('styles/styles.css', __FILE__));
    wp_enqueue_style('attestations_css');
}
add_action('init', 'attestations_css');

function add_query_vars($aVars) {
    $aVars[] = "att_person_id";
    $aVars[] = "att_period_id";
    $aVars[] = "att_city_id";
    return $aVars;
}
add_filter('query_vars', 'add_query_vars');

function attestations_create_menu() {
    $l = attestations_current_user_levels();
    if (array_search('3', $l) !== false || array_search('4', $l) !== false) {
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', plugins_url('styles/jquery-ui.css', __FILE__));
        add_menu_page('Аттестации', 'Аттестации', 'edit_posts', __FILE__, 'attestations_settings_page', 'dashicons-welcome-learn-more');
        add_submenu_page(__FILE__, 'История оценок', 'История оценок', 'edit_posts', 'attestations_history_page', 'attestations_history_page');
    }
}
add_action('admin_menu', 'attestations_create_menu');


require_once (dirname(__FILE__) . '/includes/main_page.php');
add_filter('the_posts', 'attestations_page_filter');
add_filter('parse_query', 'atttestations_parser');

require_once (dirname(__FILE__) . '/includes/admin.php');
add_action('wp_ajax_att_people', 'attestations_get_people_callback');
add_action('wp_ajax_att_person_add', 'attestations_new_person_callback');
add_action('wp_ajax_att_get_levels', 'attestations_get_person_levels_callback');

add_action('admin_post_attestations_form', 'attestations_submitted');
add_action('admin_notices', "show_admin_notice");

require_once (dirname(__FILE__) . '/includes/history.php');
add_action('wp_ajax_attestation_report', 'attestations_attestation_report_callback');
add_action('wp_ajax_attestation_remove', 'attestations_attestation_remove_callback');

require_once (dirname(__FILE__) . '/includes/profile.php');
add_action('show_user_profile', 'attestations_user_profile_fields');
add_action('edit_user_profile', 'attestations_user_profile_fields');
add_action('personal_options_update', 'attestations_profile_fields');
add_action('edit_user_profile_update', 'attestations_profile_fields');
