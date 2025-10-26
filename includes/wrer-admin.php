<?php
/**
 * Handles the WRER admin dashboard pages.
 *
 * @package WisdomRain\EPUBReader
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRER_Admin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_admin_menu']);
    }

    public function register_admin_menu(): void
    {
        add_menu_page(
            'WRER EPUB Engine',
            'WRER EPUB Engine',
            'manage_options',
            'wrer-overview',
            [$this, 'overview_page'],
            'dashicons-book-alt',
            6
        );

        add_submenu_page(
            'wrer-overview',
            'Overview',
            'Overview',
            'manage_options',
            'wrer-overview',
            [$this, 'overview_page']
        );

        add_submenu_page(
            'wrer-overview',
            'Manage Readers',
            'Manage Readers',
            'manage_options',
            'wrer-manage-readers',
            [$this, 'placeholder_page']
        );

        add_submenu_page(
            'wrer-overview',
            'Manage Categories',
            'Manage Categories',
            'manage_options',
            'wrer-manage-categories',
            [$this, 'placeholder_page']
        );
    }

    public function overview_page(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Wisdom Rain EPUB Reader', 'wrer') . '</h1>';
        echo '<p>' . esc_html__('Hello WRER Engine – Plugin successfully loaded.', 'wrer') . '</p>';
        echo '<p>' . esc_html__('Manage multilingual EPUB readers and categories from this dashboard.', 'wrer') . '</p>';
        echo '<hr>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wrer-manage-readers')) . '" class="button button-primary">' . esc_html__('Manage Readers', 'wrer') . '</a> ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wrer-manage-categories')) . '" class="button">' . esc_html__('Manage Categories', 'wrer') . '</a>';
        echo '</div>';
    }

    public function placeholder_page(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Coming Soon', 'wrer') . '</h1>';
        echo '<p>' . esc_html__('This section will be implemented in the next commits.', 'wrer') . '</p>';
        echo '</div>';
    }
}
