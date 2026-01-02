<?php
/*
Plugin Name: DB ACF Extension
Description: Aangepaste ACF interface voor Digitale Bazen
Version: 1.2.2
Author: Digitale Bazen
Text Domain: db-acf-ui
Update URI: bitbucket.org/digitale-bazen/db-acf-extension

Icon: assets/images/icon-128x128.png
Banner: assets/images/banner-772x250.png
Banner-HighRes: assets/images/banner-1544x500.png
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ---------------------------
 * Constants
 * ---------------------------
 */
define( 'DB_ACF_UI_VERSION', '1.2.2' );
define( 'DB_ACF_UI_MIN_PHP_VERSION', '8.0' );

define( 'DB_ACF_UI_FILE', __FILE__ );
define( 'DB_ACF_UI_DIR', plugin_dir_path( __FILE__ ) );
define( 'DB_ACF_UI_URL', plugin_dir_url( __FILE__ ) );
define( 'DB_ACF_UI_SLUG', 'db-acf-extension' );

// GitHub repo info
define( 'DB_ACF_UI_GITHUB_REPO', 'yanickvdkorst/DB-ACF-Extension' );

/**
 * ---------------------------
 * PHP version check
 * ---------------------------
 */
if ( version_compare( PHP_VERSION, DB_ACF_UI_MIN_PHP_VERSION, '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>DB ACF Extension vereist PHP 8.0 of hoger.</p></div>';
    } );
    return;
}

/**
 * ---------------------------
 * Autoload
 * ---------------------------
 */
require_once DB_ACF_UI_DIR . 'autoload.php';

/**
 * ---------------------------
 * Init plugin
 * ---------------------------
 */
add_action( 'plugins_loaded', function () {
    if ( ! function_exists( 'acf' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>Advanced Custom Fields is vereist voor DB ACF Extension.</p></div>';
        } );
        return;
    }

    \DB_ACF_UI\Main::get_instance();
} );

/**
 * ---------------------------
 * Bitbucket updater
 * ---------------------------
 */

add_filter('pre_set_site_transient_update_plugins', function($transient) {

    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = plugin_basename(DB_ACF_UI_FILE);
    $url = 'https://api.bitbucket.org/2.0/repositories/digitale-bazen/db-acf-extension/refs/tags?sort=-name&pagelen=1';

    $response = wp_remote_get($url, [
        'headers' => ['User-Agent' => 'WordPress'],
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        add_action('admin_notices', function() use ($response) {
            echo '<div class="notice notice-error"><p>[DB ACF] Bitbucket API error: ' . esc_html($response->get_error_message()) . '</p></div>';
        });
        return $transient;
    }

    $data = json_decode(wp_remote_retrieve_body($response));
    if (empty($data->values)) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p>[DB ACF] Geen tags gevonden op Bitbucket.</p></div>';
        });
        return $transient;
    }

    // Vind de hoogste versie tag
    $latest_tag = '';
    foreach ($data->values as $tag) {
        $ver = ltrim($tag->name, 'v');
        if (!$latest_tag || version_compare($ver, ltrim($latest_tag, 'v'), '>')) {
            $latest_tag = $tag->name;
        }
    }

    $remote_version = ltrim($latest_tag, 'v');

    // Als remote hoger is, bied update aan
    if (version_compare($remote_version, DB_ACF_UI_VERSION, '>')) {
        $transient->response[$plugin_slug] = (object)[
            'slug'        => DB_ACF_UI_SLUG,
            'plugin'      => $plugin_slug,
            'new_version' => $remote_version,
            'url'         => 'https://bitbucket.org/digitale-bazen/db-acf-extension',
            'package'     => 'https://bitbucket.org/digitale-bazen/db-acf-extension/get/' . $latest_tag . '.zip',
        ];
    }

    return $transient;
});



/**
 * Forceer vaste pluginmapnaam na uitpakken van Bitbucket ZIP (met backup/restore).
 */
add_filter('upgrader_source_selection', function ($source, $remote_source, $upgrader, $hook_extra) {

    // Alleen ingrijpen bij plugin install/update
    $is_plugin_op = isset($hook_extra['type']) && $hook_extra['type'] === 'plugin';
    if (!$is_plugin_op) {
        return $source;
    }

    $desired_folder_name = 'db-acf-extension';

    global $wp_filesystem;

    if (!$wp_filesystem || !is_object($upgrader) || !isset($upgrader->skin)) {
        return $source; // geen veilige context
    }

    $plugins_dir  = trailingslashit($upgrader->skin->plugins_dir);
    $desired_path = trailingslashit($plugins_dir . $desired_folder_name);

    // Als bron al de gewenste naam heeft -> niets doen
    if (basename($source) === $desired_folder_name) {
        return $source;
    }

    // ---- Backup naam (timestamp) ----
    // Wil je een vaste suffix? Vervang $backup_suffix door bijvoorbeeld 'IT-gids-studenten-website'
    $backup_suffix = gmdate('Ymd-His');
    $backup_path   = untrailingslashit($desired_path) . '-backup-' . $backup_suffix . '/';

    // Bestaat de doelmap? -> eerst veilig wegzetten als backup (rename)
    if ($wp_filesystem->is_dir($desired_path)) {
        // Maak desnoods de parent dir klaar (normaal al aanwezig)
        // Rename i.p.v. delete (veiliger)
        if (!$wp_filesystem->move($desired_path, $backup_path, true)) {
            // Backup rename faalde -> laat origineel staan en niet ingrijpen
            return $source;
        }
    }

    // Probeer de uitgepakte bronmap te verplaatsen/hernoemen naar de vaste naam
    $moved = $wp_filesystem->move($source, $desired_path, true);

    if ($moved) {
        // Move gelukt -> backup opruimen (niet verplicht, maar netjes)
        if ($wp_filesystem->is_dir($backup_path)) {
            $wp_filesystem->delete($backup_path, true);
        }
        return $desired_path;
    }

    // Move faalde -> herstel backup (rollback)
    if ($wp_filesystem->is_dir($backup_path)) {
        $wp_filesystem->move($backup_path, $desired_path, true);
    }

    // Fallback: ga verder met originele bron
    return $source;

}, 20, 4);

