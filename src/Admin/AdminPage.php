<?php
/**
 * Backend: Leisten verwalten, Buttons bearbeiten, Live-Vorschau,
 * Einstellungen sowie Export und Import.
 *
 * Alle schreibenden Aktionen laufen über admin-post.php bzw. admin-ajax.php
 * mit Berechtigungs- und Nonce-Prüfung (guard()).
 *
 * @package B83\FloatingButtons
 */

declare(strict_types=1);

namespace B83\FloatingButtons\Admin;

use B83\FloatingButtons\CachePurge;
use B83\FloatingButtons\Frontend\Renderer;
use B83\FloatingButtons\Icons;
use B83\FloatingButtons\Migration;
use B83\FloatingButtons\Repository;
use B83\FloatingButtons\Sanitizer;

defined( 'ABSPATH' ) || exit;

final class AdminPage {

	public const SLUG = 'b83-floating-buttons';

	private const MAX_IMPORT_BYTES = 1048576;

	private string $hook = '';

	public function __construct( private Repository $repository, private Icons $icons, private Renderer $renderer ) {}

	public function register(): void {
		( new B83Menu( B83_FB_URL, B83_FB_DIR ) )->register();

		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'b83_admin_plugin_links', array( $this, 'overview_link' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( B83_FB_FILE ), array( $this, 'action_links' ) );

		foreach ( array( 'save', 'delete', 'toggle', 'duplicate', 'settings', 'export', 'import' ) as $action ) {
			add_action( 'admin_post_b83_fb_' . $action, array( $this, 'handle_' . $action ) );
		}
		add_action( 'wp_ajax_b83_fb_preview', array( $this, 'ajax_preview' ) );
	}

	/** Berechtigung für alle Aktionen dieses Plugins. */
	public static function capability(): string {
		/**
		 * Filtert die Capability zum Verwalten der Floating Buttons.
		 *
		 * @param string $capability Standard: manage_options.
		 */
		return (string) apply_filters( 'b83_floating_buttons_capability', 'manage_options' );
	}

