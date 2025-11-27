# Changelog

Alle wichtigen Änderungen an diesem Plugin werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [1.0.0] - 2024-11-27

### Hinzugefügt
- Initiale Plugin-Version
- KI-Bildgenerierung mit Google Gemini 3 Pro Image
- Automatische Prompt-Generierung aus Artikelinhalt und Titel
- 13 verschiedene Bildstile (photorealistic, editorial, cinematic, artistic, etc.)
- 10 Stimmungs-Optionen (professionell, dramatisch, ruhig, energetisch, etc.)
- 11 Farbpaletten-Optionen (lebhaft, gedämpft, monochrom, etc.)
- SEO-Integration mit automatischer Metadaten-Generierung
  - RankMath SEO Support
  - Yoast SEO Support
  - Automatische Keyword-Analyse aus Artikelinhalt
- Automatische Alt-Text-Generierung mit SEO-Keywords
- Automatische Bildtitel- und Beschreibungs-Generierung
- Bildoptimierung und -skalierung
  - WebP, JPEG, PNG Format-Support
  - Konfigurierbare Qualitätsstufen (Standard 1080p, Hoch 4K)
  - Automatische Skalierung (Breite, Höhe, beides, keine)
  - Intelligente Komprimierung
- Abschnittsbilder für H2-Überschriften
- Verschlüsselte API-Key-Speicherung
- Admin-Einstellungsseite mit umfangreichen Optionen
- Editor-Metabox mit Benutzerfreundlichem Interface
- Prompt-Vorschau und Bearbeitung
- Bildvorschau vor dem Setzen als Featured Image
- System-Prompt-Konfiguration für Prompt-Generierung
- Negativprompt-Funktion
- Automatische H2-Abschnitte-Erkennung
- Unterstützung für verschiedene Seitenverhältnisse (16:9, 4:3, 1:1, 9:16, 3:4)
- API-Verbindungstest im Admin-Bereich
- Filter und Action Hooks für Entwickler
- Internationalisierung vorbereitet (Text Domain: gemini-image-generator)

### Technische Details
- WordPress 5.8+ Kompatibilität
- PHP 7.4+ erforderlich
- Imagick oder GD für Bildverarbeitung
- OpenSSL für API-Key-Verschlüsselung

---

## [Unreleased]

### Hinzugefügt
- Kontext-bewusste Prompt-Generierung (Kategorien, Tags, Custom Taxonomien)
- Bulk-Bildgenerierung für mehrere Artikel
- Filter-Hooks für Entwickler (gig_prompt_before_generation, gig_image_params, gig_image_metadata)
- Action-Hooks für Entwickler (gig_image_generated, gig_featured_image_set)
- Caching für SEO-Keywords (Transients, 1 Stunde)
- Retry-Logic für API-Requests mit Exponential Backoff
- Verbesserte Fehlerbehandlung und Memory-Optimierungen

### Geändert
- Prompt-Generierung nutzt jetzt Kontext-Informationen (Kategorien, Tags)
- API-Requests haben jetzt automatische Retry-Logic bei Fehlern
- Bessere Fehlermeldungen für API-Requests

## Zukünftige Versionen

### [1.1.0] - Geplant
- Bulk-Generierung für mehrere Artikel
- Bild-Varianten generieren (mehrere Versuche)
- Gutenberg-Block für Bildgenerierung
- Shortcode-Support
- REST API Endpoints
- Erweiterte SEO-Plugin-Unterstützung (All in One SEO, SEOPress)
- Bildverwaltung (Übersicht aller generierten Bilder)
- Statistiken (API-Nutzung, Erfolgsrate)
- Logging & Debug-Modus

### [1.2.0] - Geplant
- Kontext-bewusste Prompts (Artikel-Kategorie, Tags)
- Prompt-Vorschläge basierend auf erfolgreichen Bildern
- Custom Prompt-Templates
- Mehrsprachige Prompt-Generierung
- Bildbearbeitung (Crop, Filter, Overlays)
- Bild-Galerien für Abschnitte
- Batch-Operations UI
- Asynchrone Bildverarbeitung (Background-Jobs)

---

## Format

- **Hinzugefügt** - für neue Features
- **Geändert** - für Änderungen an bestehenden Features
- **Veraltet** - für bald entfernte Features
- **Entfernt** - für entfernte Features
- **Behoben** - für Bugfixes
- **Sicherheit** - für Sicherheits-Updates

