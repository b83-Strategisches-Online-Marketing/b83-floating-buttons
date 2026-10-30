# Changelog

Alle wichtigen Änderungen an b83 Floating Buttons. Versionen nach [Semantic Versioning](https://semver.org/lang/de/).

## 2.0.1 – 2026-10-30

- Neu zu Freemius hochgeladen, nachdem der Pro-Plan angelegt war (2.0.0 hatte keine Pro-Version)

## 2.0.0 – 2026-10-30

- Wird Freemium-Plugin: Gratisversion über wordpress.org, Pro mit Lizenz über Freemius (Testphase 30 Tage)
- Neu in Pro: Öffnungszeiten je Button (cachefest, im Browser geprüft, Zeitzone der Website)
- Neu in Pro: Anzeigeregeln je Leiste (nur auf bestimmten Seiten, Inhaltstypen oder Sprachen mit WPML/Polylang)
- Neu in Pro: Klick-Tracking als Ereignis für Google Analytics 4 und den Google Tag Manager
- Texte im Code englisch, deutsche Übersetzung mitgeliefert (de_DE, de_AT, de_CH)
- Updates nicht mehr über GitHub, sondern über wordpress.org bzw. Freemius
- Neuer Filter `b83_floating_buttons_item_attributes` für die Attribute eines Buttons
- Einstellungen und Leisten bleiben beim Umstieg unverändert erhalten

## 1.1.1 – 2026-10-23

- Behoben: Globale Link-Farben aus Theme oder Elementor (Website-Einstellungen → Globale Farben/Links) überschrieben die Icon-Farbe der Buttons. Hintergrund-, Icon-, Text- und Info-Text-Farben aus dem Plugin haben jetzt immer Vorrang
- Icons und Texte zusätzlich gegen allgemeine Theme-Regeln wie „svg { fill: … }“ oder „a span { color: … }“ abgesichert

## 1.1.0 – 2026-10-21

- Neu: Icons aus Font Awesome Free 7 (über 2.100 Icons: Solid, Regular, Marken) mit Suche und Stil-Filter. Das gewählte Icon wird beim Speichern als SVG am Button abgelegt – im Frontend wird keine Font-Awesome-Schrift und kein zusätzliches CSS geladen
- Neu: optionaler Zusatztext in kleiner Schrift unter der Überschrift, eigene Schriftgröße je Leiste
- Neu: Aktion „Chat / Messenger“ für Messenger-, Telegram-, Signal-, Skype-, Viber- und Teams-Links sowie Chat-Systeme mit eigener Adresse
- Schriftgröße der Überschrift bis 32 px einstellbar (bisher 24 px)
- Neue Buttons starten mit einem Font-Awesome-Icon; bestehende Buttons bleiben unverändert

## 1.0.0 – 2026-10-16

Erste Version.

- Leisten mit schwebenden Buttons am linken, rechten, oberen oder unteren Rand, Ausrichtung Anfang/Mitte/Ende mit Abstand
- Je Button: Icon aus 76 mitgelieferten Icons (inkl. WhatsApp, Facebook, Instagram, YouTube, Booking.com u. a.) oder eigene Grafik aus der Mediathek
- Text klappt bei Mouseover verzögert aus, Verzögerung und Animationsdauer einstellbar; Buttons können eingeklappt teilweise im Rand verborgen sein und herausfahren
- Info-Text (Tooltip) je Button, erscheint nach dem Ausklappen
- Aktionen: Link, Telefon, E-Mail (mit Betreff), WhatsApp (mit Nachricht), SMS, nach oben scrollen, nur Info
- Links wahlweise in neuem Fenster, optional nofollow
- Gestaltung je Leiste: Größe, Icon-Größe, Eckenradius, Schatten, Schrift, Farben normal und Mouseover, Info-Text-Farben; eigene Farben je Button
- Responsiv: Anzeige je Gerät (Desktop/Tablet/Mobil), eigene Größe mobil, mobile Darstellung wie Desktop, nur Icons oder als Leiste am unteren Rand
- Touch-Geräte: erstes Antippen klappt aus, zweites öffnet; Tastaturbedienung und Screenreader-Texte
- Seiten ausschließen
- Live-Vorschau im Backend für Desktop, Tablet und Mobil
- Export und Import aller Leisten (Übertragung auf andere Websites)
- Seiten-Cache wird nach dem Speichern automatisch geleert (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, SiteGround)
- Gemeinsames b83-Menü, Updates über GitHub-Releases
