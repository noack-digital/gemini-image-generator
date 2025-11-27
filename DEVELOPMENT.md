# Entwickler-Dokumentation

Diese Dokumentation richtet sich an Entwickler, die das Gemini Image Generator Plugin erweitern oder anpassen möchten.

## Projekt-Struktur

```
gemini-image-generator/
├── gemini-image-generator.php    # Haupt-Plugin-Datei
├── includes/                      # Core-Klassen
│   ├── class-gemini-api.php      # Gemini API Wrapper
│   ├── class-prompt-generator.php # Prompt-Generierung
│   └── class-image-handler.php   # Bildverarbeitung
├── admin/                         # Admin-Interface
│   ├── class-admin-settings.php  # Einstellungsseite
│   ├── class-editor-metabox.php  # Editor-Metabox
│   ├── css/
│   │   └── editor-metabox.css    # Metabox-Styles
│   └── js/
│       ├── editor-metabox.js     # Metabox-JavaScript
│       └── settings.js           # Settings-JavaScript
├── languages/                     # Übersetzungen
│   └── index.php                 # Sicherheitsdatei
├── README.md                      # Benutzer-Dokumentation
├── CHANGELOG.md                   # Versionshistorie
├── DEVELOPMENT.md                 # Diese Datei
└── .gitignore                     # Git-Ignore-Datei
```

## Architektur

### Klassen-Hierarchie

```
GIG_Plugin (Singleton)
├── GIG_Gemini_API
├── GIG_Prompt_Generator
│   └── verwendet: GIG_Gemini_API
├── GIG_Image_Handler
├── GIG_Admin_Settings
│   └── verwendet: GIG_Gemini_API
└── GIG_Editor_Metabox
    ├── verwendet: GIG_Gemini_API
    ├── verwendet: GIG_Prompt_Generator
    └── verwendet: GIG_Image_Handler
```

### Datenfluss

1. **Benutzer generiert Prompt:**
   - `GIG_Editor_Metabox` → `GIG_Prompt_Generator` → `GIG_Gemini_API`

2. **Benutzer generiert Bild:**
   - `GIG_Editor_Metabox` → `GIG_Gemini_API::generate_image()` → `GIG_Image_Handler::save_base64_image()`

3. **Bildverarbeitung:**
   - `GIG_Image_Handler` → Imagick/GD → Optimierung → WordPress Media Library

## API-Referenz

### GIG_Gemini_API

Hauptklasse für die Kommunikation mit der Google Gemini API.

#### Öffentliche Methoden

```php
// Einstellungen abrufen
$settings = $api->get_settings();

// API-Konfiguration prüfen
if ($api->is_configured()) { ... }

// SEO-Keyword extrahieren
$keyword = $api->get_seo_keyword($post_id);

// Text generieren
$text = $api->generate_text($prompt, $system_instruction);

// Bild generieren
$image_data = $api->generate_image($prompt, $params);
// Rückgabe: ['mime_type' => '...', 'data' => '...', 'target_format' => '...']

// Metadaten generieren
$metadata = $api->generate_image_metadata($post_id, $image_prompt);
// Rückgabe: ['alt_text' => '...', 'title' => '...', 'caption' => '...', 'description' => '...', 'keyword' => '...']
```

#### Statische Methoden

```php
// Verschlüsselung
$encrypted = GIG_Gemini_API::encrypt_api_key($api_key);
$decrypted = GIG_Gemini_API::decrypt_api_key($encrypted_key);

// Optionen-Listen
$styles = GIG_Gemini_API::get_supported_styles();
$moods = GIG_Gemini_API::get_supported_moods();
$colors = GIG_Gemini_API::get_supported_colors();
$ratios = GIG_Gemini_API::get_supported_ratios();
$qualities = GIG_Gemini_API::get_supported_qualities();
$formats = GIG_Gemini_API::get_supported_formats();
```

### GIG_Prompt_Generator

Generiert Bildprompts aus Artikelinhalt.

#### Öffentliche Methoden

```php
// Prompt für gesamten Artikel
$prompt = $generator->generate_prompt_for_post($post_id);

// Prompt aus Titel und Inhalt
$prompt = $generator->generate_prompt_from_content($title, $content);

// H2-Abschnitte extrahieren
$sections = $generator->get_h2_sections($post_id);
// Rückgabe: [['id' => '...', 'title' => '...', 'excerpt' => '...', 'heading_html' => '...'], ...]

// Prompt für Abschnitt
$prompt = $generator->generate_prompt_for_section($post_id, $section_id);
```

