<?php
/**
 * Deinstallation. Daten werden nur entfernt, wenn das in den Einstellungen
 * ausdrücklich aktiviert wurde – sonst bleiben die Leisten erhalten.
 *
 * @package B83\FloatingButtons
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$b83_fb_settings = get_option( 'b83_floating_buttons_settings', array() );

if ( is_array( $b83_fb_settings ) && ! empty( $b83_fb_settings['delete_on_uninstall'] ) ) {
	foreach ( array(
		'b83_floating_buttons_groups',
		'b83_floating_buttons_settings',
		'b83_floating_buttons_db_version',
	) as $b83_fb_option ) {
		delete_option( $b83_fb_option );
	}
	delete_transient( 'b83_floating_buttons_release' );
}
