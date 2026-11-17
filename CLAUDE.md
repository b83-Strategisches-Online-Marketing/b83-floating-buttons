# b83 Floating Buttons – öffentliche Gratisversion

> **Dieses Repository ist nur ein Abbild der Gratisversion. Hier nicht entwickeln.** Entwickelt wird im privaten Repository `b83-floating-buttons-pro`. Die Standards unten gelten dort.

> Diese Datei liest Claude Code bei jedem Start. Sie gilt für alle im Team.
> Persönliche Notizen gehören in `CLAUDE.local.md` (wird nicht eingecheckt).

## Über dieses Plugin

- **Name:** b83 Floating Buttons
- **Slug / Text-Domain:** `b83-floating-buttons`
- **Präfix für Funktionen, Optionen, Hooks:** `b83_fb_` (Namespace: `B83\FloatingButtons`)
- **Repository:** github.com/b83-Strategisches-Online-Marketing/b83-floating-buttons (öffentlich)
- **Vertrieb:** Freemium über Freemius (Freemius-ID 43519, Pro-Plan mit 30 Tagen Testphase). Die Gratisversion kommt von wordpress.org, Pro von Freemius. Dieses öffentliche Repository ist nur das Abbild der von Freemius erzeugten Gratisversion und liefert Websites mit 1.x (eingebauter GitHub-Updater, in 2.x entfernt) das Update auf 2.x. Das private Repository heißt `b83-floating-buttons-pro`, weil dieser Name hier schon belegt ist.
- **Im Einsatz auf:** Kundenseiten von b83 mit eigener unbegrenzter Pro-Lizenz; welche Seiten genau, ist offen (Wolfgang ergänzt)
- **Zweck in einem Satz:** Floating Buttons am Seitenrand mit Icons, Info-Texten, Links, E-Mail, Telefon, WhatsApp und Chat.

## b83-Standards (gelten für alle b83-Plugins)

### Plugin-Header
```
Author:            b83 – Wolfgang Grabs-Schrempf
Author URI:        https://b83.at
Plugin URI:        https://b83.at
Requires PHP:      8.1
Requires at least: 6.2
Text Domain:       b83-floating-buttons
License:           GPL-2.0-or-later
Update URI:        https://github.com/b83-Strategisches-Online-Marketing/b83-floating-buttons   (nicht bei Freemius-Plugins)
```
Kontakt in Doku und Support-Texten: office@b83.at

### Technik
- PHP 8.1 oder neuer. Typdeklarationen verwenden, wo sinnvoll.
- WordPress Coding Standards (WPCS). Kein schließendes `?>` am Dateiende.
- Jede PHP-Datei beginnt mit `defined( 'ABSPATH' ) || exit;`
- Neue Plugins: Klassen mit Namespace (`B83\PluginName`) und Autoloader. Dann **keine** `class_exists`-Guards.
- Klassen-Guards (`if ( ! class_exists( '...' ) )`) nur bei älteren Plugins ohne Namespace.
- `uninstall.php` beginnt mit `defined( 'WP_UNINSTALL_PLUGIN' ) || exit;` (nicht ABSPATH).
- Einstellungen über die Settings API, Optionen gesammelt in einem Array unter `b83_fb_settings`.
- **Admin-Menü:** Einstellungen hängen unter dem gemeinsamen Menü „b83“ (Slug `b83`). Wenn das Menü noch nicht existiert, legt das Plugin es selbst an. Kein eigenes Top-Level-Menü.
- Muss ohne Elementor und ohne SEOPress funktionieren. Integrationen nur, wenn das jeweilige Plugin aktiv ist.
- Assets nur dort laden, wo sie gebraucht werden (nicht global im Frontend).
- Eigene Actions und Filter mit Präfix an sinnvollen Stellen anbieten und im README dokumentieren.
- CSS/JS als eigene Dateien, kein Inline-Code außer kleiner Konfiguration (`wp_localize_script`).

### Struktur
- Eine Zuständigkeit → eine Datei reicht.
- Zwei bis drei Bereiche → `includes/` mit einer Klasse pro Bereich.
- Vier oder mehr → tiefere Ordnerstruktur mit Autoloader.
- Assets in `assets/css`, `assets/js`, `assets/img`. Sprachdateien in `languages/`.

