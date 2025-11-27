<?php
/**
 * Editor Meta-Box mit SEO-Integration und Abschnittsbildern
 */

if (!defined('ABSPATH')) {
    exit;
}

class GIG_Editor_Metabox {

    private $gemini_api;
    private $prompt_generator;
    private $image_handler;

    public function __construct($gemini_api, $prompt_generator, $image_handler) {
        $this->gemini_api = $gemini_api;
        $this->prompt_generator = $prompt_generator;
        $this->image_handler = $image_handler;

        add_action('add_meta_boxes', array($this, 'register_metabox'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        add_action('wp_ajax_gig_generate_prompt', array($this, 'ajax_generate_prompt'));
        add_action('wp_ajax_gig_generate_image', array($this, 'ajax_generate_image'));
        add_action('wp_ajax_gig_set_featured_image', array($this, 'ajax_set_featured_image'));

        add_action('wp_ajax_gig_get_sections', array($this, 'ajax_get_sections'));
        add_action('wp_ajax_gig_generate_section_prompt', array($this, 'ajax_generate_section_prompt'));
    }

    /**
     * Registriert die Metabox für unterstützte Post-Types
     */
    public function register_metabox() {
        $post_types = apply_filters('gig_supported_post_types', array('post', 'page'));

        foreach ($post_types as $post_type) {
            add_meta_box(
                'gig_meta_box',
                __('🎨 KI Artikelbild', 'gemini-image-generator'),
                array($this, 'render_metabox'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    public function enqueue_assets($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) return;

        wp_enqueue_style('gig-metabox', GIG_PLUGIN_URL . 'admin/css/editor-metabox.css', array(), GIG_PLUGIN_VERSION);
        wp_enqueue_script('gig-metabox', GIG_PLUGIN_URL . 'admin/js/editor-metabox.js', array('jquery'), GIG_PLUGIN_VERSION, true);

        $settings = $this->gemini_api->get_settings();

        wp_localize_script('gig-metabox', 'GIGMetabox', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gig_metabox_nonce'),
            'defaults' => array(
                'ratio'   => $settings['default_ratio'],
                'quality' => $settings['default_quality'],
                'format'  => $settings['default_format'],
                'style'   => $settings['default_style'],
                'mood'    => $settings['default_mood'],
                'colors'  => $settings['default_colors'],
            ),
            'strings' => array(
                'generatingPrompt' => __('Prompt wird generiert…', 'gemini-image-generator'),
                'generatingImage'  => __('Bild wird erzeugt (30-60 Sek.)…', 'gemini-image-generator'),
                'missingPrompt'    => __('Bitte zuerst einen Prompt eingeben.', 'gemini-image-generator'),
                'setFeatured'      => __('✓ Als Artikelbild gesetzt!', 'gemini-image-generator'),
                'loadingSections'  => __('Abschnitte werden geladen…', 'gemini-image-generator'),
                'noSections'       => __('Keine H2-Überschriften gefunden.', 'gemini-image-generator'),
            ),
        ));
    }

    public function render_metabox($post) {
        wp_nonce_field('gig_metabox_action', 'gig_metabox_nonce');
        $settings = $this->gemini_api->get_settings();
        $styles = GIG_Gemini_API::get_supported_styles();
        $moods = GIG_Gemini_API::get_supported_moods();
        $colors = GIG_Gemini_API::get_supported_colors();
        $ratios = GIG_Gemini_API::get_supported_ratios();
        $qualities = GIG_Gemini_API::get_supported_qualities();
        $formats = GIG_Gemini_API::get_supported_formats();

        $current_keyword = $this->gemini_api->get_seo_keyword($post->ID);
        ?>
        <div class="gig-metabox" data-post="<?php echo esc_attr($post->ID); ?>">
            
            <?php if (!empty($current_keyword)): ?>
            <div class="gig-keyword-info">
                <small><?php esc_html_e('SEO-Keyword:', 'gemini-image-generator'); ?> <strong><?php echo esc_html($current_keyword); ?></strong></small>
            </div>
            <?php endif; ?>

            <!-- Prompt Section -->
            <div class="gig-section">
                <button type="button" class="button gig-generate-prompt">
                    <?php esc_html_e('✨ Prompt generieren', 'gemini-image-generator'); ?>
                </button>
                <textarea class="widefat gig-prompt-field" rows="4" placeholder="<?php esc_attr_e('Bildprompt eingeben oder automatisch generieren…', 'gemini-image-generator'); ?>"></textarea>
            </div>

            <!-- Stil & Stimmung -->
            <div class="gig-section gig-row">
                <div class="gig-col">
                    <label><?php esc_html_e('Stil', 'gemini-image-generator'); ?></label>
                    <select class="widefat gig-select-style">
                        <?php foreach ($styles as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_style'], $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="gig-col">
                    <label><?php esc_html_e('Stimmung', 'gemini-image-generator'); ?></label>
                    <select class="widefat gig-select-mood">
                        <?php foreach ($moods as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_mood'], $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Farben -->
            <div class="gig-section">
                <label><?php esc_html_e('Farbpalette', 'gemini-image-generator'); ?></label>
                <select class="widefat gig-select-colors">
                    <?php foreach ($colors as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_colors'], $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Technische Optionen (einklappbar) -->
            <details class="gig-details">
                <summary><?php esc_html_e('Technische Optionen', 'gemini-image-generator'); ?></summary>
                <div class="gig-section gig-row">
                    <div class="gig-col">
                        <label><?php esc_html_e('Format', 'gemini-image-generator'); ?></label>
                        <select class="widefat gig-select-ratio">
                            <?php foreach ($ratios as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_ratio'], $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="gig-col">
                        <label><?php esc_html_e('Qualität', 'gemini-image-generator'); ?></label>
                        <select class="widefat gig-select-quality">
                            <?php foreach ($qualities as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_quality'], $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="gig-section">
                    <label><?php esc_html_e('Dateiformat', 'gemini-image-generator'); ?></label>
                    <select class="widefat gig-select-format">
                        <?php foreach ($formats as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_format'], $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </details>

            <!-- Generate Button -->
            <button type="button" class="button button-primary button-large gig-generate-image">
                <?php esc_html_e('🖼️ Bild generieren', 'gemini-image-generator'); ?>
            </button>

            <div class="gig-status"></div>

            <!-- Preview -->
            <div class="gig-preview" style="display:none;">
                <img src="" alt="" class="gig-preview-image" />
                <div class="gig-preview-actions">
                    <button type="button" class="button gig-generate-new">
                        <?php esc_html_e('🔄 Neu generieren', 'gemini-image-generator'); ?>
                    </button>
                    <button type="button" class="button button-primary gig-set-featured">
                        <?php esc_html_e('✓ Als Artikelbild', 'gemini-image-generator'); ?>
                    </button>
                </div>
            </div>

            <hr>
            <div class="gig-sections" data-loaded="false">
                <div class="gig-sections-header">
                    <strong><?php esc_html_e('Abschnittsbilder (H2)', 'gemini-image-generator'); ?></strong>
                    <button type="button" class="button-link gig-refresh-sections">⟳</button>
                </div>
                <div class="gig-sections-list">
                    <p class="gig-sections-placeholder"><?php esc_html_e('Abschnitte werden geladen…', 'gemini-image-generator'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_generate_prompt() {
        check_ajax_referer('gig_metabox_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Keine Berechtigung.', 'gemini-image-generator'));
        }

        $prompt = $this->prompt_generator->generate_prompt_for_post($post_id);

        if (is_wp_error($prompt)) {
            wp_send_json_error($prompt->get_error_message());
        }

        wp_send_json_success(array('prompt' => $prompt));
    }

    public function ajax_get_sections() {
        check_ajax_referer('gig_metabox_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Keine Berechtigung.', 'gemini-image-generator'));
        }

        $sections = $this->prompt_generator->get_h2_sections($post_id);

        wp_send_json_success(array('sections' => $sections));
    }

    public function ajax_generate_section_prompt() {
        check_ajax_referer('gig_metabox_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $section_id = isset($_POST['section_id']) ? sanitize_text_field($_POST['section_id']) : '';

        if (!$post_id || !$section_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Ungültige Anfrage.', 'gemini-image-generator'));
        }

        $prompt = $this->prompt_generator->generate_prompt_for_section($post_id, $section_id);

        if (is_wp_error($prompt)) {
            wp_send_json_error($prompt->get_error_message());
        }

        wp_send_json_success(array('prompt' => $prompt));
    }

    public function ajax_generate_image() {
        check_ajax_referer('gig_metabox_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Keine Berechtigung.', 'gemini-image-generator'));
        }

        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';

        if (empty($prompt)) {
            wp_send_json_error(__('Prompt ist erforderlich.', 'gemini-image-generator'));
        }

        // Filter: Prompt vor Generierung anpassen
        $prompt = apply_filters('gig_prompt_before_generation', $prompt, $post_id);

        $params = array(
            'ratio'   => isset($_POST['ratio']) ? sanitize_text_field($_POST['ratio']) : '16:9',
            'quality' => isset($_POST['quality']) ? sanitize_text_field($_POST['quality']) : 'high',
            'format'  => isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'webp',
            'style'   => isset($_POST['style']) ? sanitize_text_field($_POST['style']) : 'photorealistic',
            'mood'    => isset($_POST['mood']) ? sanitize_text_field($_POST['mood']) : '',
            'colors'  => isset($_POST['colors']) ? sanitize_text_field($_POST['colors']) : '',
        );

        // Filter: Bildparameter anpassen
        $params = apply_filters('gig_image_params', $params, $post_id);

        $section_id = isset($_POST['section_id']) ? sanitize_text_field($_POST['section_id']) : '';
        $max_width = isset($_POST['max_width']) ? absint($_POST['max_width']) : 0;
        $max_height = isset($_POST['max_height']) ? absint($_POST['max_height']) : 0;

        $image_response = $this->gemini_api->generate_image($prompt, $params);

        if (is_wp_error($image_response)) {
            wp_send_json_error($image_response->get_error_message());
        }

        $settings = $this->gemini_api->get_settings();
        $metadata = array();

        if (!empty($settings['auto_seo_meta'])) {
            $metadata = $this->gemini_api->generate_image_metadata($post_id, $prompt);
            
            // Filter: Metadaten anpassen
            $metadata = apply_filters('gig_image_metadata', $metadata, $post_id, $prompt);
        }

        $target_format = isset($image_response['target_format']) ? $image_response['target_format'] : 'webp';

        $attachment_id = $this->image_handler->save_base64_image(
            $image_response['data'],
            $image_response['mime_type'],
            $post_id,
            $prompt,
            $target_format,
            $metadata,
            array(
                'max_width'  => $max_width,
                'max_height' => $max_height,
            )
        );

        if (is_wp_error($attachment_id)) {
            wp_send_json_error($attachment_id->get_error_message());
        }

        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'image_url'     => wp_get_attachment_image_url($attachment_id, 'large'),
            'keyword'       => isset($metadata['keyword']) ? $metadata['keyword'] : '',
            'alt'           => isset($metadata['alt_text']) ? $metadata['alt_text'] : '',
            'caption'       => isset($metadata['caption']) ? $metadata['caption'] : '',
            'section_id'    => $section_id,
        ));
    }

    public function ajax_set_featured_image() {
        check_ajax_referer('gig_metabox_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

        if (!$post_id || !$attachment_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Ungültige Anfrage.', 'gemini-image-generator'));
        }

        $this->image_handler->set_featured_image($post_id, $attachment_id);

        wp_send_json_success(__('Artikelbild gesetzt.', 'gemini-image-generator'));
    }
}
