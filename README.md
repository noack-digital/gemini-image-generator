# Gemini Image Generator

Ein WordPress-Plugin zur automatischen Generierung von KI-basierten Artikelbildern mit Google Gemini 3 Pro Image direkt aus dem WordPress-Editor.

## Übersicht

Das **Gemini Image Generator** Plugin ermöglicht es Redakteuren, hochwertige Artikelbilder automatisch zu generieren, ohne die WordPress-Oberfläche zu verlassen. Das Plugin nutzt Google Gemini 3 Pro Image für die Bildgenerierung und bietet umfangreiche SEO-Integration sowie automatische Metadaten-Generierung.

## Hauptfeatures

- 🤖 **KI-Bildgenerierung** - Direkte Integration von Google Gemini 3 Pro Image
- ✨ **Automatische Prompt-Generierung** - Erstellt Bildprompts aus Artikelinhalt und Titel
- 🎨 **Stil-Optionen** - 13+ verschiedene Bildstile (fotorealistisch, künstlerisch, cinematic, etc.)
- 🔍 **SEO-Integration** - Automatische Alt-Texte, Titel und Beschreibungen mit Keyword-Integration
  - RankMath SEO Support
  - Yoast SEO Support
  - Automatische Keyword-Analyse
- 📐 **Bildoptimierung** - Automatische Skalierung und Formatkonvertierung
  - WebP, JPEG, PNG Support
  - Konfigurierbare Qualitätsstufen
  - Intelligente Komprimierung
- 📝 **Abschnittsbilder** - Generiert Bilder für H2-Überschriften
- 🔒 **Sicherheit** - Verschlüsselte API-Key-Speicherung

## Systemanforderungen

- **WordPress:** 5.8 oder höher
- **PHP:** 7.4 oder höher
- **PHP-Erweiterungen:**
  - `imagick` (empfohlen) oder `gd` für Bildverarbeitung
  - `openssl` für API-Key-Verschlüsselung
  - `libxml` für HTML-Parsing
- **Google Gemini API Key** - Erhältlich unter https://aistudio.google.com/app/apikey

## Installation

### 1. Plugin hochladen

```bash
# Via Git (empfohlen für Updates)
cd wp-content/plugins/
git clone <repository-url> gemini-image-generator

# Oder manuell: Plugin-Dateien in wp-content/plugins/gemini-image-generator/ kopieren
```

### 2. Plugin aktivieren

1. Gehen Sie zu **Plugins** im WordPress-Admin
2. Aktivieren Sie **Gemini Image Generator**

### 3. API-Key konfigurieren

1. Gehen Sie zu **Einstellungen → Gemini Image**
2. Tragen Sie Ihren **Google Gemini API Key** ein
   - API-Key erstellen: https://aistudio.google.com/app/apikey
3. Wählen Sie das gewünschte **Textmodell** für Prompt-Generierung
4. Speichern Sie die Einstellungen

### 4. Optional: Erweiterte Einstellungen

- **Prompt-Einstellungen** - System-Prompt und Negativprompt anpassen
- **Standard Bild-Einstellungen** - Stil, Stimmung, Farbpalette vorkonfigurieren
- **Skalierung & Optimierung** - Bildgrößen und Komprimierung anpassen
- **SEO & Barrierefreiheit** - Automatische Metadaten aktivieren

## Verwendung

### Artikelbild generieren

1. Öffnen Sie einen **Beitrag** oder eine **Seite** im WordPress-Editor
2. In der **Metabox "🎨 KI Artikelbild"** (rechts im Editor):
   - Klicken Sie auf **"✨ Prompt generieren"** - Das Plugin analysiert Ihren Artikel
   - Passen Sie den Prompt bei Bedarf an
   - Wählen Sie **Stil**, **Stimmung** und **Farbpalette**
   - Klicken Sie auf **"🖼️ Bild generieren"**
3. Nach 30-60 Sekunden erscheint das generierte Bild
4. Klicken Sie auf **"✓ Als Artikelbild"** um es als Featured Image zu setzen

### Abschnittsbilder generieren

1. In der Metabox scrollen Sie nach unten zu **"Abschnittsbilder (H2)"**
2. Das Plugin erkennt automatisch alle H2-Überschriften
3. Für jeden Abschnitt:
   - Klicken Sie auf **"Prompt generieren"**
   - Passen Sie den Prompt an und generieren Sie das Bild
   - Das Bild wird automatisch in den Artikel eingefügt

### Einstellungen anpassen

**Einstellungen → Gemini Image** bietet umfangreiche Konfigurationsmöglichkeiten:

