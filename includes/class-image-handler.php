<?php
/**
 * Image Handler mit Skalierung, Optimierung und SEO-Metadaten
 */

if (!defined('ABSPATH')) {
    exit;
}

class GIG_Image_Handler {

    /**
     * Speichert, konvertiert, skaliert und optimiert das Bild
     */
    public function save_base64_image($base64_data, $source_mime, $post_id = 0, $prompt = '', $target_format = 'webp', $metadata = array(), $resize_override = array()) {
        if (empty($base64_data)) {
            return new WP_Error('gig_no_data', __('Keine Bilddaten.', 'gemini-image-generator'));
        }

        $image_binary = base64_decode($base64_data);
        if (false === $image_binary) {
            return new WP_Error('gig_decode_error', __('Dekodierung fehlgeschlagen.', 'gemini-image-generator'));
        }

        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return new WP_Error('gig_upload_error', $upload_dir['error']);
        }

        // Einstellungen holen
        $settings = $this->get_settings($resize_override);

        // Ziel-Format bestimmen
        $target_mime = $this->format_to_mime($target_format);
        $target_ext = $this->mime_to_extension($target_mime);

        // Bild verarbeiten (Konvertieren + Skalieren + Optimieren)
        $processed = $this->process_image($image_binary, $source_mime, $target_mime, $settings);
        
        if (!is_wp_error($processed)) {
            $image_binary = $processed;
        } else {
            // Bei Fehler: Original verwenden, aber Format anpassen
            $target_ext = $this->mime_to_extension($source_mime);
            $target_mime = $source_mime;
        }

        // Datei speichern
        $filename = sprintf('gig-%s-%s.%s', 
            $post_id ? $post_id : 'img',
            wp_generate_password(6, false),
            $target_ext
        );
        $file_path = trailingslashit($upload_dir['path']) . $filename;

        if (false === file_put_contents($file_path, $image_binary)) {
            return new WP_Error('gig_save_error', __('Speichern fehlgeschlagen.', 'gemini-image-generator'));
        }

        // Attachment erstellen
        $file_url = trailingslashit($upload_dir['url']) . $filename;
        
        // Titel aus Metadaten oder Fallback
        $title = !empty($metadata['title']) ? $metadata['title'] : __('KI-generiertes Bild', 'gemini-image-generator');
        
        $attachment = array(
            'guid'           => $file_url,
            'post_mime_type' => $target_mime,
            'post_title'     => wp_strip_all_tags($title),
            'post_content'   => !empty($metadata['description']) ? wp_strip_all_tags($metadata['description']) : '',
            'post_excerpt'   => !empty($metadata['caption']) ? wp_strip_all_tags($metadata['caption']) : '',
            'post_status'    => 'inherit',
        );

