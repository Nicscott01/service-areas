<?php
namespace Creare\ServiceAreas;

defined('ABSPATH') || exit;

/** Explicit, draft-only import. No activation seeds, remote calls, or scheduled jobs. */
final class Importer
{
    public static function validate($records)
    {
        if (!is_array($records) || !$records || array_keys($records) !== range(0, count($records) - 1) || count($records) > 100) {
            return new \WP_Error('csa_records', 'Supply a JSON array containing 1 to 100 service areas.');
        }
        $result = [];
        $slugs = [];
        foreach ($records as $index => $record) {
            $unknown = is_array($record) ? array_diff(array_keys($record), ['title', 'slug', 'content', 'excerpt', 'meta']) : ['record'];
            if ($unknown || !is_string($record['title'] ?? null) || !is_string($record['slug'] ?? null) || !is_string($record['content'] ?? null)) {
                return new \WP_Error('csa_shape', 'Record ' . ($index + 1) . ': use title, slug, content, optional excerpt and meta only.');
            }
            $title = sanitize_text_field($record['title']);
            $slug = sanitize_title($record['slug']);
            $content = wp_kses_post($record['content']);
            if (!$title || !$slug || '' === trim(wp_strip_all_tags($content)) || $slug !== $record['slug'] || isset($slugs[$slug])) {
                return new \WP_Error('csa_identity', 'Record ' . ($index + 1) . ': title, unique clean slug, and content are required.');
            }
            if (isset($record['excerpt']) && !is_string($record['excerpt'])) {
                return new \WP_Error('csa_excerpt', 'Record ' . ($index + 1) . ': excerpt must be text.');
            }
            $meta = $record['meta'] ?? [];
            if (!is_array($meta) || array_diff(array_keys($meta), array_merge(array_keys(Fields::definitions()), ['csa_faqs']))) {
                return new \WP_Error('csa_meta', 'Record ' . ($index + 1) . ': unknown service-area fields.');
            }
            foreach ($meta as $key => $value) {
                if ('csa_faqs' === $key) {
                    if (!is_array($value) || count($value) > 50 || array_values($value) !== $value) {
                        return new \WP_Error('csa_faqs', 'FAQs must be an array with at most 50 question/answer rows.');
                    }
                    foreach ($value as $row) {
                        if (!is_array($row) || array_diff(array_keys($row), ['question', 'answer']) || !is_string($row['question'] ?? null) || !is_string($row['answer'] ?? null)) {
                            return new \WP_Error('csa_faqs', 'Each FAQ needs a text question and answer.');
                        }
                    }
                    $meta[$key] = Fields::sanitize_faqs($value);
                    if (count($meta[$key]) !== count($value)) {
                        return new \WP_Error('csa_faqs', 'FAQ questions and answers cannot be empty.');
                    }
                } else {
                    if (!is_string($value)) {
                        return new \WP_Error('csa_field', $key . ' must be a string.');
                    }
                    $meta[$key] = Fields::sanitize($key, $value);
                    if ('' !== $value && '' === $meta[$key]) {
                        return new \WP_Error('csa_field', 'Invalid value for ' . $key . '.');
                    }
                }
            }
            $slugs[$slug] = true;
            $result[] = ['title' => $title, 'slug' => $slug, 'content' => $content,
                'excerpt' => sanitize_textarea_field($record['excerpt'] ?? ''), 'meta' => $meta];
        }
        return $result;
    }

    public static function run($records, bool $dry_run = false)
    {
        if (!current_user_can('edit_posts')) {
            return new \WP_Error('csa_permission', 'An authenticated user who can edit posts is required.');
        }
        $clean = self::validate($records);
        if (is_wp_error($clean)) {
            return $clean;
        }
        // Preflight the whole batch. Never silently replace a draft or a published area.
        foreach ($clean as $record) {
            if (get_page_by_path($record['slug'], OBJECT, Plugin::POST_TYPE)) {
                return new \WP_Error('csa_exists', 'A service area already uses ' . $record['slug'] . '. No content was changed.');
            }
        }
        if ($dry_run) {
            return array_map(static function ($record) { return ['slug' => $record['slug'], 'status' => 'draft', 'action' => 'would-create']; }, $clean);
        }
        $created = [];
        foreach ($clean as $record) {
            $post_id = wp_insert_post(wp_slash([
                'post_type' => Plugin::POST_TYPE, 'post_status' => 'draft',
                'post_title' => $record['title'], 'post_name' => $record['slug'],
                'post_content' => $record['content'], 'post_excerpt' => $record['excerpt'],
                'meta_input' => $record['meta'],
            ]), true);
            if (is_wp_error($post_id)) {
                self::rollback($created);
                return $post_id;
            }
            $created[] = $post_id;
            $post = get_post($post_id);
            if (!$post || 'draft' !== $post->post_status || $record['slug'] !== $post->post_name) {
                self::rollback($created);
                return new \WP_Error('csa_verify', 'The draft did not match the requested status and slug. Newly created records were removed.');
            }
            foreach ($record['meta'] as $key => $expected) {
                if (get_post_meta($post_id, $key, true) !== $expected) {
                    self::rollback($created);
                    return new \WP_Error('csa_verify', 'Draft metadata verification failed for ' . $key . '. Newly created records were removed.');
                }
            }
        }
        return array_map(static function ($id) {
            return ['id' => $id, 'slug' => get_post_field('post_name', $id), 'status' => get_post_status($id),
                'edit_url' => admin_url('post.php?post=' . $id . '&action=edit'), 'preview_url' => get_preview_post_link($id)];
        }, $created);
    }

    private static function rollback(array $ids): void
    {
        foreach ($ids as $id) {
            wp_delete_post($id, true);
        }
    }
}
