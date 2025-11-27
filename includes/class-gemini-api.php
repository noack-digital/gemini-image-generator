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
    );

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
            'text_model'        => 'gemini-2.0-flash',
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
     * Holt SEO-Keyword aus RankMath oder analysiert selbst
     */
    public function get_seo_keyword($post_id) {
        $post = get_post($post_id);
        if (!$post) return '';

        // 1. Versuche RankMath Focus Keyword
        $rankmath_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (!empty($rankmath_keyword)) {
            // RankMath kann mehrere Keywords haben, nimm das erste
            $keywords = explode(',', $rankmath_keyword);
            return trim($keywords[0]);
        }

        // 2. Versuche Yoast SEO als Fallback
        $yoast_keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        if (!empty($yoast_keyword)) {
            return trim($yoast_keyword);
        }

        // 3. Analysiere selbst mit KI
        return $this->analyze_keyword($post->post_title, $post->post_content);
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
     */
    private function extract_keyword_from_title($title) {
        // Stoppwörter entfernen
        $stopwords = array('der', 'die', 'das', 'ein', 'eine', 'und', 'oder', 'für', 'mit', 'von', 'zu', 'im', 'am', 'ist', 'sind', 'wird', 'werden', 'the', 'a', 'an', 'and', 'or', 'for', 'with', 'in', 'on', 'is', 'are');
        
        $words = preg_split('/\s+/', strtolower($title));
        $relevant = array();
        
        foreach ($words as $word) {
            $word = preg_replace('/[^a-zäöüß0-9-]/i', '', $word);
            if (strlen($word) > 3 && !in_array($word, $stopwords)) {
                $relevant[] = $word;
                if (count($relevant) >= 3) break;
            }
        }

        return implode(' ', $relevant);
    }

    /**
     * Generiert SEO-optimierte Bild-Metadaten
     */
    public function generate_image_metadata($post_id, $image_prompt) {
        $keyword = $this->get_seo_keyword($post_id);
        $post = get_post($post_id);
        $post_title = $post ? $post->post_title : '';
        $settings = $this->get_settings();

        // Generiere Beschreibung für Barrierefreiheit
        $description = $this->generate_accessibility_description($image_prompt, $keyword);

        return array(
            'alt_text'    => $this->generate_alt_text($keyword, $post_title),
            'title'       => $this->generate_image_title($keyword, $post_title),
            'caption'     => $settings['default_caption'],
            'description' => $description,
            'keyword'     => $keyword,
        );
    }

    /**
     * Generiert Alt-Text mit Keyword
     */
    private function generate_alt_text($keyword, $post_title) {
        if (empty($keyword)) {
            return wp_strip_all_tags(substr($post_title, 0, 125));
        }

        // Kurzer, prägnanter Alt-Text
        $alt = ucfirst($keyword);
        
        // Wenn Titel zusätzliche Info bietet
        if (!empty($post_title) && stripos($post_title, $keyword) === false) {
            $alt .= ' – ' . wp_strip_all_tags(substr($post_title, 0, 80));
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

        $model_config = $this->get_model_config('gemini-3-pro-image-preview', 'image');

        $size_map = array('medium' => '1080p', 'high' => '4K');
        $image_size = isset($size_map[$params['quality']]) ? $size_map[$params['quality']] : '4K';

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
                    'imageSize' => $image_size,
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
     * API Request
     */
    private function post_to_gemini($model, $body, $api_version = 'v1beta') {
        $url = sprintf(
            'https://generativelanguage.googleapis.com/%s/models/%s:generateContent?key=%s',
            $api_version,
            rawurlencode($model),
            rawurlencode($this->settings['api_key'])
        );

        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 120,
            'body'    => wp_json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $message = isset($data['error']['message']) ? $data['error']['message'] : __('API-Fehler', 'gemini-image-generator');
            return new WP_Error('gig_api_error', $message);
        }

        return $data;
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
