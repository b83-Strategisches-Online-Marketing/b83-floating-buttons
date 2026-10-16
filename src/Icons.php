<?php
/**
 * Zugriff auf die Icon-Bibliothek (erweiterbar per Filter).
 *
 * @package B83\FloatingButtons
 */

declare(strict_types=1);

namespace B83\FloatingButtons;

defined( 'ABSPATH' ) || exit;

final class Icons {

	/** @var array<string, array{label:string,group:string,mode:string,svg:string}>|null */
	private ?array $icons = null;

	/**
	 * Alle Icons.
	 *
	 * @return array<string, array{label:string,group:string,mode:string,svg:string}>
	 */
	public function all(): array {
		if ( null !== $this->icons ) {
			return $this->icons;
		}

		$icons = array();
		foreach ( IconLibrary::raw() as $key => $row ) {
			$icons[ $key ] = array(
				'label' => $row[0],
				'group' => $row[1],
				'mode'  => $row[2],
				'svg'   => $row[3],
			);
		}

		/**
		 * Eigene Icons ergänzen. Format je Eintrag:
		 * 'mein-icon' => array( 'label' => 'Name', 'group' => 'Eigene', 'mode' => 'fill'|'stroke',
		 *                       'svg' => '<path d="…"/>' ) – SVG-Inhalt für viewBox 0 0 24 24.
		 *
		 * @param array<string, array<string,string>> $icons Icons.
		 */
		$filtered = (array) apply_filters( 'b83_floating_buttons_icons', $icons );

		$this->icons = array();
		foreach ( $filtered as $key => $icon ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || ! is_array( $icon ) || empty( $icon['svg'] ) ) {
				continue;
			}
			$this->icons[ $key ] = array(
				'label' => (string) ( $icon['label'] ?? $key ),
				'group' => (string) ( $icon['group'] ?? __( 'Eigene', 'b83-floating-buttons' ) ),
				'mode'  => 'stroke' === ( $icon['mode'] ?? '' ) ? 'stroke' : 'fill',
				'svg'   => (string) $icon['svg'],
			);
		}
		return $this->icons;
	}

	public function exists( string $key ): bool {
		return isset( $this->all()[ $key ] );
	}

	/** Vollständiges SVG-Element (dekorativ, für Screenreader verborgen). */
	public function svg( string $key ): string {
		$icon = $this->all()[ $key ] ?? null;
		if ( null === $icon ) {
			return '';
		}
		$paint = 'stroke' === $icon['mode']
			? 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
			: 'fill="currentColor"';

		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" ' . $paint . ' aria-hidden="true" focusable="false">'
			. self::filter_svg( $icon['svg'] )
			. '</svg>';
	}

	/**
	 * SVG-Inhalt filtern. wp_kses() entfernt das „/“ selbstschließender Tags –
	 * im HTML-Dokument würde dann das nächste Element im vorigen „verschwinden“.
	 * Daher Formelemente danach wieder selbstschließend schreiben.
	 */
	private static function filter_svg( string $svg ): string {
		$clean = wp_kses( $svg, self::allowed_svg() );
		$clean = (string) preg_replace( '#</(path|circle|ellipse|rect|line|polyline|polygon)>#', '', $clean );
		return (string) preg_replace( '#<(path|circle|ellipse|rect|line|polyline|polygon)\b([^>]*?)\s*/?>#', '<$1$2/>', $clean );
	}

	/**
	 * Erlaubte SVG-Elemente für Icon-Inhalte (schützt auch per Filter ergänzte Icons).
	 *
	 * @return array<string, array<string,bool>>
	 */
	public static function allowed_svg(): array {
		$common = array(
			'd'              => true,
			'fill'           => true,
			'stroke'         => true,
			'stroke-width'   => true,
			'stroke-linecap' => true,
			'transform'      => true,
			'fill-rule'      => true,
			'clip-rule'      => true,
			'opacity'        => true,
		);
		return array(
			'path'     => $common,
			'circle'   => $common + array( 'cx' => true, 'cy' => true, 'r' => true ),
			'ellipse'  => $common + array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ),
			'rect'     => $common + array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ),
			'line'     => $common + array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ),
			'polyline' => $common + array( 'points' => true ),
			'polygon'  => $common + array( 'points' => true ),
			'g'        => $common,
		);
	}
}
