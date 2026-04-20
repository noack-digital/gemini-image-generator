<?php
/**
 * Gemini API Wrapper mit SEO-Funktionen
 */

if (!defined('ABSPATH')) {
    exit;
}

class GIG_Gemini_API {

    const OPTION_NAME = 'gig_settings';

    private $settings = array();

    private $model_catalog = array(
        'gemini-2.0-flash' => array(
            'id' => 'gemini-2.0-flash',
            'api_version' => 'v1beta',
            'type' => 'text',
        ),
        'gemini-2.5-flash' => array(
            'id' => 'gemini-2.5-flash',
            'api_version' => 'v1beta',
            'type' => 'text',
        ),
        'gemini-flash-latest' => array(
            'id' => 'gemini-flash-latest',
            'api_version' => 'v1beta',
            'type' => 'text',
        ),
        'gemini-2.5-pro' => array(
            'id' => 'gemini-2.5-pro',
            'api_version' => 'v1beta',
            'type' => 'text',
        ),
        'gemini-3-pro-preview' => array(
            'id' => 'gemini-3-pro-preview',
            'api_version' => 'v1beta',
            'type' => 'text',
        ),
        'gemini-3-pro-image-preview' => array(
            'id' => 'gemini-3-pro-image-preview',
            'api_version' => 'v1beta',
            'type' => 'image',
        ),
        'gemini-3.1-flash-image-preview' => array(
            'id' => 'gemini-3.1-flash-image-preview',
            'api_version' => 'v1beta',
            'type' => 'image',
        ),
        'gemini-2.5-flash-image' => array(
            'id' => 'gemini-2.5-flash-image',
            'api_version' => 'v1beta',
            'type' => 'image',
        ),
        'nano-banana-pro-preview' => array(
            'id' => 'nano-banana-pro-preview',
            'api_version' => 'v1beta',
            'type' => 'image',
        ),
    );

    /**
     * Gibt die auswählbaren Image-Modelle für das Admin-UI zurück.
     */
    public static function get_supported_image_models() {
        return array(
            'gemini-3-pro-image-preview'      => 'Gemini 3 Pro Image (beste Qualität)',
            'gemini-3.1-flash-image-preview'  => 'Gemini 3.1 Flash Image (schnell)',
            'gemini-2.5-flash-image'          => 'Gemini 2.5 Flash Image (stabil)',
            'nano-banana-pro-preview'         => 'Nano Banana Pro (Preview)',
        );
    }

    /**
     * Gibt die auswählbaren Text-Modelle für das Admin-UI zurück.
     */
    public static function get_supported_text_models() {
        return array(
            'gemini-2.5-flash'     => 'Gemini 2.5 Flash (empfohlen, schnell & stabil)',
            'gemini-flash-latest'  => 'Gemini Flash Latest (immer aktuellste Flash-Version)',
            'gemini-2.5-pro'       => 'Gemini 2.5 Pro (beste Qualität)',
            'gemini-3-pro-preview' => 'Gemini 3 Pro Preview (neu, Preview)',
            'gemini-2.0-flash'     => 'Gemini 2.0 Flash (Legacy, kann auf manchen Keys stark gedrosselt sein)',
        );
    }

    public function __construct() {
        $this->settings = $this->get_settings();
    }

    /**
     * Einstellungen mit Defaults
     */
    public function get_settings() {
        $defaults = array(
            // API
            'api_key'           => '',
            'text_model'        => 'gemini-2.5-flash',
            'image_model'       => 'gemini-3-pro-image-preview',
            
            // Bild-Defaults
            'default_ratio'     => '16:9',
            'default_quality'   => 'high',
            'default_format'    => 'webp',
            
            // Skalierung
            'resize_enabled'    => true,
            'resize_mode'       => 'width', // 'width', 'height', 'both', 'none'
            'resize_width'      => 1200,
            'resize_height'     => 800,
            'jpeg_quality'      => 85,
            'webp_quality'      => 85,
            
            // Prompt-Einstellungen
            'system_prompt'     => 'Du bist ein Art Director für digitale Magazine. Erstelle präzise, bildhafte Prompts für KI-Bildgeneratoren. Beschreibe Hauptmotiv, Umgebung, Stimmung, Farbpalette und Bildstil. Antworte in max. 150 Wörtern auf Englisch.',
            'default_style'     => 'photorealistic',
            'default_mood'      => '',
            'default_colors'    => '',
            'negative_prompt'   => 'text, watermark, logo, signature, blurry, low quality, distorted, deformed',
            
            // Erweitert
            'append_negative'   => true,
            'english_prompts'   => true,
            
            // SEO
            'auto_seo_meta'     => true,
            'default_caption'   => 'Bild: Generiert mit AI',
            'caption_prompt'    => 'Du schreibst kurze, prägnante Bild-Untertitel (Captions) für ein digitales Online-Magazin. Maximal 15 Wörter, ein Satz, ohne Emojis, ohne Anführungszeichen. Nimm Bezug auf das Motiv des Bildes und – wenn vorhanden – auf das Fokus-Keyword. Antworte nur mit dem fertigen Untertitel.',
        );

        $stored = get_option(self::OPTION_NAME, array());
        $settings = wp_parse_args($stored, $defaults);

        if (!empty($settings['api_key'])) {
            $settings['api_key'] = self::decrypt_api_key($settings['api_key']);
        }

        return $settings;
    }

