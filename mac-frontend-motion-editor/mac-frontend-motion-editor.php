<?php
/**
 * Plugin Name:       MAC Frontend Motion Editor
 * Description:       Edit Elementor entrance animations directly on the frontend — hover an element, pick animation/speed/delay/device, Apply writes to _elementor_data through the Elementor document save lifecycle.
 * Version:           0.2.1
 * Plugin URI:        https://github.com/LeoVo2003/mac-frontend-motion-editor
 * Update URI:        https://github.com/LeoVo2003/mac-frontend-motion-editor
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            MAC
 * License:           GPL-2.0-or-later
 * Text Domain:       mac-motion-editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MFME_VERSION', '0.2.1' );
define( 'MFME_FILE', __FILE__ );
define( 'MFME_DIR', plugin_dir_path( __FILE__ ) );
define( 'MFME_URL', plugin_dir_url( __FILE__ ) );

require_once MFME_DIR . 'includes/class-mfme-animations.php';
require_once MFME_DIR . 'includes/class-mfme-settings.php';
require_once MFME_DIR . 'includes/class-mfme-element-map.php';
require_once MFME_DIR . 'includes/class-mfme-ajax.php';
require_once MFME_DIR . 'includes/class-mfme-plugin.php';
require_once MFME_DIR . 'includes/class-mfme-updater.php';

add_action( 'plugins_loaded', [ 'MFME_Plugin', 'init' ] );
add_action( 'plugins_loaded', [ 'MFME_Updater', 'register' ] );
