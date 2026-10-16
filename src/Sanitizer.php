<?php
/**
 * Zentrale Validierung aller Eingaben (Whitelist, Längen- und Wertegrenzen).
 *
 * Alles, was gespeichert, importiert oder in der Vorschau gezeigt wird,
 * läuft durch diese Klasse. Unbekannte Felder werden verworfen, ungültige
 * Werte durch Standardwerte ersetzt.
 *
 * @package B83\FloatingButtons
 */

declare(strict_types=1);

namespace B83\FloatingButtons;

defined( 'ABSPATH' ) || exit;

final class Sanitizer {

	public const MAX_GROUPS = 20;
	public const MAX_ITEMS  = 20;

	public const POSITIONS     = array( 'left', 'right', 'top', 'bottom' );
	public const ALIGNS        = array( 'start', 'center', 'end' );
	public const SHADOWS       = array( 'none', 'soft', 'strong' );
	public const ENTRANCES     = array( 'none', 'fade', 'slide' );
	public const MOBILE_LAYOUT = array( 'same', 'compact', 'bar' );
	public const ICON_TYPES    = array( 'icon', 'image' );
	public const LINK_TYPES    = array( 'url', 'email', 'phone', 'whatsapp', 'sms', 'top', 'none' );
	public const FONT_WEIGHTS  = array( '400', '600', '700' );

	/** Farbfelder einer Leiste. */
	public const GROUP_COLORS = array( 'bg', 'color', 'text_color', 'bg_hover', 'color_hover', 'text_hover', 'tip_bg', 'tip_color' );

	/** Farbfelder, die ein einzelner Button überschreiben kann. */
	public const ITEM_COLORS = array( 'bg', 'color', 'bg_hover', 'color_hover' );

	/**
	 * Zahlenfelder einer Leiste: Feld => array( min, max, Standard ).
	 *
	 * @var array<string, array{0:int,1:int,2:int}>
	 */
	public const GROUP_NUMBERS = array(
		'align_offset' => array( 0, 600, 0 ),
		'edge_offset'  => array( 0, 200, 0 ),
		'gap'          => array( 0, 40, 6 ),
		'size'         => array( 32, 96, 52 ),
		'icon_size'    => array( 30, 90, 50 ),
		'radius'       => array( 0, 60, 10 ),
		'peek'         => array( 0, 60, 0 ),
		'font_size'    => array( 11, 24, 15 ),
		'delay'        => array( 0, 1500, 150 ),
		'duration'     => array( 100, 1000, 300 ),
		'mobile_size'  => array( 32, 80, 44 ),
		'z_index'      => array( 1, 999999, 9990 ),
	);

	/* ---------------------------------------------------------------------
	 * Standardwerte
	 * ------------------------------------------------------------------- */

	/** @return array<string,mixed> */
	public static function group_defaults(): array {
		$numbers = array();
		foreach ( self::GROUP_NUMBERS as $key => $range ) {
			$numbers[ $key ] = $range[2];
		}

		return array_merge(
			array(
				'id'            => '',
				'name'          => __( 'Neue Leiste', 'b83-floating-buttons' ),
				'active'        => true,
				'position'      => 'right',
				'align'         => 'center',
				'bg'            => '#1d2a3a',
				'color'         => '#ffffff',
				'text_color'    => '#ffffff',
				'bg_hover'      => '#e30613',
				'color_hover'   => '#ffffff',
				'text_hover'    => '#ffffff',
				'tip_bg'        => '#111111',
				'tip_color'     => '#ffffff',
				'shadow'        => 'soft',
				'font_weight'   => '600',
				'tooltips'      => true,
				'entrance'      => 'slide',
				'show_desktop'  => true,
				'show_tablet'   => true,
				'show_mobile'   => true,
				'mobile_layout' => 'same',
				'exclude_ids'   => array(),
				'items'         => array(),
			),
			$numbers
		);
	}

	/** @return array<string,mixed> */
	public static function item_defaults(): array {
		return array(
			'id'             => '',
			'active'         => true,
			'label'          => '',
			'tooltip'        => '',
			'icon_type'      => 'icon',
			'icon'           => 'phone',
			'image_id'       => 0,
			'link_type'      => 'url',
			'url'            => '',
			'email'          => '',
			'email_subject'  => '',
			'phone'          => '',
			'whatsapp'       => '',
			'whatsapp_text'  => '',
			'target_blank'   => false,
			'nofollow'       => false,
			'bg'             => '',
			'color'          => '',
			'bg_hover'       => '',
			'color_hover'    => '',
		);
	}

	/* ---------------------------------------------------------------------
	 * Leisten und Buttons
	 * ------------------------------------------------------------------- */