#### 🔑 API Konfiguration
- API-Key (verschlüsselt gespeichert)
- Textmodell für Prompt-Generierung (Gemini 2.0 Flash oder 3 Pro)

#### ✨ Prompt-Einstellungen
- **System-Prompt**: Steuert die automatische Prompt-Generierung
- **Negativprompt**: Elemente die vermieden werden sollen
- **Englische Prompts**: Für bessere Ergebnisse

#### 🎨 Standard Bild-Einstellungen
- **Standard-Stil**: 13 Optionen (photorealistic, editorial, cinematic, etc.)
- **Standard-Stimmung**: 10 Optionen (professionell, dramatisch, ruhig, etc.)
- **Standard-Farbpalette**: 11 Optionen (lebhaft, gedämpft, monochrom, etc.)
- **Technische Defaults**: Seitenverhältnis (16:9, 4:3, 1:1, etc.), Qualität, Format

#### 📐 Skalierung & Optimierung
- Automatische Skalierung aktivieren/deaktivieren
- Modus: Nur Breite, Nur Höhe, Maximale Größe, Keine Skalierung
- JPEG/WebP Qualität (50-100%)

#### 🔍 SEO & Barrierefreiheit
- Automatische SEO-Metadaten aktivieren
- Standard-Untertitel konfigurieren
- Unterstützt RankMath und Yoast SEO Keywords

## Entwickler-Dokumentation

### Hooks & Filter

#### Actions

```php
// Wird ausgelöst wenn ein Bild generiert wurde
do_action('gig_image_generated', $attachment_id, $post_id, $prompt);

// Wird ausgelöst wenn ein Featured Image gesetzt wurde
do_action('gig_featured_image_set', $post_id, $attachment_id);
```

#### Filters

```php
// Unterstützte Post-Types anpassen
apply_filters('gig_supported_post_types', array('post', 'page'));

// Prompt vor der Generierung anpassen
apply_filters('gig_prompt_before_generation', $prompt, $post_id);

// Bildparameter anpassen
apply_filters('gig_image_params', $params, $post_id);

// Metadaten anpassen
apply_filters('gig_image_metadata', $metadata, $post_id, $prompt);
```

### Klassen-Struktur

- **`GIG_Plugin`** - Haupt-Plugin-Klasse (Singleton)
- **`GIG_Gemini_API`** - API-Wrapper für Google Gemini
- **`GIG_Prompt_Generator`** - Generiert Prompts aus Artikelinhalt
- **`GIG_Image_Handler`** - Verarbeitet Bilder (Skalierung, Konvertierung)
- **`GIG_Admin_Settings`** - Admin-Einstellungsseite
- **`GIG_Editor_Metabox`** - Metabox im Editor

### Konstanten

```php
GIG_PLUGIN_VERSION        // Aktuelle Plugin-Version
GIG_PLUGIN_FILE           // Haupt-Plugin-Datei-Pfad
GIG_PLUGIN_DIR            // Plugin-Verzeichnis-Pfad
GIG_PLUGIN_URL            // Plugin-URL
GIG_PLUGIN_BASENAME       // Plugin-Basename
```

### Optionen

Alle Einstellungen werden in der WordPress-Option `gig_settings` gespeichert.

## Fehlerbehebung

### "API-Key nicht konfiguriert"
- Gehen Sie zu **Einstellungen → Gemini Image**
- Tragen Sie einen gültigen Google Gemini API-Key ein

### "WebP-Konvertierung nicht verfügbar"
- Installieren Sie die PHP-Erweiterung `imagick`
- Oder aktivieren Sie GD mit WebP-Support

### Bilder werden nicht generiert
- Überprüfen Sie die **PHP-Fehlermeldungen** im Debug-Log
- Testen Sie die API-Verbindung mit dem **"Verbindung testen"** Button
- Überprüfen Sie ob genügend **Speicherplatz** vorhanden ist

### Timeout-Fehler
- Erhöhen Sie `max_execution_time` in `php.ini`
- Überprüfen Sie die **Server-Zeitlimits**

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) für detaillierte Versionshistorie.

## Entwickler-Guide

Siehe [DEVELOPMENT.md](DEVELOPMENT.md) für detaillierte Entwickler-Dokumentation.

## Support

Bei Fragen oder Problemen:
- Überprüfen Sie die [Fehlerbehebung](#fehlerbehebung)
- Konsultieren Sie die [Entwickler-Dokumentation](#entwickler-dokumentation)

## Lizenz

Dieses Plugin wurde für Digital Magazin entwickelt.

## Credits

- **Google Gemini API** - https://ai.google.dev/
- Entwickelt für **Digital Magazin**

---

**Version:** 1.0.0  
**Letztes Update:** 2024

