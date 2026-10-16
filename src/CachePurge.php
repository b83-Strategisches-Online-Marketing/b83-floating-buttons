<?php
/**
 * Seiten-Cache leeren, nachdem Buttons geändert wurden.
 *
 * Die Buttons erscheinen auf jeder Seite – deshalb wird der gesamte Cache
 * geleert (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, SiteGround).
 *
 * @package B83\FloatingButtons
 */

declare(strict_types=1);

namespace B83\FloatingButtons;

defined( 'ABSPATH' ) || exit;

final class CachePurge {

	public static function purge_all(): void {
		if ( ! Plugin::instance()->repository()->settings()['purge_cache'] ) {
			return;
		}

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain(); // WP Rocket.
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all(); // W3 Total Cache.
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache(); // WP Super Cache.
		}
		if ( function_exists( 'sg_cachepress_purge_everything' ) ) {
			sg_cachepress_purge_everything(); // SiteGround.
		}
		do_action( 'litespeed_purge_all' ); // LiteSpeed Cache.

		/**
		 * Nach dem Leeren des Caches – für weitere Cache-Lösungen.
		 */
		do_action( 'b83_floating_buttons_purge_cache' );
	}

	/** Name des erkannten Cache-Plugins für die Anzeige im Backend ('' wenn keins). */
	public static function detected_plugin(): string {
		return match ( true ) {
			function_exists( 'rocket_clean_domain' )            => 'WP Rocket',
			defined( 'LSCWP_V' )                                => 'LiteSpeed Cache',
			function_exists( 'w3tc_flush_all' )                 => 'W3 Total Cache',
			function_exists( 'wp_cache_clear_cache' )           => 'WP Super Cache',
			function_exists( 'sg_cachepress_purge_everything' ) => 'SiteGround Speed Optimizer',
			default                                             => '',
		};
	}
}