    /**
     * Prüft, ob API konfiguriert ist
     */
    public function is_configured() {
        return !empty($this->settings['api_key']);
    }

    /**
     * Prüft, ob ein Post-Titel nur ein WordPress-Platzhalter ist
     * (Auto-Draft). Solche Titel dürfen nicht in Metadaten oder Prompts einfließen.
     */
    public static function is_placeholder_title($title) {
        if (!is_string($title) || trim($title) === '') {
            return true;
        }
        $normalized = strtolower(trim($title));
        $placeholders = array(
            'auto draft',
            'auto-draft',
            'automatisch gespeicherter entwurf',
            '(kein titel)',
            '(no title)',
        );
        return in_array($normalized, $placeholders, true);
    }

    /**
     * Bereinigt den Post-Titel:
     *  - Ist der Titel insgesamt ein Platzhalter → leerer String.
     *  - Ist einem echten Titel ein Platzhalter als Präfix vorangestellt
     *    (z.B. "Automatisch gespeicherter Entwurf: Echter Titel"), wird
     *    dieses Präfix inkl. Trennzeichen (: – — - |) entfernt.
     */
    public static function sanitize_post_title($title) {
        if (self::is_placeholder_title($title)) {
            return '';
        }

        $placeholders = array(
            'automatisch gespeicherter entwurf',
            'auto draft',
            'auto-draft',
            '(kein titel)',
            '(no title)',
        );

        $trimmed = ltrim((string) $title);
        foreach ($placeholders as $ph) {
            $ph_len = strlen($ph);
            if (strlen($trimmed) > $ph_len && strcasecmp(substr($trimmed, 0, $ph_len), $ph) === 0) {
                $rest = ltrim(substr($trimmed, $ph_len));
                // Trennzeichen nach dem Platzhalter entfernen
                $rest = preg_replace('/^[:\-–—|]+\s*/u', '', $rest);
                if ($rest !== '' && !self::is_placeholder_title($rest)) {
                    return $rest;
                }
            }
        }

        return $title;
    }

    /**
     * Liefert alle RankMath- (bzw. Yoast-) Fokus-Keywords als Array.
     *
     * @param int $post_id
     * @return string[]
     */
    public function get_rankmath_keywords($post_id) {
        $raw = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (empty($raw)) {
            $raw = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        }
        if (empty($raw)) {
            return array();
        }
        $parts = array_map('trim', explode(',', (string) $raw));
        return array_values(array_filter($parts, 'strlen'));
    }