**So sind die bestehenden b83-Plugins aufgebaut** (bei neuen Plugins übernehmen):
- Hauptdatei nur Header, Konstanten (`B83_<KÜRZEL>_VERSION`, `_FILE`, `_DIR`, `_URL`), PSR-4-Autoloader auf `src/` und Bootstrap.
- Logik in `src/`, Backend in `src/Admin/`, Ausgabe in `src/Frontend/`, Fremd-Plugins in `src/Integrations/`, Datenbank-Version als Option und Migrationen in `src/Migration.php`.
- `src/Admin/B83Menu.php` ist eine Kopie der gemeinsamen Menüklasse (nur Namespace und Text Domain angepasst), dazu `assets/img/b83-logo.svg`, `assets/img/b83-menu-icon.svg`, `assets/css/b83-menu.css`. Eigene Seite mit `add_submenu_page( B83Menu::SLUG, … )`, Link über den Filter `b83_admin_plugin_links`. Wird die Klasse geändert, `MENU_VERSION` erhöhen und in alle Plugins übernehmen: Die neueste Version auf einer Website baut das Menü.
- Eigene `Updater`-Klasse (Filter `update_plugins_github.com`). `.github/workflows/release.yml` baut das Release automatisch, sobald eine neue Version auf `main` liegt (Freemius-Plugins: Upload zu Freemius).
- Im Repo: `README.md`, `CHANGELOG.md`, `SECURITY.md` (Meldeweg für Lücken), `.github/dependabot.yml`.
- `uninstall.php` löscht Daten nur, wenn das in den Einstellungen aktiviert ist.
- Keine Daten im Plugin-Ordner speichern, er wird bei Updates ersetzt.

### Sicherheit (nicht verhandelbar)
- Jede Formular- und AJAX-Aktion: Nonce prüfen **und** `current_user_can()`.
- Eingaben: `wp_unslash`, dann zentral validieren (Whitelist statt Blacklist), Längen begrenzen, mit `sanitize_*` bereinigen.
- Ausgaben spät escapen mit `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`.
- Datenbank nur über `$wpdb->prepare()`. REST-Routen immer mit `permission_callback`.
- Uploads: `is_uploaded_file`, Größe und Format prüfen.
- Hinweise per festem Schlüssel in der URL, nie Freitext aus der URL ausgeben.
- AJAX: vor `wp_send_json_*()` den Output-Buffer leeren (`ob_clean()` falls aktiv), sonst kaputtes JSON.
- Keine Secrets im Code. Tokens kommen aus `wp-config.php` (z. B. `B83_GITHUB_TOKEN`).

### Updates und Lizenz
- Öffentliche Repos: Updates direkt über GitHub-Releases.
- Private Repos: Update-Abfrage mit `B83_GITHUB_TOKEN` aus der `wp-config.php` (Fine-grained Token, nur Lesen).
- Das Release-Asset heißt immer `<slug>.zip`, ohne Versionsnummer. Download-Link für andere: `https://github.com/b83-Strategisches-Online-Marketing/<slug>/releases/latest/download/<slug>.zip`. Nie „Code → Download ZIP“ weitergeben: Das erzeugt einen Ordner `<slug>-main`, und Updates greifen danach nicht mehr.
- Freemius-Plugins: SDK im Ordner `freemius/` (bzw. `vendor/freemius/`), nicht verändern. Freemius-Secrets nie in öffentliche Repos.
  - Pro-Code nur in eigenen `if ( …_fs()->can_use_premium_code__premium_only() ) { … }`-Blöcken, nie mit anderen Bedingungen kombiniert oder einer Variablen zugewiesen. Sonst erzeugt Freemius die Gratisversion nicht sauber. Gratis-Code verwendet keine Klassen aus `src/Pro/`.
  - Neue Versionen bleiben bei Freemius auf „pending“. Wolfgang gibt jede Version selbst frei. Nach jedem Release daran erinnern.
  - Plugin Check nur mit der Gratisversion ausführen. Die Pro-Version liegt im Ordner `<slug>-premium` und erzeugt falsche Meldungen (Text Domain, Update URI, Name).
  - Die Kundenseiten von b83 laufen mit einer eigenen unbegrenzten Pro-Lizenz.

