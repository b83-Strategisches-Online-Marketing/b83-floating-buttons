<?php
/**
 * Speicherung der Leisten und Einstellungen in den WordPress-Optionen.
 *
 * @package B83\FloatingButtons
 */

declare(strict_types=1);

namespace B83\FloatingButtons;

defined( 'ABSPATH' ) || exit;

final class Repository {

	public const OPTION_GROUPS   = 'b83_floating_buttons_groups';
	public const OPTION_SETTINGS = 'b83_floating_buttons_settings';

	/**
	 * Alle Leisten (bereits validiert gespeichert).
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public function groups(): array {
		$groups = get_option( self::OPTION_GROUPS, array() );
		if ( ! is_array( $groups ) ) {
			return array();
		}
		$defaults = Sanitizer::group_defaults();
		$result   = array();
		foreach ( $groups as $group ) {
			if ( is_array( $group ) && ! empty( $group['id'] ) ) {
				// Neue Felder späterer Versionen mit Standardwerten auffüllen.
				$group['items'] = array_map(
					static fn( $item ) => array_merge( Sanitizer::item_defaults(), (array) $item ),
					(array) ( $group['items'] ?? array() )
				);
				$result[]       = array_merge( $defaults, $group );
			}
		}
		return $result;
	}

	/** @return array<string,mixed>|null */
	public function group( string $id ): ?array {
		foreach ( $this->groups() as $group ) {
			if ( $group['id'] === $id ) {
				return $group;
			}
		}
		return null;
	}

	/**
	 * Leiste anlegen oder ersetzen (Daten müssen durch Sanitizer::group gelaufen sein).
	 *
	 * @param array<string,mixed> $group Leiste.
	 */
	public function save_group( array $group ): bool {
		$groups = $this->groups();
		$found  = false;
		foreach ( $groups as $index => $existing ) {
			if ( $existing['id'] === $group['id'] ) {
				$groups[ $index ] = $group;
				$found            = true;
				break;
			}
		}
		if ( ! $found ) {
			if ( count( $groups ) >= Sanitizer::MAX_GROUPS ) {
				return false;
			}
			$groups[] = $group;
		}
		$this->store( $groups );

		/**
		 * Nach dem Speichern einer Leiste.
		 *
		 * @param array<string,mixed> $group Leiste.
		 */
		do_action( 'b83_floating_buttons_saved', $group );
		return true;
	}

	public function delete_group( string $id ): bool {
		$groups = $this->groups();
		$kept   = array_values( array_filter( $groups, static fn( $g ) => $g['id'] !== $id ) );
		if ( count( $kept ) === count( $groups ) ) {
			return false;
		}
		$this->store( $kept );
		do_action( 'b83_floating_buttons_saved', array( 'id' => $id ) );
		return true;
	}

	/**
	 * Alle Leisten ersetzen (Import).
	 *
	 * @param array<int, array<string,mixed>> $groups Validierte Leisten.
	 */
	public function replace_groups( array $groups ): void {
		$this->store( array_slice( array_values( $groups ), 0, Sanitizer::MAX_GROUPS ) );
		do_action( 'b83_floating_buttons_saved', array() );
	}

	/** @param array<int, array<string,mixed>> $groups Leisten. */
	private function store( array $groups ): void {
		update_option( self::OPTION_GROUPS, array_values( $groups ), true );
	}

	/** @return array{delete_on_uninstall:bool,purge_cache:bool} */
	public function settings(): array {
		$raw = get_option( self::OPTION_SETTINGS, array() );
		return Sanitizer::settings( is_array( $raw ) ? $raw : array() );
	}

	/** @param array{delete_on_uninstall:bool,purge_cache:bool} $settings Einstellungen. */
	public function save_settings( array $settings ): void {
		update_option( self::OPTION_SETTINGS, $settings, false );
	}
}