### GIG_Image_Handler

Verarbeitet und speichert Bilder.

#### Öffentliche Methoden

```php
// Bild speichern
$attachment_id = $handler->save_base64_image(
    $base64_data,      // Base64-kodierte Bilddaten
    $source_mime,      // MIME-Type (z.B. 'image/png')
    $post_id,          // Post-ID (optional)
    $prompt,           // Prompt (für Metadaten)
    $target_format,    // 'webp', 'png', 'jpeg'
    $metadata,         // ['alt_text' => '...', 'title' => '...', ...]
    $resize_override   // ['max_width' => 1200, 'max_height' => 800] (optional)
);

// Featured Image setzen
$handler->set_featured_image($post_id, $attachment_id);

// WebP-Support prüfen
if (GIG_Image_Handler::can_convert_webp()) { ... }
```

### GIG_Admin_Settings

Verwaltet die Admin-Einstellungsseite.

Keine öffentlichen Methoden für Entwickler direkt relevant. Verwendet WordPress Settings API.

### GIG_Editor_Metabox

Metabox im WordPress-Editor.

Keine öffentlichen Methoden. Verwendet AJAX-Endpunkte.

## Hooks & Filter

### Actions

#### `gig_image_generated`

Wird ausgelöst, wenn ein Bild erfolgreich generiert wurde.

```php
do_action('gig_image_generated', $attachment_id, $post_id, $prompt);
```

**Parameter:**
- `$attachment_id` (int) - WordPress Attachment ID
- `$post_id` (int) - Post ID
- `$prompt` (string) - Verwendeter Prompt

**Beispiel:**
```php
add_action('gig_image_generated', function($attachment_id, $post_id, $prompt) {
    // Logging, Notifications, etc.
    error_log("Image generated: {$attachment_id} for post {$post_id}");
}, 10, 3);
```

#### `gig_featured_image_set`

Wird ausgelöst, wenn ein Bild als Featured Image gesetzt wurde.

```php
do_action('gig_featured_image_set', $post_id, $attachment_id);
```

**Parameter:**
- `$post_id` (int) - Post ID
- `$attachment_id` (int) - Attachment ID

### Filters

#### `gig_supported_post_types`

Filtert die unterstützten Post-Types für die Metabox.

```php
$post_types = apply_filters('gig_supported_post_types', array('post', 'page'));
```

**Parameter:**
- `$post_types` (array) - Array von Post-Type-Slugs

**Beispiel:**
```php
add_filter('gig_supported_post_types', function($post_types) {
    $post_types[] = 'product'; // WooCommerce Produkte hinzufügen
    return $post_types;
});
```

#### `gig_prompt_before_generation`

Ermöglicht Anpassung des Prompts vor der Bildgenerierung.

```php
$prompt = apply_filters('gig_prompt_before_generation', $prompt, $post_id);
```

**Parameter:**
- `$prompt` (string) - Der Prompt
- `$post_id` (int) - Post ID

**Beispiel:**
```php
add_filter('gig_prompt_before_generation', function($prompt, $post_id) {
    // Keyword hinzufügen
    $keyword = get_post_meta($post_id, 'custom_keyword', true);
    if ($keyword) {
        $prompt = $keyword . ', ' . $prompt;
    }
    return $prompt;
}, 10, 2);
```

#### `gig_image_params`

Ermöglicht Anpassung der Bildparameter vor der Generierung.

```php
$params = apply_filters('gig_image_params', $params, $post_id);
```

**Parameter:**
- `$params` (array) - Parameter-Array:
  - `ratio` (string) - Seitenverhältnis
  - `quality` (string) - Qualität
  - `format` (string) - Dateiformat
  - `style` (string) - Stil
  - `mood` (string) - Stimmung
  - `colors` (string) - Farbpalette
  - `negative` (string) - Negativprompt
  - `append_negative` (bool) - Negativprompt anhängen
- `$post_id` (int) - Post ID

#### `gig_image_metadata`

Ermöglicht Anpassung der generierten Metadaten.

```php
$metadata = apply_filters('gig_image_metadata', $metadata, $post_id, $prompt);
```

**Parameter:**
- `$metadata` (array) - Metadaten-Array:
  - `alt_text` (string)
  - `title` (string)
  - `caption` (string)
  - `description` (string)
  - `keyword` (string)
- `$post_id` (int) - Post ID
- `$prompt` (string) - Verwendeter Prompt

## AJAX-Endpunkte

Alle AJAX-Endpunkte verwenden `wp_ajax_` und `wp_ajax_nopriv_` Hooks.