### Sprache und Texte
- **Interne Plugins:** Oberfläche und Code-Kommentare auf Deutsch. Englisch kommt bei Bedarf über `languages/`.
- **Verkaufs-Plugins:** Oberfläche (Quellsprache) und Code-Kommentare auf Englisch, Deutsch über `languages/` (de_DE, de_AT, de_CH). Wird ein internes Plugin zum Verkaufs-Plugin, vorher mit `/uebersetzen` umstellen.
- Bezeichner (Klassen, Funktionen, Variablen) immer englisch.
- Oberfläche immer übersetzbar (`__()`, `esc_html__()` usw.).
- Beschreibungen, readme.txt, Doku und Marketingtexte ohne typische KI-Schreibmuster formulieren (Humanizer-Regeln): keine Floskeln, keine Übertreibungen, keine Gedankenstrich-Ketten.
- Kein „DSGVO-konform“ in Beschreibungen oder Slogans. Ob eine Seite rechtskonform ist, hängt von ihrer Konfiguration ab.

## Erfahrungen aus allen b83-Plugins

- **Theme- und Elementor-CSS überschreibt Plugin-Stile.** Elementor-Kit-Regeln (`.elementor-kit-N a { color: … }`) und Theme-Linkfarben haben Buttons in mehreren Plugins umgefärbt. Deshalb: Selektoren unter eigener ID scopen oder Klassen verdoppeln (`.b83-x.b83-x`), Farben über CSS-Variablen setzen und bei den eigentlichen Farbeigenschaften `!important` verwenden. Alle Zustände (hover, focus, visited) abdecken.
- **b83-Orange:** `#d96f21` auf Weiß erfüllt WCAG AA nicht. Für Text und Buttons `#b85a12` verwenden.
- **Seiten-Cache:** WP Rocket läuft auf vielen Kundenseiten. Zeitabhängige Anzeigen (Zeitfenster, Öffnungszeiten, veraltete Daten) zusätzlich im Browser prüfen, nicht nur beim Rendern. Nach dem Speichern den Cache der betroffenen Seiten leeren. Bei WP Rocket die nötigen Ausnahmen (z. B. „JavaScript verzögern“, LazyLoad) per Filter setzen.
- **Externe Dienste:** Nur serverseitig abrufen und zwischenspeichern, damit keine Besucher-IP beim Dienst landet. Bibliotheken, Icons und Schriften lokal mitliefern, kein CDN und keine Google Fonts.
- **B83Menu:** Pro-Versionen von Freemius liegen in einem anderen Ordner (`<slug>-premium`). Plugins deshalb nie am Ordnernamen erkennen. Fehlt das b83-Menü oder ist es doppelt, ist die Ursache meist die White-Label-Funktion von Astra Pro mit demselben Slug `b83` (z. B. swstb.at). Das ist kein Fehler im Plugin, dort die Astra-Einstellung ändern.
- **Elementor speichert Pfade mit Backslashes** (`plugin\/assets\/…`). Beim Suchen und Ersetzen in der Datenbank nur nach dem Dateinamen suchen.
- **Tests:** Elementor ließ sich in früheren Testumgebungen meist nicht installieren. Immer offen sagen, was nur simuliert getestet wurde und was nicht in echtem WordPress lief.

## Arbeitsweise

- Nur die Dateien lesen, die für die Aufgabe nötig sind. `freemius/`, `vendor/` und Build-Ordner nie durchsuchen.
- Vor größeren Änderungen kurz den Plan nennen, dann umsetzen.
- Sagt Wolfgang „nur meine Frage“ oder „noch nichts ändern“, wird zuerst besprochen und nichts umgesetzt.
- Nach jeder Änderung: `php -l` auf geänderte Dateien.
- Bestehende Shortcodes, Optionen und Hooks bleiben abwärtskompatibel, außer es ist ausdrücklich anders entschieden.
- Bei Reviews trennen: **kritisch** (Sicherheit, Datenverlust, Fatal Errors) vs. **sollte** vs. **wäre schön**.
- Versionsnummer nur beim Release erhöhen (siehe `/release`). Version in Header, Versionskonstante, `CHANGELOG.md` und `readme.txt` gleich halten.
- Ein erfolgreich gebautes Release erledigt keine offenen Folgeaufgaben (wordpress.org-Assets, Freemius-Freigabe, Einstellungen auf Kundenseiten). Diese getrennt nachverfolgen.
- Dependabot-PRs nur nach ausdrücklichem OK mergen.
- Commit-Nachrichten auf Deutsch, kurz, im Präsens („Fügt Ticker-Tempo hinzu“).

