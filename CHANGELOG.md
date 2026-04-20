# Changelog

Alle wichtigen Änderungen an diesem Plugin werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [1.2.0] - 2026-04-20

### Hinzugefügt
- Textmodell-Dropdown deutlich erweitert: `gemini-2.5-flash`, `gemini-flash-latest`, `gemini-2.5-pro`, `gemini-3-pro-preview`, `gemini-2.0-flash`. Vorher nur zwei Optionen.
- Neue Helper-Methode `GIG_Gemini_API::get_supported_text_models()` (analog zu `get_supported_image_models()`).
- Beschreibung beim Textmodell weist auf modellspezifische Rate-Limits hin – bei 429-Fehlern können Nutzer auf ein anderes Modell wechseln.

### Geändert
- **Default-Textmodell von `gemini-2.0-flash` auf `gemini-2.5-flash` geändert.** Grund: `gemini-2.0-flash` ist auf manchen Paid-Tier-1-Keys aktuell stark gedrosselt (in Tests 0/8 Requests erfolgreich), während `gemini-2.5-flash` und neuere Modelle einwandfrei laufen (8/8). Bestehende Installationen bleiben auf ihrer konfigurierten Auswahl und können manuell wechseln.

## [1.1.0] - 2026-04-20

### Behoben
- Bildgenerierung schlug mit „Request contains an invalid argument" fehl, weil der Parameter `imageConfig.imageSize` vom Modell `gemini-3-pro-image-preview` nicht akzeptiert wird. Der Parameter wurde aus dem Request entfernt.
- Der WordPress-Auto-Draft-Platzhalter „Automatisch gespeicherter Entwurf" erschien in Alt-Text, Bildtitel und Accessibility-Beschreibung. `sanitize_post_title()` entfernt den Platzhalter jetzt sowohl als kompletten Titel als auch als Präfix vor echten Titeln (case-insensitive, inkl. Trennzeichen `:`, `–`, `—`, `-`, `|`). Die Bereinigung greift auch bei der Prompt-Generierung für Gemini, damit der Platzhalter nicht mehr indirekt in die KI-Antworten leckt.
- Transient-Cache für SEO-Keywords wird bei Auto-Draft-Posts nicht mehr gespeichert (sonst würde ein aus dem Platzhalter abgeleitetes Keyword 1 Stunde hängen bleiben).

### Hinzugefügt
- Neue Einstellung **Bildmodell** (Dropdown) zur Auswahl des Gemini-Image-Modells: `gemini-3-pro-image-preview`, `gemini-3.1-flash-image-preview`, `gemini-2.5-flash-image`, `nano-banana-pro-preview`. Vorher war das Modell hartkodiert.
- Neue Einstellung **Caption-Prompt**: Textarea im SEO-Abschnitt für einen System-Prompt, der die KI-Generierung des Bild-Untertitels steuert (Stil, Länge, Tonalität). Fokus-Keywords, Artikel-Titel und Bildmotiv werden automatisch als Kontext übergeben. Fällt bei leerem Prompt oder API-Fehlern auf `default_caption` zurück.
- Neue Methode `GIG_Gemini_API::get_rankmath_keywords()`: Liefert **alle** RankMath-Fokus-Keywords als Array (Fallback auf Yoast). Der Alt-Text enthält jetzt immer sämtliche Fokus-Keywords, nicht nur das erste.

### Geändert
- Feld „Standard-Untertitel (Caption)" umbenannt in „Fallback-Untertitel" — wird nur noch verwendet, wenn kein Caption-Prompt gesetzt ist oder die KI-Generierung fehlschlägt.
- Alt-Text-Logik neu priorisiert: Wenn RankMath-Keywords vorhanden sind, werden sie vollständig aufgenommen; der Post-Titel wird nur angehängt, wenn das 125-Zeichen-Limit es erlaubt und er zusätzliche Info liefert.

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

