<?php
namespace Creare\ServiceAreas;

defined('ABSPATH') || exit;

final class Renderer
{
    public static bool $rendering = false;

    public static function render(int $post_id, array $options = []): string
    {
        $post = get_post($post_id);
        if (!$post || Plugin::POST_TYPE !== $post->post_type || self::$rendering) {
            return '';
        }
        // A template preview must never expose a draft chosen by an anonymous visitor.
        if ('publish' !== $post->post_status && !current_user_can('read_post', $post_id)) {
            return '';
        }
        if (post_password_required($post)) {
            return '';
        }
        self::$rendering = true;
        try {
            $html = '<div class="csa-document">';
            if ($options['show_heading'] ?? true) {
                $heading = get_post_meta($post_id, 'csa_heading', true) ?: get_the_title($post_id);
                $html .= '<h1 class="csa-heading">' . esc_html($heading) . '</h1>';
            }
            if ($options['show_content'] ?? true) {
                $body = has_blocks($post->post_content) ? do_blocks($post->post_content) : wpautop($post->post_content);
                $html .= '<div class="csa-body">' . wp_kses_post($body) . '</div>';
            }
            $html .= self::details($post_id, $options);
            return $html . '</div>';
        } finally {
            self::$rendering = false;
        }
    }

    public static function details(int $post_id, array $options = []): string
    {
        $post = get_post($post_id);
        if (!$post || Plugin::POST_TYPE !== $post->post_type || post_password_required($post) ||
            ('publish' !== $post->post_status && !current_user_can('read_post', $post_id))) {
            return '';
        }
        $html = '';
        $faqs = Fields::sanitize_faqs(get_post_meta($post_id, 'csa_faqs', true));
        if (($options['show_faqs'] ?? true) && $faqs) {
            $heading = $options['faq_heading'] ?? '';
            $heading = $heading ?: (get_post_meta($post_id, 'csa_faq_heading', true) ?: __('Frequently asked questions', 'creare-service-areas'));
            $html .= '<section class="csa-faqs"><h2>' . esc_html($heading) . '</h2>';
            foreach ($faqs as $row) {
                $html .= '<details class="csa-faq"><summary>' . esc_html($row['question']) . '</summary><div class="csa-faq-answer">' . wp_kses_post(wpautop($row['answer'])) . '</div></details>';
            }
            $html .= '</section>';
        }
        $url = esc_url(get_post_meta($post_id, 'csa_cta_url', true), ['http', 'https']);
        $label = get_post_meta($post_id, 'csa_cta_label', true);
        $phone = get_post_meta($post_id, 'csa_phone', true);
        if (($options['show_contact'] ?? true) && (($url && $label) || $phone)) {
            $html .= '<section class="csa-contact">';
            $heading = get_post_meta($post_id, 'csa_cta_heading', true);
            $text = get_post_meta($post_id, 'csa_cta_text', true);
            if ($heading) {
                $html .= '<h2>' . esc_html($heading) . '</h2>';
            }
            if ($text) {
                $html .= wpautop(esc_html($text));
            }
            $html .= '<div class="csa-actions">';
            if ($url && $label) {
                $html .= '<a class="csa-booking bde-button__button" href="' . $url . '">' . esc_html($label) . '</a>';
            }
            if ($phone && preg_match('/[0-9]/', $phone)) {
                $html .= '<a class="csa-phone" href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $phone)) . '">' . esc_html($phone) . '</a>';
            }
            $html .= '</div></section>';
        }
        return $html;
    }
}
