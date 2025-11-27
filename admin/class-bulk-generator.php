<?php
/**
 * Bulk-Bildgenerierung für mehrere Artikel
 */

if (!defined('ABSPATH')) {
    exit;
}

class GIG_Bulk_Generator {

    private $page_slug = 'gig-bulk-generator';
    private $gemini_api;
    private $prompt_generator;
    private $image_handler;

    public function __construct($gemini_api, $prompt_generator, $image_handler) {
        $this->gemini_api = $gemini_api;
        $this->prompt_generator = $prompt_generator;
        $this->image_handler = $image_handler;

        add_action('admin_menu', array($this, 'register_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_gig_bulk_get_posts', array($this, 'ajax_get_posts'));
        add_action('wp_ajax_gig_bulk_generate', array($this, 'ajax_generate'));
    }

    /**
     * Registriert die Bulk-Generierungs-Seite
     */
    public function register_page() {
        add_submenu_page(
            'tools.php',
            __('Bulk Bildgenerierung', 'gemini-image-generator'),
            __('Bulk Bilder', 'gemini-image-generator'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_page')
        );
    }

    /**
     * Lädt Assets
     */
    public function enqueue_assets($hook) {
        if ('tools_page_' . $this->page_slug !== $hook) {
            return;
        }

        wp_enqueue_script(
            'gig-bulk-generator',
            GIG_PLUGIN_URL . 'admin/js/bulk-generator.js',
            array('jquery'),
            GIG_PLUGIN_VERSION,
            true
        );

        wp_localize_script('gig-bulk-generator', 'GIGBulk', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gig_bulk_nonce'),
            'strings' => array(
                'generating' => __('Generiere Bilder…', 'gemini-image-generator'),
                'success' => __('Alle Bilder erfolgreich generiert!', 'gemini-image-generator'),
                'error' => __('Fehler bei der Generierung.', 'gemini-image-generator'),
            ),
        ));
    }

    /**
     * Rendert die Bulk-Generierungs-Seite
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->gemini_api->get_settings();

        if (!$this->gemini_api->is_configured()) {
            echo '<div class="wrap"><div class="notice notice-error"><p>';
            echo __('Bitte konfigurieren Sie zuerst den API-Key unter <a href="' . admin_url('options-general.php?page=gig-settings') . '">Einstellungen → Gemini Image</a>.', 'gemini-image-generator');
            echo '</p></div></div>';
            return;
        }

        ?>
        <div class="wrap gig-bulk-generator">
            <h1><?php esc_html_e('Bulk Bildgenerierung', 'gemini-image-generator'); ?></h1>
            <p class="description">
                <?php esc_html_e('Generieren Sie Artikelbilder für mehrere Beiträge auf einmal.', 'gemini-image-generator'); ?>
            </p>

            <div class="gig-bulk-filters">
                <h2><?php esc_html_e('Filter', 'gemini-image-generator'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="gig-post-type"><?php esc_html_e('Post-Type', 'gemini-image-generator'); ?></label>
                        </th>
                        <td>
                            <select id="gig-post-type" class="regular-text">
                                <option value="post"><?php esc_html_e('Beiträge', 'gemini-image-generator'); ?></option>
                                <option value="page"><?php esc_html_e('Seiten', 'gemini-image-generator'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="gig-post-status"><?php esc_html_e('Status', 'gemini-image-generator'); ?></label>
                        </th>
                        <td>
                            <select id="gig-post-status" class="regular-text">
                                <option value="publish"><?php esc_html_e('Veröffentlicht', 'gemini-image-generator'); ?></option>
                                <option value="draft"><?php esc_html_e('Entwurf', 'gemini-image-generator'); ?></option>
                                <option value="all"><?php esc_html_e('Alle', 'gemini-image-generator'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="gig-has-featured"><?php esc_html_e('Featured Image', 'gemini-image-generator'); ?></label>
                        </th>
                        <td>
                            <select id="gig-has-featured" class="regular-text">
                                <option value="no"><?php esc_html_e('Nur ohne Featured Image', 'gemini-image-generator'); ?></option>
                                <option value="yes"><?php esc_html_e('Nur mit Featured Image', 'gemini-image-generator'); ?></option>
                                <option value="all"><?php esc_html_e('Alle', 'gemini-image-generator'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="gig-limit"><?php esc_html_e('Limit', 'gemini-image-generator'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="gig-limit" value="10" min="1" max="100" class="small-text">
                            <p class="description"><?php esc_html_e('Maximale Anzahl an Beiträgen (1-100)', 'gemini-image-generator'); ?></p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button button-primary" id="gig-load-posts">
                        <?php esc_html_e('Beiträge laden', 'gemini-image-generator'); ?>
                    </button>
                </p>
            </div>

            <div id="gig-posts-list" style="display:none;">
                <h2><?php esc_html_e('Gefundene Beiträge', 'gemini-image-generator'); ?></h2>
                <div id="gig-posts-container"></div>
                <p>
                    <button type="button" class="button button-primary button-large" id="gig-start-bulk" disabled>
                        <?php esc_html_e('Bilder generieren', 'gemini-image-generator'); ?>
                    </button>
                    <span id="gig-bulk-progress" style="margin-left: 15px;"></span>
                </p>
            </div>

            <div id="gig-bulk-results" style="display:none;">
                <h2><?php esc_html_e('Ergebnisse', 'gemini-image-generator'); ?></h2>
                <div id="gig-results-container"></div>
            </div>
        </div>

        <style>
            .gig-bulk-generator .form-table th { width: 200px; }
            .gig-bulk-generator .gig-post-item {
                padding: 10px;
                border: 1px solid #ddd;
                margin-bottom: 10px;
                background: #f9f9f9;
            }
            .gig-bulk-generator .gig-post-item.selected {
                background: #e7f5e7;
                border-color: #46b450;
            }
            .gig-bulk-generator .gig-result-item {
                padding: 10px;
                margin-bottom: 5px;
            }
            .gig-bulk-generator .gig-result-item.success {
                background: #e7f5e7;
                border-left: 4px solid #46b450;
            }
            .gig-bulk-generator .gig-result-item.error {
                background: #ffeaea;
                border-left: 4px solid #dc3232;
            }
        </style>
        <?php
    }

    /**
     * AJAX: Gibt Posts zurück
     */
    public function ajax_get_posts() {
        check_ajax_referer('gig_bulk_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung.', 'gemini-image-generator'));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
        $post_status = isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'publish';
        $has_featured = isset($_POST['has_featured']) ? sanitize_text_field($_POST['has_featured']) : 'no';
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 10;

        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => min($limit, 100),
            'post_status' => $post_status === 'all' ? 'any' : $post_status,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        if ($has_featured === 'no') {
            $args['meta_query'] = array(
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'NOT EXISTS',
                ),
            );
        } elseif ($has_featured === 'yes') {
            $args['meta_query'] = array(
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ),
            );
        }

        $query = new WP_Query($args);
        $posts = array();

        foreach ($query->posts as $post) {
            $posts[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'date' => get_the_date('', $post->ID),
                'has_featured' => has_post_thumbnail($post->ID),
            );
        }

        wp_send_json_success(array(
            'posts' => $posts,
            'count' => count($posts),
        ));
    }

    /**
     * AJAX: Generiert Bild für einen Post
     */
    public function ajax_generate() {
        check_ajax_referer('gig_bulk_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung.', 'gemini-image-generator'));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(__('Ungültige Post-ID.', 'gemini-image-generator'));
        }

        // Prompt generieren
        $prompt = $this->prompt_generator->generate_prompt_for_post($post_id);

        if (is_wp_error($prompt)) {
            wp_send_json_error($prompt->get_error_message());
        }

        // Bild generieren
        $settings = $this->gemini_api->get_settings();
        $params = array(
            'ratio' => $settings['default_ratio'],
            'quality' => $settings['default_quality'],
            'format' => $settings['default_format'],
            'style' => $settings['default_style'],
            'mood' => $settings['default_mood'],
            'colors' => $settings['default_colors'],
        );

        $image_response = $this->gemini_api->generate_image($prompt, $params);

        if (is_wp_error($image_response)) {
            wp_send_json_error($image_response->get_error_message());
        }

        // Metadaten generieren
        $metadata = array();
        if (!empty($settings['auto_seo_meta'])) {
            $metadata = $this->gemini_api->generate_image_metadata($post_id, $prompt);
        }

        // Bild speichern
        $attachment_id = $this->image_handler->save_base64_image(
            $image_response['data'],
            $image_response['mime_type'],
            $post_id,
            $prompt,
            $params['format'],
            $metadata
        );

        if (is_wp_error($attachment_id)) {
            wp_send_json_error($attachment_id->get_error_message());
        }

        // Als Featured Image setzen
        $this->image_handler->set_featured_image($post_id, $attachment_id);

        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'image_url' => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
        ));
    }
}

