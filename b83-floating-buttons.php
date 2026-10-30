<?php

/**
 * Plugin Name:       b83 Floating Buttons
 * Description:       Floating buttons on the left, right, top or bottom edge of every page, with Font Awesome icons, own icons or images, expanding text on hover, info texts, links, email, phone, WhatsApp and chat. Fully customisable and responsive.
 * Version:           2.0.1
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            b83 – Wolfgang Grabs-Schrempf
 * Author URI:        https://b83.at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       b83-floating-buttons
 * Domain Path:       /languages
 *
 *
 * @package B83\FloatingButtons
 */
declare (strict_types = 1);
defined( 'ABSPATH' ) || exit;
/*
 * Freemius: Lizenzen, Testphase, Updates der Pro-Version.
 * Ist bereits die andere Ausgabe (Free/Pro) geladen, übernimmt deren Instanz.
 * Aufbau nach Freemius-Vorgabe; beim Erzeugen der Gratisversion setzt Freemius
 * den ersten Parameter von set_basename() selbst auf false.
 */
if ( function_exists( 'b83fb_fs' ) ) {
    b83fb_fs()->set_basename( false, __FILE__ );
    return;
} else {
    /*
     * DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE
     * `function_exists` CALL ABOVE TO PROPERLY WORK.
     */
    if ( !function_exists( 'b83fb_fs' ) ) {
        /**
         * Freemius-Instanz des Plugins.
         *
         * @return Freemius
         */
        function b83fb_fs() {
            global $b83fb_fs;
            if ( !isset( $b83fb_fs ) ) {
                require_once __DIR__ . '/vendor/freemius/start.php';
                $b83fb_fs = fs_dynamic_init( array(
                    'id'               => '43519',
                    'slug'             => 'b83-floating-buttons',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_daacdb179b62e5f47839edbcd34a1',
                    'is_premium'       => false,
                    'premium_suffix'   => 'Pro',
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'is_org_compliant' => true,
                    'trial'            => array(
                        'days'               => 30,
                        'is_require_payment' => false,
                    ),
                    'menu'             => array(
                        'slug'        => 'b83-floating-buttons',
                        'parent'      => array(
                            'slug' => 'b83',
                        ),
                        'first-path'  => 'admin.php?page=b83-floating-buttons',
                        'account'     => false,
                        'pricing'     => false,
                        'contact'     => false,
                        'support'     => false,
                        'affiliation' => false,
                        'addons'      => false,
                    ),
                    'is_live'          => true,
                ) );
            }
            return $b83fb_fs;
        }

        b83fb_fs();
        // Eigenes Icon für Anmelde-, Konto- und Preisseite von Freemius.
        b83fb_fs()->add_filter( 'plugin_icon', static fn(): string => __DIR__ . '/assets/img/b83-floating-buttons-icon.png' );
        do_action( 'b83fb_fs_loaded' );
    }
}
define( 'B83_FB_VERSION', '2.0.1' );
define( 'B83_FB_FILE', __FILE__ );
define( 'B83_FB_DIR', plugin_dir_path( __FILE__ ) );
define( 'B83_FB_URL', plugin_dir_url( __FILE__ ) );
/*
 * PSR-4-Autoloader für den Namespace B83\FloatingButtons → src/.
 */
spl_autoload_register( static function ( string $class ) : void {
    $prefix = 'B83\\FloatingButtons\\';
    if ( !str_starts_with( $class, $prefix ) ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    $file = B83_FB_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
    if ( is_readable( $file ) ) {
        require_once $file;
    }
} );
register_activation_hook( __FILE__, array(\B83\FloatingButtons\Migration::class, 'on_activation') );
// Aufräumen beim Löschen: über Freemius statt uninstall.php, damit Freemius die Deinstallation erfasst.
b83fb_fs()->add_action( 'after_uninstall', array(\B83\FloatingButtons\Migration::class, 'uninstall') );
add_action( 'plugins_loaded', static function () : void {
    \B83\FloatingButtons\Plugin::instance()->boot();
} );