<?php
if (!defined('ABSPATH')) exit;

class WRER_Admin_Categories {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_submenu']);
        add_action('admin_post_wrer_add_category', [$this, 'add_category']);
        add_action('admin_post_wrer_delete_category', [$this, 'delete_category']);
    }

    public function add_submenu() {
        add_submenu_page(
            'wrer-overview',
            __('Manage Categories', 'wrer'),
            __('Manage Categories', 'wrer'),
            'manage_options',
            'wrer-manage-categories',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        if (!current_user_can('manage_options')) return;
        $categories = get_option('wrer_categories', []);
        if (!is_array($categories)) $categories = [];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Manage Categories', 'wrer') . '</h1>';

        if (isset($_GET['added'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Category added.', 'wrer') . '</p></div>';
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Category deleted.', 'wrer') . '</p></div>';
        }
        if (isset($_GET['exists'])) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Category already exists.', 'wrer') . '</p></div>';
        }
        if (isset($_GET['error'])) {
            $message = '';
            if (sanitize_text_field($_GET['error']) === 'invalid_nonce') {
                $message = esc_html__('Action could not be verified. Please try again.', 'wrer');
            }
            if ($message) {
                echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
            }
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="wrer_add_category">';
        wp_nonce_field('wrer_add_category_action', 'wrer_nonce');
        echo '<label class="screen-reader-text" for="wrer-category-name">' . esc_html__('Category name', 'wrer') . '</label>';
        echo '<input type="text" id="wrer-category-name" name="category_name" placeholder="' . esc_attr__('Add new category', 'wrer') . '" required> ';
        submit_button(__('Add Category', 'wrer'), 'primary', '', false);
        echo '</form><hr>';

        if (empty($categories)) {
            echo '<p>' . esc_html__('No categories defined yet.', 'wrer') . '</p>';
        } else {
            echo '<table class="widefat fixed striped"><thead>';
            echo '<tr><th>' . esc_html__('Name', 'wrer') . '</th><th>' . esc_html__('Slug', 'wrer') . '</th><th>' . esc_html__('Actions', 'wrer') . '</th></tr>';
            echo '</thead><tbody>';
            foreach ($categories as $cat) {
                $name = isset($cat['name']) ? esc_html($cat['name']) : '';
                $raw_slug = isset($cat['slug']) ? $cat['slug'] : '';
                $slug = esc_html($raw_slug);
                $delete_url = wp_nonce_url(
                    add_query_arg(
                        [
                            'action' => 'wrer_delete_category',
                            'slug'   => $raw_slug,
                        ],
                        admin_url('admin-post.php')
                    ),
                    'wrer_delete_category_action',
                    'wrer_nonce'
                );
                echo '<tr>';
                echo '<td>' . $name . '</td>';
                echo '<td>' . $slug . '</td>';
                echo '<td><a href="' . esc_url($delete_url) . '" onclick="return confirm(' . wp_json_encode(__('Delete this category?', 'wrer')) . ')">' . esc_html__('Delete', 'wrer') . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    public function add_category() {
        if (!current_user_can('manage_options')) wp_die('Access denied');

        if (!isset($_POST['wrer_nonce']) || !wp_verify_nonce($_POST['wrer_nonce'], 'wrer_add_category_action')) {
            wp_die('Action could not be verified. Please try again.');
        }

        $name = sanitize_text_field($_POST['category_name']);
        $slug = sanitize_title($name);

        $categories = get_option('wrer_categories', []);
        if (!is_array($categories)) $categories = [];

        // Duplicate kontrolü
        foreach ($categories as $c) {
            if ($c['slug'] === $slug) {
                wp_safe_redirect(admin_url('admin.php?page=wrer-manage-categories&exists=1'));
                exit;
            }
        }

        $categories[] = ['name' => $name, 'slug' => $slug];
        update_option('wrer_categories', $categories);
        wp_safe_redirect(admin_url('admin.php?page=wrer-manage-categories&added=1'));
        exit;
    }

    public function delete_category() {
        if (!current_user_can('manage_options')) wp_die('Access denied');

        if (!isset($_GET['wrer_nonce']) || !wp_verify_nonce($_GET['wrer_nonce'], 'wrer_delete_category_action')) {
            wp_die('Action could not be verified. Please try again.');
        }

        $slug = sanitize_text_field($_GET['slug']);
        $categories = get_option('wrer_categories', []);
        $categories = array_filter($categories, fn($c) => $c['slug'] !== $slug);
        update_option('wrer_categories', $categories);
        wp_safe_redirect(admin_url('admin.php?page=wrer-manage-categories&deleted=1'));
        exit;
    }
}
