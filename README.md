# Creare Service Areas

Reusable WordPress location pages for service businesses. Derived from WickedWarm's
Delivery Town pattern: a location post type, location metadata, area grouping, and
a shared Breakdance presentation. This package uses `service_area`, so a business
can describe towns, cities, counties, villages, or other regions without fuel-delivery terminology.

## Install with Bedrock / Composer

Add this VCS repository to the Bedrock project's `composer.json`:

```json
{"type": "vcs", "url": "https://github.com/Nicscott01/service-areas"}
```

Require `"nicscott01/service-areas": "^1.0"`, run a scoped Composer update, deploy,
and activate `service-areas`. The installed folder is always `service-areas`.
Use a `service-areas-dev` source checkout during development.

The plugin requires WordPress 6.4+ and PHP 8.0+. It does not require ACF, Breakdance,
an AI provider, Action Scheduler, or the AI generator. There are no outgoing API
requests or background jobs. Activation creates no posts, templates, or settings.
Deactivation and uninstall retain the site's content.

## Editing

Open **Service Areas → Add Service Area**. Use the title for the location name and
the main editor for business-specific service copy. The **Service area details**
box contains area type, region, optional postal codes / community URL, page heading,
an editable FAQ repeater, and contact fields. FAQs can be added and removed.
Area Groups organize related locations without creating thin public taxonomy archives.

The default single URL is `/service-area/{slug}/`. There is no automatic archive.
Choose explicit stable slugs such as `york-me` or `bergen-county-nj`; ordinary saves
never silently rename them. Add links to approved locations when ready to publish.

All fields are registered post metadata with REST schemas, edit capabilities,
sanitization, and revision support. Metadata uses the `csa_` prefix. The main content,
title, excerpt, featured image, and publishing status use native WordPress fields.

## Breakdance

Use **Service Area Content** from **Creare Service Areas** in a template scoped to
the `service_area` post type. It reads the current area; leave its fixed area ID
empty for a shared template. A fixed ID is useful for a builder preview, but clear
it before using the template for multiple areas. The renderer checks permissions
and password protection before showing any non-public area.

The element exposes section visibility, a FAQ heading override, typography, maximum
width, padding, and section spacing. Editorial copy remains in the Service Area
editor. Use the site's existing header/footer and global design settings.
Without a Breakdance document, the plugin appends FAQs and contact information to
normal single-post content. It does not replace a theme or install a global template.

To customize the post-type labels, slug, archive, or supported features before registration:

```php
add_filter('creare_service_areas_post_type_args', function ($args) {
    $args['rewrite']['slug'] = 'locations';
    return $args;
});
```

Flush rewrite rules once after changing a URL base. Redirect old published URLs when changing them.

## Draft importer

Run with an authenticated WordPress user who can edit posts:

```sh
wp --user=editor service-area import areas.json --dry-run
wp --user=editor service-area import areas.json
```

The input is a JSON array of `title`, `slug`, `content`, optional `excerpt`, and
optional `meta`. Allowed metadata is defined in `Fields::definitions()` plus
`csa_faqs`, an array of `{ "question": "...", "answer": "..." }` objects.
The importer validates the entire batch first, rejects unknown fields or duplicate
slugs, and always creates drafts. It refuses to replace any existing location,
including drafts. It verifies saved metadata and removes only records it just
created if a write fails. It has no option to publish or silently update content.

```json
[
  {
    "title": "Bergen County",
    "slug": "bergen-county-nj",
    "content": "<p>Approved service information goes here.</p>",
    "meta": {
      "csa_area_name": "Bergen County",
      "csa_area_kind": "county",
      "csa_region_code": "NJ",
      "csa_country_code": "US",
      "csa_heading": "Service in Bergen County, New Jersey",
      "csa_faqs": [{"question": "How do I request service?", "answer": "<p>Contact our team to confirm coverage and scheduling.</p>"}]
    }
  }
]
```

## Optional AI Post Content Generator bridge

Keep `nicscott01/post-content-generator` a separate installation. Its public
post-type discovery will find Service Areas. This plugin's `pcg_custom_fields`
filter exposes editable heading/contact text as native scalar fields, without
duplicating geographic facts or treating a FAQ array as a string. Nothing is
enabled, queued, generated, or billed by activating this package.

Configure provider credentials and model selection in the existing generator.
Set generation status to **Draft**, keep location titles and facts authoritative,
and have a human review output before publishing. Suggested prompt:

> Write HTML service information for {post_title} in the business's existing voice.
> Use only the supplied, approved business and location facts. Explain the services
> and how to request them. Avoid invented guarantees, regulations, addresses,
> service history, prices, or availability. Preserve known service limitations.
> Prepare a draft for human review. Put the FAQs in the structured Service Area
> FAQ fields after review, rather than generating a scalar value for the repeater.

The generator remains responsible for its own queue behavior and publication settings.
This package does not change those settings or modify its source.

## Verification

Run `composer lint` and `composer test` from a development checkout installed in a
WordPress project. Set `SERVICE_AREAS_WP_ROOT` to the WordPress core directory if
automatic local/Bedrock discovery cannot find it. Tests refuse non-local hosts,
create their own disposable fixtures, restore the current user, and remove fixtures.
They cover draft isolation, validation, existing-content protection, metadata,
rendering, REST permissions, nonce handling, and optional integrations.

The public package contains no client copy, credentials, or site configuration.
