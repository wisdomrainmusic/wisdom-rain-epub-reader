<?php
/**
 * Provides the reader editing interface in the admin area.
 *
 * @package WisdomRain\EPUBReader
 */

if (!defined('ABSPATH')) {
    exit;
}

class WRER_Admin_Edit
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_submenu']);
        add_action('admin_post_wrer_update_books', [$this, 'update_books']);
    }

    public function add_submenu(): void
    {
        add_submenu_page(
            null,
            __('Edit Reader', 'wrer'),
            __('Edit Reader', 'wrer'),
            'manage_options',
            'wrer-edit-reader',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to edit readers.', 'wrer'));
        }

        $reader_id = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : '';
        $readers = get_option('wrer_readers', []);
        if (!is_array($readers)) {
            $readers = [];
        }

        $reader = null;
        foreach ($readers as $item) {
            if (isset($item['id']) && (string) $item['id'] === $reader_id) {
                $reader = $item;
                break;
            }
        }

        $categories = get_option('wrer_categories', []);
        if (!is_array($categories)) {
            $categories = [];
        }

        $name = isset($reader['name']) ? $reader['name'] : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(sprintf(__('Manage Books for: %s', 'wrer'), $name)); ?></h1>
            <?php if (!$reader) : ?>
                <p><?php echo esc_html__('The requested reader could not be found.', 'wrer'); ?></p>
                <p>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wrer-manage-readers')); ?>">
                        <?php esc_html_e('Back to Readers', 'wrer'); ?>
                    </a>
                </p>
                <?php
                echo '</div>';
                return;
            endif;

            if (isset($_GET['updated'])) :
                ?>
                <div class="notice notice-success"><p><?php esc_html_e('Reader record updated.', 'wrer'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wrer_update_books">
                <input type="hidden" name="reader_id" value="<?php echo esc_attr($reader_id); ?>">
                <?php wp_nonce_field('wrer_update_books_' . $reader_id); ?>

                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Title', 'wrer'); ?></th>
                            <th><?php esc_html_e('Author', 'wrer'); ?></th>
                            <th><?php esc_html_e('Language', 'wrer'); ?></th>
                            <th><?php esc_html_e('Image URL', 'wrer'); ?></th>
                            <th><?php esc_html_e('EPUB URL', 'wrer'); ?></th>
                            <th><?php esc_html_e('Buy Link', 'wrer'); ?></th>
                            <th><?php esc_html_e('Delete', 'wrer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $books = isset($reader['books']) && is_array($reader['books']) ? $reader['books'] : [];
                        if (!empty($books)) {
                            foreach ($books as $index => $book) {
                                $this->render_book_row((string) $index, $book, $categories);
                            }
                        }

                        $this->render_book_row('new', [], $categories);
                        ?>
                    </tbody>
                </table>

                <?php submit_button(__('Update Reader Record', 'wrer')); ?>
            </form>
        </div>
        <?php
    }

    private function render_book_row(string $index, array $book, array $categories): void
    {
        $title = isset($book['title']) ? $book['title'] : '';
        $author = isset($book['author']) ? $book['author'] : '';
        $language = isset($book['language']) ? $book['language'] : '';
        $image = isset($book['image']) ? $book['image'] : '';
        $epub = isset($book['epub_url']) ? $book['epub_url'] : '';
        $buy = isset($book['buy_link']) ? $book['buy_link'] : '';

        ?>
        <tr>
            <td><input type="text" name="books[<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($title); ?>" placeholder="<?php echo esc_attr__('Book title', 'wrer'); ?>"></td>
            <td><input type="text" name="books[<?php echo esc_attr($index); ?>][author]" value="<?php echo esc_attr($author); ?>" placeholder="<?php echo esc_attr__('Author', 'wrer'); ?>"></td>
            <td>
                <select name="books[<?php echo esc_attr($index); ?>][language]">
                    <option value=""><?php esc_html_e('Select', 'wrer'); ?></option>
                    <?php foreach ($categories as $cat) :
                        $cat_name = isset($cat['name']) ? $cat['name'] : '';
                        ?>
                        <option value="<?php echo esc_attr($cat_name); ?>" <?php selected($language, $cat_name); ?>><?php echo esc_html($cat_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="url" name="books[<?php echo esc_attr($index); ?>][image]" value="<?php echo esc_attr($image); ?>" placeholder="<?php echo esc_attr__('Image URL', 'wrer'); ?>"></td>
            <td><input type="url" name="books[<?php echo esc_attr($index); ?>][epub_url]" value="<?php echo esc_attr($epub); ?>" placeholder="<?php echo esc_attr__('EPUB URL', 'wrer'); ?>"></td>
            <td><input type="url" name="books[<?php echo esc_attr($index); ?>][buy_link]" value="<?php echo esc_attr($buy); ?>" placeholder="<?php echo esc_attr__('Buy Link', 'wrer'); ?>"></td>
            <td><label class="screen-reader-text" for="wrer-delete-<?php echo esc_attr($index); ?>"><?php esc_html_e('Delete book', 'wrer'); ?></label><input type="checkbox" id="wrer-delete-<?php echo esc_attr($index); ?>" name="books[<?php echo esc_attr($index); ?>][delete]"></td>
        </tr>
        <?php
    }

    public function update_books(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to edit readers.', 'wrer'));
        }

        $reader_id = isset($_POST['reader_id']) ? sanitize_text_field(wp_unslash($_POST['reader_id'])) : '';
        if ($reader_id === '' || !check_admin_referer('wrer_update_books_' . $reader_id)) {
            wp_die(__('Nonce verification failed. Please try again.', 'wrer'));
        }

        $books = isset($_POST['books']) ? wp_unslash($_POST['books']) : [];
        if (!is_array($books)) {
            $books = [];
        }

        $readers = get_option('wrer_readers', []);
        if (!is_array($readers)) {
            $readers = [];
        }

        foreach ($readers as &$reader) {
            if (!isset($reader['id']) || (string) $reader['id'] !== $reader_id) {
                continue;
            }

            $new_books = [];
            foreach ($books as $book) {
                if (!is_array($book)) {
                    continue;
                }

                $delete = isset($book['delete']);
                $title = isset($book['title']) ? sanitize_text_field($book['title']) : '';
                if ($delete || $title === '') {
                    continue;
                }

                $new_books[] = [
                    'title' => $title,
                    'author' => isset($book['author']) ? sanitize_text_field($book['author']) : '',
                    'language' => isset($book['language']) ? sanitize_text_field($book['language']) : '',
                    'image' => isset($book['image']) ? esc_url_raw($book['image']) : '',
                    'epub_url' => isset($book['epub_url']) ? esc_url_raw($book['epub_url']) : '',
                    'buy_link' => isset($book['buy_link']) ? esc_url_raw($book['buy_link']) : '',
                ];
            }

            $reader['books'] = $new_books;
            break;
        }
        unset($reader);

        update_option('wrer_readers', $readers);

        $redirect = add_query_arg(
            [
                'page' => 'wrer-edit-reader',
                'id' => $reader_id,
                'updated' => 1,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }
}
