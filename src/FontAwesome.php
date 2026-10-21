<?php
/**
 * Font Awesome Free als Icon-Quelle.
 *
 * Die Icons liegen als kompakte Liste in assets/fontawesome/icons.json
 * (Font Awesome Free 7, Icons CC BY 4.0 – siehe assets/fontawesome/LICENSE.txt).
 * Es wird keine Schrift und kein CSS von Font Awesome geladen: Beim Speichern
 * wird das gewählte Icon aus der Liste nachgeschlagen und als SVG-Pfad am
 * Button abgelegt. Im Frontend entsteht so nur ein kleines Inline-SVG.
 *
 * @package B83\FloatingButtons
 */

declare(strict_types=1);

namespace B83\FloatingButtons;

defined( 'ABSPATH' ) || exit;

final class FontAwesome {

	/** Stile: Kürzel => Bezeichnung. */
	public const STYLES = array(
		's' => 'Solid',
		'r' => 'Regular',
		'b' => 'Brands',
	);

	/** @var array<string, array{label:string,width:int,path:string}>|null */
	private static ?array $index = null;

	/** Relativer Pfad der Icon-Liste im Plugin. */
	public static function data_path(): string {
		return 'assets/fontawesome/icons.json';
	}

	/**
	 * Schlüssel prüfen und normalisieren. Format: „s:phone“, „b:whatsapp“.
	 *
	 * @param mixed $value Rohwert.
	 */
	public static function normalize( $value ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return 1 === preg_match( '/^[srb]:[a-z0-9][a-z0-9\-]{0,60}$/', $value ) ? $value : '';
	}

	/**
	 * Icon nachschlagen.
	 *
	 * @return array{label:string,width:int,path:string}|null
	 */
	public static function find( string $key ): ?array {
		$key = self::normalize( $key );
		if ( '' === $key ) {
			return null;
		}
		return self::index()[ $key ] ?? null;
	}

	/**
	 * Abgelegten Pfad prüfen: nur Zahlen und SVG-Pfadbefehle, keine Tags oder Attribute.
	 *
	 * @param mixed $path Rohwert.
	 */
	public static function clean_path( $path ): string {
		$path = is_scalar( $path ) ? (string) $path : '';
		if ( strlen( $path ) > 20000 || 1 !== preg_match( '/^[0-9MmLlHhVvCcSsQqTtAaZz.,\-\s]+$/', $path ) ) {
			return '';
		}
		return $path;
	}

	/** Vollständiges SVG-Element (dekorativ, für Screenreader verborgen). */
	public static function svg( int $width, string $path ): string {
		$path  = self::clean_path( $path );
		$width = max( 1, min( 1024, $width ) );
		if ( '' === $path ) {
			return '';
		}
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' 512" fill="currentColor" aria-hidden="true" focusable="false"><path d="' . esc_attr( $path ) . '"/></svg>';
	}

	/**
	 * Gesamte Liste, einmal pro Aufruf geladen. Wird nur beim Speichern und im
	 * Backend gebraucht, nie bei der Ausgabe im Frontend.
	 *
	 * @return array<string, array{label:string,width:int,path:string}>
	 */
	private static function index(): array {
		if ( null !== self::$index ) {
			return self::$index;
		}
		self::$index = array();

		$file = B83_FB_DIR . self::data_path();
		$json = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lokale Plugin-Datei.
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || ! isset( $data['i'] ) || ! is_array( $data['i'] ) ) {
			return self::$index;
		}

		// Zeile: array( Name, Stil-Kürzel, Bezeichnung, Suchbegriffe, Breite, Pfad ).
		foreach ( $data['i'] as $row ) {
			if ( ! is_array( $row ) || count( $row ) < 6 ) {
				continue;
			}
			self::$index[ $row[1] . ':' . $row[0] ] = array(
				'label' => (string) $row[2],
				'width' => (int) $row[4],
				'path'  => (string) $row[5],
			);
		}
		return self::$index;
	}
}