	/**
	 * Eine Leiste aus Formular, Import oder Speicher.
	 *
	 * @param array<mixed> $raw   Rohdaten (bereits wp_unslash).
	 * @param Icons        $icons Icon-Bibliothek zur Prüfung der Icon-Schlüssel.
	 * @return array<string,mixed>
	 */
	public static function group( array $raw, Icons $icons ): array {
		$d = self::group_defaults();
		$g = array();

		$g['id']       = self::id( $raw['id'] ?? '', 'g' );
		$g['name']     = self::text( $raw['name'] ?? '', 80 );
		$g['name']     = '' !== $g['name'] ? $g['name'] : $d['name'];
		$g['active']   = self::bool( $raw['active'] ?? false );
		$g['position'] = self::choice( $raw['position'] ?? '', self::POSITIONS, $d['position'] );
		$g['align']    = self::choice( $raw['align'] ?? '', self::ALIGNS, $d['align'] );

		foreach ( self::GROUP_NUMBERS as $key => $range ) {
			$g[ $key ] = self::int( $raw[ $key ] ?? $range[2], $range[0], $range[1] );
		}
		foreach ( self::GROUP_COLORS as $key ) {
			$color     = self::color( $raw[ $key ] ?? '' );
			$g[ $key ] = '' !== $color ? $color : $d[ $key ];
		}

		$g['shadow']        = self::choice( $raw['shadow'] ?? '', self::SHADOWS, $d['shadow'] );
		$g['font_weight']   = self::choice( (string) ( $raw['font_weight'] ?? '' ), self::FONT_WEIGHTS, $d['font_weight'] );
		$g['tooltips']      = self::bool( $raw['tooltips'] ?? false );
		$g['entrance']      = self::choice( $raw['entrance'] ?? '', self::ENTRANCES, $d['entrance'] );
		$g['show_desktop']  = self::bool( $raw['show_desktop'] ?? false );
		$g['show_tablet']   = self::bool( $raw['show_tablet'] ?? false );
		$g['show_mobile']   = self::bool( $raw['show_mobile'] ?? false );
		$g['mobile_layout'] = self::choice( $raw['mobile_layout'] ?? '', self::MOBILE_LAYOUT, $d['mobile_layout'] );
		$g['exclude_ids']   = self::id_list( $raw['exclude_ids'] ?? array() );

		$items = array();
		foreach ( array_slice( (array) ( $raw['items'] ?? array() ), 0, self::MAX_ITEMS ) as $item ) {
			if ( is_array( $item ) ) {
				$items[] = self::item( $item, $icons );
			}
		}
		$g['items'] = $items;

		return $g;
	}

	/**
	 * Ein Button.
	 *
	 * @param array<mixed> $raw   Rohdaten.
	 * @param Icons        $icons Icon-Bibliothek.
	 * @return array<string,mixed>
	 */
	public static function item( array $raw, Icons $icons ): array {
		$d = self::item_defaults();
		$i = array();

		$i['id']        = self::id( $raw['id'] ?? '', 'b' );
		$i['active']    = self::bool( $raw['active'] ?? false );
		$i['label']     = self::text( $raw['label'] ?? '', 60 );
		$i['tooltip']   = self::text( $raw['tooltip'] ?? '', 200 );
		$i['icon_type'] = self::choice( $raw['icon_type'] ?? '', self::ICON_TYPES, $d['icon_type'] );

		$icon      = sanitize_key( (string) ( $raw['icon'] ?? '' ) );
		$i['icon'] = $icons->exists( $icon ) ? $icon : $d['icon'];

		$image_id      = self::int( $raw['image_id'] ?? 0, 0, PHP_INT_MAX );
		$i['image_id'] = ( $image_id > 0 && wp_attachment_is_image( $image_id ) ) ? $image_id : 0;
		if ( 'image' === $i['icon_type'] && 0 === $i['image_id'] ) {
			$i['icon_type'] = 'icon';
		}

		$i['link_type']     = self::choice( $raw['link_type'] ?? '', self::LINK_TYPES, $d['link_type'] );
		$i['url']           = self::url( $raw['url'] ?? '' );
		$email              = sanitize_email( self::text( $raw['email'] ?? '', 254 ) );
		$i['email']         = is_email( $email ) ? $email : '';
		$i['email_subject'] = self::text( $raw['email_subject'] ?? '', 150 );
		$i['phone']         = self::phone( $raw['phone'] ?? '' );
		$i['whatsapp']      = preg_replace( '/\D+/', '', self::text( $raw['whatsapp'] ?? '', 30 ) ) ?? '';
		$i['whatsapp_text'] = self::text( $raw['whatsapp_text'] ?? '', 300 );
		$i['target_blank']  = self::bool( $raw['target_blank'] ?? false );
		$i['nofollow']      = self::bool( $raw['nofollow'] ?? false );

		foreach ( self::ITEM_COLORS as $key ) {
			$i[ $key ] = self::color( $raw[ $key ] ?? '' );
		}

		return $i;
	}

