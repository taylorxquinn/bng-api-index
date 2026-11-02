# Changelog — BNB API Index

## 2026.11.2.004 — 2026-11-02

- Self-healing rewrite rules. Rollout-day finding on builtnotbegged.com: after the plugin was verified live, another actor's permalink flush regenerated the stored rewrite rules without this plugin's, and /api.toon + /api.json + /openapi.json 404'd while the REST routes (which do not depend on those stored rules) kept working. The plugin now checks the autoloaded `rewrite_rules` option on init and, if its rules are absent, re-registers and soft-flushes them once. No per-request query or cost when the rules are present.

## 2026.11.2.003 — 2026-11-02

- OpenAPI 3.1 layer (owner addition, "publish a swagger inside there"): new `/openapi.json` file route and `GET /wp-json/bng-api-index/v1/openapi` REST twin, generated from the live route table into a separate one-hour transient.
- Per-route skeleton: WP route regexes converted to path templates; operations carry tags (namespace), parameters extracted from route registration (name, path/query placement, required flag, schema type — never defaults, enums, or any value), a 200 response, a 401 response on gated surfaces, and the security requirement implied by the namespace's auth classification.
- Security schemes defined once in components: `appPassword` (HTTP Basic, WordPress application passwords), `agentKey` and `dedicatedCredential` (apiKey headers with placeholder names — the operator issues the exact header name with the credential), `bearerToken`, `adminSession` (logged-in cookie).
- Restricted namespaces (Server Stats) publish no paths; they appear under `x-restricted-surfaces` with namespace + auth type only.
- Self-describe filter extended: entries may add `openapi_paths` and `openapi_schemas`, merged over the generated skeleton into paths and components.schemas.
- Safety valve: route tables over 2,500 routes fall back to a namespace-level document (marked `x-index-mode`) so every site still ships a spec.
- `/api.toon` links the document as `openapi_spec:`; the JSON index gains an `openapi` field.

## 2026.11.2.002 — 2026-11-02

- Classification accuracy pass from the builtnotbegged.com pilot output: `oembed` (bare form) is core/public; `batch/v1` and `wp-abilities/v1` are WordPress core, not vendor; `mcp` (MCP adapter) is described as an administrator-session agent surface.

## 2026.11.2.001 — 2026-11-02

- Initial release.
- Serves the site API index three ways from one generator: `/api.toon` (TOON), `/api.json` (JSON twin), and `GET /wp-json/bng-api-index/v1` (live REST route, public read-only).
- Generator walks the live REST route table and caches the result in a one-hour transient; activation flushes rewrite rules and clears the cache.
- Built-in classification map for the Built Not Begged fleet: BNB FluentCart SEO (agent-key), Server Stats (dedicated-credential, restricted — existence and auth only, no route detail), CPH Assistant (dedicated-credential), CPH Licenses (public / dedicated-credential), Taylor Analytics (public /public/reports + dedicated-credential), MusePort (admin), BNB Syndicate, BNG Catalog (public), Bookshelf, Net30 (public / dedicated-credential), Staff Sync and the BNG Updates client (app-password); WordPress core namespaces (wp/v2, oEmbed, site health, block editor); vendor defaults for WooCommerce (dedicated-credential), Fluent plugins and Site Kit (admin).
- `bng_api_index_describe` filter: plugins self-describe their namespace (auth, purpose, owner, restricted) and merge into the index.
- `bng_api_index_exclude` filter plus automatic exclusion of namespaces whose names mark them as internal.
- Output contains names, owners, auth types and route counts only — no credential values, key names, key file paths, or per-route parameters.
