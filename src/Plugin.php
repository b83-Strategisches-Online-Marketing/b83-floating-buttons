<?php

/**
 * Einstiegspunkt: verdrahtet alle Komponenten.
 *
 * @package B83\FloatingButtons
 */
declare (strict_types = 1);
namespace B83\FloatingButtons;

use B83\FloatingButtons\Admin\AdminPage;
use B83\FloatingButtons\Frontend\Renderer;
defined( 'ABSPATH' ) || exit;
final class Plugin {
    private static ?Plugin $instance = null;

    private Repository $repository;

    private Icons $icons;

    private Renderer $renderer;

    private bool $booted = false;

    public static function instance() : Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->repository = new Repository();
        $this->icons = new Icons();
        $this->renderer = new Renderer($this->repository, $this->icons);
    }

    public function boot() : void {
        if ( $this->booted ) {
            return;
        }
        $this->booted = true;
        add_action( 'init', array($this, 'load_textdomain') );
        add_action( 'admin_init', array(Migration::class, 'maybe_run') );
        add_action( 'b83_floating_buttons_saved', array(CachePurge::class, 'purge_all') );
        $this->renderer->register();
        if ( is_admin() ) {
            ( new AdminPage($this->repository, $this->icons, $this->renderer) )->register();
        }
        /**
         * Nach dem Laden aller Komponenten. Erweiterungen können sich hier einhängen.
         *
         * @param Plugin $plugin Instanz.
         */
        do_action( 'b83_floating_buttons_loaded', $this );
    }

    public function load_textdomain() : void {
        load_plugin_textdomain( 'b83-floating-buttons', false, dirname( plugin_basename( B83_FB_FILE ) ) . '/languages' );
    }

    public function repository() : Repository {
        return $this->repository;
    }

    public function icons() : Icons {
        return $this->icons;
    }

    public function renderer() : Renderer {
        return $this->renderer;
    }

}