	/**
	 * Allgemeine Einstellungen.
	 *
	 * @param array<mixed> $raw Rohdaten.
	 * @return array{delete_on_uninstall:bool,purge_cache:bool}
	 */
	public static function settings( array $raw ): array {
		return array(
			'delete_on_uninstall' => self::bool( $raw['delete_on_uninstall'] ?? false ),
			'purge_cache'         => self::bool( $raw['purge_cache'] ?? true ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Einzelwerte
	 * ------------------------------------------------------------------- */

	/** @param mixed $value Rohwert. */
	public static function text( $value, int $max ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = sanitize_text_field( (string) $value );
		return mb_substr( $text, 0, $max );
	}

	/** @param mixed $value Rohwert. */
	public static function bool( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'on', 'yes', 'true' ), true );
	}

	/** @param mixed $value Rohwert. */
	public static function int( $value, int $min, int $max ): int {
		$number = is_numeric( $value ) ? (int) $value : $min;
		return max( $min, min( $max, $number ) );
	}

	/**
	 * @param mixed    $value   Rohwert.
	 * @param string[] $allowed Erlaubte Werte.
	 */
	public static function choice( $value, array $allowed, string $fallback ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Hex-Farbe (#rgb, #rrggbb, #rrggbbaa) oder leer.
	 *
	 * @param mixed $value Rohwert.
	 */
	public static function color( $value ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return 1 === preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value ) ? $value : '';
	}

	/**
	 * Stabile ID: vorhandene übernehmen, sonst neu vergeben.
	 *
	 * @param mixed $value Rohwert.
	 */
	public static function id( $value, string $prefix ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		if ( 1 === preg_match( '/^[a-z][0-9a-f]{8}$/', $value ) && $value[0] === $prefix ) {
			return $value;
		}
		return $prefix . substr( md5( wp_generate_uuid4() ), 0, 8 );
	}

	/**
	 * Link-Ziel: http(s), relative Pfade (/kontakt/) und Sprungmarken (#anfahrt).
	 *
	 * @param mixed $value Rohwert.
	 */
	public static function url( $value ): string {
		$value = self::text( $value, 2000 );
		if ( '' === $value ) {
			return '';
		}
		if ( str_starts_with( $value, '#' ) ) {
			return '#' . ( preg_replace( '/[^A-Za-z0-9\-_:.]/', '', substr( $value, 1 ) ) ?? '' );
		}
		if ( str_starts_with( $value, '/' ) && ! str_starts_with( $value, '//' ) ) {
			return esc_url_raw( $value );
		}
		// Andere Protokolle (javascript:, data:, …) nicht zulassen.
		if ( preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $value ) && ! preg_match( '#^https?://#i', $value ) ) {
			return '';
		}
		if ( str_starts_with( $value, '//' ) ) {
			$value = 'https:' . $value;
		} elseif ( ! preg_match( '#^https?://#i', $value ) ) {
			$value = 'https://' . $value;
		}
		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	/**
	 * Telefonnummer für tel:-Links: nur Ziffern und führendes +.
	 *
	 * @param mixed $value Rohwert.
	 */
	public static function phone( $value ): string {
		$value = self::text( $value, 40 );
		$plus  = str_starts_with( ltrim( $value ), '+' ) ? '+' : '';
		$value = preg_replace( '/\D+/', '', $value ) ?? '';
		// Österreichische Schreibweise 0043… → +43…
		if ( '' === $plus && str_starts_with( $value, '00' ) ) {
			return '+' . substr( $value, 2 );
		}
		return '' !== $value ? $plus . $value : '';
	}

	/**
	 * Liste von Seiten-/Beitrags-IDs (Komma, Leerzeichen oder Array).
	 *
	 * @param mixed $value Rohwert.
	 * @return int[]
	 */
	public static function id_list( $value ): array {
		$ids = array();
		foreach ( (array) $value as $entry ) {
			// Einträge können selbst Listen sein ("12, 34" aus dem Freitextfeld).
			foreach ( preg_split( '/[\s,;]+/', is_scalar( $entry ) ? (string) $entry : '' ) ?: array() as $id ) {
				if ( is_numeric( $id ) && (int) $id > 0 ) {
					$ids[] = (int) $id;
				}
			}
		}
		return array_slice( array_values( array_unique( $ids ) ), 0, 200 );
	}
}
