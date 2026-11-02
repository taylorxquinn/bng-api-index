<?php
/**
 * Plugin Name:       BNB API Index
 * Plugin URI:        https://github.com/taylorxquinn/bng-api-index
 * Description:       Publishes an agent-readable index of this site's API surface at /api.toon (TOON), /api.json, and GET /wp-json/bng-api-index/v1. Discovery is public; access stays behind each surface's own credential.
 * Version:           2026.11.2.002
 * Author:            Built Not Begged
 * Author URI:        https://builtnotbegged.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bng-api-index
 *
 * The index is generated from the live REST route table (cached in a
 * one-hour transient), so namespaces appear and disappear automatically
 * as plugins are installed or removed. It contains names, owners, auth
 * types and route counts only: no credential values, no key names, and
 * no route detail for restricted (credential-gated) namespaces.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BNG_API_INDEX_VERSION', '2026.11.2.002' );
define( 'BNG_API_INDEX_TRANSIENT', 'bng_api_index_cache_v1' );

/* -------------------------------------------------------------------------
 * Rewrite rules: /api.toon and /api.json at the site root.
 * ---------------------------------------------------------------------- */

add_action( 'init', 'bng_api_index_add_rewrite_rules' );
function bng_api_index_add_rewrite_rules() {
	add_rewrite_rule( '^api\.toon$', 'index.php?bng_api_index_format=toon', 'top' );
	add_rewrite_rule( '^api\.json$', 'index.php?bng_api_index_format=json', 'top' );
}

add_filter( 'query_vars', 'bng_api_index_query_vars' );
function bng_api_index_query_vars( $vars ) {
	$vars[] = 'bng_api_index_format';
	return $vars;
}

register_activation_hook( __FILE__, 'bng_api_index_activate' );
function bng_api_index_activate() {
	bng_api_index_add_rewrite_rules();
	flush_rewrite_rules();
	delete_transient( BNG_API_INDEX_TRANSIENT );
}

register_deactivation_hook( __FILE__, 'bng_api_index_deactivate' );
function bng_api_index_deactivate() {
	flush_rewrite_rules();
	delete_transient( BNG_API_INDEX_TRANSIENT );
}

