<?php

namespace DB_ACF_UI;

class Compatibility {
	/**
	 * admin_init hook callback
	 */
	public static function admin_init() {
		// Niet op AJAX
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		// Check of de gebruiker plugin kan activeren
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		trigger_error( sprintf(
			__( 'DB ACF UI requires PHP version %s or greater to be activated.', 'db-acf-ui' ),
			DB_ACF_UI_MIN_PHP_VERSION
		) );

		// Deactiveer de plugin
		deactivate_plugins( DB_ACF_UI_PATH . 'db-acf-ui.php' );

		unset( $_GET['activate'] );

		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	}

	/**
	 * Toon melding over incompatibiliteit
	 */
	public static function admin_notices() {
		echo '<div class="notice error is-dismissible">';
		echo '<p>' . esc_html( sprintf(
			__( 'DB ACF UI requires PHP version %s or greater to be activated. Your server is currently running PHP version %s.', 'db-acf-ui' ),
			DB_ACF_UI_MIN_PHP_VERSION,
			PHP_VERSION
		) ) . '</p>';
		echo '</div>';
	}
}