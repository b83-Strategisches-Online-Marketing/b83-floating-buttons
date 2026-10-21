<?php
/**
 * Ausgabe der schwebenden Buttons im Frontend.
 *
 * Die Gestaltung jeder Leiste wird als CSS-Variablen an das Stylesheet
 * angehängt (wp_add_inline_style, nur validierte Werte). Markup und Logik
 * sind für alle Leisten gleich, damit Theme-Anpassungen über feste
 * CSS-Klassen (b83-fb__…) möglich sind.
 *
 * @package B83\FloatingButtons
 */

declare(strict_types=1);

namespace B83\FloatingButtons\Frontend;

use B83\FloatingButtons\FontAwesome;
use B83\FloatingButtons\Icons;
use B83\FloatingButtons\Repository;
use B83\FloatingButtons\Sanitizer;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	public const HANDLE = 'b83-floating-buttons';

	/** @var array<int, array<string,mixed>>|null Für diesen Aufruf sichtbare Leisten. */
	private ?array $visible = null;

	public function __construct( private Repository $repository, private Icons $icons ) {}

	public function register(): void {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'output' ), 20 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/* ---------------------------------------------------------------------
	 * Sichtbarkeit
	 * ------------------------------------------------------------------- */

	/**
	 * Leisten, die auf der aktuellen Seite erscheinen.
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public function visible_groups(): array {
		if ( null !== $this->visible ) {
			return $this->visible;
		}

		$current = is_singular() || ( is_home() && ! is_front_page() ) ? (int) get_queried_object_id() : 0;
		$groups  = array();

		foreach ( $this->repository->groups() as $group ) {
			$show = $group['active']
				&& array() !== $this->active_items( $group )
				&& ( $group['show_desktop'] || $group['show_tablet'] || $group['show_mobile'] )
				&& ! ( $current > 0 && in_array( $current, (array) $group['exclude_ids'], true ) );

			/**
			 * Filtert, ob eine Leiste auf der aktuellen Seite angezeigt wird.
			 *
			 * @param bool                $show  Anzeigen.
			 * @param array<string,mixed> $group Leiste.
			 */
			if ( apply_filters( 'b83_floating_buttons_show_group', $show, $group ) ) {
				$groups[] = $group;
			}
		}

		$this->visible = $groups;
		return $groups;
	}

	/**
	 * @param array<string,mixed> $group Leiste.
	 * @return array<int, array<string,mixed>>
	 */
	private function active_items( array $group ): array {
		return array_values( array_filter( (array) $group['items'], static fn( $item ) => ! empty( $item['active'] ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Frontend
	 * ------------------------------------------------------------------- */

	public function enqueue(): void {
		$groups = $this->visible_groups();
		if ( array() === $groups ) {
			return;
		}
		wp_enqueue_style( self::HANDLE, B83_FB_URL . 'assets/css/frontend.css', array(), B83_FB_VERSION );
		wp_add_inline_style( self::HANDLE, $this->css( $groups ) );
		wp_enqueue_script( self::HANDLE, B83_FB_URL . 'assets/js/frontend.js', array(), B83_FB_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}

	public function output(): void {
		foreach ( $this->visible_groups() as $group ) {
			echo $this->group_html( $group ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in group_html() escaped.
		}
	}

	/**
	 * Unten-Leiste auf Mobilgeräten: Platz am Seitenende freihalten.
	 *
	 * @param string[] $classes Body-Klassen.
	 * @return string[]
	 */
	public function body_class( array $classes ): array {
		foreach ( $this->visible_groups() as $group ) {
			if ( 'bar' === $group['mobile_layout'] && $group['show_mobile'] ) {
				$classes[] = 'b83-fb-has-bar';
				break;
			}
		}
		return $classes;
	}

	/* ---------------------------------------------------------------------
	 * Markup
	 * ------------------------------------------------------------------- */

	/** @param array<string,mixed> $group Leiste. */
	public function group_html( array $group ): string {
		$items = $this->active_items( $group );
		if ( array() === $items ) {
			return '';
		}

		$classes = array(
			'b83-fb',
			'b83-fb--' . $group['position'],
			'b83-fb--align-' . $group['align'],
			'b83-fb--shadow-' . $group['shadow'],
			'b83-fb--enter-' . $group['entrance'],
			'b83-fb--m-' . $group['mobile_layout'],
		);
		if ( 0 === (int) $group['edge_offset'] ) {
			$classes[] = 'b83-fb--flush';
		}
		if ( ! $group['tooltips'] ) {
			$classes[] = 'b83-fb--no-tips';
		}
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( ! $group[ 'show_' . $device ] ) {
				$classes[] = 'b83-fb--hide-' . $device;
			}
		}

		$html  = sprintf(
			'<nav class="%1$s" id="b83-fb-%2$s" data-b83-fb="%2$s" data-mobile="%3$s" aria-label="%4$s">',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( (string) $group['id'] ),
			esc_attr( (string) $group['mobile_layout'] ),
			esc_attr( (string) $group['name'] )
		);
		$html .= '<ul class="b83-fb__list">';
		foreach ( $items as $item ) {
			$html .= $this->item_html( $item, $group );
		}
		$html .= '</ul></nav>';

		/**
		 * Filtert das HTML einer Leiste.
		 *
		 * @param string              $html  HTML.
		 * @param array<string,mixed> $group Leiste.
		 */
		return (string) apply_filters( 'b83_floating_buttons_group_html', $html, $group );
	}

	/**
	 * @param array<string,mixed> $item  Button.
	 * @param array<string,mixed> $group Leiste.
	 */
	private function item_html( array $item, array $group ): string {
		$id      = (string) $item['id'];
		$label   = (string) $item['label'];
		$sub     = (string) ( $item['sublabel'] ?? '' );
		// Zusatztext ohne Überschrift wird selbst zur Überschrift.
		if ( '' === $label && '' !== $sub ) {
			$label = $sub;
			$sub   = '';
		}
		$tooltip = $group['tooltips'] ? (string) $item['tooltip'] : '';
		$link    = $this->link( $item );

		$attrs = array( 'class' => 'b83-fb__btn' );
		if ( '' !== $tooltip ) {
			$attrs['aria-describedby'] = 'b83-fb-tip-' . $id;
		}

		if ( null !== $link ) {
			$tag           = 'a';
			$attrs['href'] = $link['href'];
			if ( $link['blank'] ) {
				$attrs['target'] = '_blank';
			}
			if ( '' !== $link['rel'] ) {
				$attrs['rel'] = $link['rel'];
			}
			if ( 'top' === $item['link_type'] ) {
				$attrs['data-b83-fb-top'] = '1';
			}
		} else {
			$tag               = 'span';
			$attrs['tabindex'] = '0';
		}

		// Zugänglicher Name: sichtbarer Text, sonst Info-Text bzw. Icon-Bezeichnung.
		$name = '' !== $sub ? $label . ' – ' . $sub : $label;
		if ( '' === $name ) {
			$name = '' !== (string) $item['tooltip'] ? (string) $item['tooltip'] : $this->icon_label( $item );
		}

		$classes = 'b83-fb__item';
		if ( '' === $label ) {
			$classes .= ' b83-fb__item--icon-only';
		} elseif ( '' !== $sub ) {
			$classes .= ' b83-fb__item--has-sub';
		}
		$html = '<li class="' . $classes . '" data-b83-fb-item="' . esc_attr( $id ) . '">';

		$html .= '<' . $tag;
		foreach ( $attrs as $attr => $value ) {
			$html .= ' ' . $attr . '="' . ( 'href' === $attr ? esc_url( $value, $this->protocols() ) : esc_attr( $value ) ) . '"';
		}
		$html .= '>';

		$html .= '<span class="b83-fb__icon">' . $this->media( $item ) . '</span>';
		if ( '' !== $label ) {
			$html .= '<span class="b83-fb__label"><span class="b83-fb__label-text">';
			$html .= '<span class="b83-fb__title">' . esc_html( $label ) . '</span>';
			if ( '' !== $sub ) {
				$html .= '<span class="b83-fb__sub">' . esc_html( $sub ) . '</span>';
			}
			$html .= '</span></span>';
		} else {
			$html .= '<span class="b83-fb__sr">' . esc_html( $name ) . '</span>';
		}
		if ( null !== $link && $link['blank'] ) {
			$html .= '<span class="b83-fb__sr">' . esc_html__( '(öffnet in neuem Fenster)', 'b83-floating-buttons' ) . '</span>';
		}
		if ( '' !== $tooltip ) {
			$html .= '<span class="b83-fb__tip" role="tooltip" id="b83-fb-tip-' . esc_attr( $id ) . '">' . esc_html( $tooltip ) . '</span>';
		}

		$html .= '</' . $tag . '></li>';

		/**
		 * Filtert das HTML eines Buttons.
		 *
		 * @param string              $html  HTML.
		 * @param array<string,mixed> $item  Button.
		 * @param array<string,mixed> $group Leiste.
		 */
		return (string) apply_filters( 'b83_floating_buttons_item_html', $html, $item, $group );
	}

	/** @param array<string,mixed> $item Button. */
	private function media( array $item ): string {
		if ( 'image' === $item['icon_type'] && (int) $item['image_id'] > 0 ) {
			$img = wp_get_attachment_image(
				(int) $item['image_id'],
				'thumbnail',
				false,
				array(
					'class'    => 'b83-fb__img',
					'alt'      => '',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
			if ( '' !== $img ) {
				return $img;
			}
		}
		if ( 'fa' === $item['icon_type'] && '' !== (string) $item['fa_path'] ) {
			return FontAwesome::svg( (int) $item['fa_width'], (string) $item['fa_path'] );
		}
		return $this->icons->svg( (string) $item['icon'] );
	}

	/** @param array<string,mixed> $item Button. */
	private function icon_label( array $item ): string {
		if ( 'fa' === $item['icon_type'] && '' !== (string) $item['fa_label'] ) {
			return (string) $item['fa_label'];
		}
		$all = $this->icons->all();
		return (string) ( $all[ (string) $item['icon'] ]['label'] ?? '' );
	}

	/**
	 * Link-Ziel und -Attribute eines Buttons; null = kein Link.
	 *
	 * @param array<string,mixed> $item Button.
	 * @return array{href:string,blank:bool,rel:string}|null
	 */
	public function link( array $item ): ?array {
		$href     = '';
		$external = false;

		switch ( $item['link_type'] ) {
			case 'url':
				$href     = (string) $item['url'];
				$external = '' !== $href && ! str_starts_with( $href, '#' ) && ! str_starts_with( $href, '/' );
				break;
			case 'email':
				if ( '' !== $item['email'] ) {
					$href = 'mailto:' . $item['email'];
					if ( '' !== $item['email_subject'] ) {
						$href .= '?subject=' . rawurlencode( (string) $item['email_subject'] );
					}
				}
				break;
			case 'phone':
				$href = '' !== $item['phone'] ? 'tel:' . $item['phone'] : '';
				break;
			case 'sms':
				$href = '' !== $item['phone'] ? 'sms:' . $item['phone'] : '';
				break;
			case 'whatsapp':
				if ( '' !== $item['whatsapp'] ) {
					$href     = 'https://wa.me/' . $item['whatsapp'];
					$external = true;
					if ( '' !== $item['whatsapp_text'] ) {
						$href .= '?text=' . rawurlencode( (string) $item['whatsapp_text'] );
					}
				}
				break;
			case 'chat':
				$href     = (string) $item['chat_url'];
				$external = str_starts_with( $href, 'http' );
				break;
			case 'top':
				$href = '#';
				break;
		}

		if ( '' === $href ) {
			return null;
		}

		// Neues Fenster nur für echte Webseiten sinnvoll (nicht für tel:, mailto:, Sprungmarken).
		$blank = $external && ! empty( $item['target_blank'] );
		$rel   = array();
		if ( $blank ) {
			$rel[] = 'noopener';
		}
		if ( $external && ! empty( $item['nofollow'] ) ) {
			$rel[] = 'nofollow';
		}

		/**
		 * Filtert Link-Ziel und -Attribute eines Buttons.
		 *
		 * @param array{href:string,blank:bool,rel:string} $link Link.
		 * @param array<string,mixed>                      $item Button.
		 */
		return (array) apply_filters(
			'b83_floating_buttons_link',
			array(
				'href'  => $href,
				'blank' => $blank,
				'rel'   => implode( ' ', $rel ),
			),
			$item
		) + array(
			'href'  => '',
			'blank' => false,
			'rel'   => '',
		);
	}

	/** @return string[] Erlaubte Link-Protokolle. */
	private function protocols(): array {
		return array_merge( array( 'http', 'https', 'mailto', 'tel', 'sms' ), Sanitizer::CHAT_PROTOCOLS );
	}

	/* ---------------------------------------------------------------------
	 * Gestaltung (CSS-Variablen)
	 * ------------------------------------------------------------------- */

	/**
	 * CSS-Variablen aller Leisten und abweichender Button-Farben.
	 * Alle Werte sind validiert (Zahlen, Hex-Farben).
	 *
	 * @param array<int, array<string,mixed>> $groups Leisten.
	 */
	public function css( array $groups ): string {
		$css = '';
		foreach ( $groups as $group ) {
			$id   = preg_replace( '/[^a-z0-9]/', '', (string) $group['id'] );
			$vars = array(
				'--b83fb-size'      => (int) $group['size'] . 'px',
				'--b83fb-size-m'    => (int) $group['mobile_size'] . 'px',
				'--b83fb-icon'      => (int) $group['icon_size'] / 100,
				'--b83fb-radius'    => (int) $group['radius'] . 'px',
				'--b83fb-gap'       => (int) $group['gap'] . 'px',
				'--b83fb-edge'      => (int) $group['edge_offset'] . 'px',
				'--b83fb-offset'    => (int) $group['align_offset'] . 'px',
				'--b83fb-peek'      => (int) $group['peek'] . '%',
				'--b83fb-font'      => (int) $group['font_size'] . 'px',
				'--b83fb-sub'       => (int) $group['sub_size'] . 'px',
				'--b83fb-weight'    => (int) $group['font_weight'],
				'--b83fb-delay'     => (int) $group['delay'] . 'ms',
				'--b83fb-dur'       => (int) $group['duration'] . 'ms',
				'--b83fb-z'         => (int) $group['z_index'],
				'--b83fb-bg'        => $group['bg'],
				'--b83fb-color'     => $group['color'],
				'--b83fb-text'      => $group['text_color'],
				'--b83fb-bg-h'      => $group['bg_hover'],
				'--b83fb-color-h'   => $group['color_hover'],
				'--b83fb-text-h'    => $group['text_hover'],
				'--b83fb-tip-bg'    => $group['tip_bg'],
				'--b83fb-tip-color' => $group['tip_color'],
			);
			$css .= '#b83-fb-' . $id . '{' . $this->declarations( $vars ) . '}';

			foreach ( (array) $group['items'] as $item ) {
				$over = array();
				$map  = array(
					'bg'          => '--b83fb-bg',
					'color'       => '--b83fb-color',
					'bg_hover'    => '--b83fb-bg-h',
					'color_hover' => '--b83fb-color-h',
				);
				foreach ( $map as $key => $var ) {
					if ( ! empty( $item[ $key ] ) ) {
						$over[ $var ] = $item[ $key ];
					}
				}
				// Eigener Hintergrund ohne eigene Hover-Farbe: beim Drüberfahren gleich bleiben (z. B. WhatsApp-Grün).
				if ( isset( $over['--b83fb-bg'] ) && ! isset( $over['--b83fb-bg-h'] ) ) {
					$over['--b83fb-bg-h'] = $over['--b83fb-bg'];
				}
				if ( isset( $over['--b83fb-color'] ) && ! isset( $over['--b83fb-color-h'] ) ) {
					$over['--b83fb-color-h'] = $over['--b83fb-color'];
				}
				// Eigene Icon-Farbe gilt auch für den Text.
				if ( isset( $over['--b83fb-color'] ) ) {
					$over['--b83fb-text'] = $over['--b83fb-color'];
				}
				if ( isset( $over['--b83fb-color-h'] ) ) {
					$over['--b83fb-text-h'] = $over['--b83fb-color-h'];
				}
				if ( array() !== $over ) {
					$item_id = preg_replace( '/[^a-z0-9]/', '', (string) $item['id'] );
					$css    .= '#b83-fb-' . $id . ' [data-b83-fb-item="' . $item_id . '"]{' . $this->declarations( $over ) . '}';
				}
			}
		}
		return $css;
	}

	/** @param array<string, string|int|float> $vars Variablen. */
	private function declarations( array $vars ): string {
		$out = '';
		foreach ( $vars as $name => $value ) {
			$value = (string) $value;
			// Letzte Absicherung: nur Zahlen, Einheiten, Prozent und Hex-Farben.
			if ( 1 === preg_match( '/^(?:#[0-9a-f]{3,8}|-?[0-9.]+(?:px|ms|%)?)$/', $value ) ) {
				$out .= $name . ':' . $value . ';';
			}
		}
		return $out;
	}
}