add_action( 'template_redirect', 'bng_api_index_serve_file', 0 );
function bng_api_index_serve_file() {
	$format = get_query_var( 'bng_api_index_format' );
	if ( ! $format ) {
		return;
	}
	$index = bng_api_index_get();
	if ( 'json' === $format ) {
		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo wp_json_encode( $index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	} else {
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo bng_api_index_to_toon( $index );
	}
	exit;
}

/* -------------------------------------------------------------------------
 * REST route: GET /wp-json/bng-api-index/v1
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', 'bng_api_index_register_rest_route' );
function bng_api_index_register_rest_route() {
	register_rest_route(
		'bng-api-index/v1',
		'/',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => 'bng_api_index_rest_callback',
		)
	);
}

function bng_api_index_rest_callback() {
	return rest_ensure_response( bng_api_index_get() );
}

/* -------------------------------------------------------------------------
 * Index generation.
 * ---------------------------------------------------------------------- */

function bng_api_index_get() {
	$cached = get_transient( BNG_API_INDEX_TRANSIENT );
	if ( is_array( $cached ) && isset( $cached['surfaces'] ) ) {
		return $cached;
	}
	$index = bng_api_index_build();
	set_transient( BNG_API_INDEX_TRANSIENT, $index, HOUR_IN_SECONDS );
	return $index;
}

function bng_api_index_build() {
	$server = rest_get_server();
	$routes = $server->get_routes();

	$counts = array();
	foreach ( $routes as $route => $handlers ) {
		if ( '/' === $route ) {
			continue;
		}
		$ns = bng_api_index_namespace_of( $route );
		if ( null === $ns ) {
			continue;
		}
		if ( ! isset( $counts[ $ns ] ) ) {
			$counts[ $ns ] = 0;
		}
		$counts[ $ns ]++;
	}

	/**
	 * Namespaces that must never be advertised. Default: anything whose
	 * name marks it as internal. Sites can extend this with the
	 * 'bng_api_index_exclude' filter (array of namespace strings).
	 */
	$excluded = array();
	foreach ( array_keys( $counts ) as $ns ) {
		if ( preg_match( '/(^|[-_])internal([-_]|$)/i', $ns ) ) {
			$excluded[] = $ns;
		}
	}
	$excluded = apply_filters( 'bng_api_index_exclude', $excluded );

	$map = bng_api_index_builtin_map();

	/**
	 * Self-description filter. Any plugin can describe its own namespace:
	 *
	 *   add_filter( 'bng_api_index_describe', function ( $d ) {
	 *       $d['my-plugin/v1'] = array(
	 *           'auth'      => 'agent-key',   // public|app-password|agent-key|dedicated-credential|admin|token
	 *           'purpose'   => 'One line.',
	 *           'owner'     => 'ours',        // ours|vendor|core|platform
	 *           'restricted'=> false,         // true = list existence + auth only, no route count
	 *       );
	 *       return $d;
	 *   } );
	 */
	$described = apply_filters( 'bng_api_index_describe', array() );
	if ( is_array( $described ) ) {
		foreach ( $described as $ns => $entry ) {
			if ( isset( $map[ $ns ] ) && is_array( $entry ) ) {
				$map[ $ns ] = array_merge( $map[ $ns ], $entry );
			} elseif ( is_array( $entry ) ) {
				$map[ $ns ] = $entry;
			}
		}
	}

	$surfaces = array();
	foreach ( $counts as $ns => $count ) {
		if ( in_array( $ns, (array) $excluded, true ) ) {
			continue;
		}
		$entry = isset( $map[ $ns ] ) ? $map[ $ns ] : bng_api_index_classify_default( $ns );

		$auth       = isset( $entry['auth'] ) ? $entry['auth'] : 'app-password';
		$owner      = isset( $entry['owner'] ) ? $entry['owner'] : 'vendor';
		$purpose    = isset( $entry['purpose'] ) ? $entry['purpose'] : '';
		$restricted = ! empty( $entry['restricted'] );

		$surfaces[] = array(
			'namespace'  => $ns,
			'base'       => rest_url( $ns ),
			'owner'      => $owner,
			'auth'       => $auth,
			'credential' => bng_api_index_credential_text( $auth ),
			'routes'     => $restricted ? null : $count,
			'restricted' => $restricted,
			'purpose'    => $purpose,
		);
	}

	usort(
		$surfaces,
		function ( $a, $b ) {
			$order = array( 'ours' => 0, 'core' => 1, 'platform' => 2, 'vendor' => 3 );
			$oa    = isset( $order[ $a['owner'] ] ) ? $order[ $a['owner'] ] : 4;
			$ob    = isset( $order[ $b['owner'] ] ) ? $order[ $b['owner'] ] : 4;
			if ( $oa !== $ob ) {
				return $oa - $ob;
			}
			return strcmp( $a['namespace'], $b['namespace'] );
		}
	);

	return array(
		'site'           => array(
			'name' => get_bloginfo( 'name' ),
			'url'  => home_url( '/' ),
		),
		'generated'      => gmdate( 'c' ),
		'generator'      => 'BNB API Index ' . BNG_API_INDEX_VERSION,
		'rest_index'     => rest_url( '/' ),
		'note'           => 'Discovery is public; access is not. Gated surfaces require their own credential and return 401 without it.',
		'surface_count'  => count( $surfaces ),
		'surfaces'       => $surfaces,
	);
}

function bng_api_index_namespace_of( $route ) {
	$parts = explode( '/', trim( $route, '/' ) );
	if ( count( $parts ) < 2 ) {
		return null;
	}
	if ( preg_match( '/^v\d+(\.\d+)?$/', $parts[1] ) ) {
		return $parts[0] . '/' . $parts[1];
	}
	return $parts[0];
}

function bng_api_index_credential_text( $auth ) {
	switch ( $auth ) {
		case 'public':
			return 'No credential needed.';
		case 'agent-key':
			return 'Agent key issued by the site operator; sent as a request header.';
		case 'dedicated-credential':
			return 'Dedicated credential issued by the site operator.';
		case 'admin':
			return 'Signed-in administrator session.';
		case 'token':
			return 'API token issued by the site operator.';
		case 'public / dedicated-credential':
			return 'Public reads need no credential; gated operations use a dedicated credential from the site operator.';
		case 'app-password':
		default:
			return 'WordPress application password (profile > Application Passwords).';
	}
}

/**
 * Classification for namespaces with no explicit map or filter entry.
 * Our own plugins default to app-password (the fleet pattern); anything
 * Fluent or WooCommerce-shaped is vendor; the rest is vendor.
 */
function bng_api_index_classify_default( $ns ) {
	$own_prefixes = array(
		'bnb-', 'bng-', 'cph-', 'tq-', 'mxm-', 'net30', 'museport',
		'server-stats-built-not-begged', 'boldteehub', 'trailerbounty',
		'creator-publishing-hub', 'factology', 'cnd', 'cnd-feed',
	);
	foreach ( $own_prefixes as $prefix ) {
		if ( 0 === strpos( $ns, $prefix ) ) {
			return array(
				'owner'   => 'ours',
				'auth'    => 'app-password',
				'purpose' => 'Built Not Begged plugin API.',
			);
		}
	}
	if ( 0 === strpos( $ns, 'wc/' ) || 0 === strpos( $ns, 'wc-' ) || false !== strpos( $ns, 'woocommerce' ) ) {
		return array(
			'owner'   => 'vendor',
			'auth'    => 'dedicated-credential',
			'purpose' => 'WooCommerce API; uses its own consumer keys.',
		);
	}
	if ( false !== strpos( $ns, 'fluent' ) ) {
		return array(
			'owner'   => 'vendor',
			'auth'    => 'app-password',
			'purpose' => 'Fluent (WPManageNinja) plugin API.',
		);
	}
	return array(
		'owner'   => 'vendor',
		'auth'    => 'app-password',
		'purpose' => 'Third-party plugin API.',
	);
}

function bng_api_index_builtin_map() {
	return array(
		'wp/v2'                           => array(
			'owner'   => 'core',
			'auth'    => 'app-password',
			'purpose' => 'WordPress core content API. Published content is public to read; writes need an application password.',
		),
		'wp-site-health/v1'               => array(
			'owner'   => 'core',
			'auth'    => 'admin',
			'purpose' => 'WordPress site health checks for administrators.',
		),
		'wp-block-editor/v1'              => array(
			'owner'   => 'core',
			'auth'    => 'app-password',
			'purpose' => 'WordPress block editor settings.',
		),
		'oembed/1.0'                      => array(
			'owner'   => 'core',
			'auth'    => 'public',
			'purpose' => 'oEmbed discovery for this site\'s published content.',
		),
		'oembed'                          => array(
			'owner'   => 'core',
			'auth'    => 'public',
			'purpose' => 'oEmbed discovery for this site\'s published content.',
		),
		'batch/v1'                        => array(
			'owner'   => 'core',
			'auth'    => 'app-password',
			'purpose' => 'WordPress core batch endpoint: groups REST requests into one call.',
		),
		'wp-abilities/v1'                 => array(
			'owner'   => 'core',
			'auth'    => 'app-password',
			'purpose' => 'WordPress core Abilities API.',
		),
		'mcp'                             => array(
			'owner'   => 'vendor',
			'auth'    => 'admin',
			'purpose' => 'MCP adapter endpoint: agent-callable WordPress abilities over the Model Context Protocol.',
		),
		'bng-api-index/v1'                => array(
			'owner'   => 'ours',
			'auth'    => 'public',
			'purpose' => 'This API index: every API surface on the site, listed in one place.',
		),
		'bnb-fluentcart-seo/v1'           => array(
			'owner'   => 'ours',
			'auth'    => 'agent-key',
			'purpose' => 'BNB FluentCart SEO: store SEO audit, plan and apply endpoints for agents.',
		),
		'bnb-fluentcart-seo-pro/v1'       => array(
			'owner'   => 'ours',
			'auth'    => 'agent-key',
			'purpose' => 'BNB FluentCart SEO Pro executor: applies approved SEO plans with rollback.',
		),
		'bnb-syndicate/v1'                => array(
			'owner'   => 'ours',
			'auth'    => 'app-password',
			'purpose' => 'BNB Syndicate product feed health and merchant feed status.',
		),
		'bng-catalog/v1'                  => array(
			'owner'   => 'ours',
			'auth'    => 'public',
			'purpose' => 'Built Not Begged product catalog. Public by design.',
		),
		'server-stats-built-not-begged/v1' => array(
			'owner'      => 'ours',
			'auth'       => 'dedicated-credential',
			'purpose'    => 'Server Stats: server, traffic and crawler statistics. Exists and is credential-gated; routes are not listed by design.',
			'restricted' => true,
		),
		'museport/v1'                     => array(
			'owner'   => 'ours',
			'auth'    => 'admin',
			'purpose' => 'MusePort: connected-agent activity reporting, private to administrators.',
		),
		'cph-assistant/v1'                => array(
			'owner'   => 'ours',
			'auth'    => 'dedicated-credential',
			'purpose' => 'Creator Publishing Hub assistant API: content, media, publishing and diagnostics for the CPH fleet.',
		),
		'cph-licenses/v1'                 => array(
			'owner'   => 'ours',
			'auth'    => 'public / dedicated-credential',
			'purpose' => 'License plans and offers are public by design; activation and validation take a key in the request.',
		),
		'cph-staff/v1'                    => array(
			'owner'   => 'ours',
			'auth'    => 'app-password',
			'purpose' => 'CPH Staff Sync: shared staff accounts across our sites.',
		),
		'net30-updates/v1'                => array(
			'owner'   => 'ours',
			'auth'    => 'app-password',
			'purpose' => 'Built Not Begged update feed client for our plugins.',
		),
		'net30/v1'                        => array(
			'owner'   => 'ours',
			'auth'    => 'public / dedicated-credential',
			'purpose' => 'Net30 Hosting API: domain check, public marketing config, subscribe and setup flows.',
		),
		'tq-analytics/v1'                 => array(
			'owner'   => 'ours',
			'auth'    => 'public / dedicated-credential',
			'purpose' => 'Taylor Analytics reports and signed collector feeds. /public/reports is public by design; everything else takes the assigned analytics credential.',
		),
		'mxm-bookshelf/v1'                => array(
			'owner'   => 'ours',
			'auth'    => 'app-password',
			'purpose' => 'Bookshelf: book catalog shelves and new-release feeds.',
		),
		'cnd-feed/v1'                     => array(
			'owner'   => 'ours',
			'auth'    => 'public',
			'purpose' => 'Creator Newsdesk public article feed. Public by design.',
		),
		'google-site-kit/v1'              => array(
			'owner'   => 'vendor',
			'auth'    => 'admin',
			'purpose' => 'Google Site Kit proxy API (Analytics / Search Console context), administrator session only.',
		),
		'betterdocs/v1'                   => array(
			'owner'   => 'vendor',
			'auth'    => 'app-password',
			'purpose' => 'BetterDocs documentation API.',
		),
	);
}

/* -------------------------------------------------------------------------
 * TOON rendering (tabular form, same family as library.toon / join.toon).
 * ---------------------------------------------------------------------- */

function bng_api_index_toon_quote( $value ) {
	if ( is_int( $value ) ) {
		return (string) $value;
	}
	$value = (string) $value;
	return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $value ) . '"';
}

