<?php
/**
 * Plugin Name: Gemini Image Generator
 * Description: Generiert KI-basierte Artikelbilder mit Google Gemini 3 Pro Image direkt aus dem Editor.
 * Version: 1.0.0
 * Author: Digital Magazin
 * Text Domain: gemini-image-generator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GIG_PLUGIN_VERSION', '1.0.0');
define('GIG_PLUGIN_FILE', __FILE__);
define('GIG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GIG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GIG_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once GIG_PLUGIN_DIR . 'includes/class-gemini-api.php';
require_once GIG_PLUGIN_DIR . 'includes/class-prompt-generator.php';
require_once GIG_PLUGIN_DIR . 'includes/class-image-handler.php';
require_once GIG_PLUGIN_DIR . 'admin/class-admin-settings.php';
require_once GIG_PLUGIN_DIR . 'admin/class-editor-metabox.php';
require_once GIG_PLUGIN_DIR . 'admin/class-bulk-generator.php';

/**
 * Haupt-Plugin-Klasse
 */
final class GIG_Plugin {

    private static $instance = null;

    public $gemini_api;
    public $prompt_generator;
    public $image_handler;

    private function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        $this->gemini_api = new GIG_Gemini_API();
        $this->prompt_generator = new GIG_Prompt_Generator($this->gemini_api);
        $this->image_handler = new GIG_Image_Handler();

        if (is_admin()) {
            new GIG_Admin_Settings($this->gemini_api);
            new GIG_Editor_Metabox($this->gemini_api, $this->prompt_generator, $this->image_handler);
            new GIG_Bulk_Generator($this->gemini_api, $this->prompt_generator, $this->image_handler);
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain('gemini-image-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

function gig_bootstrap_plugin() {
    GIG_Plugin::get_instance();
}
add_action('plugins_loaded', 'gig_bootstrap_plugin');

// Alte Plugin-Datei löschen falls vorhanden
register_activation_hook(__FILE__, function() {
    $old_file = GIG_PLUGIN_DIR . 'ai-featured-image-generator.php';
    if (file_exists($old_file)) {
        @unlink($old_file);
    }
});
