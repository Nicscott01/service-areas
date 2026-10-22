<?php
use Creare\ServiceAreas\Fields;
use Creare\ServiceAreas\Importer;
use Creare\ServiceAreas\Plugin;
use Creare\ServiceAreas\Renderer;

$host = wp_parse_url(home_url(), PHP_URL_HOST);
if (!in_array($host, ['localhost', '127.0.0.1', 'localdev.test'], true) && !str_ends_with($host, '.test')) {
    throw new RuntimeException('Refusing integration fixtures outside a local/test host.');
}
$original_user = get_current_user_id();
$original_post = $_POST;
$ids = [];
$user_ids = [];
$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) { throw new RuntimeException($message); }
};
$prefix = 'csa-test-' . strtolower(wp_generate_password(10, false));
try {
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    if (!$admins) { throw new RuntimeException('A local administrator is needed for fixtures.'); }
    wp_set_current_user((int) $admins[0]);
    $assert(post_type_exists(Plugin::POST_TYPE), 'Post type registration');
    $type = get_post_type_object(Plugin::POST_TYPE);
    $assert($type->show_in_rest && !$type->has_archive, 'REST support without automatic archive');
    $assert(post_type_supports(Plugin::POST_TYPE, 'revisions'), 'Revisions support');
    $assert(taxonomy_exists('service_area_region'), 'Area grouping');
    $assert(!get_taxonomy('service_area_region')->public, 'No public taxonomy archive');
    $meta_schema = get_registered_meta_keys('post', Plugin::POST_TYPE);
    $assert(isset($meta_schema['csa_faqs']['show_in_rest']['schema']['items']), 'Structured FAQ REST schema');
    $assert($meta_schema['csa_faqs']['revisions_enabled'], 'FAQ revisions enabled');
    $record = ['title' => 'Test County', 'slug' => $prefix, 'content' => '<p>A plumber\'s approved <strong>service</strong>.</p>',
        'excerpt' => 'Local service information.', 'meta' => [
            'csa_area_name' => 'Test County', 'csa_area_kind' => 'county', 'csa_region_code' => 'NJ',
            'csa_heading' => 'Service in Test County', 'csa_cta_heading' => 'Talk to our team',
            'csa_cta_text' => 'We\'ll help you plan.', 'csa_cta_label' => 'Request service',
            'csa_cta_url' => 'https://example.com/book?town=test&ref=area', 'csa_phone' => '207-216-3333',
            'csa_faqs' => [['question' => 'Can I book?', 'answer' => '<p>Yes, we\'ll help. <a href="https://example.com/">Contact us</a>.</p>']],
        ]];
    $assert(is_wp_error(Importer::validate([])), 'Empty batch rejected');
    $assert(is_wp_error(Importer::validate([$record, $record])), 'Duplicate batch slugs rejected');
    $bad = $record; $bad['post_status'] = 'publish';
    $assert(is_wp_error(Importer::validate([$bad])), 'Publication input rejected');
    $bad = $record; $bad['meta']['unknown'] = 'value';
    $assert(is_wp_error(Importer::validate([$bad])), 'Unknown metadata rejected');
    $bad = $record; $bad['meta']['csa_cta_url'] = 'javascript:alert(1)';
    $assert(is_wp_error(Importer::validate([$bad])), 'Unsafe URL rejected');
    $bad = $record; $bad['meta']['csa_area_kind'] = 'bogus';
    $assert(is_wp_error(Importer::validate([$bad])), 'Invalid geography type rejected');
    $bad = $record; $bad['meta']['csa_faqs'][0]['answer'] = '';
    $assert(is_wp_error(Importer::validate([$bad])), 'Empty FAQ rejected');
    $bad = $record; $bad['meta']['csa_faqs'][0]['extra'] = 'value';
    $assert(is_wp_error(Importer::validate([$bad])), 'Unknown FAQ keys rejected');
    $bad = $record; $bad['slug'] = 'Invalid slug!';
    $assert(is_wp_error(Importer::validate([$bad])), 'Noncanonical slug rejected');
    $dry = Importer::run([$record], true);
    $assert(!is_wp_error($dry) && 'would-create' === $dry[0]['action'], 'Dry run validates');
    $assert(!get_page_by_path($prefix, OBJECT, Plugin::POST_TYPE), 'Dry run writes nothing');
    $created = Importer::run([$record]);
    $assert(!is_wp_error($created), is_wp_error($created) ? $created->get_error_message() : 'Import succeeds');
    $id = $created[0]['id']; $ids[] = $id;
    $assert('draft' === get_post_status($id), 'Import is draft-only');
    $assert(get_post_field('post_content', $id) === $record['content'], 'HTML and apostrophes survive storage');
    foreach ($record['meta'] as $key => $value) {
        $assert(get_post_meta($id, $key, true) === $value, 'Metadata roundtrip: ' . $key);
    }
    $assert(is_wp_error(Importer::run([$record])), 'Existing draft protected');
    $render = Renderer::render($id);
    $assert(str_contains($render, 'Service in Test County'), 'Heading rendered');
    $assert(str_contains($render, '<details') && str_contains($render, 'Can I book?'), 'Semantic FAQs rendered');
    $assert(str_contains($render, 'tel:2072163333'), 'Phone link rendered');
    $assert(!str_contains(Renderer::render($id, ['show_faqs' => false]), '<details'), 'FAQ visibility control');
    $assert(!str_contains(Renderer::render($id, ['show_heading' => false]), '<h1'), 'Heading visibility control');
    wp_set_current_user(0);
    $assert('' === Renderer::render($id) && '' === Renderer::details($id), 'Anonymous users cannot render a draft');
    $assert(is_wp_error(Importer::run([$record], true)), 'Anonymous import refused');
    $request = new WP_REST_Request('GET', '/wp/v2/service-areas/' . $id);
    $assert(rest_do_request($request)->get_status() >= 400, 'REST denies anonymous draft reads');
    wp_set_current_user((int) $admins[0]);
    $_POST = ['csa_fields' => ['csa_heading' => 'Untrusted overwrite']];
    Fields::save($id, get_post($id));
    $assert(get_post_meta($id, 'csa_heading', true) === 'Service in Test County', 'Missing nonce prevents writes');
    $_POST['csa_nonce'] = wp_create_nonce('csa_save_' . $id);
    $_POST['csa_fields']['csa_heading'] = 'Updated <script>unsafe</script> heading';
    Fields::save($id, get_post($id));
    $assert(!str_contains(get_post_meta($id, 'csa_heading', true), '<script>'), 'Admin text sanitized');
    $assert(!str_contains(Fields::sanitize_faqs([['question' => 'Q', 'answer' => '<script>alert(1)</script><p>A</p>']])[0]['answer'], '<script>'), 'FAQ HTML sanitized');
    $_POST = [];
    wp_update_post(['ID' => $id, 'post_status' => 'publish']);
    $assert(is_wp_error(Importer::run([$record])), 'Published content protected');
    $assert('publish' === get_post_status($id), 'Protected publication state unchanged');
    wp_set_current_user(0);
    $assert('' !== Renderer::render($id), 'Published content readable');
    wp_set_current_user((int) $admins[0]);
    wp_update_post(['ID' => $id, 'post_password' => 'fixture-secret']);
    $assert('' === Renderer::render($id), 'Password protection respected');
    $bridge = Plugin::generator_fields([], 'service_area');
    $assert(count($bridge) === 5, 'Optional generator sees five editorial fields');
    $assert(count(Plugin::generator_fields($bridge, 'service_area')) === 5, 'Bridge avoids duplicate fields');
    $assert([] === Plugin::generator_fields([], 'post'), 'Bridge does not affect other post types');
    $assert(!in_array('csa_cta_url', array_column($bridge, 'key'), true), 'Generator cannot invent contact URL through bridge');
    $assert(class_exists('CreareServiceAreaElements\\ServiceAreaContent'), 'Breakdance element registered');
    $assert(!empty(CreareServiceAreaElements\ServiceAreaContent::contentControls()), 'Element has native display controls');
    $assert(!empty(CreareServiceAreaElements\ServiceAreaContent::designControls()), 'Element has native design controls');
    echo 'Passed ' . $checks . " integration checks.\n";
} finally {
    $_POST = $original_post;
    wp_set_current_user((int) ($admins[0] ?? $original_user));
    foreach ($ids as $id) { wp_delete_post($id, true); }
    foreach ($user_ids as $id) { wp_delete_user($id); }
    wp_set_current_user($original_user);
}
