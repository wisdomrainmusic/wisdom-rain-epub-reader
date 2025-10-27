<?php
if (!defined('ABSPATH')) exit;

class WRER_Admin_Categories {

    public function __construct() {
        add_action('admin_post_wrer_add_category', [$this, 'add_category']);
        add_action('admin_post_wrer_delete_category', [$this, 'delete_category']);
    }

    public function render_page() {
        if (!current_user_can('manage_options')) return;
        $categories = get_option('wrer_categories', []);
        if (!is_array($categories)) $categories = [];

        echo '<div class="wrap">';
        echo '<h1>Manage Categories</h1>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        echo '<input type="hidden" name="action" value="wrer_add_category">';
        echo '<input type="text" name="category_name" placeholder="Add new category" required> ';
        submit_button('Add Category', 'primary', '', false);
        echo '</form><hr>';

        if (empty($categories)) {
            echo '<p>No categories defined yet.</p>';
        } else {
            echo '<table class="widefat fixed striped"><thead>
                    <tr><th>Name</th><th>Slug</th><th>Actions</th></tr>
                  </thead><tbody>';
            foreach ($categories as $cat) {
                $name = isset($cat['name']) ? esc_html($cat['name']) : '';
                $raw_slug = isset($cat['slug']) ? $cat['slug'] : '';
                $slug = esc_html($raw_slug);
                $delete_url = add_query_arg(
                    ['action' => 'wrer_delete_category', 'slug' => $raw_slug],
                    admin_url('admin-post.php')
                );
                echo '<tr>';
                echo '<td>' . $name . '</td>';
                echo '<td>' . $slug . '</td>';
                echo '<td><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Delete this category?\')">Delete</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    public function add_category() {
        if (!current_user_can('manage_options')) return;
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
        if (!current_user_can('manage_options')) return;
        $slug = sanitize_text_field($_GET['slug']);
        $categories = get_option('wrer_categories', []);
        $categories = array_filter($categories, fn($c) => $c['slug'] !== $slug);
        update_option('wrer_categories', $categories);
        wp_safe_redirect(admin_url('admin.php?page=wrer-manage-categories&deleted=1'));
        exit;
    }
}

new WRER_Admin_Categories();
