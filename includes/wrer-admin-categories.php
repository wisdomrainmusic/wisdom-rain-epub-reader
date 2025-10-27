<?php
/**
 * Manage WRER category administration pages.
 *
 * @package WisdomRain\EPUBReader
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRER_Admin_Categories
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void
    {
        add_submenu_page(
            'wrer-overview',
            __('Manage Categories', 'wrer'),
            __('Manage Categories', 'wrer'),
            'manage_options',
            'wrer-manage-categories',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to manage categories.', 'wrer'));
        }

        $categories = get_option('wrer_categories', []);
        if (!is_array($categories)) {
            $categories = [];
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Manage Categories', 'wrer'); ?></h1>
            <p><?php echo esc_html__('Category management tools will arrive in a future update.', 'wrer'); ?></p>
            <?php if (!empty($categories)) : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'wrer'); ?></th>
                            <th><?php esc_html_e('Slug', 'wrer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $category) :
                        $name = isset($category['name']) ? $category['name'] : '';
                        $slug = isset($category['slug']) ? $category['slug'] : '';
                        ?>
                        <tr>
                            <td><?php echo esc_html($name); ?></td>
                            <td><?php echo esc_html($slug); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php echo esc_html__('No categories found yet.', 'wrer'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
