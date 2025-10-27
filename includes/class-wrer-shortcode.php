<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WRER_DEFAULT_PDF_WORKER')) {
    define('WRER_DEFAULT_PDF_WORKER', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js');
}

if (!function_exists('wrer_reader_shortcode')) {
    /**
     * Renders the WRER PDF reader shortcode.
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string
     */
    function wrer_reader_shortcode($atts): string
    {
        $atts = shortcode_atts([
            'src' => '',
        ], $atts, 'wrer_reader');

        $raw_src = trim((string) $atts['src']);
        $pdf_url = '';

        if ($raw_src === '') {
            return '<p>' . esc_html__('No PDF was specified for the reader.', 'wrer') . '</p>';
        }

        if (is_numeric($raw_src)) {
            $attachment_url = wp_get_attachment_url((int) $raw_src);
            if (is_string($attachment_url) && $attachment_url !== '') {
                $pdf_url = $attachment_url;
            }
        }

        if ($pdf_url === '' && filter_var($raw_src, FILTER_VALIDATE_URL)) {
            $pdf_url = $raw_src;
        }

        if ($pdf_url === '') {
            $upload_dir = wp_get_upload_dir();
            if (!empty($upload_dir['baseurl'])) {
                $candidate = trailingslashit($upload_dir['baseurl']) . ltrim($raw_src, '/');
                $pdf_url = $candidate;
            }
        }

        if ($pdf_url === '' && str_starts_with($raw_src, '/')) {
            $pdf_url = home_url($raw_src);
        }

        if ($pdf_url === '') {
            $pdf_url = $raw_src;
        }

        $parsed_path = parse_url($pdf_url, PHP_URL_PATH);
        $extension = strtolower((string) pathinfo(is_string($parsed_path) ? $parsed_path : '', PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            return '<p>' . esc_html__('The provided file is not a valid PDF.', 'wrer') . '</p>';
        }

        if (function_exists('wrer_enqueue_reader_assets')) {
            wrer_enqueue_reader_assets();
        }

        $resume_key = 'wrer_pdf_resume_' . md5($pdf_url);
        $viewer_id = 'wrer-viewer-' . substr(md5($resume_key), 0, 8);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($viewer_id); ?>"
             class="wrer-container wrer-reader-item"
             data-src="<?php echo esc_url($pdf_url); ?>"
             data-resume-key="<?php echo esc_attr($resume_key); ?>"
             data-worker="<?php echo esc_url(WRER_DEFAULT_PDF_WORKER); ?>">
            <div class="wrer-controls" role="toolbar" aria-label="<?php esc_attr_e('Document controls', 'wrer'); ?>">
                <button type="button" class="wrer-control wrer-prev" aria-label="<?php esc_attr_e('Previous page', 'wrer'); ?>">
                    ◀ <?php esc_html_e('Prev', 'wrer'); ?>
                </button>
                <span class="wrer-page-info" aria-live="polite">
                    <?php esc_html_e('Page', 'wrer'); ?>
                    <span class="wrer-current-page">1</span>
                    <span class="wrer-page-separator">/</span>
                    <span class="wrer-total-pages">--</span>
                </span>
                <button type="button" class="wrer-control wrer-next" aria-label="<?php esc_attr_e('Next page', 'wrer'); ?>">
                    <?php esc_html_e('Next', 'wrer'); ?> ▶
                </button>
                <button type="button" class="wrer-control wrer-zoom-out" aria-label="<?php esc_attr_e('Zoom out', 'wrer'); ?>">
                    −
                </button>
                <span class="wrer-zoom-indicator">100%</span>
                <button type="button" class="wrer-control wrer-zoom-in" aria-label="<?php esc_attr_e('Zoom in', 'wrer'); ?>">
                    +
                </button>
            </div>
            <canvas class="wrer-canvas" role="img"></canvas>
            <div class="wrer-status" aria-live="polite" hidden></div>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}

add_action('init', static function () {
    if (shortcode_exists('wrer_reader')) {
        remove_shortcode('wrer_reader');
    }

    add_shortcode('wrer_reader', 'wrer_reader_shortcode');
}, 20);