function bng_api_index_to_toon( $index ) {
	$lines   = array();
	$lines[] = 'title: API Index — ' . $index['site']['name'];
	$lines[] = 'site: ' . $index['site']['url'];
	$lines[] = 'generated: ' . $index['generated'];
	$lines[] = 'generator: ' . $index['generator'];
	$lines[] = 'format: TOON';
	$lines[] = 'rest_index: ' . $index['rest_index'];
	$lines[] = 'json_twin: ' . $index['site']['url'] . 'api.json';
	$lines[] = 'note: "' . $index['note'] . '"';
	$lines[] = '';
	$count   = count( $index['surfaces'] );
	$lines[] = 'surfaces[' . $count . ']{namespace,base,owner,auth,routes,purpose}:';
	foreach ( $index['surfaces'] as $surface ) {
		$routes  = $surface['restricted'] ? 'restricted' : (string) $surface['routes'];
		$lines[] = '  ' . implode(
			',',
			array(
				bng_api_index_toon_quote( $surface['namespace'] ),
				bng_api_index_toon_quote( $surface['base'] ),
				bng_api_index_toon_quote( $surface['owner'] ),
				bng_api_index_toon_quote( $surface['auth'] ),
				$routes,
				bng_api_index_toon_quote( $surface['purpose'] ),
			)
		);
	}
	$lines[] = '';
	$lines[] = 'credential_pointers: "Auth types are named only; no credential value is ever listed. App passwords: WordPress profile. Agent keys and dedicated credentials: issued by the site operator."';
	return implode( "\n", $lines ) . "\n";
}