    /**
     * Holt SEO-Keyword aus RankMath oder analysiert selbst
     *
     * @param int $post_id Post-ID
     * @return string SEO-Keyword
     */
    public function get_seo_keyword($post_id) {
        $post = get_post($post_id);
        if (!$post) return '';

        // Cache-Check (1 Stunde Cache)
        $cache_key = 'gig_seo_keyword_' . $post_id;
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $keyword = '';

        // 1. Versuche RankMath Focus Keyword
        $rankmath_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (!empty($rankmath_keyword)) {
            // RankMath kann mehrere Keywords haben, nimm das erste
            $keywords = explode(',', $rankmath_keyword);
            $keyword = trim($keywords[0]);
        }

        // 2. Versuche Yoast SEO als Fallback
        if (empty($keyword)) {
            $yoast_keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            if (!empty($yoast_keyword)) {
                $keyword = trim($yoast_keyword);
            }
        }

        // 3. Analysiere selbst mit KI (nur wenn keine SEO-Plugins vorhanden)
        if (empty($keyword)) {
            $clean_title = self::sanitize_post_title($post->post_title);
            // Bei Auto-Draft ohne Content liefert die Analyse sinnlose Ergebnisse
            if ($clean_title !== '' || !empty(trim(wp_strip_all_tags($post->post_content)))) {
                $keyword = $this->analyze_keyword($clean_title, $post->post_content);
            }
        }

        // Cache nur speichern, wenn der Post bereits einen echten Titel hat
        // (sonst würde ein Auto-Draft-Ergebnis für 1 Stunde hängenbleiben).
        if (!empty($keyword) && !self::is_placeholder_title($post->post_title)) {
            set_transient($cache_key, $keyword, HOUR_IN_SECONDS);
        }

        return $keyword;
    }

    /**
     * Analysiert Artikel und findet Haupt-Keyword
     */
    public function analyze_keyword($title, $content) {
        if (!$this->is_configured()) {
            // Fallback: Ersten relevanten Begriff aus Titel nehmen
            return $this->extract_keyword_from_title($title);
        }

        $clean_content = wp_strip_all_tags($content);
        if (strlen($clean_content) > 3000) {
            $clean_content = substr($clean_content, 0, 3000);
        }

        $prompt = sprintf(
            "Analysiere diesen Artikel und finde das wichtigste SEO-Keyword (1-3 Wörter). Antworte NUR mit dem Keyword, keine Erklärungen.\n\nTitel: %s\n\nInhalt: %s",
            $title,
            $clean_content
        );

        $result = $this->generate_text($prompt, 'Du bist ein SEO-Experte. Finde das relevanteste Haupt-Keyword für den Artikel.');

        if (is_wp_error($result)) {
            return $this->extract_keyword_from_title($title);
        }

        // Bereinigen
        $keyword = trim($result);
        $keyword = preg_replace('/^(keyword|haupt-keyword|seo-keyword):\s*/i', '', $keyword);
        $keyword = trim($keyword, '"\'.');
        
        // Max 50 Zeichen
        if (strlen($keyword) > 50) {
            return $this->extract_keyword_from_title($title);
        }

        return $keyword;
    }

    /**
     * Extrahiert Keyword aus Titel (Fallback)
     * 
     * @param string $title Artikel-Titel
     * @return string Extrahierte Keywords
     */
    private function extract_keyword_from_title($title) {
        if (empty($title)) {
            return '';
        }

        // Stoppwörter entfernen
        $stopwords = array('der', 'die', 'das', 'ein', 'eine', 'und', 'oder', 'für', 'mit', 'von', 'zu', 'im', 'am', 'ist', 'sind', 'wird', 'werden', 'the', 'a', 'an', 'and', 'or', 'for', 'with', 'in', 'on', 'is', 'are');
        
        $words = preg_split('/\s+/', strtolower($title));
        if (false === $words || empty($words)) {
            return '';
        }

        $relevant = array();
        
        foreach ($words as $word) {
            $word = preg_replace('/[^a-zäöüß0-9-]/i', '', $word);
            if (strlen($word) > 3 && !in_array($word, $stopwords)) {
                $relevant[] = $word;
                if (count($relevant) >= 3) break;
            }
        }

        $keyword = implode(' ', $relevant);
        
        // Fallback: Erste 3 Wörter wenn keine relevanten gefunden
        if (empty($keyword)) {
            $words = array_slice(preg_split('/\s+/', $title), 0, 3);
            $keyword = implode(' ', array_filter($words));
        }

        return $keyword;
    }

    /**
     * Generiert SEO-optimierte Bild-Metadaten
     */
    public function generate_image_metadata($post_id, $image_prompt) {
        $rm_keywords = $this->get_rankmath_keywords($post_id);
        $keyword = $this->get_seo_keyword($post_id);
        $post = get_post($post_id);
        $post_title = $post ? self::sanitize_post_title($post->post_title) : '';
        $settings = $this->get_settings();

        // Generiere Beschreibung für Barrierefreiheit
        $description = $this->generate_accessibility_description($image_prompt, $keyword);

        return array(
            'alt_text'    => $this->generate_alt_text($keyword, $post_title, $rm_keywords),
            'title'       => $this->generate_image_title($keyword, $post_title),
            'caption'     => $this->generate_caption($image_prompt, $keyword, $post_title, $rm_keywords),
            'description' => $description,
            'keyword'     => $keyword,
        );
    }

