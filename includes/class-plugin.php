<?php
namespace Creare\ServiceAreas;

defined('ABSPATH') || exit;

final class Plugin
{
    public const POST_TYPE = 'service_area';

    public static function boot(): void
    {
        add_action('init', [self::class, 'register'], 9);
        add_action('add_meta_boxes_' . self::POST_TYPE, [Fields::class, 'add_box']);
        add_action('save_post_' . self::POST_TYPE, [Fields::class, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        add_action('wp_enqueue_scripts', [self::class, 'frontend_assets']);
        add_action('breakdance_loaded', [self::class, 'breakdance'], 9);
        add_filter('the_content', [self::class, 'append_details'], 20);
        add_filter('pcg_custom_fields', [self::class, 'generator_fields'], 10, 2);
    }

    public static function register(): void
    {
        register_post_type(self::POST_TYPE, apply_filters('creare_service_areas_post_type_args', [
            'labels' => [
                'name' => __('Service Areas', 'creare-service-areas'),
                'singular_name' => __('Service Area', 'creare-service-areas'),
                'add_new_item' => __('Add Service Area', 'creare-service-areas'),
                'edit_item' => __('Edit Service Area', 'creare-service-areas'),
                'all_items' => __('All Service Areas', 'creare-service-areas'),
            ],
            'public' => true,
            'show_in_rest' => true,
            'rest_base' => 'service-areas',
            'menu_icon' => 'dashicons-location-alt',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes'],
            // Match WickedWarm's single-location pattern; an index is an editorial choice.
            'has_archive' => false,
            'rewrite' => ['slug' => 'service-area', 'with_front' => false],
            'delete_with_user' => false,
            'map_meta_cap' => true,
        ]));
        register_taxonomy('service_area_region', self::POST_TYPE, [
            'labels' => ['name' => __('Area Groups', 'creare-service-areas'), 'singular_name' => __('Area Group', 'creare-service-areas')],
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'public' => false,
            'rewrite' => false,
        ]);
        Fields::register();
    }

    public static function activate(): void
    {
        self::register();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void
    {
        unregister_post_type(self::POST_TYPE);
        unregister_taxonomy('service_area_region');
        flush_rewrite_rules(false);
        // Content and metadata intentionally survive deactivation and uninstall.
    }

    public static function breakdance(): void
    {
        if (function_exists('Breakdance\\ElementStudio\\registerSaveLocation')) {
            \Breakdance\ElementStudio\registerSaveLocation(
                \Breakdance\Util\getDirectoryPathRelativeToPluginFolder(dirname(CREARE_SERVICE_AREAS_FILE)) . '/elements',
                'CreareServiceAreaElements', 'element', 'Creare Service Areas', false
            );
        }
    }

    public static function admin_assets(): void
    {
        $screen = get_current_screen();
        if ($screen && self::POST_TYPE === $screen->post_type) {
            wp_enqueue_script('creare-service-areas-admin', plugins_url('assets/admin.js', CREARE_SERVICE_AREAS_FILE), [], CREARE_SERVICE_AREAS_VERSION, true);
        }
    }

    public static function frontend_assets(): void
    {
        if (is_singular(self::POST_TYPE)) {
            wp_enqueue_style('creare-service-areas', plugins_url('assets/frontend.css', CREARE_SERVICE_AREAS_FILE), [], CREARE_SERVICE_AREAS_VERSION);
        }
    }

    public static function append_details(string $content): string
    {
        if (is_admin() || !is_singular(self::POST_TYPE) || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        // A Breakdance element renders its own full document; avoid duplicate details.
        if (Renderer::$rendering || !empty(get_post_meta(get_the_ID(), '_breakdance_data', true))) {
            return $content;
        }
        return $content . Renderer::details(get_the_ID());
    }

    public static function generator_fields(array $fields, string $post_type): array
    {
        if (self::POST_TYPE !== $post_type) {
            return $fields;
        }
        $existing = array_column($fields, 'key');
        // Only editorial text. Geography, URLs, phone numbers, and structured FAQ rows
        // must come from approved source data rather than scalar AI field generation.
        foreach (['csa_heading', 'csa_faq_heading', 'csa_cta_heading', 'csa_cta_text', 'csa_cta_label'] as $key) {
            if (!in_array($key, $existing, true)) {
                $definition = Fields::definitions()[$key];
                $fields[] = ['key' => $key, 'label' => $definition['label'], 'type' => 'text',
                    'description' => 'Use the approved business voice and supplied facts. Return plain text. Prepare for human review.', 'framework' => 'native'];
            }
        }
        return $fields;
    }
}
