<?php
/*
Plugin Name: DB ACF Extension
Description: Aangepaste ACF interface voor Digitale Bazen
Version: 1.2.5
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
define( 'DB_ACF_UI_VERSION', '1.2.5' );
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
 * Forceer vaste pluginmapnaam bij updates én migreer 'active_plugins' naar het nieuwe pad.
 */
add_filter('upgrader_source_selection', function ($source, $remote_source, $upgrader, $hook_extra) {

    // Alleen ingrijpen bij plugin install/update
    if (!isset($hook_extra['type']) || $hook_extra['type'] !== 'plugin') {
        return $source;
    }

    global $wp_filesystem;
    if (!$wp_filesystem) {
        return $source;
    }

    // ---- Stel hier je vaste map- en bestandsnaam in ----
    $desired_folder_name = 'db-acf-extension';
    $main_file_name      = 'db-acf-ui.php'; // hoofd-pluginbestand
    $desired_basename    = $desired_folder_name . '/' . $main_file_name;

    // Huidige (oude) basename zoals WP hem nu kent (kan de hash-map zijn)
    $old_basename = plugin_basename(DB_ACF_UI_FILE);

    // 1) Hernoem de uitgepakte bronmap BINNEN $remote_source naar de vaste naam
    //    zodat de upgrader de uiteindelijke map als wp-content/plugins/db-acf-extension neerzet.
    if (basename($source) !== $desired_folder_name) {

        $new_source = trailingslashit($remote_source) . $desired_folder_name . '/';

        // Ruim eventueel bestaande map op die naam in de unpack-temp op
        if ($wp_filesystem->is_dir($new_source)) {
            $wp_filesystem->delete($new_source, true);
        }

        // Verplaats/rename de uitgepakte bronmap naar de vaste naam
        $moved = $wp_filesystem->move($source, $new_source, true);

        if ($moved) {
            // Zeer belangrijk: vanaf hier moet de upgrader dit nieuwe pad gebruiken
            $source = $new_source;
        }
        // Als move faalt, laten we $source ongemoeid (fallback)
    }

    // 2) Migreer de opgeslagen pluginpaden naar de nieuwe basename, zodat WP hem actief houdt.
    //    Dit vangt het scenario waarin de plugin eerder onder een hash-map actief was.

    // a) Gewone (niet-multisite) activatie
    $active = get_option('active_plugins', []);
    $changed = false;

    foreach ($active as $i => $path) {
        // Exact oude waarde, of een pad dat op de hash-variant lijkt
        if ($path === $old_basename
            || preg_match('#^digitale-bazen-db-acf-extension-[^/]+/' . preg_quote($main_file_name, '#') . '$#', $path)
            || preg_match('#^db-acf-extension-[^/]+/' . preg_quote($main_file_name, '#') . '$#', $path) // voor andere prefixen
        ) {
            $active[$i] = $desired_basename;
            $changed = true;
        }
    }

    if ($changed) {
        update_option('active_plugins', $active);
    }

    // b) Multisite: netwerkactivatie
    if (is_multisite()) {
        $sitewide = get_site_option('active_sitewide_plugins', []);
        $sw_changed = false;

        // Exact key match
        if (isset($sitewide[$old_basename])) {
            $sitewide[$desired_basename] = $sitewide[$old_basename];
            unset($sitewide[$old_basename]);
            $sw_changed = true;
        } else {
            // Hash-variant zoeken
            foreach (array_keys($sitewide) as $key) {
                if (preg_match('#^digitale-bazen-db-acf-extension-[^/]+/' . preg_quote($main_file_name, '#') . '$#', $key)
                    || preg_match('#^db-acf-extension-[^/]+/' . preg_quote($main_file_name, '#') . '$#', $key)
                ) {
                    $sitewide[$desired_basename] = $sitewide[$key];
                    unset($sitewide[$key]);
                    $sw_changed = true;
                }
            }
        }

        if ($sw_changed) {
            update_site_option('active_sitewide_plugins', $sitewide);
        }
    }

    // Retourneer (mogelijk) aangepast source-pad in de unpack-temp
    return $source;

}, 99, 4);
