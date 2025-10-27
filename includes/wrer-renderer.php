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
        'wrer-reader',
        WRER_URL . 'assets/js/wrer-reader.js',
        [],
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

add_shortcode('wrer_reader', 'wrer_render_reader_shortcode');

/**
 * Renders the WRER reader shortcode.
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string
 */
function wrer_render_reader_shortcode($atts): string
{
    $atts = shortcode_atts(['id' => ''], $atts);
    $reader_id = sanitize_text_field($atts['id']);

    if ($reader_id === '') {
        return '<p>' . esc_html__('Reader not found.', 'wrer') . '</p>';
    }

    $readers = get_option('wrer_readers', []);
    if (!is_array($readers)) {
        $decoded = json_decode($readers, true);
        $readers = is_array($decoded) ? $decoded : [];
    }

    $reader = null;
    foreach ($readers as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $candidate_id = isset($candidate['id']) ? (string) $candidate['id'] : '';
        if ($candidate_id === $reader_id) {
            $reader = $candidate;
            break;
        }
    }

    if (!is_array($reader)) {
        return '<p>' . esc_html__('Reader not found.', 'wrer') . '</p>';
    }

    $books = isset($reader['books']) && is_array($reader['books']) ? $reader['books'] : [];
    if (empty($books)) {
        return '<p>' . esc_html__('No books available for this reader yet.', 'wrer') . '</p>';
    }

    wrer_enqueue_reader_assets();

    ob_start();

    /** @var array $reader */
    include WRER_PATH . 'templates/frontend-reader.php';

    return (string) ob_get_clean();
}
