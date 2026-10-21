<?php
/**
 * Plugin Name:       b83 Floating Buttons
 * Plugin URI:        https://b83.at
 * Description:       Schwebende Buttons am linken, rechten, oberen oder unteren Rand jeder Seite – mit Font-Awesome-Icons, eigenen Icons oder Bildern, Ausklapp-Text mit Zusatztext bei Mouseover, Info-Text, Links, E-Mail, Telefon, WhatsApp und Chat. Frei gestaltbar und responsiv.
 * Version:           1.1.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            b83 – Wolfgang Grabs-Schrempf
 * Author URI:        https://b83.at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       b83-floating-buttons
 * Domain Path:       /languages
 * Update URI:        https://github.com/b83-Strategisches-Online-Marketing/b83-floating-buttons
 *
 * @package B83\FloatingButtons
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'B83_FB_VERSION', '1.1.0' );
define( 'B83_FB_FILE', __FILE__ );
define( 'B83_FB_DIR', plugin_dir_path( __FILE__ ) );
define( 'B83_FB_URL', plugin_dir_url( __FILE__ ) );

/*
 * PSR-4-Autoloader für den Namespace B83\FloatingButtons → src/.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'B83\\FloatingButtons\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = B83_FB_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( \B83\FloatingButtons\Migration::class, 'on_activation' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\B83\FloatingButtons\Plugin::instance()->boot();
	}
);