        $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);

        if (is_wp_error($attachment_id)) {
            @unlink($file_path);
            return $attachment_id;
        }

        // Attachment-Metadaten generieren
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_meta = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attach_meta);

        // SEO-Metadaten setzen
        $this->set_seo_metadata($attachment_id, $metadata);

        return $attachment_id;
    }

    /**
     * Verarbeitet das Bild (Konvertieren, Skalieren, Optimieren)
     */
    private function process_image($binary, $source_mime, $target_mime, $settings) {
        // Imagick bevorzugen
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            return $this->process_with_imagick($binary, $target_mime, $settings);
        }

        // GD als Fallback
        if (extension_loaded('gd')) {
            return $this->process_with_gd($binary, $source_mime, $target_mime, $settings);
        }

        return new WP_Error('gig_no_processor', __('Weder Imagick noch GD verfügbar.', 'gemini-image-generator'));
    }

    /**
     * Verarbeitung mit Imagick
     */
    private function process_with_imagick($binary, $target_mime, $settings) {
        try {
            $imagick = new Imagick();
            $imagick->readImageBlob($binary);

            // Skalierung
            if ($settings['resize_enabled'] && $settings['resize_mode'] !== 'none') {
                $this->resize_with_imagick($imagick, $settings);
            }

            // Format setzen
            $format_map = array(
                'image/webp' => 'WEBP',
                'image/png'  => 'PNG',
                'image/jpeg' => 'JPEG',
            );
            $format = isset($format_map[$target_mime]) ? $format_map[$target_mime] : 'WEBP';
            $imagick->setImageFormat($format);

            // Qualität und Optimierung
            if ($format === 'WEBP') {
                $imagick->setImageCompressionQuality($settings['webp_quality']);
            } elseif ($format === 'JPEG') {
                $imagick->setImageCompressionQuality($settings['jpeg_quality']);
                $imagick->setInterlaceScheme(Imagick::INTERLACE_PLANE); // Progressive JPEG
                $imagick->setSamplingFactors(array('2x2', '1x1', '1x1')); // Chroma subsampling
            } elseif ($format === 'PNG') {
                $imagick->setImageCompressionQuality(9);
            }

            // Metadaten entfernen für kleinere Dateien
            $imagick->stripImage();

            $result = $imagick->getImageBlob();
            $imagick->destroy();

            return $result;

        } catch (Exception $e) {
            return new WP_Error('gig_imagick_error', $e->getMessage());
        }
    }

    /**
     * Skalierung mit Imagick
     */
    private function resize_with_imagick(&$imagick, $settings) {
        $orig_width = $imagick->getImageWidth();
        $orig_height = $imagick->getImageHeight();
        
        $new_width = $orig_width;
        $new_height = $orig_height;

        switch ($settings['resize_mode']) {
            case 'width':
                if ($settings['resize_width'] > 0 && $orig_width > $settings['resize_width']) {
                    $new_width = $settings['resize_width'];
                    $new_height = (int) round($orig_height * ($new_width / $orig_width));
                }
                break;

            case 'height':
                if ($settings['resize_height'] > 0 && $orig_height > $settings['resize_height']) {
                    $new_height = $settings['resize_height'];
                    $new_width = (int) round($orig_width * ($new_height / $orig_height));
                }
                break;

            case 'both':
                $target_width = $settings['resize_width'] > 0 ? $settings['resize_width'] : $orig_width;
                $target_height = $settings['resize_height'] > 0 ? $settings['resize_height'] : $orig_height;

                $ratio_w = $target_width / $orig_width;
                $ratio_h = $target_height / $orig_height;
                $ratio = min($ratio_w, $ratio_h);

                if ($ratio < 1) {
                    $new_width = (int) round($orig_width * $ratio);
                    $new_height = (int) round($orig_height * $ratio);
                }
                break;
        }

        if ($new_width !== $orig_width || $new_height !== $orig_height) {
            $imagick->resizeImage($new_width, $new_height, Imagick::FILTER_LANCZOS, 1);
        }
    }

    /**
     * Verarbeitung mit GD
     */
    private function process_with_gd($binary, $source_mime, $target_mime, $settings) {
        $source = @imagecreatefromstring($binary);
        if (!$source) {
            return new WP_Error('gig_gd_error', __('GD konnte Bild nicht laden.', 'gemini-image-generator'));
        }

        // Skalierung
        if ($settings['resize_enabled'] && $settings['resize_mode'] !== 'none') {
            $source = $this->resize_with_gd($source, $settings);
        }

        // Alpha-Kanal erhalten
        imagealphablending($source, false);
        imagesavealpha($source, true);

        ob_start();

        switch ($target_mime) {
            case 'image/webp':
                if (!function_exists('imagewebp')) {
                    ob_end_clean();
                    imagedestroy($source);
                    return new WP_Error('gig_no_webp', __('WebP nicht von GD unterstützt.', 'gemini-image-generator'));
                }
                imagewebp($source, null, $settings['webp_quality']);
                break;

            case 'image/jpeg':
                $width = imagesx($source);
                $height = imagesy($source);
                $jpeg = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($jpeg, 255, 255, 255);
                imagefill($jpeg, 0, 0, $white);
                imagecopy($jpeg, $source, 0, 0, 0, 0, $width, $height);
                imagejpeg($jpeg, null, $settings['jpeg_quality']);
                imagedestroy($jpeg);
                break;

            case 'image/png':
            default:
                imagepng($source, null, 9);
                break;
        }

        $result = ob_get_clean();
        imagedestroy($source);

        return $result ?: new WP_Error('gig_gd_output_error', __('GD Ausgabe fehlgeschlagen.', 'gemini-image-generator'));
    }

    /**
     * Skalierung mit GD
     */
    private function resize_with_gd($source, $settings) {
        $orig_width = imagesx($source);
        $orig_height = imagesy($source);
        
        $new_width = $orig_width;
        $new_height = $orig_height;

        switch ($settings['resize_mode']) {
            case 'width':
                if ($settings['resize_width'] > 0 && $orig_width > $settings['resize_width']) {
                    $new_width = $settings['resize_width'];
                    $new_height = (int) round($orig_height * ($new_width / $orig_width));
                }
                break;

            case 'height':
                if ($settings['resize_height'] > 0 && $orig_height > $settings['resize_height']) {
                    $new_height = $settings['resize_height'];
                    $new_width = (int) round($orig_width * ($new_height / $orig_height));
                }
                break;

            case 'both':
                $target_width = $settings['resize_width'] > 0 ? $settings['resize_width'] : $orig_width;
                $target_height = $settings['resize_height'] > 0 ? $settings['resize_height'] : $orig_height;

                $ratio_w = $target_width / $orig_width;
                $ratio_h = $target_height / $orig_height;
                $ratio = min($ratio_w, $ratio_h);

                if ($ratio < 1) {
                    $new_width = (int) round($orig_width * $ratio);
                    $new_height = (int) round($orig_height * $ratio);
                }
                break;
        }

        if ($new_width !== $orig_width || $new_height !== $orig_height) {
            $resized = imagecreatetruecolor($new_width, $new_height);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
            imagedestroy($source);
            return $resized;
        }

        return $source;
    }

    /**
     * Setzt SEO-Metadaten für das Attachment
     */
    private function set_seo_metadata($attachment_id, $metadata) {
        // Alt-Text
        if (!empty($metadata['alt_text'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', wp_strip_all_tags($metadata['alt_text']));
        }

        // RankMath Metadaten (falls RankMath aktiv)
        if (function_exists('rank_math') || class_exists('RankMath')) {
            if (!empty($metadata['alt_text'])) {
                update_post_meta($attachment_id, 'rank_math_image_alt', wp_strip_all_tags($metadata['alt_text']));
            }
            if (!empty($metadata['title'])) {
                update_post_meta($attachment_id, 'rank_math_image_title', wp_strip_all_tags($metadata['title']));
            }
        }

        update_post_meta($attachment_id, '_gig_generated', 1);
        update_post_meta($attachment_id, '_gig_generated_date', current_time('mysql'));
        
        if (!empty($metadata['keyword'])) {
            update_post_meta($attachment_id, '_gig_seo_keyword', $metadata['keyword']);
        }
    }

    public function set_featured_image($post_id, $attachment_id) {
        return set_post_thumbnail($post_id, $attachment_id);
    }

    private function get_settings($override = array()) {
        $settings = get_option(GIG_Gemini_API::OPTION_NAME, array());
        
        $resize_enabled = isset($settings['resize_enabled']) ? (bool) $settings['resize_enabled'] : true;
        $resize_mode = isset($settings['resize_mode']) ? $settings['resize_mode'] : 'width';
        $resize_width = isset($settings['resize_width']) ? (int) $settings['resize_width'] : 1200;
        $resize_height = isset($settings['resize_height']) ? (int) $settings['resize_height'] : 800;

        if (!empty($override['max_width'])) {
            $resize_enabled = true;
            $resize_mode = 'width';
            $resize_width = (int) $override['max_width'];
        }

        if (!empty($override['max_height'])) {
            $resize_enabled = true;
            if (!empty($override['max_width'])) {
                $resize_mode = 'both';
                $resize_height = (int) $override['max_height'];
            } else {
                $resize_mode = 'height';
                $resize_height = (int) $override['max_height'];
            }
        }

        return array(
            'resize_enabled' => $resize_enabled,
            'resize_mode'    => $resize_mode,
            'resize_width'   => $resize_width,
            'resize_height'  => $resize_height,
            'jpeg_quality'   => isset($settings['jpeg_quality']) ? (int) $settings['jpeg_quality'] : 85,
            'webp_quality'   => isset($settings['webp_quality']) ? (int) $settings['webp_quality'] : 85,
        );
    }

    private function format_to_mime($format) {
        $map = array(
            'webp' => 'image/webp',
            'png'  => 'image/png',
            'jpeg' => 'image/jpeg',
            'jpg'  => 'image/jpeg',
        );
        return isset($map[strtolower($format)]) ? $map[strtolower($format)] : 'image/webp';
    }

    private function mime_to_extension($mime) {
        $map = array(
            'image/webp' => 'webp',
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
        );
        return isset($map[$mime]) ? $map[$mime] : 'png';
    }

    public static function can_convert_webp() {
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $formats = Imagick::queryFormats('WEBP');
            return !empty($formats);
        }
        return function_exists('imagewebp');
    }
}
