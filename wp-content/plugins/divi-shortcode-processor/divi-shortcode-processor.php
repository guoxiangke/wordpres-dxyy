<?php
/*
Plugin Name: Divi Shortcode Enabler
Plugin URI: https://divibooster.com
Description: Runs shortcodes where Divi doesn't let you.
Author: Divi Booster
Version: 0.5.2
Author URI: https://divibooster.com
*/

define('DBDSP_VERSION', '0.5.2');

// === Updates ===
$updatename = 'divi-shortcode-processor';
$updateurl = 'https://d2hhmc1yvsixby.cloudfront.net/updates.json'; 
include_once(dirname(__FILE__).'/updates/plugin-update-checker.php');
try {
	$MyUpdateChecker = new DMWP_PluginUpdateChecker_1_0_0($updateurl, __FILE__, $updatename);
} catch (Exception $e) {}

// === Shortcode processing ===

add_filter('the_content', 'dbdsp_process_url_shortcodes');

function dbdsp_process_url_shortcodes($content) {
	
	$modules = apply_filters('dbdsp_fields_to_process', 
		array(
			'et_pb_accordion_item' => array('title'),
			'et_pb_blurb' => array('title', 'url', 'image'),
			'et_pb_button' => array('button_url', 'button_text'),
			'et_pb_cta' => array('title', 'button_text', 'button_url'),
			'et_pb_image' => array('url', 'src'),
			'et_pb_pricing_table' => array('title', 'subtitle', 'currency', 'sum', 'button_text', 'button_url'),
			'et_pb_tab' => array('title')
		)
	);
	
	do_action('dbdsp_pre_shortcode_processing');
	
	// Process the shortcodes
	foreach($modules as $module=>$fields) {
		foreach($fields as $field) {
			$regex = '#\['.preg_quote($module).' [^\]]*?\b'.preg_quote($field).'="([^"]+)"#';
			$content = preg_replace_callback($regex, 'dbdsp_process_url_shortcodes_callback', $content);
		}
	}
	
	do_action('dbdsp_post_shortcode_processing'); 
	
	return $content;
}

function dbdsp_process_url_shortcodes_callback($matches) {
	
	// Exit if not properly matched
	if (!is_array($matches) || !isset($matches[0])) { return ''; } 
	if (!isset($matches[1])) { return $matches[0]; }
	
	// Define character replacements
	$encoded = array('%22', '%91', '%93');
	$decoded = array('"', '[', ']');
		
	// Get the decoded parameter value
	$val = $matches[1];
	$val = str_replace($encoded, $decoded, $val); // decode encoded characters
	
	// Process any shortcodes in value
	$val = preg_replace_callback('#'.get_shortcode_regex().'#', 'dbdsp_process_url_shortcodes_individual', $val);
	
	// Re-encode the parameter value
	$val = str_replace($decoded, $encoded, $val); // Encode [ and ]
	
	// Return the replacement value
	return str_replace($matches[1], $val, $matches[0]);
}

// Process matched (url encoded) shortcode
function dbdsp_process_url_shortcodes_individual($matches) {
	
	// Exit if not properly matched
	if (!is_array($matches) || !isset($matches[0])) { return ''; } 
	
	// Exit if not a defined shortcode
	if (!isset($matches[2]) || !shortcode_exists($matches[2])) { return $matches[0]; } 
	
	// Evaluate the shortcode
	$shortcode = $matches[0];
	$output = do_shortcode($shortcode);
	
	return $output;
}

// === Register shortcodes for use in the fields ===

add_action('dbdsp_pre_shortcode_processing', 'dbdsp_register_shortcodes');
add_action('dbdsp_post_shortcode_processing', 'dbdsp_deregister_shortcodes');

function dbdsp_register_shortcodes() {
	add_shortcode('site_url', 'dbdsp_shortcode_site_url'); 
	add_shortcode('custom_field', 'dbdsp_shortcode_custom_field'); 
}

function dbdsp_deregister_shortcodes() {
	remove_shortcode('site_url'); 
	remove_shortcode('custom_field'); 
}

// Shortcode to return the site URL
function dbdsp_shortcode_site_url() {
    return site_url();
}

// Shortcode to return the value from a custom field
function dbdsp_shortcode_custom_field($args) {
	global $post;
	
	$args = wp_parse_args($args, array(
		'name' => '',
		'post_id' => $post->ID
	));

	$key = $args['name'];
	if (empty($key)) { return ''; }

	return get_post_meta($args['post_id'], $key, true);
}
add_shortcode('custom_field', 'dbdsp_shortcode_custom_field');