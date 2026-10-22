<?php
namespace Creare\ServiceAreas;

defined('ABSPATH') || exit;

final class Fields
{
    public static function definitions(): array
    {
        return [
            'csa_area_name' => ['label' => 'Area name', 'type' => 'text'],
            'csa_area_kind' => ['label' => 'Area type', 'type' => 'select', 'options' => ['town' => 'Town', 'city' => 'City', 'county' => 'County', 'region' => 'Region', 'village' => 'Village', 'other' => 'Other']],
            'csa_region_code' => ['label' => 'State / province / region', 'type' => 'text'],
            'csa_country_code' => ['label' => 'Country code', 'type' => 'text'],
            'csa_postal_codes' => ['label' => 'Postal codes (optional, comma separated)', 'type' => 'text'],
            'csa_website' => ['label' => 'Community website (optional)', 'type' => 'url'],
            'csa_heading' => ['label' => 'Page heading', 'type' => 'text'],
            'csa_faq_heading' => ['label' => 'FAQ heading', 'type' => 'text'],
            'csa_cta_heading' => ['label' => 'Contact heading', 'type' => 'text'],
            'csa_cta_text' => ['label' => 'Contact text', 'type' => 'textarea'],
            'csa_cta_label' => ['label' => 'Booking button label', 'type' => 'text'],
            'csa_cta_url' => ['label' => 'Booking URL', 'type' => 'url'],
            'csa_phone' => ['label' => 'Phone number', 'type' => 'text'],
        ];
    }

    public static function register(): void
    {
        foreach (self::definitions() as $key => $definition) {
            register_post_meta(Plugin::POST_TYPE, $key, [
                'type' => 'string', 'single' => true, 'show_in_rest' => true,
                'sanitize_callback' => static function ($value) use ($key) { return self::sanitize($key, $value); },
                'auth_callback' => [self::class, 'can_edit'],
                'revisions_enabled' => true,
            ]);
        }
        register_post_meta(Plugin::POST_TYPE, 'csa_faqs', [
            'type' => 'array', 'single' => true, 'default' => [],
            'show_in_rest' => ['schema' => ['type' => 'array', 'items' => [
                'type' => 'object', 'required' => ['question', 'answer'], 'additionalProperties' => false,
                'properties' => ['question' => ['type' => 'string'], 'answer' => ['type' => 'string']],
            ]]],
            'sanitize_callback' => [self::class, 'sanitize_faqs'],
            'auth_callback' => [self::class, 'can_edit'],
            'revisions_enabled' => true,
        ]);
    }

    public static function can_edit($allowed, $key, $post_id): bool
    {
        return current_user_can('edit_post', (int) $post_id);
    }

    public static function sanitize(string $key, $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $definitions = self::definitions();
        if (!isset($definitions[$key])) {
            return '';
        }
        $type = $definitions[$key]['type'];
        if ('url' === $type) {
            return esc_url_raw((string) $value, ['http', 'https']);
        }
        if ('select' === $type) {
            return isset($definitions[$key]['options'][$value]) ? (string) $value : '';
        }
        return 'textarea' === $type ? sanitize_textarea_field((string) $value) : sanitize_text_field((string) $value);
    }

    public static function sanitize_faqs($rows): array
    {
        if (!is_array($rows)) {
            return [];
        }
        $clean = [];
        foreach (array_slice($rows, 0, 50) as $row) {
            if (!is_array($row) || !is_scalar($row['question'] ?? null) || !is_scalar($row['answer'] ?? null)) {
                continue;
            }
            $question = sanitize_text_field((string) $row['question']);
            $answer = wp_kses_post((string) $row['answer']);
            if ('' !== trim($question) && '' !== trim(wp_strip_all_tags($answer))) {
                $clean[] = ['question' => $question, 'answer' => $answer];
            }
        }
        return $clean;
    }

