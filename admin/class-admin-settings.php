<?php
/**
 * Admin-Einstellungsseite mit Skalierung und SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class GIG_Admin_Settings {

    private $page_slug = 'gig-settings';
    private $gemini_api;

    public function __construct($gemini_api) {
        $this->gemini_api = $gemini_api;

        add_action('admin_menu', array($this, 'register_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_gig_test_api_connection', array($this, 'ajax_test_api'));
    }

    public function register_settings_page() {
        add_options_page(
            __('Gemini Image Generator', 'gemini-image-generator'),
            __('Gemini Image', 'gemini-image-generator'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('gig_settings_group', GIG_Gemini_API::OPTION_NAME, array($this, 'sanitize_settings'));

        // Sektion: API
        add_settings_section('gig_api_section', __('🔑 API Konfiguration', 'gemini-image-generator'), '__return_false', $this->page_slug);
        add_settings_field('gig_api_key', __('Gemini API Key', 'gemini-image-generator'), array($this, 'render_api_key_field'), $this->page_slug, 'gig_api_section');
        add_settings_field('gig_text_model', __('Textmodell', 'gemini-image-generator'), array($this, 'render_text_model_field'), $this->page_slug, 'gig_api_section');

        // Sektion: Prompt
        add_settings_section('gig_prompt_section', __('✨ Prompt-Einstellungen', 'gemini-image-generator'), '__return_false', $this->page_slug);
        add_settings_field('gig_system_prompt', __('System-Prompt', 'gemini-image-generator'), array($this, 'render_system_prompt_field'), $this->page_slug, 'gig_prompt_section');
        add_settings_field('gig_negative_prompt', __('Negativprompt', 'gemini-image-generator'), array($this, 'render_negative_prompt_field'), $this->page_slug, 'gig_prompt_section');

        // Sektion: Bild-Defaults
        add_settings_section('gig_image_section', __('🎨 Standard Bild-Einstellungen', 'gemini-image-generator'), '__return_false', $this->page_slug);
        add_settings_field('gig_default_style', __('Standard-Stil', 'gemini-image-generator'), array($this, 'render_style_field'), $this->page_slug, 'gig_image_section');
        add_settings_field('gig_default_mood', __('Standard-Stimmung', 'gemini-image-generator'), array($this, 'render_mood_field'), $this->page_slug, 'gig_image_section');
        add_settings_field('gig_default_colors', __('Standard-Farbpalette', 'gemini-image-generator'), array($this, 'render_colors_field'), $this->page_slug, 'gig_image_section');
        add_settings_field('gig_image_defaults', __('Technische Defaults', 'gemini-image-generator'), array($this, 'render_defaults_field'), $this->page_slug, 'gig_image_section');

        // Sektion: Skalierung & Optimierung
        add_settings_section('gig_resize_section', __('📐 Skalierung & Optimierung', 'gemini-image-generator'), array($this, 'render_resize_section_description'), $this->page_slug);
        add_settings_field('gig_resize_settings', __('Bildgröße', 'gemini-image-generator'), array($this, 'render_resize_field'), $this->page_slug, 'gig_resize_section');
        add_settings_field('gig_quality_settings', __('Komprimierung', 'gemini-image-generator'), array($this, 'render_quality_field'), $this->page_slug, 'gig_resize_section');

        // Sektion: SEO
        add_settings_section('gig_seo_section', __('🔍 SEO & Barrierefreiheit', 'gemini-image-generator'), array($this, 'render_seo_section_description'), $this->page_slug);
        add_settings_field('gig_seo_settings', __('Automatische Metadaten', 'gemini-image-generator'), array($this, 'render_seo_field'), $this->page_slug, 'gig_seo_section');
    }

    public function sanitize_settings($input) {
        $output = get_option(GIG_Gemini_API::OPTION_NAME, array());

        // API Key
        if (isset($input['api_key'])) {
            $api_key = trim(sanitize_text_field($input['api_key']));
            $output['api_key'] = !empty($api_key) ? GIG_Gemini_API::encrypt_api_key($api_key) : '';
        }

        // Text-Felder
        $text_fields = array('text_model', 'default_style', 'default_mood', 'default_colors', 'default_ratio', 'default_quality', 'default_format', 'resize_mode', 'default_caption');
        foreach ($text_fields as $field) {
            if (isset($input[$field])) {
                $output[$field] = sanitize_text_field($input[$field]);
            }
        }

        // Integer-Felder
        $int_fields = array('resize_width', 'resize_height', 'jpeg_quality', 'webp_quality');
        foreach ($int_fields as $field) {
            if (isset($input[$field])) {
                $output[$field] = absint($input[$field]);
            }
        }

        // Textarea-Felder
        if (isset($input['system_prompt'])) {
            $output['system_prompt'] = sanitize_textarea_field($input['system_prompt']);
        }
        if (isset($input['negative_prompt'])) {
            $output['negative_prompt'] = sanitize_textarea_field($input['negative_prompt']);
        }

        // Checkboxen
        $output['append_negative'] = !empty($input['append_negative']);
        $output['english_prompts'] = !empty($input['english_prompts']);
        $output['resize_enabled'] = !empty($input['resize_enabled']);
        $output['auto_seo_meta'] = !empty($input['auto_seo_meta']);

        return $output;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;

        $settings = $this->gemini_api->get_settings();
        $can_webp = GIG_Image_Handler::can_convert_webp();
        $has_rankmath = function_exists('rank_math') || class_exists('RankMath');
        ?>
        <div class="wrap gig-settings">
            <h1><?php esc_html_e('Gemini Image Generator', 'gemini-image-generator'); ?></h1>
            
            <?php if (!$can_webp): ?>
            <div class="notice notice-warning">
                <p><strong><?php esc_html_e('Hinweis:', 'gemini-image-generator'); ?></strong> 
                <?php esc_html_e('WebP-Konvertierung nicht verfügbar. Bitte Imagick installieren.', 'gemini-image-generator'); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($has_rankmath): ?>
            <div class="notice notice-info">
                <p>✓ <strong>RankMath SEO</strong> <?php esc_html_e('erkannt – SEO-Keywords werden automatisch übernommen.', 'gemini-image-generator'); ?></p>
            </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('gig_settings_group');
                do_settings_sections($this->page_slug);
                submit_button(__('Einstellungen speichern', 'gemini-image-generator'));
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e('API-Test', 'gemini-image-generator'); ?></h2>
            <button type="button" class="button button-secondary" id="gig-test-api" data-nonce="<?php echo esc_attr(wp_create_nonce('gig_test_api')); ?>">
                <?php esc_html_e('Verbindung testen', 'gemini-image-generator'); ?>
            </button>
            <span id="gig-test-result" style="margin-left:10px;"></span>
        </div>
        <style>
            .gig-settings .form-table th { width: 200px; }
            .gig-settings textarea.large-text { min-height: 80px; }
            .gig-settings .description { color: #666; margin-top: 5px; }
            .gig-settings hr { margin: 30px 0; }
            .gig-settings h2 { margin-top: 30px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
            .gig-resize-table td { padding: 5px 15px 5px 0; vertical-align: middle; }
            .gig-resize-table input[type="number"] { width: 100px; }
            .gig-inline-label { display: inline-block; min-width: 80px; }
            .gig-section-note { background: #f9f9f9; padding: 10px 15px; border-left: 4px solid #2271b1; margin: 10px 0; }
        </style>
        <?php
    }

    public function render_resize_section_description() {
        echo '<p class="description">' . esc_html__('Bilder werden automatisch für optimale Web-Performance skaliert und komprimiert.', 'gemini-image-generator') . '</p>';
    }

    public function render_seo_section_description() {
        echo '<p class="description">' . esc_html__('Automatische SEO-Metadaten für bessere Suchmaschinenoptimierung und Barrierefreiheit.', 'gemini-image-generator') . '</p>';
    }

    public function render_api_key_field() {
        $settings = $this->gemini_api->get_settings();
        ?>
        <input type="password" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[api_key]" 
               value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text" autocomplete="off">
        <p class="description">
            <a href="https://aistudio.google.com/app/apikey" target="_blank"><?php esc_html_e('API Key erstellen →', 'gemini-image-generator'); ?></a>
        </p>
        <?php
    }

    public function render_text_model_field() {
        $settings = $this->gemini_api->get_settings();
        $value = $settings['text_model'] ?? 'gemini-2.0-flash';
        ?>
        <select name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[text_model]">
            <option value="gemini-2.0-flash" <?php selected($value, 'gemini-2.0-flash'); ?>>Gemini 2.0 Flash (schnell)</option>
            <option value="gemini-3-pro-preview" <?php selected($value, 'gemini-3-pro-preview'); ?>>Gemini 3 Pro (beste Qualität)</option>
        </select>
        <p class="description"><?php esc_html_e('Für Prompt-Generierung und Keyword-Analyse.', 'gemini-image-generator'); ?></p>
        <?php
    }

    public function render_system_prompt_field() {
        $settings = $this->gemini_api->get_settings();
        ?>
        <textarea name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[system_prompt]" 
                  class="large-text" rows="4"><?php echo esc_textarea($settings['system_prompt']); ?></textarea>
        <p class="description"><?php esc_html_e('Steuert die automatische Bildprompt-Generierung.', 'gemini-image-generator'); ?></p>
        <p>
            <label>
                <input type="checkbox" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[english_prompts]" value="1" 
                       <?php checked($settings['english_prompts']); ?>>
                <?php esc_html_e('Prompts auf Englisch generieren (bessere Ergebnisse)', 'gemini-image-generator'); ?>
            </label>
        </p>
        <?php
    }

    public function render_negative_prompt_field() {
        $settings = $this->gemini_api->get_settings();
        ?>
        <textarea name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[negative_prompt]" 
                  class="large-text" rows="2"><?php echo esc_textarea($settings['negative_prompt']); ?></textarea>
        <p class="description"><?php esc_html_e('Elemente die vermieden werden sollen (Komma-getrennt).', 'gemini-image-generator'); ?></p>
        <p>
            <label>
                <input type="checkbox" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[append_negative]" value="1" 
                       <?php checked($settings['append_negative']); ?>>
                <?php esc_html_e('Negativprompt automatisch anhängen', 'gemini-image-generator'); ?>
            </label>
        </p>
        <?php
    }

    public function render_style_field() {
        $settings = $this->gemini_api->get_settings();
        $styles = GIG_Gemini_API::get_supported_styles();
        ?>
        <select name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[default_style]">
            <?php foreach ($styles as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_style'], $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_mood_field() {
        $settings = $this->gemini_api->get_settings();
        $moods = GIG_Gemini_API::get_supported_moods();
        ?>
        <select name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[default_mood]">
            <?php foreach ($moods as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_mood'], $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_colors_field() {
        $settings = $this->gemini_api->get_settings();
        $colors = GIG_Gemini_API::get_supported_colors();
        ?>
        <select name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[default_colors]">
            <?php foreach ($colors as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_colors'], $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_defaults_field() {
        $settings = $this->gemini_api->get_settings();
        $ratios = GIG_Gemini_API::get_supported_ratios();
        $qualities = GIG_Gemini_API::get_supported_qualities();
        $formats = GIG_Gemini_API::get_supported_formats();
        ?>
        <table class="gig-resize-table">
            <tr>
                <td>
                    <span class="gig-inline-label"><?php esc_html_e('Seitenverhältnis', 'gemini-image-generator'); ?></span>
                    <select name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[default_ratio]">
                        <?php foreach ($ratios as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_ratio'], $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <span class="gig-inline-label"><?php esc_html_e('Qualität', 'gemini-image-generator'); ?></span>
                    <select name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[default_quality]">
                        <?php foreach ($qualities as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_quality'], $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <span class="gig-inline-label"><?php esc_html_e('Dateiformat', 'gemini-image-generator'); ?></span>
                    <select name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[default_format]">
                        <?php foreach ($formats as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['default_format'], $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_resize_field() {
        $settings = $this->gemini_api->get_settings();
        ?>
        <p>
            <label>
                <input type="checkbox" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[resize_enabled]" value="1" 
                       <?php checked($settings['resize_enabled']); ?> id="gig-resize-enabled">
                <?php esc_html_e('Bilder automatisch skalieren', 'gemini-image-generator'); ?>
            </label>
        </p>
        
        <table class="gig-resize-table" id="gig-resize-options">
            <tr>
                <td>
                    <label>
                        <input type="radio" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[resize_mode]" value="width" 
                               <?php checked($settings['resize_mode'], 'width'); ?>>
                        <?php esc_html_e('Nur Breite (Höhe proportional)', 'gemini-image-generator'); ?>
                    </label>
                </td>
                <td>
                    <input type="number" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[resize_width]" 
                           value="<?php echo esc_attr($settings['resize_width']); ?>" min="100" max="4000" step="10"> px
                </td>
            </tr>
            <tr>
                <td>
                    <label>
                        <input type="radio" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[resize_mode]" value="height" 
                               <?php checked($settings['resize_mode'], 'height'); ?>>
                        <?php esc_html_e('Nur Höhe (Breite proportional)', 'gemini-image-generator'); ?>
                    </label>
                </td>
                <td>
                    <input type="number" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[resize_height]" 
                           value="<?php echo esc_attr($settings['resize_height']); ?>" min="100" max="4000" step="10"> px
                </td>
            </tr>
            <tr>
                <td>
                    <label>
                        <input type="radio" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[resize_mode]" value="both" 
                               <?php checked($settings['resize_mode'], 'both'); ?>>
                        <?php esc_html_e('Maximale Größe (proportional)', 'gemini-image-generator'); ?>
                    </label>
                </td>
                <td>
                    <span class="description"><?php esc_html_e('Verwendet Breite UND Höhe als Maximum', 'gemini-image-generator'); ?></span>
                </td>
            </tr>
            <tr>
                <td>
                    <label>
                        <input type="radio" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[resize_mode]" value="none" 
                               <?php checked($settings['resize_mode'], 'none'); ?>>
                        <?php esc_html_e('Keine Skalierung', 'gemini-image-generator'); ?>
                    </label>
                </td>
                <td>
                    <span class="description"><?php esc_html_e('Originalgröße beibehalten', 'gemini-image-generator'); ?></span>
                </td>
            </tr>
        </table>
        
        <p class="description"><?php esc_html_e('Empfohlen: 1200px Breite für optimale Web-Performance.', 'gemini-image-generator'); ?></p>
        <?php
    }

    public function render_quality_field() {
        $settings = $this->gemini_api->get_settings();
        ?>
        <table class="gig-resize-table">
            <tr>
                <td><span class="gig-inline-label"><?php esc_html_e('JPEG Qualität', 'gemini-image-generator'); ?></span></td>
                <td>
                    <input type="number" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[jpeg_quality]" 
                           value="<?php echo esc_attr($settings['jpeg_quality']); ?>" min="50" max="100" step="5"> %
                </td>
            </tr>
            <tr>
                <td><span class="gig-inline-label"><?php esc_html_e('WebP Qualität', 'gemini-image-generator'); ?></span></td>
                <td>
                    <input type="number" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[webp_quality]" 
                           value="<?php echo esc_attr($settings['webp_quality']); ?>" min="50" max="100" step="5"> %
                </td>
            </tr>
        </table>
        <p class="description"><?php esc_html_e('80-90% bietet gute Balance zwischen Qualität und Dateigröße.', 'gemini-image-generator'); ?></p>
        <?php
    }

    public function render_seo_field() {
        $settings = $this->gemini_api->get_settings();
        $has_rankmath = function_exists('rank_math') || class_exists('RankMath');
        ?>
        <p>
            <label>
                <input type="checkbox" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[auto_seo_meta]" value="1" 
                       <?php checked($settings['auto_seo_meta']); ?>>
                <?php esc_html_e('SEO-Metadaten automatisch setzen', 'gemini-image-generator'); ?>
            </label>
        </p>
        
        <div class="gig-section-note">
            <strong><?php esc_html_e('Automatisch generierte Metadaten:', 'gemini-image-generator'); ?></strong>
            <ul style="margin: 10px 0 0 20px;">
                <li><strong><?php esc_html_e('Alternativtext:', 'gemini-image-generator'); ?></strong> <?php esc_html_e('Kurzer Text mit SEO-Keyword', 'gemini-image-generator'); ?></li>
                <li><strong><?php esc_html_e('Titel:', 'gemini-image-generator'); ?></strong> <?php esc_html_e('Lesbarer Titel mit SEO-Keyword', 'gemini-image-generator'); ?></li>
                <li><strong><?php esc_html_e('Beschreibung:', 'gemini-image-generator'); ?></strong> <?php esc_html_e('Barrierefreie Bildbeschreibung für Screenreader', 'gemini-image-generator'); ?></li>
            </ul>
            <?php if ($has_rankmath): ?>
            <p style="margin-top: 10px; color: #00a32a;">✓ <?php esc_html_e('SEO-Keyword wird aus RankMath übernommen', 'gemini-image-generator'); ?></p>
            <?php else: ?>
            <p style="margin-top: 10px; color: #666;"><?php esc_html_e('SEO-Keyword wird automatisch aus dem Artikelinhalt ermittelt', 'gemini-image-generator'); ?></p>
            <?php endif; ?>
        </div>

        <p style="margin-top: 15px;">
            <label>
                <strong><?php esc_html_e('Standard-Untertitel (Caption):', 'gemini-image-generator'); ?></strong><br>
                <input type="text" name="<?php echo esc_attr(GIG_Gemini_API::OPTION_NAME); ?>[default_caption]" 
                       value="<?php echo esc_attr($settings['default_caption']); ?>" class="regular-text">
            </label>
        </p>
        <?php
    }

    public function enqueue_assets($hook) {
        if ('settings_page_' . $this->page_slug !== $hook) return;

        wp_enqueue_script('gig-settings', GIG_PLUGIN_URL . 'admin/js/settings.js', array('jquery'), GIG_PLUGIN_VERSION, true);
        wp_localize_script('gig-settings', 'GIGSettings', array('ajaxUrl' => admin_url('admin-ajax.php')));
    }

    public function ajax_test_api() {
        check_ajax_referer('gig_test_api', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung.', 'gemini-image-generator'));
        }

        $result = $this->gemini_api->generate_text('Antworte nur mit: OK');

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('API-Verbindung erfolgreich!', 'gemini-image-generator'));
    }
}
