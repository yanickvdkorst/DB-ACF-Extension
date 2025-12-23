<?php
/*
 Plugin Name: DB ACF Extension
 Version: 1.0.0
 Description: Aangepaste ACF interface voor Digitale Bazen
 Author: Digitale Bazen
 Text Domain: db-acf-ui

* GitHub Plugin URI: https://github.com/yanickvdkorst/DB-ACF-Extension.git
* GitHub Branch: main
*/

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Plugin constants
 */
define( 'DB_ACF_UI_VERSION', '1.0.0' );
define( 'DB_ACF_UI_MIN_PHP_VERSION', '8.0' );

/**
 * Plugin URL and PATH
 */
define( 'DB_ACF_UI_URL', plugin_dir_url( __FILE__ ) );
define( 'DB_ACF_UI_DIR', plugin_dir_path( __FILE__ ) );
define( 'DB_ACF_UI_PLUGIN_DIRNAME', basename( rtrim( dirname( __FILE__ ), '/' ) ) );

/**
 * Check PHP version
 */
if ( version_compare( PHP_VERSION, DB_ACF_UI_MIN_PHP_VERSION, '<' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p>DB ACF UI vereist PHP 8.0 of hoger.</p></div>';
	});
	return;
}

/**
 * Autoload
 */
require_once DB_ACF_UI_DIR . 'autoload.php';

/**
 * Init plugin
 */
add_action( 'plugins_loaded', 'db_acf_ui_init' );

function db_acf_ui_init() {

	// Check ACF
	if ( ! function_exists( 'acf' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>Advanced Custom Fields is vereist voor DB ACF UI.</p></div>';
		});
		return;
	}

	// Init main class
	\DB_ACF_UI\Main::get_instance();
}