    /**
     * Generiert einen Bild-Untertitel (Caption) via Gemini.
     * Der eigentliche System-Prompt kommt aus den Plugin-Einstellungen
     * (`caption_prompt`), damit Redakteure den Stil steuern können.
     * Fällt bei fehlendem Prompt, fehlender API oder Fehlern auf
     * `default_caption` zurück.
     */
    private function generate_caption($image_prompt, $keyword, $post_title, $rm_keywords = array()) {
        $fallback = isset($this->settings['default_caption']) ? $this->settings['default_caption'] : '';
        $system_instruction = isset($this->settings['caption_prompt']) ? trim($this->settings['caption_prompt']) : '';

        if ($system_instruction === '' || !$this->is_configured()) {
            return $fallback;
        }

        $context_parts = array();
        if (!empty($rm_keywords)) {
            $context_parts[] = 'Fokus-Keywords: ' . implode(', ', $rm_keywords);
        } elseif (!empty($keyword)) {
            $context_parts[] = 'Fokus-Keyword: ' . $keyword;
        }
        if (!empty($post_title)) {
            $context_parts[] = 'Artikel-Titel: ' . $post_title;
        }
        if (!empty($image_prompt)) {
            $context_parts[] = 'Bildmotiv (Prompt): ' . $image_prompt;
        }

        $user_prompt = "Erstelle jetzt den Bild-Untertitel basierend auf diesen Informationen:\n\n" . implode("\n", $context_parts);

        $result = $this->generate_text($user_prompt, $system_instruction);

        if (is_wp_error($result)) {
            return $fallback;
        }

        $caption = trim(wp_strip_all_tags($result));
        $caption = trim($caption, " \t\n\r\0\x0B\"'`");

        if ($caption === '') {
            return $fallback;
        }

        // Harte Obergrenze, damit keine ausufernden Texte ins Caption-Feld geraten.
        if (mb_strlen($caption) > 250) {
            $caption = mb_substr($caption, 0, 247) . '…';
        }

        return $caption;
    }

    /**
     * Generiert Alt-Text. Wenn RankMath-Fokus-Keywords vorhanden sind,
     * müssen ALLE darin enthalten sein.
     */
    private function generate_alt_text($keyword, $post_title, $rm_keywords = array()) {
        $post_title = wp_strip_all_tags((string) $post_title);

        // 1. RankMath-Keywords haben Vorrang und müssen vollständig im Alt-Text sein.
        if (!empty($rm_keywords)) {
            $alt = ucfirst(implode(', ', $rm_keywords));

            // Post-Titel nur anhängen, wenn er echte Zusatzinfo liefert.
            if ($post_title !== '') {
                $already_covered = false;
                foreach ($rm_keywords as $kw) {
                    if (stripos($post_title, $kw) !== false) {
                        $already_covered = true;
                        break;
                    }
                }
                if (!$already_covered) {
                    $candidate = $alt . ' – ' . $post_title;
                    // Nur übernehmen, wenn alle Keywords im 125-Zeichen-Limit erhalten bleiben.
                    if (strlen($candidate) <= 125) {
                        $alt = $candidate;
                    }
                }
            }

            return substr($alt, 0, 125);
        }

        // 2. Fallback: einzelnes (analysiertes) Keyword
        if (empty($keyword)) {
            return substr($post_title, 0, 125);
        }

        $alt = ucfirst($keyword);
        if ($post_title !== '' && stripos($post_title, $keyword) === false) {
            $alt .= ' – ' . substr($post_title, 0, 80);
        }

        return substr($alt, 0, 125);
    }

    /**
     * Generiert Bildtitel
     */
    private function generate_image_title($keyword, $post_title) {
        if (empty($keyword)) {
            return wp_strip_all_tags($post_title);
        }

        // Keyword prominent im Titel
        $title = ucfirst($keyword);
        
        if (!empty($post_title) && strlen($post_title) < 100) {
            $title = wp_strip_all_tags($post_title);
            // Stelle sicher, dass Keyword enthalten ist
            if (stripos($title, $keyword) === false) {
                $title = ucfirst($keyword) . ': ' . $title;
            }
        }

        return $title;
    }