## Offene Ideen für alle Plugins

- Slogan „Made in Austria“ als Eintrag in der Meta-Zeile der Plugin-Liste (`plugin_row_meta`), zentral über B83Menu mit neuer `MENU_VERSION`. Wortlaut noch offen.
- Icons und Banner aus `.wordpress-org/` nach der Freigabe eines Plugins per SVN in `/assets` auf wordpress.org hochladen (braucht Wolfgangs SVN-Zugang).

## Entscheidungen in diesem Plugin

> Hier festhalten, was entschieden wurde, damit niemand es neu diskutiert.
> Wo eine Entscheidung von den b83-Standards oben abweicht, gilt für dieses Plugin die Entscheidung.

- Seit Version 2.0 ist dieses Repository nur noch ein Abbild der von Freemius erzeugten Gratisversion, hier wird nicht entwickelt:
  - Entwicklung, Pro-Code und Plugin-Standard: privates Repository `b83-floating-buttons-pro`
  - Zweck: Websites mit Version 1.x holen sich über den eingebauten GitHub-Updater das Update auf 2.x. Ab 2.x kommen Updates von wordpress.org (Gratis) bzw. Freemius (Pro)
  - Neue Versionen hier nur ablegen, wenn ältere 1.x-Installationen sie brauchen: Inhalt der Datei `b83-floating-buttons-free.zip` aus dem Release des privaten Repositorys übernehmen, `.github/` behalten, pushen
- Entscheidungen aus der Entwicklung (Quelle: privates Repository):
  - Der Slug `b83-floating-buttons` bleibt, damit Seiten mit 1.x das Update auf 2.x als normales Update bekommen (gleicher Ordner, gleiche Einstellungen).
  - Gratis: mehrere Leisten, Export/Import, „Nicht anzeigen auf“. Pro: Anzeigeregeln (Seiten, Inhaltstypen, Sprachen über WPML/Polylang), Öffnungszeiten je Button (bis zu drei Zeiträume pro Wochentag), Klick-Tracking an GA4 bzw. GTM.
  - Öffnungszeiten werden im Browser geprüft, damit sie mit Seiten-Cache stimmen.
  - Das Klick-Tracking setzt selbst keine Cookies, die Einwilligung regelt der Cookie Banner.
  - Pro-Einstellungen bleiben in der Gratisversion gespeichert, damit nach einer verlängerten Lizenz alles wieder da ist.
  - Die Überschrift im Button ist bewusst kein echtes `<h5>` (Überschriftenstruktur der Seite, SEO, Barrierefreiheit).
  - Elementor-Kit-CSS überschrieb Icon-Farben (behoben in 1.1.1): CSS-Variablen je Zustand plus `!important` beibehalten.
- Abweichungen von den b83-Standards, bewusst belassen (Rückwärtskompatibilität, nicht umbenennen):
  - Optionen heißen `b83_floating_buttons_groups`, `b83_floating_buttons_settings`, `b83_floating_buttons_db_version` statt `b83_fb_settings`. Hooks und Filter beginnen mit `b83_floating_buttons_`. AJAX- und Nonce-Aktionen mit `b83_fb_`. Die Funktion `b83fb_fs()` kommt von Freemius.
  - Einstellungen liegen in zwei Optionen (Leisten, Einstellungen) statt in einem Array.
  - Kein `uninstall.php`: Aufräumen läuft über Freemius `after_uninstall` (nur bei aktivierter Löschoption).
  - Freemius-SDK liegt in `vendor/freemius/` (laut Standard erlaubt).

## Offene Punkte

- Quellsprache der Oberfläche ist Englisch (passt zum Verkaufs-Plugin). Code-Kommentare sind teils Deutsch (z. B. Hauptdatei, `AdminPage.php`); laut Standard für Verkaufs-Plugins Englisch. Nicht umgestellt, im privaten Repository entscheiden.
- Kundenseiten mit Einsatz dieses Plugins eintragen.
- Icons, Banner und Screenshots auf wordpress.org per SVN hochladen, falls noch nicht geschehen.
