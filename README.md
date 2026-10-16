# b83 Floating Buttons

Schwebende Buttons am Bildschirmrand für WordPress – entwickelt von [b83 – Strategisches Online Marketing](https://b83.at).

Buttons stehen fix am linken, rechten, oberen oder unteren Rand jeder Seite, zeigen zunächst nur ein Icon und klappen bei Mouseover leicht verzögert mit Text aus. Ein Info-Text liefert weitere Hinweise. Funktioniert mit jedem Theme und Page-Builder (Elementor, Block-Editor …), ohne externe Icon-Schriften.

## Funktionen

- **Leisten**: beliebig viele Gruppen von Buttons (max. 20), je Leiste ein Rand und eine Ausrichtung
- **Grafik**: 76 mitgelieferte Icons (Inline-SVG) oder eigene Grafik aus der Mediathek
- **Ausklappen**: Text erscheint verzögert beim Drüberfahren, Buttons können eingeklappt teils im Rand verborgen sein
- **Info-Text** als Hinweisblase
- **Aktionen**: Link, Telefon, E-Mail, WhatsApp, SMS, nach oben scrollen, nur Info; neues Fenster und nofollow wählbar
- **Gestaltung**: Größe, Radius, Schatten, Schrift, Farben normal/Mouseover, je Button eigene Farben
- **Responsiv**: Geräte einzeln ein-/ausblenden; mobil wie Desktop, nur Icons oder Leiste am unteren Rand
- **Barrierearm**: Tastatur (Tab, Enter, Esc), Screenreader-Texte, `prefers-reduced-motion`
- **Live-Vorschau** im Backend (Desktop, Tablet, Mobil)
- **Export/Import** zum Übertragen zwischen Websites
- **Cache** wird nach dem Speichern automatisch geleert

## Installation

1. ZIP `b83-floating-buttons.zip` unter *Plugins → Installieren → Plugin hochladen* einspielen und aktivieren
2. *b83 → Floating Buttons → Neue Leiste*

Updates erscheinen danach automatisch unter *Dashboard → Aktualisierungen* (aus den GitHub-Releases).

## Anpassung im Theme

Feste CSS-Klassen: `.b83-fb` (Leiste, zusätzlich `.b83-fb--left|right|top|bottom`), `.b83-fb__item`, `.b83-fb__btn`, `.b83-fb__icon`, `.b83-fb__label`, `.b83-fb__tip`. Jede Leiste hat die ID `#b83-fb-<id>`, jeder Button das Attribut `data-b83-fb-item="<id>"`. Gestaltung über CSS-Variablen `--b83fb-…` (siehe `assets/css/frontend.css`).

## Hooks für Entwickler

| Hook | Typ | Zweck |
|---|---|---|
| `b83_floating_buttons_loaded` | Action | Nach dem Laden des Plugins (`Plugin`-Instanz) |
| `b83_floating_buttons_saved` | Action | Nach dem Speichern/Löschen einer Leiste |
| `b83_floating_buttons_purge_cache` | Action | Nach dem Leeren des Caches – für weitere Cache-Lösungen |
| `b83_floating_buttons_show_group` | Filter | Leiste auf der aktuellen Seite zeigen (`bool`, Leiste) – z. B. nur für bestimmte Sprachen |
| `b83_floating_buttons_group_html` | Filter | HTML einer Leiste |
| `b83_floating_buttons_item_html` | Filter | HTML eines Buttons |
| `b83_floating_buttons_link` | Filter | Link-Ziel und -Attribute eines Buttons |
| `b83_floating_buttons_icons` | Filter | Eigene Icons ergänzen (SVG-Inhalt für viewBox 0 0 24 24) |
| `b83_floating_buttons_capability` | Filter | Berechtigung zum Verwalten (Standard `manage_options`) |
| `b83_admin_plugin_links` | Filter | Eintrag in der b83-Übersicht (gemeinsam für alle b83-Plugins) |

Beispiel – Leiste nur auf Deutsch zeigen (WPML/Polylang):

```php
add_filter( 'b83_floating_buttons_show_group', function ( $show, $group ) {
	return $show && ( 'Kontakt DE' !== $group['name'] || 'de' === apply_filters( 'wpml_current_language', 'de' ) );
}, 10, 2 );
```

## Daten

Optionen `b83_floating_buttons_groups` (Leisten, automatisch geladen), `b83_floating_buttons_settings`, `b83_floating_buttons_db_version`. Beim Löschen des Plugins bleiben die Daten erhalten, außer in den Einstellungen ist das Entfernen aktiviert.

## Lizenzen

Plugin: GPL-2.0-or-later. Icons: [Lucide](https://lucide.dev) (ISC), [Simple Icons](https://simpleicons.org) (CC0). Markennamen und -logos gehören den jeweiligen Inhabern.
