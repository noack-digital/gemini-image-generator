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
     * 
     * @param int|WP_Post $post Post-ID oder Post-Objekt
     * @return string|WP_Error Generierter Prompt oder Fehler
     */
    public function generate_prompt_for_post($post) {
        $post = get_post($post);
        $post_id = $post ? $post->ID : 0;

        if (!$post) {
            return new WP_Error('gig_no_post', __('Beitrag nicht gefunden.', 'gemini-image-generator'));
        }

        return $this->generate_prompt_from_content($post->post_title, $post->post_content, $post_id);
    }

    /**
     * Erzeugt Prompt aus Titel & Inhalt mit Kontext (Kategorien, Tags)
     * 
     * @param string $title Artikel-Titel
     * @param string $content Artikel-Inhalt
     * @param int $post_id Post-ID (optional, für Kontext)
     * @return string|WP_Error Generierter Prompt oder Fehler
     */
    public function generate_prompt_from_content($title, $content, $post_id = 0) {
        $settings = $this->gemini_api->get_settings();
        
        // Content bereinigen und kürzen
        $clean_content = $this->sanitize_content($content, 6000);

        // Kontext sammeln (Kategorien, Tags)
        $context = $this->get_post_context($post_id);

        // System-Prompt aus Einstellungen
        $system_instruction = $this->get_system_prompt();

        $prompt = sprintf(
            "Analysiere diesen Artikel und erstelle einen detaillierten Bildprompt für ein Titelbild.\n\nTitel: %s\n\nInhalt:\n%s",
            $title,
            $clean_content
        );

        // Kontext hinzufügen, falls vorhanden
        if (!empty($context)) {
            $prompt .= "\n\nKontext:\n" . $context;
        }

        $prompt .= "\n\nErstelle NUR den Bildprompt, keine Erklärungen.";

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
     * Holt Kontext-Informationen für einen Post (Kategorien, Tags)
     * 
     * @param int $post_id Post-ID
     * @return string Kontext-String
     */
    private function get_post_context($post_id) {
        if (!$post_id) {
            return '';
        }

        $context_parts = array();

        // Kategorien (nur für Posts)
        $categories = get_the_category($post_id);
        if (!empty($categories)) {
            $category_names = array_map(function($cat) {
                return $cat->name;
            }, $categories);
            $context_parts[] = 'Kategorien: ' . implode(', ', $category_names);
        }

        // Tags
        $tags = get_the_tags($post_id);
        if (!empty($tags)) {
            $tag_names = array_map(function($tag) {
                return $tag->name;
            }, $tags);
            $context_parts[] = 'Tags: ' . implode(', ', $tag_names);
        }

        // Custom Taxonomien (erste 3)
        $taxonomies = get_object_taxonomies(get_post_type($post_id), 'objects');
        foreach ($taxonomies as $taxonomy) {
            if (in_array($taxonomy->name, array('category', 'post_tag'), true)) {
                continue; // Bereits geholt
            }

            $terms = get_the_terms($post_id, $taxonomy->name);
            if (!empty($terms) && !is_wp_error($terms)) {
                $term_names = array_slice(array_map(function($term) {
                    return $term->name;
                }, $terms), 0, 3);
                $context_parts[] = $taxonomy->label . ': ' . implode(', ', $term_names);
            }
        }

        return !empty($context_parts) ? implode("\n", $context_parts) : '';
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
