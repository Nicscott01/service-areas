<?php
namespace Creare\ServiceAreas;

defined('ABSPATH') || exit;

final class CLI
{
    /**
     * Validate a JSON file and create new service-area drafts. Never updates existing content.
     *
     * ## OPTIONS
     *
     * <file>
     * : Local path to a JSON array of service areas.
     *
     * [--dry-run]
     * : Validate the complete batch without writing.
     *
     * ## EXAMPLES
     *
     *     wp --user=editor service-area import areas.json --dry-run
     *     wp --user=editor service-area import areas.json
     */
    public function import(array $args, array $assoc_args): void
    {
        $file = $args[0] ?? '';
        if (!is_readable($file) || !is_file($file) || filesize($file) > 2097152) {
            \WP_CLI::error('Supply a readable local JSON file no larger than 2 MiB.');
        }
        $data = json_decode(file_get_contents($file), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            \WP_CLI::error('Invalid JSON: ' . json_last_error_msg());
        }
        $result = Importer::run($data, isset($assoc_args['dry-run']));
        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
        }
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
