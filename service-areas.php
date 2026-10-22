<?php
/**
 * Plugin Name: Creare Service Areas
 * Description: Editable location pages for businesses serving towns, cities, counties, and regions. Optional Breakdance and AI Post Content Generator integrations.
 * Version: 1.0.0
 * Author: Creare Web Solutions
 * Author URI: https://www.crearewebsolutions.com/
 * License: GPL-2.0-or-later
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: creare-service-areas
 */

defined('ABSPATH') || exit;

define('CREARE_SERVICE_AREAS_VERSION', '1.0.0');
define('CREARE_SERVICE_AREAS_FILE', __FILE__);

require_once __DIR__ . '/includes/class-fields.php';
require_once __DIR__ . '/includes/class-renderer.php';
require_once __DIR__ . '/includes/class-importer.php';
require_once __DIR__ . '/includes/class-plugin.php';

\Creare\ServiceAreas\Plugin::boot();
register_activation_hook(__FILE__, [\Creare\ServiceAreas\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [\Creare\ServiceAreas\Plugin::class, 'deactivate']);

if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/includes/class-cli.php';
    WP_CLI::add_command('service-area', \Creare\ServiceAreas\CLI::class);
}
