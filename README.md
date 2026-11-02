# BNB API Index

A WordPress micro-plugin from **Built Not Begged** that publishes an agent-readable index of a site's API surface.

> "I can't fix humans not having a API but I can fix our sites to having one." — Matthew Murphy, 2026-11-02

## What it serves

| Route | Format |
|---|---|
| `GET /api.toon` | TOON (Token-Oriented Object Notation) |
| `GET /api.json` | JSON twin of the same index |
| `GET /wp-json/bng-api-index/v1` | Live REST route |
| `GET /openapi.json` | OpenAPI 3.1 document from the live route table |
| `GET /wp-json/bng-api-index/v1/openapi` | REST twin of the OpenAPI document |

The index is generated from the live REST route table (one-hour transient cache), so it stays true as plugins come and go. Each surface lists: namespace, base path, owner (`ours` / `vendor` / `core`), auth type **by name only** (`public`, `app-password`, `agent-key`, `dedicated-credential`, `admin`, `token`), route count, and a one-line purpose.

## Security posture

Discovery is public; access is not.

- No credential values, key names, or key file paths are ever emitted.
- Restricted namespaces (credential-gated by design, e.g. Server Stats) are listed as existing with their auth type; their routes are **not** enumerated.
- Namespaces whose names mark them as internal are excluded automatically, and the `bng_api_index_exclude` filter can exclude more.
- The plugin writes nothing, adds no auth surface, and its own routes are public read-only GETs.

## Self-describe filter

Any plugin can describe its own namespace so the index stays accurate without an update here:

```php
add_filter( 'bng_api_index_describe', function ( $d ) {
    $d['my-plugin/v1'] = array(
        'auth'       => 'agent-key', // public|app-password|agent-key|dedicated-credential|admin|token
        'purpose'    => 'One line about what this API does.',
        'owner'      => 'ours',      // ours|vendor|core|platform
        'restricted' => false,       // true = existence + auth only, no route count
    );
    return $d;
} );
```

The same entry can enrich the OpenAPI document with two optional keys: `openapi_paths` (path => path item, merged over the generated skeleton) and `openapi_schemas` (name => schema, merged into `components.schemas`).

## Install

Copy `bng-api-index/` into `wp-content/plugins/` and activate. Requires WordPress 6.0+ and PHP 8.0+.

## Versioning

CalVer. Current: **2026.11.2.004** — see `CHANGELOG.md` for item-by-item notes (never "bug fixes").

## License

GPL-2.0-or-later.