	public function add_menu(): void {
		$this->hook = (string) add_submenu_page(
			B83Menu::SLUG,
			__( 'Floating Buttons', 'b83-floating-buttons' ),
			__( 'Floating Buttons', 'b83-floating-buttons' ),
			self::capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * @param array<string,string> $links Links für die b83-Übersicht.
	 * @return array<string,string>
	 */
	public function overview_link( array $links ): array {
		$links[ plugin_basename( B83_FB_FILE ) ] = self::url();
		return $links;
	}

	/**
	 * @param string[] $links Links in der Plugin-Liste.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Einstellungen', 'b83-floating-buttons' ) . '</a>' );
		return $links;
	}

	/** @param array<string,string> $args Zusätzliche Parameter. */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Zurück zur Seite mit festem Hinweis-Schlüssel.
	 *
	 * @param string               $notice Hinweis-Schlüssel (siehe notices()).
	 * @param array<string,string> $args   Weitere Parameter.
	 */
	private static function redirect( string $notice, array $args = array() ): void {
		wp_safe_redirect( self::url( array_merge( $args, array( 'b83_fb_notice' => $notice ) ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------- */

	public function enqueue( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'b83-fb-admin', B83_FB_URL . 'assets/css/admin.css', array( 'wp-color-picker' ), B83_FB_VERSION );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Ansicht.
		if ( ! empty( $_GET['edit'] ) ) {
			wp_enqueue_media();
		}
		wp_enqueue_script( 'b83-fb-admin', B83_FB_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ), B83_FB_VERSION, true );
		wp_localize_script(
			'b83-fb-admin',
			'b83FbAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'b83_fb_preview' ),
				'i18n'    => array(
					'mediaTitle'  => __( 'Grafik für den Button wählen', 'b83-floating-buttons' ),
					'mediaButton' => __( 'Grafik verwenden', 'b83-floating-buttons' ),
					'confirmDel'  => __( 'Diesen Button entfernen?', 'b83-floating-buttons' ),
					'noLabel'     => __( '(ohne Text)', 'b83-floating-buttons' ),
					'maxItems'    => sprintf(
						/* translators: %d: Höchstzahl */
						__( 'Höchstens %d Buttons je Leiste.', 'b83-floating-buttons' ),
						Sanitizer::MAX_ITEMS
					),
					'previewErr'  => __( 'Vorschau konnte nicht geladen werden.', 'b83-floating-buttons' ),
				),
				'maxItems' => Sanitizer::MAX_ITEMS,
				'linkTypes' => array(
					'url'      => __( 'Link', 'b83-floating-buttons' ),
					'phone'    => __( 'Telefon', 'b83-floating-buttons' ),
					'email'    => __( 'E-Mail', 'b83-floating-buttons' ),
					'whatsapp' => __( 'WhatsApp', 'b83-floating-buttons' ),
					'sms'      => __( 'SMS', 'b83-floating-buttons' ),
					'top'      => __( 'Nach oben', 'b83-floating-buttons' ),
					'none'     => __( 'Nur Info', 'b83-floating-buttons' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Aktionen
	 * ------------------------------------------------------------------- */

	private function guard( string $nonce_action ): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-floating-buttons' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	private function posted_id(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce wird in guard() geprüft.
		$id = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		return 1 === preg_match( '/^g[0-9a-f]{8}$/', $id ) ? $id : '';
	}

	/**
	 * Leistendaten aus dem Formular.
	 *
	 * @return array<string,mixed>
	 */
	private function posted_group(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce in guard()/ajax_preview(), Validierung in Sanitizer::group().
		$raw = isset( $_POST['group'] ) && is_array( $_POST['group'] ) ? wp_unslash( $_POST['group'] ) : array();
		return Sanitizer::group( (array) $raw, $this->icons );
	}

	public function handle_save(): void {
		$this->guard( 'b83_fb_save' );
		$group = $this->posted_group();

		// Bestehende Leiste: ID muss existieren, sonst wäre es eine neue.
		$existing = $this->repository->group( (string) $group['id'] );
		if ( null === $existing && count( $this->repository->groups() ) >= Sanitizer::MAX_GROUPS ) {
			self::redirect( 'limit' );
		}
		if ( ! $this->repository->save_group( $group ) ) {
			self::redirect( 'limit' );
		}
		self::redirect( 'saved', array( 'edit' => (string) $group['id'] ) );
	}

	public function handle_delete(): void {
		$this->guard( 'b83_fb_delete' );
		$this->repository->delete_group( $this->posted_id() );
		self::redirect( 'deleted' );
	}

	public function handle_toggle(): void {
		$this->guard( 'b83_fb_toggle' );
		$group = $this->repository->group( $this->posted_id() );
		if ( null === $group ) {
			self::redirect( 'not_found' );
		}
		$group['active'] = ! $group['active'];
		$this->repository->save_group( Sanitizer::group( $group, $this->icons ) );
		self::redirect( $group['active'] ? 'activated' : 'deactivated' );
	}

	public function handle_duplicate(): void {
		$this->guard( 'b83_fb_duplicate' );
		$group = $this->repository->group( $this->posted_id() );
		if ( null === $group ) {
			self::redirect( 'not_found' );
		}
		$group = $this->fresh_ids( $group );
		/* translators: %s: Name der Leiste */
		$group['name']   = mb_substr( sprintf( __( '%s (Kopie)', 'b83-floating-buttons' ), $group['name'] ), 0, 80 );
		$group['active'] = false;
		if ( ! $this->repository->save_group( Sanitizer::group( $group, $this->icons ) ) ) {
			self::redirect( 'limit' );
		}
		self::redirect( 'duplicated' );
	}

	public function handle_settings(): void {
		$this->guard( 'b83_fb_settings' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce in guard(), Validierung in Sanitizer::settings().
		$raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$this->repository->save_settings( Sanitizer::settings( (array) $raw ) );
		self::redirect( 'settings', array( 'tab' => 'settings' ) );
	}

	/** Alle Leisten als JSON-Datei herunterladen. */
	public function handle_export(): void {
		$this->guard( 'b83_fb_export' );

		$groups = $this->repository->groups();
		foreach ( $groups as &$group ) {
			foreach ( $group['items'] as &$item ) {
				// Zur Orientierung: Adresse der eigenen Grafik (wird beim Import nicht geladen).
				$item['image_url'] = $item['image_id'] ? (string) wp_get_attachment_url( (int) $item['image_id'] ) : '';
			}
			unset( $item );
		}
		unset( $group );

		$data = array(
			'format'   => Migration::EXPORT_FORMAT,
			'version'  => B83_FB_VERSION,
			'site'     => home_url( '/' ),
			'exported' => gmdate( 'c' ),
			'groups'   => $groups,
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="b83-floating-buttons-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-Download.
		exit;
	}

	public function handle_import(): void {
		$this->guard( 'b83_fb_import' );
		$args = array( 'tab' => 'settings' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Datei wird unten geprüft.
		$file = $_FILES['import_file'] ?? null;
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			self::redirect( 'import_error', $args );
		}
		$name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
		if ( (int) $file['size'] > self::MAX_IMPORT_BYTES || 'json' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			self::redirect( 'import_error', $args );
		}

		$decoded = json_decode( (string) file_get_contents( (string) $file['tmp_name'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- hochgeladene Datei.
		$groups  = is_array( $decoded ) ? Migration::normalize_import( $decoded ) : null;
		if ( null === $groups ) {
			self::redirect( 'import_error', $args );
		}

		// Grafiken aus einer anderen Website gibt es hier nicht (gleiche ID wäre ein anderes Bild).
		$same_site = ( $decoded['site'] ?? '' ) === home_url( '/' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce in guard().
		$replace = isset( $_POST['import_mode'] ) && 'replace' === sanitize_key( wp_unslash( $_POST['import_mode'] ) );

		$clean = array();
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			if ( ! $replace ) {
				$group = $this->fresh_ids( $group );
			}
			if ( ! $same_site ) {
				foreach ( (array) ( $group['items'] ?? array() ) as $index => $item ) {
					if ( is_array( $item ) ) {
						$group['items'][ $index ]['image_id']  = 0;
						$group['items'][ $index ]['icon_type'] = 'icon';
					}
				}
			}
			$clean[] = Sanitizer::group( $group, $this->icons );
		}

		$all = $replace ? $clean : array_merge( $this->repository->groups(), $clean );
		if ( count( $all ) > Sanitizer::MAX_GROUPS ) {
			self::redirect( 'limit', $args );
		}
		$this->repository->replace_groups( $all );
		self::redirect( 'imported', $args );
	}

	/**
	 * Neue IDs für Leiste und Buttons (Duplikat, Import als Ergänzung).
	 *
	 * @param array<mixed> $group Leiste.
	 * @return array<mixed>
	 */
	private function fresh_ids( array $group ): array {
		$group['id'] = '';
		foreach ( (array) ( $group['items'] ?? array() ) as $index => $item ) {
			if ( is_array( $item ) ) {
				$group['items'][ $index ]['id'] = '';
			}
		}
		return $group;
	}

	/* ---------------------------------------------------------------------
	 * Live-Vorschau
	 * ------------------------------------------------------------------- */

	public function ajax_preview(): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( 'b83_fb_preview', 'nonce' );

		$group = $this->posted_group();
		// In der Vorschau immer zeigen, auch wenn die Leiste deaktiviert ist.
		$group['active'] = true;

		wp_send_json_success( array( 'doc' => $this->preview_document( $group ) ) );
	}

	/**
	 * Vollständiges HTML-Dokument für das Vorschau-iframe (srcdoc).
	 * Dieselbe Ausgabe und dieselben Dateien wie im Frontend.
	 *
	 * @param array<string,mixed> $group Validierte Leiste.
	 */
	private function preview_document( array $group ): string {
		$css  = $this->renderer->css( array( $group ) );
		$html = $this->renderer->group_html( $group );
		$bar  = 'bar' === $group['mobile_layout'] && $group['show_mobile'] ? ' b83-fb-has-bar' : '';

		$doc  = '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		$doc .= '<link rel="stylesheet" href="' . esc_url( B83_FB_URL . 'assets/css/preview-page.css?ver=' . B83_FB_VERSION ) . '">';
		$doc .= '<link rel="stylesheet" href="' . esc_url( B83_FB_URL . 'assets/css/frontend.css?ver=' . B83_FB_VERSION ) . '">';
		$doc .= '<style>' . $css . '</style></head><body class="b83-fb-preview-page' . esc_attr( $bar ) . '">';
		$doc .= '<header class="pp-head"><span class="pp-logo"></span><span class="pp-nav"></span><span class="pp-nav"></span><span class="pp-nav"></span></header>';
		$doc .= '<main class="pp-main"><div class="pp-hero"></div><div class="pp-line w80"></div><div class="pp-line w60"></div><div class="pp-line w70"></div>';
		$doc .= '<div class="pp-cols"><div></div><div></div><div></div></div><div class="pp-line w50"></div><div class="pp-line w75"></div><div class="pp-line w40"></div></main>';
		$doc .= $html;
		$doc .= '<script src="' . esc_url( B83_FB_URL . 'assets/js/frontend.js?ver=' . B83_FB_VERSION ) . '"></script>';
		$doc .= '<script src="' . esc_url( B83_FB_URL . 'assets/js/preview.js?ver=' . B83_FB_VERSION ) . '"></script>';
		$doc .= '</body></html>';
		return $doc;
	}

	/* ---------------------------------------------------------------------
	 * Seite
	 * ------------------------------------------------------------------- */

	public function render(): void {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-floating-buttons' ), '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nur Ansicht.
		$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) && 'settings' === $_GET['tab'] ? 'settings' : 'groups';
		// phpcs:enable

		echo '<div class="wrap b83-fb-admin">';

		if ( '' !== $edit ) {
			$this->notices();
			$this->render_edit( $edit );
			echo '</div>';
			return;
		}

		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Floating Buttons', 'b83-floating-buttons' ) . '</h1>';
		if ( 'groups' === $tab && count( $this->repository->groups() ) < Sanitizer::MAX_GROUPS ) {
			echo ' <a class="page-title-action" href="' . esc_url( self::url( array( 'edit' => 'new' ) ) ) . '">' . esc_html__( 'Neue Leiste', 'b83-floating-buttons' ) . '</a>';
		}
		echo '<hr class="wp-header-end">';
		$this->notices();

		echo '<nav class="nav-tab-wrapper">';
		printf( '<a class="nav-tab %1$s" href="%2$s">%3$s</a>', 'groups' === $tab ? 'nav-tab-active' : '', esc_url( self::url() ), esc_html__( 'Leisten', 'b83-floating-buttons' ) );
		printf( '<a class="nav-tab %1$s" href="%2$s">%3$s</a>', 'settings' === $tab ? 'nav-tab-active' : '', esc_url( self::url( array( 'tab' => 'settings' ) ) ), esc_html__( 'Einstellungen & Export', 'b83-floating-buttons' ) );
		echo '</nav>';

		if ( 'settings' === $tab ) {
			$this->render_settings();
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	private function notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- fester Schlüssel, kein Freitext.
		$key = isset( $_GET['b83_fb_notice'] ) ? sanitize_key( wp_unslash( $_GET['b83_fb_notice'] ) ) : '';
		if ( '' === $key ) {
			return;
		}
		$cache   = CachePurge::detected_plugin();
		$notices = array(
			'saved'        => array( 'success', '' !== $cache && $this->repository->settings()['purge_cache']
				/* translators: %s: Name des Cache-Plugins */
				? sprintf( __( 'Leiste gespeichert. Der Cache von %s wurde geleert.', 'b83-floating-buttons' ), $cache )
				: __( 'Leiste gespeichert.', 'b83-floating-buttons' ) ),
			'deleted'      => array( 'success', __( 'Leiste gelöscht.', 'b83-floating-buttons' ) ),
			'activated'    => array( 'success', __( 'Leiste aktiviert.', 'b83-floating-buttons' ) ),
			'deactivated'  => array( 'success', __( 'Leiste deaktiviert.', 'b83-floating-buttons' ) ),
			'duplicated'   => array( 'success', __( 'Leiste dupliziert. Die Kopie ist noch deaktiviert.', 'b83-floating-buttons' ) ),
			'settings'     => array( 'success', __( 'Einstellungen gespeichert.', 'b83-floating-buttons' ) ),
			'imported'     => array( 'success', __( 'Import abgeschlossen. Eigene Grafiken aus anderen Websites bitte neu auswählen.', 'b83-floating-buttons' ) ),
			'import_error' => array( 'error', __( 'Import fehlgeschlagen: Bitte eine Exportdatei dieses Plugins (.json, höchstens 1 MB) wählen.', 'b83-floating-buttons' ) ),
			/* translators: %d: Höchstzahl */
			'limit'        => array( 'error', sprintf( __( 'Es sind höchstens %d Leisten möglich.', 'b83-floating-buttons' ), Sanitizer::MAX_GROUPS ) ),
			'not_found'    => array( 'error', __( 'Die Leiste wurde nicht gefunden.', 'b83-floating-buttons' ) ),
		);
		if ( ! isset( $notices[ $key ] ) ) {
			return;
		}
		[ $type, $text ] = $notices[ $key ];
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $text ) );
	}

	/* ---------------------------------------------------------------------
	 * Übersicht
	 * ------------------------------------------------------------------- */

	private function render_list(): void {
		$groups = $this->repository->groups();

		if ( array() === $groups ) {
			echo '<div class="b83-fb-empty">';
			echo '<h2>' . esc_html__( 'Noch keine Leiste angelegt', 'b83-floating-buttons' ) . '</h2>';
			echo '<p>' . esc_html__( 'Eine Leiste ist eine Gruppe schwebender Buttons an einem Bildschirmrand – zum Beispiel Telefon, E-Mail und Anfahrt rechts in der Mitte. Pro Rand können Sie eine eigene Leiste anlegen.', 'b83-floating-buttons' ) . '</p>';
			echo '<p><a class="button button-primary button-hero" href="' . esc_url( self::url( array( 'edit' => 'new' ) ) ) . '">' . esc_html__( 'Erste Leiste anlegen', 'b83-floating-buttons' ) . '</a></p>';
			echo '</div>';
			return;
		}

		$positions = $this->position_labels();
		echo '<table class="widefat striped b83-fb-table"><thead><tr>';
		foreach ( array( __( 'Leiste', 'b83-floating-buttons' ), __( 'Buttons', 'b83-floating-buttons' ), __( 'Position', 'b83-floating-buttons' ), __( 'Geräte', 'b83-floating-buttons' ), __( 'Status', 'b83-floating-buttons' ), '' ) as $head ) {
			echo '<th>' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $groups as $group ) {
			$edit_url = self::url( array( 'edit' => (string) $group['id'] ) );
			$active   = array_filter( (array) $group['items'], static fn( $i ) => ! empty( $i['active'] ) );

			echo '<tr>';
			echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( (string) $group['name'] ) . '</a></strong></td>';

			echo '<td><span class="b83-fb-mini">';
			foreach ( array_slice( $active, 0, 8 ) as $item ) {
				echo '<span class="b83-fb-mini__icon">' . $this->item_thumb( $item ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- item_thumb() escaped.
			}
			echo '</span> ';
			/* translators: %d: Anzahl */
			echo '<span class="description">' . esc_html( sprintf( _n( '%d aktiv', '%d aktiv', count( $active ), 'b83-floating-buttons' ), count( $active ) ) ) . '</span></td>';

			echo '<td>' . esc_html( $positions[ $group['position'] ] ?? '' ) . '</td>';

			$devices = array();
			if ( $group['show_desktop'] ) {
				$devices[] = __( 'Desktop', 'b83-floating-buttons' );
			}
			if ( $group['show_tablet'] ) {
				$devices[] = __( 'Tablet', 'b83-floating-buttons' );
			}
			if ( $group['show_mobile'] ) {
				$devices[] = __( 'Mobil', 'b83-floating-buttons' );
			}
			echo '<td>' . esc_html( implode( ', ', $devices ) ) . '</td>';

			echo '<td>';
			$this->action_button( 'toggle', (string) $group['id'], $group['active'] ? __( 'Aktiv', 'b83-floating-buttons' ) : __( 'Inaktiv', 'b83-floating-buttons' ), 'b83-fb-status ' . ( $group['active'] ? 'is-on' : 'is-off' ) );
			echo '</td>';

			echo '<td class="b83-fb-table__actions">';
			echo '<a class="button button-small" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Bearbeiten', 'b83-floating-buttons' ) . '</a> ';
			$this->action_button( 'duplicate', (string) $group['id'], __( 'Duplizieren', 'b83-floating-buttons' ), 'button button-small' );
			$this->action_button( 'delete', (string) $group['id'], __( 'Löschen', 'b83-floating-buttons' ), 'button button-small button-link-delete', __( 'Diese Leiste wirklich löschen?', 'b83-floating-buttons' ) );
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function action_button( string $action, string $id, string $label, string $class, string $confirm = '' ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="b83-fb-inline"' . ( '' !== $confirm ? ' data-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>';
		echo '<input type="hidden" name="action" value="' . esc_attr( 'b83_fb_' . $action ) . '">';
		echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( 'b83_fb_' . $action );
		echo '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button>';
		echo '</form> ';
	}

	/** @param array<string,mixed> $item Button. */
	private function item_thumb( array $item ): string {
		if ( 'image' === $item['icon_type'] && $item['image_id'] ) {
			$url = wp_get_attachment_image_url( (int) $item['image_id'], 'thumbnail' );
			if ( $url ) {
				return '<img src="' . esc_url( $url ) . '" alt="">';
			}
		}
		return $this->icons->svg( (string) $item['icon'] );
	}

	/* ---------------------------------------------------------------------
	 * Einstellungen, Export, Import
	 * ------------------------------------------------------------------- */

	private function render_settings(): void {
		$settings = $this->repository->settings();
		$cache    = CachePurge::detected_plugin();

		echo '<div class="b83-fb-cards">';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="b83-fb-card">';
		echo '<h2>' . esc_html__( 'Allgemein', 'b83-floating-buttons' ) . '</h2>';
		echo '<input type="hidden" name="action" value="b83_fb_settings">';
		wp_nonce_field( 'b83_fb_settings' );
		echo '<p><label><input type="checkbox" name="settings[purge_cache]" value="1"' . checked( $settings['purge_cache'], true, false ) . '> ' . esc_html__( 'Seiten-Cache nach dem Speichern automatisch leeren', 'b83-floating-buttons' ) . '</label><br><span class="description">';
		echo '' !== $cache
			/* translators: %s: Name des Cache-Plugins */
			? esc_html( sprintf( __( 'Erkannt: %s', 'b83-floating-buttons' ), $cache ) )
			: esc_html__( 'Unterstützt: WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, SiteGround. Derzeit keines erkannt.', 'b83-floating-buttons' );
		echo '</span></p>';
		echo '<p><label><input type="checkbox" name="settings[delete_on_uninstall]" value="1"' . checked( $settings['delete_on_uninstall'], true, false ) . '> ' . esc_html__( 'Beim Löschen des Plugins alle Leisten und Einstellungen entfernen', 'b83-floating-buttons' ) . '</label></p>';
		submit_button( __( 'Einstellungen speichern', 'b83-floating-buttons' ) );
		echo '</form>';

		echo '<div class="b83-fb-card">';
		echo '<h2>' . esc_html__( 'Export', 'b83-floating-buttons' ) . '</h2>';
		echo '<p>' . esc_html__( 'Alle Leisten als Datei sichern oder auf eine andere Website übertragen.', 'b83-floating-buttons' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="b83_fb_export">';
		wp_nonce_field( 'b83_fb_export' );
		submit_button( __( 'Exportdatei herunterladen', 'b83-floating-buttons' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Import', 'b83-floating-buttons' ) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="b83_fb_import">';
		wp_nonce_field( 'b83_fb_import' );
		echo '<p><input type="file" name="import_file" accept=".json,application/json" required></p>';
		echo '<p><label><input type="radio" name="import_mode" value="add" checked> ' . esc_html__( 'Zu den bestehenden Leisten hinzufügen', 'b83-floating-buttons' ) . '</label><br>';
		echo '<label><input type="radio" name="import_mode" value="replace"> ' . esc_html__( 'Bestehende Leisten ersetzen', 'b83-floating-buttons' ) . '</label></p>';
		echo '<p class="description">' . esc_html__( 'Eigene Grafiken aus der Mediathek einer anderen Website werden nicht übernommen – dort wird wieder das gewählte Icon angezeigt.', 'b83-floating-buttons' ) . '</p>';
		submit_button( __( 'Importieren', 'b83-floating-buttons' ), 'secondary', 'submit', false );
		echo '</form></div>';

		echo '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Editor
	 * ------------------------------------------------------------------- */

	/** @return array<string,string> */
	private function position_labels(): array {
		return array(
			'left'   => __( 'Links', 'b83-floating-buttons' ),
			'right'  => __( 'Rechts', 'b83-floating-buttons' ),
			'top'    => __( 'Oben', 'b83-floating-buttons' ),
			'bottom' => __( 'Unten', 'b83-floating-buttons' ),
		);
	}

	/**
	 * Startinhalt einer neuen Leiste.
	 *
	 * @return array<string,mixed>
	 */
	private function new_group(): array {
		$group          = Sanitizer::group_defaults();
		$group['items'] = array(
			array_merge(
				Sanitizer::item_defaults(),
				array(
					'label'     => __( 'Anrufen', 'b83-floating-buttons' ),
					'tooltip'   => __( 'Wir sind Mo–Fr von 8 bis 17 Uhr für Sie da.', 'b83-floating-buttons' ),
					'icon'      => 'phone',
					'link_type' => 'phone',
				)
			),
			array_merge(
				Sanitizer::item_defaults(),
				array(
					'label'     => __( 'E-Mail schreiben', 'b83-floating-buttons' ),
					'tooltip'   => __( 'Wir antworten in der Regel innerhalb eines Werktags.', 'b83-floating-buttons' ),
					'icon'      => 'mail',
					'link_type' => 'email',
				)
			),
			array_merge(
				Sanitizer::item_defaults(),
				array(
					'label'     => __( 'Anfahrt', 'b83-floating-buttons' ),
					'tooltip'   => __( 'Route in Google Maps öffnen', 'b83-floating-buttons' ),
					'icon'      => 'map-pin',
					'link_type' => 'url',
				)
			),
		);
		return $group;
	}

	private function render_edit( string $edit ): void {
		if ( 'new' === $edit ) {
			$group = $this->new_group();
		} else {
			$group = $this->repository->group( $edit );
			if ( null === $group ) {
				echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Die Leiste wurde nicht gefunden.', 'b83-floating-buttons' ) . '</p></div>';
				echo '<p><a href="' . esc_url( self::url() ) . '">&larr; ' . esc_html__( 'Zur Übersicht', 'b83-floating-buttons' ) . '</a></p>';
				return;
			}
		}

		echo '<p class="b83-fb-back"><a href="' . esc_url( self::url() ) . '">&larr; ' . esc_html__( 'Alle Leisten', 'b83-floating-buttons' ) . '</a></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="b83-fb-form" class="b83-fb-editor">';
		echo '<input type="hidden" name="action" value="b83_fb_save">';
		echo '<input type="hidden" name="group[id]" value="' . esc_attr( (string) $group['id'] ) . '">';
		wp_nonce_field( 'b83_fb_save' );

		echo '<div class="b83-fb-editor__main">';

		// Kopf: Name und Status.
		echo '<div class="b83-fb-titlebar">';
		echo '<input type="text" class="b83-fb-title" name="group[name]" maxlength="80" value="' . esc_attr( (string) $group['name'] ) . '" aria-label="' . esc_attr__( 'Name der Leiste', 'b83-floating-buttons' ) . '" placeholder="' . esc_attr__( 'Name der Leiste', 'b83-floating-buttons' ) . '">';
		echo '<label class="b83-fb-switch"><input type="checkbox" name="group[active]" value="1"' . checked( $group['active'], true, false ) . '><span class="b83-fb-switch__track"></span><span>' . esc_html__( 'Aktiv auf der Website', 'b83-floating-buttons' ) . '</span></label>';
		echo '</div>';

		$this->render_items_box( $group );
		$this->render_position_box( $group );
		$this->render_style_box( $group );
		$this->render_behavior_box( $group );
		$this->render_devices_box( $group );

		echo '</div>';

		$this->render_preview_box();

		echo '</form>';

		$this->render_icon_picker();
		$this->render_item_template();
	}

	/* --- Buttons ------------------------------------------------------ */

	/** @param array<string,mixed> $group Leiste. */
	private function render_items_box( array $group ): void {
		echo '<section class="b83-fb-card">';
		echo '<div class="b83-fb-card__head"><h2>' . esc_html__( 'Buttons', 'b83-floating-buttons' ) . '</h2>';
		echo '<span class="description">' . esc_html__( 'Reihenfolge per Ziehen am Griff ändern.', 'b83-floating-buttons' ) . '</span></div>';
		echo '<div class="b83-fb-items" id="b83-fb-items">';
		foreach ( array_values( (array) $group['items'] ) as $index => $item ) {
			$this->render_item( $item, (string) $index, false );
		}
		echo '</div>';
		echo '<p><button type="button" class="button button-secondary" id="b83-fb-add"><span class="dashicons dashicons-plus-alt2"></span> ' . esc_html__( 'Button hinzufügen', 'b83-floating-buttons' ) . '</button></p>';
		echo '</section>';
	}

	/**
	 * Ein Button im Editor (auch als Vorlage mit Platzhalter-Index).
	 *
	 * @param array<string,mixed> $item  Button.
	 * @param string              $index Index oder __INDEX__.
	 * @param bool                $open  Aufgeklappt.
	 */
	private function render_item( array $item, string $index, bool $open ): void {
		$n    = 'group[items][' . $index . ']';
		$uid  = 'b83fb-' . $index . '-' . (string) $item['id'];
		$link = (string) $item['link_type'];

		echo '<div class="b83-fb-item' . ( $open ? ' is-open' : '' ) . ( $item['active'] ? '' : ' is-inactive' ) . '" data-link="' . esc_attr( $link ) . '" data-icon-type="' . esc_attr( (string) $item['icon_type'] ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $n . '[id]' ) . '" value="' . esc_attr( (string) $item['id'] ) . '">';

		// Kopfzeile.
		echo '<div class="b83-fb-item__head">';
		echo '<span class="b83-fb-item__handle dashicons dashicons-move" title="' . esc_attr__( 'Ziehen zum Sortieren', 'b83-floating-buttons' ) . '"></span>';
		echo '<span class="b83-fb-item__thumb" data-role="thumb">' . $this->item_thumb( $item ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- item_thumb() escaped.
		echo '<button type="button" class="b83-fb-item__toggle" aria-expanded="' . ( $open ? 'true' : 'false' ) . '"><span class="b83-fb-item__title" data-role="title">' . esc_html( '' !== $item['label'] ? (string) $item['label'] : __( '(ohne Text)', 'b83-floating-buttons' ) ) . '</span><span class="b83-fb-item__meta" data-role="meta"></span><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
		echo '<label class="b83-fb-switch b83-fb-switch--small" title="' . esc_attr__( 'Anzeigen', 'b83-floating-buttons' ) . '"><input type="checkbox" data-role="active" name="' . esc_attr( $n . '[active]' ) . '" value="1"' . checked( $item['active'], true, false ) . '><span class="b83-fb-switch__track"></span><span class="screen-reader-text">' . esc_html__( 'Anzeigen', 'b83-floating-buttons' ) . '</span></label>';
		echo '<button type="button" class="b83-fb-icon-btn" data-role="duplicate" title="' . esc_attr__( 'Duplizieren', 'b83-floating-buttons' ) . '"><span class="dashicons dashicons-admin-page"></span><span class="screen-reader-text">' . esc_html__( 'Duplizieren', 'b83-floating-buttons' ) . '</span></button>';
		echo '<button type="button" class="b83-fb-icon-btn is-danger" data-role="remove" title="' . esc_attr__( 'Entfernen', 'b83-floating-buttons' ) . '"><span class="dashicons dashicons-trash"></span><span class="screen-reader-text">' . esc_html__( 'Entfernen', 'b83-floating-buttons' ) . '</span></button>';
		echo '</div>';

		echo '<div class="b83-fb-item__body">';

		// Grafik.
		echo '<div class="b83-fb-row">';
		echo '<div class="b83-fb-field"><span class="b83-fb-label">' . esc_html__( 'Grafik', 'b83-floating-buttons' ) . '</span>';
		echo '<span class="b83-fb-seg">';
		foreach ( array( 'icon' => __( 'Icon', 'b83-floating-buttons' ), 'image' => __( 'Eigene Grafik', 'b83-floating-buttons' ) ) as $value => $label ) {
			echo '<label><input type="radio" data-role="icon-type" name="' . esc_attr( $n . '[icon_type]' ) . '" value="' . esc_attr( $value ) . '"' . checked( $item['icon_type'], $value, false ) . '><span>' . esc_html( $label ) . '</span></label>';
		}
		echo '</span></div>';

		echo '<div class="b83-fb-field b83-fb-when-icon"><span class="b83-fb-label">' . esc_html__( 'Icon', 'b83-floating-buttons' ) . '</span>';
		echo '<input type="hidden" data-role="icon" name="' . esc_attr( $n . '[icon]' ) . '" value="' . esc_attr( (string) $item['icon'] ) . '">';
		echo '<button type="button" class="button b83-fb-pick" data-role="pick-icon"><span class="b83-fb-pick__svg" data-role="icon-preview">' . $this->icons->svg( (string) $item['icon'] ) . '</span><span data-role="icon-name">' . esc_html( (string) ( $this->icons->all()[ (string) $item['icon'] ]['label'] ?? '' ) ) . '</span></button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() gefiltert.

		$img_url = $item['image_id'] ? (string) wp_get_attachment_image_url( (int) $item['image_id'], 'thumbnail' ) : '';
		echo '<div class="b83-fb-field b83-fb-when-image"><span class="b83-fb-label">' . esc_html__( 'Eigene Grafik', 'b83-floating-buttons' ) . '</span>';
		echo '<input type="hidden" data-role="image-id" name="' . esc_attr( $n . '[image_id]' ) . '" value="' . esc_attr( (string) (int) $item['image_id'] ) . '">';
		echo '<span class="b83-fb-media"><span class="b83-fb-media__img" data-role="image-preview">' . ( '' !== $img_url ? '<img src="' . esc_url( $img_url ) . '" alt="">' : '' ) . '</span>';
		echo '<button type="button" class="button" data-role="pick-image">' . esc_html__( 'Aus Mediathek wählen', 'b83-floating-buttons' ) . '</button> ';
		echo '<button type="button" class="button-link button-link-delete" data-role="clear-image">' . esc_html__( 'Entfernen', 'b83-floating-buttons' ) . '</button></span>';
		echo '<span class="description">' . esc_html__( 'Am besten quadratisch, PNG oder SVG mit transparentem Hintergrund.', 'b83-floating-buttons' ) . '</span></div>';
		echo '</div>';

		// Texte.
		echo '<div class="b83-fb-row">';
		$this->text_field( $uid . '-label', $n . '[label]', __( 'Text beim Ausklappen', 'b83-floating-buttons' ), (string) $item['label'], 60, __( 'z. B. Jetzt anrufen', 'b83-floating-buttons' ), __( 'Leer lassen für einen reinen Icon-Button.', 'b83-floating-buttons' ), 'label' );
		$this->text_field( $uid . '-tip', $n . '[tooltip]', __( 'Info-Text beim Drüberfahren', 'b83-floating-buttons' ), (string) $item['tooltip'], 200, __( 'z. B. Mo–Fr 8–17 Uhr erreichbar', 'b83-floating-buttons' ), __( 'Erscheint als Hinweisblase neben dem Button.', 'b83-floating-buttons' ) );
		echo '</div>';

		// Link.
		echo '<div class="b83-fb-row">';
		echo '<div class="b83-fb-field"><label class="b83-fb-label" for="' . esc_attr( $uid . '-lt' ) . '">' . esc_html__( 'Aktion beim Klick', 'b83-floating-buttons' ) . '</label>';
		echo '<select id="' . esc_attr( $uid . '-lt' ) . '" data-role="link-type" name="' . esc_attr( $n . '[link_type]' ) . '">';
		$types = array(
			'url'      => __( 'Link (Webseite)', 'b83-floating-buttons' ),
			'phone'    => __( 'Telefon anrufen', 'b83-floating-buttons' ),
			'email'    => __( 'E-Mail schreiben', 'b83-floating-buttons' ),
			'whatsapp' => __( 'WhatsApp-Chat', 'b83-floating-buttons' ),
			'sms'      => __( 'SMS senden', 'b83-floating-buttons' ),
			'top'      => __( 'Nach oben scrollen', 'b83-floating-buttons' ),
			'none'     => __( 'Keine (nur Info)', 'b83-floating-buttons' ),
		);
		foreach ( $types as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $link, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></div>';

		echo '<div class="b83-fb-field b83-fb-when b83-fb-when-url">';
		$this->text_input( $uid . '-url', $n . '[url]', __( 'Adresse', 'b83-floating-buttons' ), (string) $item['url'], 2000, 'https://… oder /kontakt/ oder #anfahrt', 'text' );
		echo '</div>';
		echo '<div class="b83-fb-field b83-fb-when b83-fb-when-phone b83-fb-when-sms">';
		$this->text_input( $uid . '-phone', $n . '[phone]', __( 'Telefonnummer', 'b83-floating-buttons' ), (string) $item['phone'], 40, '+43 6243 1234', 'tel' );
		echo '</div>';
		echo '<div class="b83-fb-field b83-fb-when b83-fb-when-email">';
		$this->text_input( $uid . '-mail', $n . '[email]', __( 'E-Mail-Adresse', 'b83-floating-buttons' ), (string) $item['email'], 254, 'office@beispiel.at', 'email' );
		echo '</div>';
		echo '<div class="b83-fb-field b83-fb-when b83-fb-when-email">';
		$this->text_input( $uid . '-subj', $n . '[email_subject]', __( 'Betreff (optional)', 'b83-floating-buttons' ), (string) $item['email_subject'], 150, __( 'Anfrage über die Website', 'b83-floating-buttons' ), 'text' );
		echo '</div>';
		echo '<div class="b83-fb-field b83-fb-when b83-fb-when-whatsapp">';
		$this->text_input( $uid . '-wa', $n . '[whatsapp]', __( 'WhatsApp-Nummer (international)', 'b83-floating-buttons' ), (string) $item['whatsapp'], 30, '436641234567', 'tel' );
		echo '</div>';
		echo '<div class="b83-fb-field b83-fb-when b83-fb-when-whatsapp">';
		$this->text_input( $uid . '-wat', $n . '[whatsapp_text]', __( 'Vorausgefüllte Nachricht (optional)', 'b83-floating-buttons' ), (string) $item['whatsapp_text'], 300, __( 'Hallo, ich habe eine Frage …', 'b83-floating-buttons' ), 'text' );
		echo '</div>';
		echo '</div>';

		echo '<div class="b83-fb-row b83-fb-when b83-fb-when-url b83-fb-when-whatsapp">';
		echo '<label class="b83-fb-check"><input type="checkbox" name="' . esc_attr( $n . '[target_blank]' ) . '" value="1"' . checked( $item['target_blank'], true, false ) . '> ' . esc_html__( 'In neuem Fenster öffnen', 'b83-floating-buttons' ) . '</label>';
		echo '<label class="b83-fb-check"><input type="checkbox" name="' . esc_attr( $n . '[nofollow]' ) . '" value="1"' . checked( $item['nofollow'], true, false ) . '> ' . esc_html__( 'nofollow (für Suchmaschinen nicht folgen)', 'b83-floating-buttons' ) . '</label>';
		echo '<span class="description">' . esc_html__( 'Gilt nur für externe Adressen.', 'b83-floating-buttons' ) . '</span>';
		echo '</div>';

		// Eigene Farben.
		$has_colors = '' !== $item['bg'] . $item['color'] . $item['bg_hover'] . $item['color_hover'];
		echo '<details class="b83-fb-details"' . ( $has_colors ? ' open' : '' ) . '><summary>' . esc_html__( 'Eigene Farben für diesen Button', 'b83-floating-buttons' ) . '</summary>';
		echo '<p class="description">' . esc_html__( 'Leer = Farben der Leiste. Ohne eigene Hover-Farbe bleibt der Button beim Drüberfahren in seiner Farbe – praktisch z. B. für einen grünen WhatsApp-Button.', 'b83-floating-buttons' ) . '</p>';
		echo '<div class="b83-fb-colors">';
		$this->color_field( $n . '[bg]', __( 'Hintergrund', 'b83-floating-buttons' ), (string) $item['bg'] );
		$this->color_field( $n . '[color]', __( 'Icon & Text', 'b83-floating-buttons' ), (string) $item['color'] );
		$this->color_field( $n . '[bg_hover]', __( 'Hintergrund (Hover)', 'b83-floating-buttons' ), (string) $item['bg_hover'] );
		$this->color_field( $n . '[color_hover]', __( 'Icon & Text (Hover)', 'b83-floating-buttons' ), (string) $item['color_hover'] );
		echo '</div></details>';

		echo '</div></div>';
	}

	private function render_item_template(): void {
		$item              = Sanitizer::item_defaults();
		$item['link_type'] = 'url';
		echo '<template id="b83-fb-item-template">';
		$this->render_item( $item, '__INDEX__', true );
		echo '</template>';
	}

	/** Gemeinsames Auswahlfenster für Icons (wird an den jeweiligen Button gehängt). */
	private function render_icon_picker(): void {
		$grouped = array();
		foreach ( $this->icons->all() as $key => $icon ) {
			$grouped[ $icon['group'] ][ $key ] = $icon;
		}

		echo '<div class="b83-fb-picker" id="b83-fb-picker" hidden role="dialog" aria-label="' . esc_attr__( 'Icon wählen', 'b83-floating-buttons' ) . '">';
		echo '<div class="b83-fb-picker__head"><input type="search" class="b83-fb-picker__search" placeholder="' . esc_attr__( 'Icon suchen …', 'b83-floating-buttons' ) . '" aria-label="' . esc_attr__( 'Icon suchen', 'b83-floating-buttons' ) . '">';
		echo '<button type="button" class="b83-fb-icon-btn" data-role="picker-close"><span class="dashicons dashicons-no-alt"></span><span class="screen-reader-text">' . esc_html__( 'Schließen', 'b83-floating-buttons' ) . '</span></button></div>';
		echo '<div class="b83-fb-picker__body">';
		foreach ( $grouped as $group => $icons ) {
			echo '<div class="b83-fb-picker__group"><h3>' . esc_html( $group ) . '</h3><div class="b83-fb-picker__grid">';
			foreach ( $icons as $key => $icon ) {
				echo '<button type="button" class="b83-fb-picker__icon" data-icon="' . esc_attr( $key ) . '" data-label="' . esc_attr( $icon['label'] ) . '" title="' . esc_attr( $icon['label'] ) . '">' . $this->icons->svg( $key ) . '<span>' . esc_html( $icon['label'] ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::svg() gefiltert.
			}
			echo '</div></div>';
		}
		echo '</div></div>';
	}

	/* --- Position ----------------------------------------------------- */

	/** @param array<string,mixed> $group Leiste. */
	private function render_position_box( array $group ): void {
		echo '<section class="b83-fb-card"><h2>' . esc_html__( 'Position', 'b83-floating-buttons' ) . '</h2>';

		echo '<div class="b83-fb-field"><span class="b83-fb-label">' . esc_html__( 'Bildschirmrand', 'b83-floating-buttons' ) . '</span><div class="b83-fb-positions">';
		foreach ( $this->position_labels() as $value => $label ) {
			echo '<label class="b83-fb-pos b83-fb-pos--' . esc_attr( $value ) . '"><input type="radio" name="group[position]" value="' . esc_attr( $value ) . '"' . checked( $group['position'], $value, false ) . '><span class="b83-fb-pos__screen"><span></span></span><span class="b83-fb-pos__label">' . esc_html( $label ) . '</span></label>';
		}
		echo '</div></div>';

		echo '<div class="b83-fb-row">';
		echo '<div class="b83-fb-field"><span class="b83-fb-label">' . esc_html__( 'Ausrichtung am Rand', 'b83-floating-buttons' ) . '</span><span class="b83-fb-seg" data-role="align">';
		$aligns = array(
			'start'  => array( __( 'Oben', 'b83-floating-buttons' ), __( 'Links', 'b83-floating-buttons' ) ),
			'center' => array( __( 'Mitte', 'b83-floating-buttons' ), __( 'Mitte', 'b83-floating-buttons' ) ),
			'end'    => array( __( 'Unten', 'b83-floating-buttons' ), __( 'Rechts', 'b83-floating-buttons' ) ),
		);
		foreach ( $aligns as $value => $labels ) {
			echo '<label><input type="radio" name="group[align]" value="' . esc_attr( $value ) . '"' . checked( $group['align'], $value, false ) . '><span data-v="' . esc_attr( $labels[0] ) . '" data-h="' . esc_attr( $labels[1] ) . '">' . esc_html( $labels[0] ) . '</span></label>';
		}
		echo '</span></div>';
		$this->number_field( 'align_offset', __( 'Abstand vom Anfang bzw. Ende', 'b83-floating-buttons' ), $group, 'px', __( 'Bei Ausrichtung Mitte ohne Wirkung.', 'b83-floating-buttons' ) );
		echo '</div>';

		echo '<div class="b83-fb-row">';
		$this->number_field( 'edge_offset', __( 'Abstand zum Rand', 'b83-floating-buttons' ), $group, 'px', __( '0 = bündig am Rand (Ecken dort gerade).', 'b83-floating-buttons' ) );
		$this->number_field( 'gap', __( 'Abstand zwischen Buttons', 'b83-floating-buttons' ), $group, 'px' );
		$this->number_field( 'peek', __( 'Eingeklappt im Rand versteckt', 'b83-floating-buttons' ), $group, '%', __( 'Fährt beim Drüberfahren ganz heraus.', 'b83-floating-buttons' ) );
		echo '</div>';

		echo '</section>';
	}

	/* --- Aussehen ----------------------------------------------------- */

	/** @param array<string,mixed> $group Leiste. */
	private function render_style_box( array $group ): void {
		echo '<section class="b83-fb-card"><h2>' . esc_html__( 'Aussehen', 'b83-floating-buttons' ) . '</h2>';

		echo '<div class="b83-fb-row">';
		$this->number_field( 'size', __( 'Button-Größe', 'b83-floating-buttons' ), $group, 'px' );
		$this->number_field( 'icon_size', __( 'Icon-Größe', 'b83-floating-buttons' ), $group, '%', __( 'Anteil an der Button-Größe.', 'b83-floating-buttons' ) );
		$this->number_field( 'radius', __( 'Eckenradius', 'b83-floating-buttons' ), $group, 'px', __( 'Halbe Button-Größe = rund.', 'b83-floating-buttons' ) );
		echo '</div>';

		echo '<div class="b83-fb-row">';
		$this->number_field( 'font_size', __( 'Schriftgröße', 'b83-floating-buttons' ), $group, 'px' );
		$this->select_field(
			'font_weight',
			__( 'Schriftstärke', 'b83-floating-buttons' ),
			$group,
			array(
				'400' => __( 'Normal', 'b83-floating-buttons' ),
				'600' => __( 'Halbfett', 'b83-floating-buttons' ),
				'700' => __( 'Fett', 'b83-floating-buttons' ),
			)
		);
		$this->select_field(
			'shadow',
			__( 'Schatten', 'b83-floating-buttons' ),
			$group,
			array(
				'none'   => __( 'Kein', 'b83-floating-buttons' ),
				'soft'   => __( 'Weich', 'b83-floating-buttons' ),
				'strong' => __( 'Kräftig', 'b83-floating-buttons' ),
			)
		);
		echo '</div>';

		echo '<h3>' . esc_html__( 'Farben', 'b83-floating-buttons' ) . '</h3>';
		echo '<div class="b83-fb-colorgrid">';
		echo '<span></span><strong>' . esc_html__( 'Normal', 'b83-floating-buttons' ) . '</strong><strong>' . esc_html__( 'Mouseover', 'b83-floating-buttons' ) . '</strong>';
		$rows = array(
			array( __( 'Hintergrund', 'b83-floating-buttons' ), 'bg', 'bg_hover' ),
			array( __( 'Icon', 'b83-floating-buttons' ), 'color', 'color_hover' ),
			array( __( 'Text', 'b83-floating-buttons' ), 'text_color', 'text_hover' ),
		);
		foreach ( $rows as $row ) {
			echo '<span class="b83-fb-colorgrid__label">' . esc_html( $row[0] ) . '</span>';
			$this->color_input( 'group[' . $row[1] . ']', (string) $group[ $row[1] ], $row[0] );
			$this->color_input( 'group[' . $row[2] . ']', (string) $group[ $row[2] ], $row[0] . ' (Mouseover)' );
		}
		echo '<span class="b83-fb-colorgrid__label">' . esc_html__( 'Info-Text', 'b83-floating-buttons' ) . '</span>';
		$this->color_input( 'group[tip_bg]', (string) $group['tip_bg'], __( 'Info-Text Hintergrund', 'b83-floating-buttons' ) );
		$this->color_input( 'group[tip_color]', (string) $group['tip_color'], __( 'Info-Text Schrift', 'b83-floating-buttons' ) );
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Info-Text: links Hintergrund, rechts Schriftfarbe. Einzelne Buttons können eigene Farben bekommen.', 'b83-floating-buttons' ) . '</p>';

		echo '</section>';
	}

	/* --- Verhalten ---------------------------------------------------- */

	/** @param array<string,mixed> $group Leiste. */
	private function render_behavior_box( array $group ): void {
		echo '<section class="b83-fb-card"><h2>' . esc_html__( 'Verhalten', 'b83-floating-buttons' ) . '</h2>';
		echo '<div class="b83-fb-row">';
		$this->number_field( 'delay', __( 'Verzögerung beim Ausklappen', 'b83-floating-buttons' ), $group, 'ms', __( 'Verhindert Aufklappen beim bloßen Vorbeifahren. Empfohlen: 100–250.', 'b83-floating-buttons' ) );
		$this->number_field( 'duration', __( 'Dauer der Animation', 'b83-floating-buttons' ), $group, 'ms' );
		$this->select_field(
			'entrance',
			__( 'Einblenden beim Laden', 'b83-floating-buttons' ),
			$group,
			array(
				'none'  => __( 'Sofort sichtbar', 'b83-floating-buttons' ),
				'fade'  => __( 'Einblenden', 'b83-floating-buttons' ),
				'slide' => __( 'Hereinfahren', 'b83-floating-buttons' ),
			)
		);
		echo '</div>';
		echo '<div class="b83-fb-row">';
		echo '<label class="b83-fb-check"><input type="checkbox" name="group[tooltips]" value="1"' . checked( $group['tooltips'], true, false ) . '> ' . esc_html__( 'Info-Texte anzeigen', 'b83-floating-buttons' ) . '</label>';
		$this->number_field( 'z_index', __( 'Ebene (z-index)', 'b83-floating-buttons' ), $group, '', __( 'Nur ändern, wenn Buttons von anderen Elementen verdeckt werden.', 'b83-floating-buttons' ) );
		echo '</div>';
		echo '</section>';
	}

	/* --- Geräte und Seiten -------------------------------------------- */

	/** @param array<string,mixed> $group Leiste. */
	private function render_devices_box( array $group ): void {
		echo '<section class="b83-fb-card"><h2>' . esc_html__( 'Geräte & Seiten', 'b83-floating-buttons' ) . '</h2>';

		echo '<div class="b83-fb-row">';
		echo '<div class="b83-fb-field"><span class="b83-fb-label">' . esc_html__( 'Anzeigen auf', 'b83-floating-buttons' ) . '</span><span class="b83-fb-checks">';
		foreach ( array(
			'show_desktop' => array( __( 'Desktop', 'b83-floating-buttons' ), 'desktop' ),
			'show_tablet'  => array( __( 'Tablet', 'b83-floating-buttons' ), 'tablet' ),
			'show_mobile'  => array( __( 'Mobil', 'b83-floating-buttons' ), 'smartphone' ),
		) as $key => $data ) {
			echo '<label class="b83-fb-check"><input type="checkbox" name="group[' . esc_attr( $key ) . ']" value="1"' . checked( $group[ $key ], true, false ) . '> <span class="dashicons dashicons-' . esc_attr( $data[1] ) . '"></span> ' . esc_html( $data[0] ) . '</label>';
		}
		echo '</span><span class="description">' . esc_html__( 'Tablet: 768–1024 px, Mobil: bis 767 px (wie Elementor).', 'b83-floating-buttons' ) . '</span></div>';
		echo '</div>';

		echo '<div class="b83-fb-row">';
		$this->select_field(
			'mobile_layout',
			__( 'Darstellung auf Mobilgeräten', 'b83-floating-buttons' ),
			$group,
			array(
				'same'    => __( 'Wie Desktop – 1. Antippen klappt aus, 2. öffnet', 'b83-floating-buttons' ),
				'compact' => __( 'Nur Icons – Antippen öffnet sofort', 'b83-floating-buttons' ),
				'bar'     => __( 'Leiste am unteren Rand (volle Breite)', 'b83-floating-buttons' ),
			)
		);
		$this->number_field( 'mobile_size', __( 'Button-Größe mobil', 'b83-floating-buttons' ), $group, 'px' );
		echo '</div>';

		// Seiten ausschließen.
		$pages    = get_pages(
			array(
				'number'      => 400,
				'sort_column' => 'menu_order,post_title',
				'post_status' => 'publish,private,draft',
			)
		);
		$excluded = array_map( 'intval', (array) $group['exclude_ids'] );
		echo '<div class="b83-fb-field"><span class="b83-fb-label">' . esc_html__( 'Nicht anzeigen auf diesen Seiten', 'b83-floating-buttons' ) . '</span>';
		echo '<input type="search" class="b83-fb-pagesearch" placeholder="' . esc_attr__( 'Seiten filtern …', 'b83-floating-buttons' ) . '" aria-label="' . esc_attr__( 'Seiten filtern', 'b83-floating-buttons' ) . '">';
		echo '<div class="b83-fb-pagelist">';
		$page_ids = array();
		foreach ( (array) $pages as $page ) {
			$page_ids[] = (int) $page->ID;
			$depth      = count( get_post_ancestors( $page ) );
			echo '<label style="--depth:' . (int) $depth . '"><input type="checkbox" name="group[exclude_ids][]" value="' . (int) $page->ID . '"' . checked( in_array( (int) $page->ID, $excluded, true ), true, false ) . '> ' . esc_html( '' !== $page->post_title ? $page->post_title : __( '(ohne Titel)', 'b83-floating-buttons' ) ) . '</label>';
		}
		echo '</div>';
		// Ausgeschlossene Beiträge u. ä. (nicht in der Seitenliste) erhalten.
		$others = array_diff( $excluded, $page_ids );
		echo '<label class="b83-fb-label b83-fb-label--small" for="b83-fb-exclude-extra">' . esc_html__( 'Weitere IDs (Beiträge, Produkte …), durch Komma getrennt', 'b83-floating-buttons' ) . '</label>';
		echo '<input type="text" id="b83-fb-exclude-extra" name="group[exclude_ids][]" value="' . esc_attr( implode( ', ', $others ) ) . '" class="regular-text">';
		echo '</div>';

		echo '</section>';
	}

	/* --- Vorschau ----------------------------------------------------- */

	private function render_preview_box(): void {
		echo '<aside class="b83-fb-editor__side"><div class="b83-fb-sticky">';
		echo '<div class="b83-fb-card b83-fb-preview">';
		echo '<div class="b83-fb-card__head"><h2>' . esc_html__( 'Live-Vorschau', 'b83-floating-buttons' ) . '</h2>';
		echo '<span class="b83-fb-seg b83-fb-devices" role="group" aria-label="' . esc_attr__( 'Gerät', 'b83-floating-buttons' ) . '">';
		foreach ( array(
			'desktop' => array( 'desktop', __( 'Desktop', 'b83-floating-buttons' ), 1280 ),
			'tablet'  => array( 'tablet', __( 'Tablet', 'b83-floating-buttons' ), 820 ),
			'mobile'  => array( 'smartphone', __( 'Mobil', 'b83-floating-buttons' ), 390 ),
		) as $key => $data ) {
			echo '<label title="' . esc_attr( $data[1] ) . '"><input type="radio" name="b83_fb_device" value="' . esc_attr( $key ) . '" data-width="' . (int) $data[2] . '"' . checked( 'desktop', $key, false ) . '><span><span class="dashicons dashicons-' . esc_attr( $data[0] ) . '"></span><span class="screen-reader-text">' . esc_html( $data[1] ) . '</span></span></label>';
		}
		echo '</span></div>';
		echo '<div class="b83-fb-stage" id="b83-fb-stage"><iframe id="b83-fb-frame" title="' . esc_attr__( 'Vorschau', 'b83-floating-buttons' ) . '" sandbox="allow-scripts"></iframe></div>';
		echo '<p class="description">' . esc_html__( 'Mit der Maus über die Buttons fahren, um Ausklappen und Info-Text zu sehen. Die Vorschau zeigt auch deaktivierte Leisten.', 'b83-floating-buttons' ) . '</p>';
		echo '</div>';

		echo '<div class="b83-fb-savebar">';
		submit_button( __( 'Speichern', 'b83-floating-buttons' ), 'primary large', 'submit', false );
		echo '</div>';
		echo '</div></aside>';
	}

	/* ---------------------------------------------------------------------
	 * Feld-Helfer
	 * ------------------------------------------------------------------- */

	private function text_field( string $id, string $name, string $label, string $value, int $max, string $placeholder, string $help = '', string $role = '' ): void {
		echo '<div class="b83-fb-field">';
		echo '<label class="b83-fb-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" maxlength="' . (int) $max . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( '' !== $role ? ' data-role="' . esc_attr( $role ) . '"' : '' ) . '>';
		if ( '' !== $help ) {
			echo '<span class="description">' . esc_html( $help ) . '</span>';
		}
		echo '</div>';
	}

	private function text_input( string $id, string $name, string $label, string $value, int $max, string $placeholder, string $type ): void {
		echo '<label class="b83-fb-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" maxlength="' . (int) $max . '" placeholder="' . esc_attr( $placeholder ) . '">';
	}

	/** @param array<string,mixed> $group Leiste. */
	private function number_field( string $key, string $label, array $group, string $unit, string $help = '' ): void {
		[ $min, $max ] = Sanitizer::GROUP_NUMBERS[ $key ];
		$id            = 'b83-fb-' . $key;
		echo '<div class="b83-fb-field b83-fb-field--' . esc_attr( $key ) . '">';
		echo '<label class="b83-fb-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<span class="b83-fb-num"><input type="number" id="' . esc_attr( $id ) . '" name="group[' . esc_attr( $key ) . ']" value="' . (int) $group[ $key ] . '" min="' . (int) $min . '" max="' . (int) $max . '" step="1">';
		if ( '' !== $unit ) {
			echo '<span class="b83-fb-num__unit">' . esc_html( $unit ) . '</span>';
		}
		echo '</span>';
		if ( '' !== $help ) {
			echo '<span class="description">' . esc_html( $help ) . '</span>';
		}
		echo '</div>';
	}

	/**
	 * @param array<string,mixed>  $group   Leiste.
	 * @param array<string,string> $options Optionen.
	 */
	private function select_field( string $key, string $label, array $group, array $options ): void {
		$id = 'b83-fb-' . $key;
		echo '<div class="b83-fb-field"><label class="b83-fb-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<select id="' . esc_attr( $id ) . '" name="group[' . esc_attr( $key ) . ']">';
		foreach ( $options as $value => $text ) {
			echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $group[ $key ], (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></div>';
	}

	private function color_field( string $name, string $label, string $value ): void {
		echo '<div class="b83-fb-field"><span class="b83-fb-label">' . esc_html( $label ) . '</span>';
		$this->color_input( $name, $value, $label );
		echo '</div>';
	}

	private function color_input( string $name, string $value, string $label ): void {
		echo '<span class="b83-fb-color-wrap"><input type="text" class="b83-fb-color" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( $label ) . '" data-alpha-enabled="false"></span>';
	}
}