    /**
     * Generiert Beschreibung für Barrierefreiheit
     */
    private function generate_accessibility_description($image_prompt, $keyword) {
        if (!$this->is_configured()) {
            return sprintf('KI-generiertes Bild zum Thema %s.', $keyword);
        }

        $prompt = sprintf(
            "Beschreibe dieses Bild in 1-2 Sätzen für sehbehinderte Menschen. Das Keyword '%s' muss vorkommen. Bildinhalt: %s",
            $keyword,
            $image_prompt
        );

        $result = $this->generate_text($prompt, 'Du schreibst barrierefreie Bildbeschreibungen. Kurz, präzise, hilfreich für Screenreader.');

        if (is_wp_error($result)) {
            return sprintf('KI-generiertes Bild zum Thema %s.', $keyword);
        }

        $description = trim($result);
        
        // Stelle sicher, dass Keyword enthalten ist
        if (!empty($keyword) && stripos($description, $keyword) === false) {
            $description = sprintf('%s – %s', ucfirst($keyword), $description);
        }

        return substr($description, 0, 500);
    }

    /**
     * Generiert Text über Gemini
     */
    public function generate_text($prompt, $system_instruction = '') {
        if (!$this->is_configured()) {
            return new WP_Error('gig_no_key', __('Gemini API-Key nicht konfiguriert.', 'gemini-image-generator'));
        }

        $model_key = !empty($this->settings['text_model']) ? $this->settings['text_model'] : 'gemini-2.0-flash';
        $model_config = $this->get_model_config($model_key, 'text');

        $full_prompt = $prompt;
        if (!empty($system_instruction)) {
            $full_prompt = "[ROLLE]: " . $system_instruction . "\n\n[AUFGABE]: " . $prompt;
        }

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $full_prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 1.0,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            ),
        );

        $response = $this->post_to_gemini($model_config['id'], $body, $model_config['api_version']);

        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error('gig_invalid_response', __('Ungültige API-Antwort.', 'gemini-image-generator'));
        }

        return $response['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Generiert ein Bild über Gemini 3 Pro Image
     */
    public function generate_image($prompt, $params = array()) {
        if (!$this->is_configured()) {
            return new WP_Error('gig_no_key', __('Gemini API-Key nicht konfiguriert.', 'gemini-image-generator'));
        }

        $defaults = array(
            'ratio'          => $this->settings['default_ratio'],
            'quality'        => $this->settings['default_quality'],
            'format'         => $this->settings['default_format'],
            'style'          => $this->settings['default_style'],
            'mood'           => $this->settings['default_mood'],
            'colors'         => $this->settings['default_colors'],
            'negative'       => $this->settings['negative_prompt'],
            'append_negative'=> $this->settings['append_negative'],
        );
        $params = wp_parse_args($params, $defaults);

        $enhanced_prompt = $this->enhance_prompt($prompt, $params);

        $image_model_key = !empty($this->settings['image_model']) ? $this->settings['image_model'] : 'gemini-3-pro-image-preview';
        $model_config = $this->get_model_config($image_model_key, 'image');

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $enhanced_prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'responseModalities' => array('IMAGE', 'TEXT'),
                'imageConfig' => array(
                    'aspectRatio' => $params['ratio'],
                ),
            ),
        );

        $response = $this->post_to_gemini($model_config['id'], $body, $model_config['api_version']);

        if (is_wp_error($response)) {
            return $response;
        }

        $image_data = $this->extract_image_from_response($response);

        if (is_wp_error($image_data)) {
            return $image_data;
        }

        return array(
            'mime_type'      => $image_data['mime_type'],
            'data'           => $image_data['data'],
            'target_format'  => $params['format'],
        );
    }

    /**
     * Erweitert den Prompt
     */
    private function enhance_prompt($prompt, $params) {
        $parts = array();

        if (!empty($params['style']) && $params['style'] !== 'none') {
            $style_labels = self::get_supported_styles();
            if (isset($style_labels[$params['style']])) {
                $parts[] = $style_labels[$params['style']] . ' style image:';
            }
        }

        $parts[] = $prompt;

        if (!empty($params['mood'])) {
            $parts[] = 'Mood: ' . $params['mood'];
        }

        if (!empty($params['colors'])) {
            $parts[] = 'Color palette: ' . $params['colors'];
        }

        if (!empty($params['negative']) && $params['append_negative']) {
            $parts[] = 'Avoid: ' . $params['negative'];
        }

        return implode('. ', $parts);
    }

    /**
     * API Request mit Retry-Logic und verbesserter Fehlerbehandlung
     * 
     * @param string $model Modell-Name
     * @param array $body Request-Body
     * @param string $api_version API-Version
     * @param int $retries Anzahl der Wiederholungsversuche
     * @return array|WP_Error Response-Daten oder Fehler
     */
    private function post_to_gemini($model, $body, $api_version = 'v1beta', $retries = 2) {
        $url = sprintf(
            'https://generativelanguage.googleapis.com/%s/models/%s:generateContent?key=%s',
            $api_version,
            rawurlencode($model),
            rawurlencode($this->settings['api_key'])
        );

        $args = array(
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 120,
            'body'    => wp_json_encode($body),
            'sslverify' => true,
            'redirection' => 5,
            'httpversion' => '1.1',
        );

        $last_error = null;

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            if ($attempt > 0) {
                // Exponential backoff: 1s, 2s, 4s
                sleep(pow(2, $attempt - 1));
            }

            $response = wp_remote_post($url, $args);

            if (is_wp_error($response)) {
                $last_error = $response;
                $error_code = $response->get_error_code();
                
                // Bei Timeout oder Verbindungsfehler: Retry
                if (in_array($error_code, array('http_request_failed', 'timeout'), true) && $attempt < $retries) {
                    continue;
                }
                
                return $response;
            }

            $code = wp_remote_retrieve_response_code($response);
            $body_text = wp_remote_retrieve_body($response);
            $data = json_decode($body_text, true);

            // 200 = Erfolg
            if ($code === 200 && !empty($data)) {
                return $data;
            }

            // 429 = Rate Limit - Retry mit längerer Pause
            if ($code === 429 && $attempt < $retries) {
                sleep(5 * ($attempt + 1));
                continue;
            }

            // 500-599 = Server-Fehler - Retry
            if ($code >= 500 && $code < 600 && $attempt < $retries) {
                continue;
            }

            // Andere Fehler
            $message = __('API-Fehler', 'gemini-image-generator');
            if (isset($data['error']['message'])) {
                $message = $data['error']['message'];
            } elseif (!empty($body_text)) {
                $message = sprintf(__('API-Fehler (Code: %d)', 'gemini-image-generator'), $code);
            }

            return new WP_Error('gig_api_error', $message, array(
                'status_code' => $code,
                'response' => $data,
            ));
        }

        // Alle Retries fehlgeschlagen
        return $last_error ?: new WP_Error('gig_api_error', __('API-Request fehlgeschlagen nach mehreren Versuchen.', 'gemini-image-generator'));
    }

    /**
     * Extrahiert Bilddaten aus Response
     */
    private function extract_image_from_response($response) {
        if (empty($response['candidates'])) {
            return new WP_Error('gig_no_candidates', __('Keine Bilddaten erhalten.', 'gemini-image-generator'));
        }

        foreach ($response['candidates'] as $candidate) {
            if (empty($candidate['content']['parts'])) continue;

            foreach ($candidate['content']['parts'] as $part) {
                if (isset($part['inlineData']['data'])) {
                    return array(
                        'data'      => $part['inlineData']['data'],
                        'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                    );
                }
                if (isset($part['inline_data']['data'])) {
                    return array(
                        'data'      => $part['inline_data']['data'],
                        'mime_type' => $part['inline_data']['mime_type'] ?? 'image/png',
                    );
                }
            }
        }

        return new WP_Error('gig_no_image', __('Kein Bild in der Antwort.', 'gemini-image-generator'));
    }

    // Static helper methods
    public static function get_supported_styles() {
        return array(
            'none'           => __('Kein Stil', 'gemini-image-generator'),
            'photorealistic' => __('Fotorealistisch', 'gemini-image-generator'),
            'editorial'      => __('Editorial / Magazin', 'gemini-image-generator'),
            'cinematic'      => __('Cinematic / Filmisch', 'gemini-image-generator'),
            'artistic'       => __('Künstlerisch', 'gemini-image-generator'),
            'illustration'   => __('Illustration', 'gemini-image-generator'),
            'digital-art'    => __('Digital Art', 'gemini-image-generator'),
            '3d-render'      => __('3D Render', 'gemini-image-generator'),
            'minimalist'     => __('Minimalistisch', 'gemini-image-generator'),
            'vintage'        => __('Vintage / Retro', 'gemini-image-generator'),
            'futuristic'     => __('Futuristisch', 'gemini-image-generator'),
            'watercolor'     => __('Aquarell', 'gemini-image-generator'),
            'sketch'         => __('Skizze', 'gemini-image-generator'),
        );
    }

    public static function get_supported_moods() {
        return array(
            ''             => __('— Keine —', 'gemini-image-generator'),
            'professional' => __('Professionell', 'gemini-image-generator'),
            'dramatic'     => __('Dramatisch', 'gemini-image-generator'),
            'calm'         => __('Ruhig / Entspannt', 'gemini-image-generator'),
            'energetic'    => __('Energetisch', 'gemini-image-generator'),
            'mysterious'   => __('Mysteriös', 'gemini-image-generator'),
            'optimistic'   => __('Optimistisch', 'gemini-image-generator'),
            'dark'         => __('Düster', 'gemini-image-generator'),
            'warm'         => __('Warm / Einladend', 'gemini-image-generator'),
            'cold'         => __('Kalt / Distanziert', 'gemini-image-generator'),
        );
    }

    public static function get_supported_colors() {
        return array(
            ''                => __('— Keine —', 'gemini-image-generator'),
            'vibrant'         => __('Lebhaft / Bunt', 'gemini-image-generator'),
            'muted'           => __('Gedämpft / Pastellfarben', 'gemini-image-generator'),
            'monochrome'      => __('Monochrom', 'gemini-image-generator'),
            'warm-tones'      => __('Warme Töne (Rot, Orange, Gelb)', 'gemini-image-generator'),
            'cool-tones'      => __('Kühle Töne (Blau, Grün, Violett)', 'gemini-image-generator'),
            'earth-tones'     => __('Erdtöne (Braun, Beige, Grün)', 'gemini-image-generator'),
            'high-contrast'   => __('Hoher Kontrast', 'gemini-image-generator'),
            'black-white'     => __('Schwarz-Weiß', 'gemini-image-generator'),
            'neon'            => __('Neon / Leuchtend', 'gemini-image-generator'),
            'corporate-blue'  => __('Corporate Blue', 'gemini-image-generator'),
        );
    }

    public static function get_supported_ratios() {
        return array(
            '16:9' => '16:9 – Landschaft',
            '4:3'  => '4:3 – Standard',
            '1:1'  => '1:1 – Quadrat',
            '9:16' => '9:16 – Hochformat',
            '3:4'  => '3:4 – Portrait',
        );
    }

    public static function get_supported_qualities() {
        return array(
            'medium' => __('Standard (1080p)', 'gemini-image-generator'),
            'high'   => __('Hoch (4K)', 'gemini-image-generator'),
        );
    }

    public static function get_supported_formats() {
        return array(
            'webp' => 'WebP (empfohlen)',
            'png'  => 'PNG',
            'jpeg' => 'JPEG',
        );
    }

    // Verschlüsselung
    public static function encrypt_api_key($api_key) {
        if (empty($api_key)) return '';
        if (!function_exists('openssl_encrypt')) return base64_encode($api_key);

        $secret = self::get_encryption_secret();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($api_key, 'AES-256-CBC', $secret, OPENSSL_RAW_DATA, $iv);
        return $encrypted ? base64_encode($iv . $encrypted) : base64_encode($api_key);
    }

    public static function decrypt_api_key($payload) {
        if (empty($payload)) return '';
        if (!function_exists('openssl_decrypt')) return base64_decode($payload);

        $decoded = base64_decode($payload, true);
        if (!$decoded || strlen($decoded) <= 16) return '';

        $iv = substr($decoded, 0, 16);
        $cipher = substr($decoded, 16);
        $decrypted = openssl_decrypt($cipher, 'AES-256-CBC', self::get_encryption_secret(), OPENSSL_RAW_DATA, $iv);
        return $decrypted ?: '';
    }

    private static function get_encryption_secret() {
        return hash('sha256', wp_salt('auth') . 'gig_api_secret');
    }

    private function get_model_config($model_key, $expected_type = 'text') {
        if (isset($this->model_catalog[$model_key])) {
            return $this->model_catalog[$model_key];
        }
        foreach ($this->model_catalog as $config) {
            if ($config['type'] === $expected_type) return $config;
        }
        return reset($this->model_catalog);
    }
}
