<?php
/**
 * Plugin Name: Nexo Player
 * Plugin URI:  https://pornoreels.blog
 * Description: Player de vídeo para posts: MP4 próprio ou embed de tubes, com capa, marca d'água, vídeos relacionados, "continuar assistindo" e códigos extras (ads/analytics).
 * Version:     1.0.1
 * Author:      Nexo
 * Text Domain: nexo-player
 * License:     GPL-2.0-or-later
 *
 * @package NexoPlayer
 */

defined( 'ABSPATH' ) || exit;

define( 'NEXOP_VERSION', '1.0.1' );
define( 'NEXOP_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEXOP_URL', plugin_dir_url( __FILE__ ) );
define( 'NEXOP_FILE', __FILE__ );

require_once NEXOP_DIR . 'includes/Options.php';
require_once NEXOP_DIR . 'includes/Metabox.php';
require_once NEXOP_DIR . 'includes/Settings.php';
require_once NEXOP_DIR . 'includes/Frontend.php';

add_action(
	'plugins_loaded',
	function () {
		\NexoPlayer\Metabox::init();
		\NexoPlayer\Settings::init();
		\NexoPlayer\Frontend::init();
	}
);
