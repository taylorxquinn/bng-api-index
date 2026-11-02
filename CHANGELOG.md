# Changelog — BNB API Index

## 2026.11.2.001 — 2026-11-02

- Initial release.
- Serves the site API index three ways from one generator: `/api.toon` (TOON), `/api.json` (JSON twin), and `GET /wp-json/bng-api-index/v1` (live REST route, public read-only).
- Generator walks the live REST route table and caches the result in a one-hour transient; activation flushes rewrite rules and clears the cache.
- Built-in classification map for the Built Not Begged fleet: BNB FluentCart SEO (agent-key), Server Stats (dedicated-credential, restricted — existence and auth only, no route detail), CPH Assistant (dedicated-credential), CPH Licenses (public / dedicated-credential), Taylor Analytics (public /public/reports + dedicated-credential), MusePort (admin), BNB Syndicate, BNG Catalog (public), Bookshelf, Net30 (public / dedicated-credential), Staff Sync and the BNG Updates client (app-password); WordPress core namespaces (wp/v2, oEmbed, site health, block editor); vendor defaults for WooCommerce (dedicated-credential), Fluent plugins and Site Kit (admin).
- `bng_api_index_describe` filter: plugins self-describe their namespace (auth, purpose, owner, restricted) and merge into the index.
- `bng_api_index_exclude` filter plus automatic exclusion of namespaces whose names mark them as internal.
- Output contains names, owners, auth types and route counts only — no credential values, key names, key file paths, or per-route parameters.