### `gig_generate_prompt`

Generiert einen Prompt für einen Artikel.

**Request:**
```javascript
{
    action: 'gig_generate_prompt',
    post_id: 123,
    nonce: '...'
}
```

**Response:**
```javascript
{
    success: true,
    data: {
        prompt: 'A detailed image prompt...'
    }
}
```

### `gig_generate_image`

Generiert ein Bild.

**Request:**
```javascript
{
    action: 'gig_generate_image',
    post_id: 123,
    prompt: '...',
    ratio: '16:9',
    quality: 'high',
    format: 'webp',
    style: 'photorealistic',
    mood: '',
    colors: '',
    nonce: '...'
}
```

**Response:**
```javascript
{
    success: true,
    data: {
        attachment_id: 456,
        image_url: 'https://...',
        keyword: '...',
        alt: '...',
        caption: '...'
    }
}
```

### `gig_set_featured_image`

Setzt ein Bild als Featured Image.

**Request:**
```javascript
{
    action: 'gig_set_featured_image',
    post_id: 123,
    attachment_id: 456,
    nonce: '...'
}
```

### `gig_get_sections`

Holt alle H2-Abschnitte eines Artikels.

### `gig_generate_section_prompt`

Generiert einen Prompt für einen Abschnitt.

### `gig_test_api_connection`

Testet die API-Verbindung (nur für Admins).

## Datenbank-Optionen

### `gig_settings`

Alle Plugin-Einstellungen werden in dieser Option gespeichert:

```php
$settings = get_option('gig_settings', array());
```

**Struktur:**
```php
array(
    // API
    'api_key' => 'encrypted_key',
    'text_model' => 'gemini-2.0-flash',
    'image_model' => 'gemini-3-pro-image-preview',
    
    // Bild-Defaults
    'default_ratio' => '16:9',
    'default_quality' => 'high',
    'default_format' => 'webp',
    
    // Skalierung
    'resize_enabled' => true,
    'resize_mode' => 'width',
    'resize_width' => 1200,
    'resize_height' => 800,
    'jpeg_quality' => 85,
    'webp_quality' => 85,
    
    // Prompt
    'system_prompt' => '...',
    'default_style' => 'photorealistic',
    'default_mood' => '',
    'default_colors' => '',
    'negative_prompt' => '...',
    'append_negative' => true,
    'english_prompts' => true,
    
    // SEO
    'auto_seo_meta' => true,
    'default_caption' => 'Bild: Generiert mit AI',
)
```

### Attachment Meta

Generierte Bilder haben folgende Meta-Felder:

- `_gig_generated` (int) - 1 wenn generiert
- `_gig_generated_date` (string) - Erstellungsdatum
- `_gig_seo_keyword` (string) - Verwendetes SEO-Keyword

## Entwicklungsumgebung

### Lokale Entwicklung

1. Plugin in lokales WordPress-Installation kopieren
2. API-Key für Google Gemini besorgen
3. Plugin aktivieren und konfigurieren
4. Debug-Modus aktivieren für detaillierte Fehlermeldungen

### Debug-Modus

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Fehler werden in `/wp-content/debug.log` gespeichert.

### Tests

```bash
# PHPUnit (wenn konfiguriert)
vendor/bin/phpunit

# Code-Sniffer (wenn konfiguriert)
vendor/bin/phpcs --standard=WordPress .
```

## Code-Standards

- WordPress Coding Standards
- PHPDoc-Kommentare für alle öffentlichen Methoden
- Präfix `GIG_` für alle Klassen
- Präfix `gig_` für alle Funktionen
- Sanitization für alle User-Inputs
- Escaping für alle Outputs

## Bekannte Limitationen

1. **API-Timeout:** Große Bilder können 60+ Sekunden dauern
2. **Speicher:** Sehr große Bilder können PHP Memory-Limits überschreiten
3. **Rate-Limiting:** Google Gemini API hat Rate-Limits
4. **Bildgröße:** Maximale Bildgröße abhängig von Server-Config

## Zukünftige Erweiterungen

Siehe [CHANGELOG.md](CHANGELOG.md) für geplante Features.

## Beitragen

1. Fork das Repository
2. Erstelle einen Feature-Branch
3. Implementiere Änderungen
4. Teste gründlich
5. Erstelle einen Pull Request

## Support

Für Entwickler-Fragen siehe die Code-Kommentare oder kontaktiere das Entwicklungsteam.

---

**Letzte Aktualisierung:** 2024-11-27

