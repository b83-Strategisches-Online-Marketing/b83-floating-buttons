# b83 Floating Buttons

> **Hinweis:** Ab Version 2.0 enthält dieses öffentliche Repository nur noch die **Gratisversion** von b83 Floating Buttons, wie sie auch auf wordpress.org erscheint. Websites mit Version 1.x erhalten darüber ein letztes Update auf 2.x; danach kommen Updates von wordpress.org bzw. für Pro von Freemius. Die Entwicklung findet im privaten Repository `b83-floating-buttons-pro` statt.

Schwebende Buttons am Bildschirmrand für WordPress – entwickelt von [b83 – Strategisches Online Marketing](https://b83.at).

Freemium-Plugin: Die Gratisversion kommt über wordpress.org, Pro wird über Freemius verkauft (Testphase 30 Tage). Dieses private Repository enthält beide, Freemius erzeugt daraus beim Hochladen die Gratisversion. Regeln dazu in `CLAUDE.md`.

Buttons stehen fix am linken, rechten, oberen oder unteren Rand jeder Seite, zeigen zunächst nur ein Icon und klappen bei Mouseover leicht verzögert mit Text aus. Ein Info-Text liefert weitere Hinweise. Funktioniert mit jedem Theme und Page-Builder (Elementor, Block-Editor …), ohne externe Icon-Schriften.

## Funktionen

- **Leisten**: beliebig viele Gruppen von Buttons (max. 20), je Leiste ein Rand und eine Ausrichtung
- **Grafik**: über 2.100 Font-Awesome-Free-Icons (Solid, Regular, Marken) mit Suche, 76 b83-Icons oder eigenes Bild aus der Mediathek – alles als Inline-SVG, ohne Icon-Schrift
- **Texte**: Überschrift beim Ausklappen, optional kleiner Zusatztext darunter (Schriftgrößen getrennt einstellbar)
- **Ausklappen**: Text erscheint verzögert beim Drüberfahren, Buttons können eingeklappt teils im Rand verborgen sein
- **Info-Text** als Hinweisblase
- **Aktionen**: Link, Telefon, E-Mail, WhatsApp, Chat/Messenger (Messenger, Telegram, Signal, Skype, Viber, Teams), SMS, nach oben scrollen, nur Info; neues Fenster und nofollow wählbar
- **Gestaltung**: Größe, Radius, Schatten, Schrift, Farben normal/Mouseover, je Button eigene Farben
- **Responsiv**: Geräte einzeln ein-/ausblenden; mobil wie Desktop, nur Icons oder Leiste am unteren Rand
- **Barrierearm**: Tastatur (Tab, Enter, Esc), Screenreader-Texte, `prefers-reduced-motion`
- **Live-Vorschau** im Backend (Desktop, Tablet, Mobil)
- **Export/Import** zum Übertragen zwischen Websites
- **Cache** wird nach dem Speichern automatisch geleert

### Pro

- **Öffnungszeiten** je Button: bis zu drei Zeiträume pro Wochentag, Zeitzone der Website. Geprüft wird im Browser, daher auch mit Seiten-Cache korrekt. Außerhalb der Zeiten ist der Button ausgeblendet, eine Leiste ohne sichtbaren Button verschwindet ganz
- **Anzeigeregeln** je Leiste: nur auf bestimmten Seiten oder Inhaltstypen (mindestens eine Regel muss zutreffen), dazu Sprachen mit WPML oder Polylang. „Nicht anzeigen auf“ gilt weiterhin
- **Klick-Tracking**: Ereignis (Standard `floating_button_click`) an `dataLayer` und, falls vorhanden, `gtag` mit den Parametern `bar_name`, `button_label`, `button_action` und bei Links `link_url`

Pro-Code liegt in `src/Pro/` und `assets/pro/`. Pro-Felder werden auch in der Gratisversion gespeichert und bleiben beim Speichern erhalten, damit nach einem Upgrade nichts fehlt.

## Installation

1. Gratisversion aus wordpress.org bzw. Pro-ZIP aus Freemius installieren und aktivieren
2. *b83 → Floating Buttons → Neue Leiste*

Updates kommen für die Gratisversion von wordpress.org, für Pro von Freemius. Die bisherigen GitHub-Versionen (bis 1.1.1) haben denselben Slug: Version 2.0.0 ersetzt sie mit allen Einstellungen.

## Release

1. Version in `b83-floating-buttons.php` (Header und `B83_FB_VERSION`) und `Stable tag` in `readme.txt` erhöhen
2. Abschnitt in `CHANGELOG.md` und im Changelog der `readme.txt` ergänzen
3. Auf `main` pushen. Die Action lädt die Version zu Freemius hoch (Status „pending“) und legt ein GitHub-Release mit Gratis- und Pro-ZIP an
4. Im Freemius-Dashboard unter *Deployment* freigeben

## Übersetzungen

Texte im Code sind englisch, Deutsch steht in `tools/i18n/de.json`. Nach Textänderungen:

```bash
python3 tools/i18n/extract.py . /tmp/strings.json && python3 tools/i18n/build.py /tmp/strings.json languages
```

## Anpassung im Theme

Feste CSS-Klassen: `.b83-fb` (Leiste, zusätzlich `.b83-fb--left|right|top|bottom`), `.b83-fb__item`, `.b83-fb__btn`, `.b83-fb__icon`, `.b83-fb__label`, `.b83-fb__title` (Überschrift), `.b83-fb__sub` (Zusatztext), `.b83-fb__tip`. Jede Leiste hat die ID `#b83-fb-<id>`, jeder Button das Attribut `data-b83-fb-item="<id>"`. Gestaltung über CSS-Variablen `--b83fb-…` (siehe `assets/css/frontend.css`).

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
| `b83_floating_buttons_item_attributes` | Filter | Attribute des Listeneintrags eines Buttons (`array`, Button, Leiste) |
| `b83_floating_buttons_admin_rules` | Action | Weitere Anzeigeregeln im Editor (Pro) |
| `b83_floating_buttons_admin_item_fields` | Action | Weitere Felder eines Buttons im Editor (Pro) |
| `b83_floating_buttons_admin_settings_fields` | Action | Weitere Felder unter Einstellungen (Pro) |
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

Optionen `b83_floating_buttons_groups` (Leisten, automatisch geladen, inkl. Pro-Felder `include_ids`, `include_types`, `languages` und je Button `hours_enabled`, `hours`), `b83_floating_buttons_settings` (inkl. `tracking`, `tracking_event`), `b83_floating_buttons_db_version`. Beim Löschen des Plugins bleiben die Daten erhalten, außer in den Einstellungen ist das Entfernen aktiviert.

## Lizenzen

Plugin: GPL-2.0-or-later. Icons: [Font Awesome Free](https://fontawesome.com) 7.3.1 (Icons CC BY 4.0, siehe `assets/fontawesome/LICENSE.txt`), [Lucide](https://lucide.dev) (ISC), [Simple Icons](https://simpleicons.org) (CC0). Markennamen und -logos gehören den jeweiligen Inhabern.
