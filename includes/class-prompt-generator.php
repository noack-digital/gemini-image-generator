<?php
/**
 * Prompt Generator mit System-Prompt Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class GIG_Prompt_Generator {

    private $gemini_api;

    public function __construct($gemini_api) {
        $this->gemini_api = $gemini_api;
    }

    /**
     * Erzeugt Prompt für einen Beitrag
     */
    public function generate_prompt_for_post($post) {
        $post = get_post($post);

        if (!$post) {
            return new WP_Error('gig_no_post', __('Beitrag nicht gefunden.', 'gemini-image-generator'));
        }

        return $this->generate_prompt_from_content($post->post_title, $post->post_content);
    }

    /**
     * Erzeugt Prompt aus Titel & Inhalt
     */
    public function generate_prompt_from_content($title, $content) {
        $settings = $this->gemini_api->get_settings();
        
        // Content bereinigen und kürzen
        $clean_content = $this->sanitize_content($content, 6000);

        // System-Prompt aus Einstellungen
        $system_instruction = $this->get_system_prompt();

        $prompt = sprintf(
            "Analysiere diesen Artikel und erstelle einen detaillierten Bildprompt für ein Titelbild.\n\nTitel: %s\n\nInhalt:\n%s\n\nErstelle NUR den Bildprompt, keine Erklärungen.",
            $title,
            $clean_content
        );

        $response = $this->gemini_api->generate_text($prompt, $system_instruction);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->clean_prompt($response);
    }

    /**
     * Gibt alle H2-Abschnitte eines Beitrags zurück
     *
     * @return array[] { id, title, excerpt, heading_html }
     */
    public function get_h2_sections($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        $content = $post->post_content;
        if (empty($content) || stripos($content, '<h2') === false) {
            return array();
        }

        $sections = array();
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $html = '<div>' . $content . '</div>';
        if (function_exists('mb_convert_encoding')) {
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        }
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $headings = $xpath->query('//h2');

        $index = 0;
        foreach ($headings as $heading) {
            $heading_html = $dom->saveHTML($heading);
            $title = trim($heading->textContent);

            $section_content = '';
            $node = $heading->nextSibling;
            while ($node && !($node->nodeName === 'h2' || ($node->nodeType === XML_ELEMENT_NODE && strtolower($node->nodeName) === 'h2'))) {
                $section_content .= $dom->saveHTML($node);
                $node = $node->nextSibling;
            }

            $excerpt = wp_trim_words(wp_strip_all_tags($section_content), 30, '…');

            $sections[] = array(
                'id'           => 'section-' . $index,
                'title'        => $title,
                'excerpt'      => $excerpt,
                'heading_html' => $heading_html,
            );
            $index++;
        }

        return $sections;
    }

    /**
     * Erzeugt Prompt für einen bestimmten Abschnitt
     */
    public function generate_prompt_for_section($post_id, $section_id) {
        $sections = $this->get_h2_sections($post_id);
        $section = null;

        foreach ($sections as $sec) {
            if ($sec['id'] === $section_id) {
                $section = $sec;
                break;
            }
        }

        if (!$section) {
            return new WP_Error('gig_no_section', __('Abschnitt nicht gefunden.', 'gemini-image-generator'));
        }

        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('gig_no_post', __('Beitrag nicht gefunden.', 'gemini-image-generator'));
        }

        $system_instruction = $this->get_system_prompt();
        $clean_content = $this->sanitize_content($section['excerpt'], 2000);

        $prompt = sprintf(
            "Analysiere folgenden Abschnitt (Überschrift + Inhalt) und erstelle einen detaillierten Bildprompt, der diese Passage illustriert. Antworte nur mit dem Prompt.\n\nArtikel: %s\n\nÜberschrift: %s\n\nAbschnitt:\n%s",
            $post->post_title,
            $section['title'],
            $clean_content
        );

        $response = $this->gemini_api->generate_text($prompt, $system_instruction);

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->clean_prompt($response);
    }

    /**
     * Hilfsfunktionen
     */
    private function sanitize_content($content, $limit = 6000) {
        $clean = wp_strip_all_tags($content);
        $clean = preg_replace('/\s+/', ' ', $clean);
        if (strlen($clean) > $limit) {
            $clean = substr($clean, 0, $limit) . '...';
        }
        return $clean;
    }

    private function get_system_prompt() {
        $settings = $this->gemini_api->get_settings();
        $system_instruction = !empty($settings['system_prompt']) 
            ? $settings['system_prompt'] 
            : 'Du bist ein Art Director. Erstelle präzise Bildprompts auf Englisch.';

        if (!empty($settings['english_prompts'])) {
            $system_instruction .= ' Antworte immer auf Englisch.';
        }

        return $system_instruction;
    }

    private function clean_prompt($prompt) {
        $cleaned = trim($prompt);
        $cleaned = preg_replace('/^(Bildprompt|Image Prompt|Prompt):\s*/i', '', $cleaned);
        $cleaned = trim($cleaned, "\"'\n\r");
        return sanitize_textarea_field($cleaned);
    }
}
