<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register frontend assets for the reader experience.
 */
function wrer_register_reader_assets(): void
{
    wp_register_style(
        'wrer-reader',
        WRER_URL . 'assets/css/wrer-reader.css',
        [],
        WRER_VERSION
    );

    wp_register_script(
        'wrer-pdfjs',
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
        [],
        '3.11.174',
        true
    );

    wp_register_script(
        'wrer-reader',
        WRER_URL . 'assets/js/wrer-reader.js',
        ['wrer-pdfjs'],
        WRER_VERSION,
        true
    );
}
add_action('init', 'wrer_register_reader_assets');

/**
 * Ensures the reader assets are enqueued when the shortcode is rendered.
 */
function wrer_enqueue_reader_assets(): void
{
    if (!wp_style_is('wrer-reader', 'registered')) {
        wrer_register_reader_assets();
    }

    wp_enqueue_style('wrer-reader');
    wp_enqueue_script('wrer-pdfjs');
    wp_enqueue_script('wrer-reader');
}

/**
 * Determines whether the current request should load the reader assets.
 */
function wrer_request_has_reader(): bool
{
    if (is_admin()) {
        return false;
    }

    $posts = [];

    if (is_singular()) {
        $post = get_post();
        if ($post instanceof \WP_Post) {
            $posts[] = $post;
        }
    } elseif (!empty($GLOBALS['posts']) && is_array($GLOBALS['posts'])) {
        $posts = array_filter(
            $GLOBALS['posts'],
            static fn($post) => $post instanceof \WP_Post
        );
    }

    foreach ($posts as $post) {
        if (has_shortcode($post->post_content ?? '', 'wrer_reader')) {
            return true;
        }
    }

    return false;
}

/**
 * Conditionally enqueue reader assets when the shortcode is present.
 */
function wrer_maybe_enqueue_reader_assets(): void
{
    if (wrer_request_has_reader()) {
        wrer_enqueue_reader_assets();
    }
}

add_action('wp_enqueue_scripts', 'wrer_maybe_enqueue_reader_assets');

require_once WRER_PATH . 'includes/class-wrer-shortcode.php';
