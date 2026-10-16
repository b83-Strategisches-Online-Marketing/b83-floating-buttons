<?php
/**
 * Installation und Datenbank-Versionen.
 *
 * DB-Version 1 (Plugin 1.0.0): Optionen anlegen. Spätere Strukturänderungen
 * werden hier als to_vN() ergänzt.
 *
 * @package B83\FloatingButtons
 */

declare(strict_types=1);

namespace B83\FloatingButtons;

defined( 'ABSPATH' ) || exit;

final class Migration {

	public const DB_VERSION        = 1;
	public const OPTION_DB_VERSION = 'b83_floating_buttons_db_version';

	/** Export-Kennung. */
	public const EXPORT_FORMAT = 'b83-floating-buttons';

	public static function on_activation(): void {
		self::run();
	}

	/** Läuft bei jedem Seitenaufruf im Backend, tut aber nur bei Versionswechsel etwas. */
	public static function maybe_run(): void {
		if ( (int) get_option( self::OPTION_DB_VERSION, 0 ) < self::DB_VERSION ) {
			self::run();
		}
	}

	private static function run(): void {
		// Leisten werden auf jeder Seite gebraucht → automatisch laden.
		if ( false === get_option( Repository::OPTION_GROUPS, false ) ) {
			add_option( Repository::OPTION_GROUPS, array(), '', true );
		}
		if ( false === get_option( Repository::OPTION_SETTINGS, false ) ) {
			add_option( Repository::OPTION_SETTINGS, Sanitizer::settings( array() ), '', false );
		}

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, true );
	}

	/**
	 * Erkennt das Format einer importierten Datei und liefert die Leisten.
	 *
	 * @param array<mixed> $decoded JSON-Inhalt.
	 * @return array<int, mixed>|null Null bei unbekanntem Format.
	 */
	public static function normalize_import( array $decoded ): ?array {
		if ( ( $decoded['format'] ?? '' ) === self::EXPORT_FORMAT && isset( $decoded['groups'] ) && is_array( $decoded['groups'] ) ) {
			return array_values( $decoded['groups'] );
		}
		return null;
	}
}
