<?php
/** Integration checks against an authorized disposable local WordPress runtime. */
$roots = array_filter([getenv('SERVICE_AREAS_WP_ROOT'), dirname(__DIR__, 4) . '/wp', dirname(__DIR__, 4)]);
foreach ($roots as $root) {
    if (is_file($root . '/wp-load.php')) {
        require_once $root . '/wp-load.php';
        break;
    }
}
if (!function_exists('get_post') || !class_exists('Creare\\ServiceAreas\\Importer')) {
    fwrite(STDERR, "Load the active plugin in a local WordPress runtime; set SERVICE_AREAS_WP_ROOT.\n");
    exit(1);
}
require __DIR__ . '/integration.php';
