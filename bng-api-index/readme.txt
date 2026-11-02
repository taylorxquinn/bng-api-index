=== BNB API Index ===
Contributors: builtnotbegged
Tags: api, rest-api, toon, agents, discovery
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 2026.11.2.002
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publishes an agent-readable index of this site's API surface at /api.toon, /api.json and /wp-json/bng-api-index/v1.

== Description ==

Every site has an API. Almost no site says what it is. BNB API Index fixes that with one small generated file:

* `GET /api.toon` — the index in TOON (Token-Oriented Object Notation), the same family as library.toon and join.toon.
* `GET /api.json` — the same index as JSON.
* `GET /wp-json/bng-api-index/v1` — the same index as a live REST route.

The index is generated from the live REST route table and cached in a one-hour transient, so it stays true as plugins are installed or removed. For each namespace it lists: base path, owner (ours / vendor / core), auth type by name only (public, app-password, agent-key, dedicated-credential, admin, token), route count, and a one-line purpose.

Security rules are built in:

* No credential values, no key names, no key file paths — ever.
* Credential-gated (restricted) namespaces are listed as existing, with their auth type, but their routes are not enumerated.
* Namespaces whose names mark them as internal are excluded automatically; the `bng_api_index_exclude` filter can exclude more.
* The index is public and read-only. Discovery is public; access is not — every gated surface keeps its own credential and returns 401 without it.

Plugins can describe themselves with the `bng_api_index_describe` filter (namespace => auth / purpose / owner / restricted), so new plugins join the index without a plugin update.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` and activate it.
2. Visit `https://your-site.example/api.toon`.

== Changelog ==

= 2026.11.2.002 =
* Classification accuracy from the Built Not Begged pilot: oEmbed (bare namespace form), the core batch endpoint and the core Abilities API are now classified as WordPress core instead of vendor; the MCP adapter endpoint is classified as an administrator-session agent surface.

= 2026.11.2.001 =
* Initial release: /api.toon (TOON) + /api.json twin + GET /wp-json/bng-api-index/v1, generated from the live route table with a one-hour transient cache.
* Built-in auth/purpose classification for the Built Not Begged fleet (BNB FluentCart SEO, Server Stats as restricted, CPH Assistant, Taylor Analytics, MusePort, Bookshelf, Syndicate, Net30) plus WordPress core and common vendor namespaces; unknown namespaces fall back to owner-based defaults.
* `bng_api_index_describe` filter so any plugin can self-describe its namespace; `bng_api_index_exclude` filter plus an automatic exclusion for internal-named namespaces.