    public static function add_box(): void
    {
        add_meta_box('creare-service-area-details', __('Service area details', 'creare-service-areas'), [self::class, 'box'], Plugin::POST_TYPE, 'normal', 'high');
    }

    public static function box(\WP_Post $post): void
    {
        wp_nonce_field('csa_save_' . $post->ID, 'csa_nonce');
        echo '<p>' . esc_html__('Write service information in the main content editor. These fields supply the location, FAQs, and contact section.', 'creare-service-areas') . '</p>';
        foreach (self::definitions() as $key => $definition) {
            $value = get_post_meta($post->ID, $key, true);
            echo '<p><label for="' . esc_attr($key) . '"><strong>' . esc_html($definition['label']) . '</strong></label><br>';
            if ('select' === $definition['type']) {
                echo '<select id="' . esc_attr($key) . '" name="csa_fields[' . esc_attr($key) . ']">';
                echo '<option value="">' . esc_html__('Choose an area type', 'creare-service-areas') . '</option>';
                foreach ($definition['options'] as $option => $label) {
                    echo '<option value="' . esc_attr($option) . '" ' . selected($value, $option, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
            } elseif ('textarea' === $definition['type']) {
                echo '<textarea class="widefat" rows="3" id="' . esc_attr($key) . '" name="csa_fields[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input class="widefat" type="' . esc_attr($definition['type']) . '" id="' . esc_attr($key) . '" name="csa_fields[' . esc_attr($key) . ']" value="' . esc_attr($value) . '">';
            }
            echo '</p>';
        }
        echo '<h3>' . esc_html__('Frequently asked questions', 'creare-service-areas') . '</h3><div id="csa-faq-rows">';
        foreach (self::sanitize_faqs(get_post_meta($post->ID, 'csa_faqs', true)) as $i => $row) {
            self::faq_row((string) $i, $row);
        }
        echo '</div><input type="hidden" name="csa_faqs_present" value="1">';
        echo '<p><button type="button" class="button" id="csa-add-faq">' . esc_html__('Add question', 'creare-service-areas') . '</button></p>';
        echo '<template id="csa-faq-template">';
        self::faq_row('__INDEX__', ['question' => '', 'answer' => '']);
        echo '</template>';
    }

    private static function faq_row(string $index, array $row): void
    {
        echo '<fieldset class="csa-faq-row" style="border:1px solid #ccc;padding:12px;margin:12px 0"><legend>' . esc_html__('Question and answer', 'creare-service-areas') . '</legend>';
        echo '<p><label>' . esc_html__('Question', 'creare-service-areas') . '<input class="widefat" name="csa_faqs[' . esc_attr($index) . '][question]" value="' . esc_attr($row['question']) . '"></label></p>';
        echo '<p><label>' . esc_html__('Answer (basic HTML allowed)', 'creare-service-areas') . '<textarea class="widefat" rows="4" name="csa_faqs[' . esc_attr($index) . '][answer]">' . esc_textarea($row['answer']) . '</textarea></label></p>';
        echo '<button type="button" class="button csa-remove-faq">' . esc_html__('Remove question', 'creare-service-areas') . '</button></fieldset>';
    }

    public static function save(int $post_id, \WP_Post $post): void
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
            return;
        }
        $nonce = $_POST['csa_nonce'] ?? '';
        if (!is_string($nonce) || !wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'csa_save_' . $post_id)) {
            return;
        }
        $values = isset($_POST['csa_fields']) && is_array($_POST['csa_fields']) ? wp_unslash($_POST['csa_fields']) : [];
        foreach (self::definitions() as $key => $definition) {
            if (array_key_exists($key, $values)) {
                update_post_meta($post_id, $key, self::sanitize($key, $values[$key]));
            }
        }
        if (isset($_POST['csa_faqs_present'])) {
            update_post_meta($post_id, 'csa_faqs', wp_slash(self::sanitize_faqs(wp_unslash($_POST['csa_faqs'] ?? []))));
        }
    }
}
