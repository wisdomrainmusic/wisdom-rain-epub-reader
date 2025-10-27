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
        if (!current_user_can('manage_options')) {
            return;
        }

        $categories = get_option('wrer_categories', []);
        if (!is_array($categories)) {
            $decoded = json_decode($categories, true);
            $categories = is_array($decoded) ? $decoded : [];
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Manage Categories', 'wrer'); ?></h1>

            <?php if (isset($_GET['added'])) : ?>
                <div class="notice notice-success"><p><?php esc_html_e('✅ Category added successfully.', 'wrer'); ?></p></div>
            <?php elseif (isset($_GET['deleted'])) : ?>
                <div class="notice notice-warning"><p><?php esc_html_e('🗑️ Category deleted successfully.', 'wrer'); ?></p></div>
            <?php elseif (isset($_GET['exists'])) : ?>
                <div class="notice notice-error"><p><?php esc_html_e('⚠️ This category already exists.', 'wrer'); ?></p></div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])) :
                $error = sanitize_text_field(wp_unslash($_GET['error']));
                $message = '';
                if ('invalid_nonce' === $error) {
                    $message = __('Action could not be verified. Please try again.', 'wrer');
                }
                if ($message) : ?>
                <div class="notice notice-error"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wrer_add_category">
                <?php wp_nonce_field('wrer_add_category_action', 'wrer_nonce'); ?>
                <label class="screen-reader-text" for="wrer-category-name"><?php esc_html_e('Category name', 'wrer'); ?></label>
                <input
                    type="text"
                    id="wrer-category-name"
                    name="category_name"
                    placeholder="<?php esc_attr_e('Add new category', 'wrer'); ?>"
                    required
                >
                <?php submit_button(__('Add Category', 'wrer'), 'primary', '', false); ?>
            </form>

            <hr>

            <?php if (empty($categories)) : ?>
                <p><?php esc_html_e('No categories defined yet.', 'wrer'); ?></p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'wrer'); ?></th>
                            <th><?php esc_html_e('Slug', 'wrer'); ?></th>
                            <th><?php esc_html_e('Actions', 'wrer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat) :
                            $name = isset($cat['name']) ? $cat['name'] : '';
                            $raw_slug = isset($cat['slug']) ? $cat['slug'] : '';
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
                            ?>
                            <tr>
                                <td><?php echo esc_html($name); ?></td>
                                <td><?php echo esc_html($raw_slug); ?></td>
                                <td>
                                    <a
                                        href="<?php echo esc_url($delete_url); ?>"
                                        onclick="return confirm(<?php echo wp_json_encode(__('Delete this category?', 'wrer')); ?>)"
                                    >
                                        <?php esc_html_e('Delete', 'wrer'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function add_category() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        $nonce = isset($_POST['wrer_nonce']) ? wp_unslash($_POST['wrer_nonce']) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'wrer_add_category_action')) {
            wp_safe_redirect(admin_url('admin.php?page=wrer-manage-categories&error=invalid_nonce'));
            exit;
        }

        $name = isset($_POST['category_name']) ? sanitize_text_field(wp_unslash($_POST['category_name'])) : '';
        $slug = sanitize_title($name);

        $categories = get_option('wrer_categories', []);
        if (!is_array($categories)) {
            $decoded = json_decode($categories, true);
            $categories = is_array($decoded) ? $decoded : [];
        }

        foreach ($categories as $c) {
            if (isset($c['slug']) && $c['slug'] === $slug) {
                wp_safe_redirect(admin_url('admin.php?page=wrer-manage-categories&exists=1'));
                exit;
            }
        }

        $categories[] = [
            'name' => $name,
            'slug' => $slug,
        ];
        update_option('wrer_categories', $categories);

        wp_safe_redirect(admin_url('admin.php?page=wrer-manage-categories&added=1'));
        exit;
    }

    public function delete_category() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        $nonce = isset($_GET['wrer_nonce']) ? wp_unslash($_GET['wrer_nonce']) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'wrer_delete_category_action')) {
            wp_safe_redirect(admin_url('admin.php?page=wrer-manage-categories&error=invalid_nonce'));
            exit;
        }

        $slug = isset($_GET['slug']) ? sanitize_text_field(wp_unslash($_GET['slug'])) : '';
        $categories = get_option('wrer_categories', []);
        if (!is_array($categories)) {
            $decoded = json_decode($categories, true);
            $categories = is_array($decoded) ? $decoded : [];
        }

        $categories = array_filter(
            $categories,
            static function ($c) use ($slug) {
                return isset($c['slug']) && $c['slug'] !== $slug;
            }
        );

        update_option('wrer_categories', array_values($categories));
        wp_safe_redirect(admin_url('admin.php?page=wrer-manage-categories&deleted=1'));
        exit;
    }
}
