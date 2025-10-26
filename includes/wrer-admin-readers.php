<?php
/**
 * Manage reader CRUD actions in admin.
 *
 * @package WisdomRain\EPUBReader
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRER_Admin_Readers
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_submenu']);
        add_action('admin_post_wrer_create_reader', [$this, 'create_reader']);
        add_action('admin_post_wrer_delete_reader', [$this, 'delete_reader']);
        add_action('admin_post_wrer_duplicate_reader', [$this, 'duplicate_reader']);
        add_action('admin_post_wrer_rename_reader', [$this, 'rename_reader']);
    }

    public function add_submenu(): void
    {
        add_submenu_page(
            'wrer-overview',
            __('Manage Readers', 'wrer'),
            __('Manage Readers', 'wrer'),
            'manage_options',
            'wrer-manage-readers',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to manage readers.', 'wrer'));
        }

        $readers = get_option('wrer_readers', []);
        if (!is_array($readers)) {
            $readers = [];
        }

        $create_nonce = wp_create_nonce('wrer_create_reader');

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Manage Readers', 'wrer'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wrer_create_reader">
                <input type="hidden" name="wrer_reader_nonce" value="<?php echo esc_attr($create_nonce); ?>">
                <label class="screen-reader-text" for="wrer-reader-name"><?php esc_html_e('Reader name', 'wrer'); ?></label>
                <input type="text" id="wrer-reader-name" name="reader_name" placeholder="<?php echo esc_attr__('Reader name', 'wrer'); ?>" required>
                <label class="screen-reader-text" for="wrer-reader-slug"><?php esc_html_e('Reader slug', 'wrer'); ?></label>
                <input type="text" id="wrer-reader-slug" name="reader_slug" placeholder="<?php echo esc_attr__('Slug (optional)', 'wrer'); ?>">
                <?php submit_button(__('Create Reader', 'wrer'), 'primary', '', false); ?>
            </form>
            <hr>
        <?php

        if (empty($readers)) {
            echo '<p>' . esc_html__('No readers found.', 'wrer') . '</p>';
            echo '</div>';
            return;
        }

        ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'wrer'); ?></th>
                        <th><?php esc_html_e('Slug', 'wrer'); ?></th>
                        <th><?php esc_html_e('Books', 'wrer'); ?></th>
                        <th><?php esc_html_e('Shortcode', 'wrer'); ?></th>
                        <th><?php esc_html_e('Actions', 'wrer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($readers as $reader) :
                    $id = isset($reader['id']) ? $reader['id'] : '';
                    $nonce_rename = wp_create_nonce('wrer_rename_reader_' . $id);

                    $name = isset($reader['name']) ? $reader['name'] : '';
                    $slug = isset($reader['slug']) ? $reader['slug'] : '';
                    $books = isset($reader['books']) && is_array($reader['books']) ? count($reader['books']) : 0;
                    $shortcode = sprintf('[wrer_reader id="%s"]', $id);

                    $edit_url = admin_url('admin.php?page=wrer-edit-reader&id=' . rawurlencode($id));
                    $delete_url = wp_nonce_url(admin_url('admin-post.php?action=wrer_delete_reader&id=' . rawurlencode($id)), 'wrer_delete_reader_' . $id);
                    $duplicate_url = wp_nonce_url(admin_url('admin-post.php?action=wrer_duplicate_reader&id=' . rawurlencode($id)), 'wrer_duplicate_reader_' . $id);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($name); ?></strong></td>
                        <td><?php echo esc_html($slug); ?></td>
                        <td><?php echo intval($books); ?></td>
                        <td>
                            <input type="text" readonly value="<?php echo esc_attr($shortcode); ?>" class="wrer-shortcode-field" style="width:250px;">
                            <button class="button wrer-copy-btn" data-code="<?php echo esc_attr($shortcode); ?>"><?php esc_html_e('Copy', 'wrer'); ?></button>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit Books', 'wrer'); ?></a> |
                            <a href="<?php echo esc_url($duplicate_url); ?>"><?php esc_html_e('Duplicate', 'wrer'); ?></a> |
                            <a href="#" class="wrer-rename" data-id="<?php echo esc_attr($id); ?>" data-nonce="<?php echo esc_attr($nonce_rename); ?>"><?php esc_html_e('Rename', 'wrer'); ?></a> |
                            <a href="<?php echo esc_url($delete_url); ?>" class="wrer-delete" data-name="<?php echo esc_attr($name); ?>"><?php esc_html_e('Delete', 'wrer'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const deleteMessage = <?php echo wp_json_encode(__('Delete this reader?', 'wrer')); ?>;
            const deleteMessageNamed = <?php echo wp_json_encode(__('Delete the reader "%s"?', 'wrer')); ?>;
            const copyLabel = <?php echo wp_json_encode(__('Copy', 'wrer')); ?>;
            const copiedLabel = <?php echo wp_json_encode(__('Copied!', 'wrer')); ?>;

            document.querySelectorAll('.wrer-delete').forEach(link => {
                link.addEventListener('click', event => {
                    const message = link.dataset.name ? deleteMessageNamed.replace('%s', link.dataset.name) : deleteMessage;
                    if (!confirm(message)) {
                        event.preventDefault();
                    }
                });
            });

            document.querySelectorAll('.wrer-rename').forEach(btn => {
                btn.addEventListener('click', event => {
                    event.preventDefault();
                    const id = btn.dataset.id;
                    const nonce = btn.dataset.nonce;
                    const newName = window.prompt('<?php echo esc_js(__('Enter new name:', 'wrer')); ?>');
                    if (!newName) {
                        return;
                    }
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '<?php echo esc_url(admin_url('admin-post.php')); ?>';

                    const actionField = document.createElement('input');
                    actionField.type = 'hidden';
                    actionField.name = 'action';
                    actionField.value = 'wrer_rename_reader';
                    form.appendChild(actionField);

                    const idField = document.createElement('input');
                    idField.type = 'hidden';
                    idField.name = 'id';
                    idField.value = id;
                    form.appendChild(idField);

                    const nameField = document.createElement('input');
                    nameField.type = 'hidden';
                    nameField.name = 'new_name';
                    nameField.value = newName;
                    form.appendChild(nameField);

                    const nonceField = document.createElement('input');
                    nonceField.type = 'hidden';
                    nonceField.name = 'wrer_reader_nonce';
                    nonceField.value = nonce;
                    form.appendChild(nonceField);

                    document.body.appendChild(form);
                    form.submit();
                });
            });

            const showCopiedState = btn => {
                btn.textContent = copiedLabel;
                btn.disabled = true;
                setTimeout(() => {
                    btn.textContent = copyLabel;
                    btn.disabled = false;
                }, 1500);
            };

            document.querySelectorAll('.wrer-copy-btn').forEach(btn => {
                btn.addEventListener('click', event => {
                    event.preventDefault();
                    const code = btn.dataset.code || '';
                    if (!code) {
                        return;
                    }

                    const copyPromise = navigator.clipboard && navigator.clipboard.writeText
                        ? navigator.clipboard.writeText(code)
                        : Promise.reject();

                    copyPromise
                        .then(() => {
                            showCopiedState(btn);
                        })
                        .catch(() => {
                            const tempInput = document.createElement('textarea');
                            tempInput.value = code;
                            tempInput.setAttribute('readonly', 'readonly');
                            tempInput.style.position = 'fixed';
                            tempInput.style.opacity = '0';
                            document.body.appendChild(tempInput);
                            tempInput.select();
                            try {
                                document.execCommand('copy');
                                showCopiedState(btn);
                            } catch (err) {
                                // Ignore copy errors silently.
                            }
                            document.body.removeChild(tempInput);
                        });
                });
            });
        });
        </script>
        <?php
    }

    public function create_reader(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to create readers.', 'wrer'));
        }

        $nonce = isset($_POST['wrer_reader_nonce']) ? $_POST['wrer_reader_nonce'] : '';
        if (!wp_verify_nonce($nonce, 'wrer_create_reader')) {
            wp_die(__('Nonce verification failed. Please try again.', 'wrer'));
        }

        $name = isset($_POST['reader_name']) ? sanitize_text_field(wp_unslash($_POST['reader_name'])) : '';
        $slug_input = isset($_POST['reader_slug']) ? sanitize_text_field(wp_unslash($_POST['reader_slug'])) : '';
        if ($name === '') {
            wp_safe_redirect(admin_url('admin.php?page=wrer-manage-readers&error=missing_name'));
            exit;
        }

        $slug = $slug_input !== '' ? sanitize_title($slug_input) : sanitize_title($name);

        $readers = get_option('wrer_readers', []);
        if (!is_array($readers)) {
            $readers = [];
        }

        $id = uniqid('wrer_', true);
        $readers[] = [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'books' => [],
        ];

        update_option('wrer_readers', $readers);
        wp_safe_redirect(admin_url('admin.php?page=wrer-manage-readers&created=1'));
        exit;
    }

    public function delete_reader(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to delete readers.', 'wrer'));
        }

        $id = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : '';
        if ($id === '' || !check_admin_referer('wrer_delete_reader_' . $id)) {
            wp_die(__('Nonce verification failed. Please try again.', 'wrer'));
        }

        $readers = get_option('wrer_readers', []);
        if (!is_array($readers)) {
            $readers = [];
        }

        $readers = array_values(array_filter($readers, static fn($reader) => isset($reader['id']) && $reader['id'] !== $id));

        update_option('wrer_readers', $readers);
        wp_safe_redirect(admin_url('admin.php?page=wrer-manage-readers&deleted=1'));
        exit;
    }

    public function duplicate_reader(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to duplicate readers.', 'wrer'));
        }

        $id = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : '';
        if ($id === '' || !check_admin_referer('wrer_duplicate_reader_' . $id)) {
            wp_die(__('Nonce verification failed. Please try again.', 'wrer'));
        }

        $readers = get_option('wrer_readers', []);
        if (!is_array($readers)) {
            $readers = [];
        }

        foreach ($readers as $reader) {
            if (isset($reader['id']) && $reader['id'] === $id) {
                $copy = $reader;
                $copy['id'] = uniqid('wrer_', true);
                $copy['name'] = trim((isset($reader['name']) ? $reader['name'] : '') . ' (' . __('Copy', 'wrer') . ')');
                $copy['slug'] = sanitize_title('copy-' . $copy['id']);
                $readers[] = $copy;
                break;
            }
        }

        update_option('wrer_readers', $readers);
        wp_safe_redirect(admin_url('admin.php?page=wrer-manage-readers&duplicated=1'));
        exit;
    }

    public function rename_reader(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to rename readers.', 'wrer'));
        }

        $nonce = isset($_POST['wrer_reader_nonce']) ? $_POST['wrer_reader_nonce'] : '';
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        if ($id === '' || !wp_verify_nonce($nonce, 'wrer_rename_reader_' . $id)) {
            wp_die(__('Nonce verification failed. Please try again.', 'wrer'));
        }

        $new_name = isset($_POST['new_name']) ? sanitize_text_field(wp_unslash($_POST['new_name'])) : '';
        if ($new_name === '') {
            wp_safe_redirect(admin_url('admin.php?page=wrer-manage-readers&error=missing_name'));
            exit;
        }

        $readers = get_option('wrer_readers', []);
        if (!is_array($readers)) {
            $readers = [];
        }

        foreach ($readers as &$reader) {
            if (isset($reader['id']) && $reader['id'] === $id) {
                $reader['name'] = $new_name;
                break;
            }
        }
        unset($reader);

        update_option('wrer_readers', $readers);
        wp_safe_redirect(admin_url('admin.php?page=wrer-manage-readers&renamed=1'));
        exit;
    }
}
