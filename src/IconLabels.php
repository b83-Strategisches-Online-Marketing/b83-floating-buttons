<?php
/**
 * Übersetzbare Bezeichnungen und Gruppen der mitgelieferten Icons.
 * Wird automatisch zu IconLibrary erzeugt (Markennamen bleiben unübersetzt).
 *
 * @package B83\FloatingButtons
 */

declare(strict_types=1);

namespace B83\FloatingButtons;

defined( 'ABSPATH' ) || exit;

final class IconLabels {

	/** @var array<string,string>|null */
	private static ?array $map = null;

	/** Übersetzte Bezeichnung (unbekannte Texte bleiben unverändert). */
	public static function translate( string $text ): string {
		if ( null === self::$map ) {
			self::$map = self::map();
		}
		return self::$map[ $text ] ?? $text;
	}

	/** @return array<string,string> */
	private static function map(): array {
		return array(
			'Accessible' => __( 'Accessible', 'b83-floating-buttons' ),
			'Appointment / booking' => __( 'Appointment / booking', 'b83-floating-buttons' ),
			'Arrow up' => __( 'Arrow up', 'b83-floating-buttons' ),
			'Back to top' => __( 'Back to top', 'b83-floating-buttons' ),
			'Bell' => __( 'Bell', 'b83-floating-buttons' ),
			'Bicycle' => __( 'Bicycle', 'b83-floating-buttons' ),
			'Booking & shop' => __( 'Booking & shop', 'b83-floating-buttons' ),
			'Bus' => __( 'Bus', 'b83-floating-buttons' ),
			'Cable car' => __( 'Cable car', 'b83-floating-buttons' ),
			'Café' => __( 'Café', 'b83-floating-buttons' ),
			'Calendar' => __( 'Calendar', 'b83-floating-buttons' ),
			'Car' => __( 'Car', 'b83-floating-buttons' ),
			'Check mark' => __( 'Check mark', 'b83-floating-buttons' ),
			'Contact' => __( 'Contact', 'b83-floating-buttons' ),
			'Customer service' => __( 'Customer service', 'b83-floating-buttons' ),
			'Discount' => __( 'Discount', 'b83-floating-buttons' ),
			'Document / PDF' => __( 'Document / PDF', 'b83-floating-buttons' ),
			'Download' => __( 'Download', 'b83-floating-buttons' ),
			'Email' => __( 'Email', 'b83-floating-buttons' ),
			'External link' => __( 'External link', 'b83-floating-buttons' ),
			'General' => __( 'General', 'b83-floating-buttons' ),
			'Heart' => __( 'Heart', 'b83-floating-buttons' ),
			'Help' => __( 'Help', 'b83-floating-buttons' ),
			'Highlight' => __( 'Highlight', 'b83-floating-buttons' ),
			'Home' => __( 'Home', 'b83-floating-buttons' ),
			'Hotel' => __( 'Hotel', 'b83-floating-buttons' ),
			'Info' => __( 'Info', 'b83-floating-buttons' ),
			'Link' => __( 'Link', 'b83-floating-buttons' ),
			'Location' => __( 'Location', 'b83-floating-buttons' ),
			'Location & travel' => __( 'Location & travel', 'b83-floating-buttons' ),
			'Map' => __( 'Map', 'b83-floating-buttons' ),
			'Message' => __( 'Message', 'b83-floating-buttons' ),
			'Mountain' => __( 'Mountain', 'b83-floating-buttons' ),
			'Nature' => __( 'Nature', 'b83-floating-buttons' ),
			'Navigation' => __( 'Navigation', 'b83-floating-buttons' ),
			'Offer' => __( 'Offer', 'b83-floating-buttons' ),
			'Parking' => __( 'Parking', 'b83-floating-buttons' ),
			'People' => __( 'People', 'b83-floating-buttons' ),
			'Person' => __( 'Person', 'b83-floating-buttons' ),
			'Phone' => __( 'Phone', 'b83-floating-buttons' ),
			'Phone (call)' => __( 'Phone (call)', 'b83-floating-buttons' ),
			'Prices' => __( 'Prices', 'b83-floating-buttons' ),
			'Restaurant' => __( 'Restaurant', 'b83-floating-buttons' ),
			'Room' => __( 'Room', 'b83-floating-buttons' ),
			'Route' => __( 'Route', 'b83-floating-buttons' ),
			'Search' => __( 'Search', 'b83-floating-buttons' ),
			'Send' => __( 'Send', 'b83-floating-buttons' ),
			'Share' => __( 'Share', 'b83-floating-buttons' ),
			'Shop' => __( 'Shop', 'b83-floating-buttons' ),
			'Shopping cart' => __( 'Shopping cart', 'b83-floating-buttons' ),
			'Smartphone' => __( 'Smartphone', 'b83-floating-buttons' ),
			'Snow' => __( 'Snow', 'b83-floating-buttons' ),
			'Social media & brands' => __( 'Social media & brands', 'b83-floating-buttons' ),
			'Speech bubble' => __( 'Speech bubble', 'b83-floating-buttons' ),
			'Star' => __( 'Star', 'b83-floating-buttons' ),
			'Sun' => __( 'Sun', 'b83-floating-buttons' ),
			'Ticket' => __( 'Ticket', 'b83-floating-buttons' ),
			'Time / opening hours' => __( 'Time / opening hours', 'b83-floating-buttons' ),
			'Tourism & weather' => __( 'Tourism & weather', 'b83-floating-buttons' ),
			'Video' => __( 'Video', 'b83-floating-buttons' ),
			'Voucher' => __( 'Voucher', 'b83-floating-buttons' ),
			'Weather' => __( 'Weather', 'b83-floating-buttons' ),
			'Webcam / photo' => __( 'Webcam / photo', 'b83-floating-buttons' ),
			'Website / language' => __( 'Website / language', 'b83-floating-buttons' ),
		);
	}
}
