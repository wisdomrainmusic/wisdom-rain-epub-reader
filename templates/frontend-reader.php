<?php
if (!defined('ABSPATH')) {
    exit;
}

$books = isset($reader['books']) && is_array($reader['books']) ? $reader['books'] : [];
$languages = get_option('wrer_categories', []);
if (!is_array($languages)) {
    $decoded = json_decode($languages, true);
    $languages = is_array($decoded) ? $decoded : [];
}

$reader_id = isset($reader['id']) ? (string) $reader['id'] : '';
$reader_name = isset($reader['name']) ? (string) $reader['name'] : '';

$first_book = !empty($books) && isset($books[0]) && is_array($books[0]) ? $books[0] : null;
$first_buy_link = '';
$first_epub = '';
if (is_array($first_book)) {
    $first_buy_link = isset($first_book['buy_link']) ? (string) $first_book['buy_link'] : '';
    $first_epub = isset($first_book['epub_url']) ? (string) $first_book['epub_url'] : '';
}
?>

<div class="wrer-reader-container" data-reader-id="<?php echo esc_attr($reader_id); ?>">
    <div class="wrer-header">
        <h2><?php echo esc_html($reader_name); ?></h2>
        <a
            id="wrer-buy-link"
            class="wrer-buy-btn<?php echo $first_buy_link ? '' : ' wrer-buy-btn--disabled'; ?>"
            href="<?php echo $first_buy_link ? esc_url($first_buy_link) : '#'; ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            <?php esc_html_e('Buy Now', 'wrer'); ?>
        </a>
    </div>

    <div class="wrer-language-filter">
        <label class="screen-reader-text" for="wrer-language"><?php esc_html_e('Filter by language', 'wrer'); ?></label>
        <select id="wrer-language">
            <option value="all"><?php esc_html_e('All Languages', 'wrer'); ?></option>
            <?php foreach ($languages as $lang) :
                if (!is_array($lang)) {
                    continue;
                }
                $slug = isset($lang['slug']) ? (string) $lang['slug'] : '';
                $name = isset($lang['name']) ? (string) $lang['name'] : '';
                ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="wrer-books" class="wrer-books-grid">
        <?php foreach ($books as $index => $book) :
            if (!is_array($book)) {
                continue;
            }
            $language = isset($book['language']) ? (string) $book['language'] : '';
            $image = isset($book['image']) ? (string) $book['image'] : '';
            $title = isset($book['title']) ? (string) $book['title'] : '';
            $author = isset($book['author']) ? (string) $book['author'] : '';
            $epub = isset($book['epub_url']) ? (string) $book['epub_url'] : '';
            $buy_link = isset($book['buy_link']) ? (string) $book['buy_link'] : '';
            $book_id = 'book-' . $index;
            ?>
            <div class="wrer-book" data-language="<?php echo esc_attr($language); ?>">
                <?php if ($image) : ?>
                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>">
                <?php else : ?>
                    <div class="wrer-book-placeholder" aria-hidden="true">📚</div>
                <?php endif; ?>
                <h4><?php echo esc_html($title); ?></h4>
                <p><?php echo esc_html($author); ?></p>
                <?php if ($epub) : ?>
                    <button
                        type="button"
                        class="wrer-read-btn"
                        data-book-id="<?php echo esc_attr($book_id); ?>"
                        data-epub="<?php echo esc_url($epub); ?>"
                        data-title="<?php echo esc_attr($title); ?>"
                        data-author="<?php echo esc_attr($author); ?>"
                        data-buy="<?php echo esc_url($buy_link); ?>"
                    >
                        <?php esc_html_e('Read Now', 'wrer'); ?>
                    </button>
                <?php else : ?>
                    <p class="wrer-missing-epub"><?php esc_html_e('EPUB not available yet.', 'wrer'); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="wrer-reader-wrapper" id="wrer-reader-shell">
        <div class="wrer-reader-toolbar">
            <button type="button" id="wrer-prev" class="wrer-btn" aria-label="<?php esc_attr_e('Previous page', 'wrer'); ?>">⬅</button>
            <span id="wrer-progress" class="wrer-progress-text"><?php esc_html_e('Page 1', 'wrer'); ?></span>
            <button type="button" id="wrer-next" class="wrer-btn" aria-label="<?php esc_attr_e('Next page', 'wrer'); ?>">➡</button>
            <button type="button" id="wrer-bookmark" class="wrer-btn" aria-label="<?php esc_attr_e('Add bookmark', 'wrer'); ?>">🔖</button>
            <button type="button" id="wrer-fullscreen" class="wrer-btn" aria-label="<?php esc_attr_e('Toggle fullscreen', 'wrer'); ?>">⛶</button>
        </div>

        <div id="wrer-reader" class="wrer-reader-area" aria-live="polite"></div>

        <div
            id="wrer-resume-popup"
            class="wrer-resume hidden"
            role="alertdialog"
            aria-labelledby="wrer-resume-message"
            aria-modal="true"
        >
            <p id="wrer-resume-message"><?php esc_html_e('Continue from where you left off?', 'wrer'); ?></p>
            <div class="wrer-resume-actions">
                <button type="button" id="wrer-resume-yes" class="wrer-resume-btn wrer-resume-btn--primary"><?php esc_html_e('Yes', 'wrer'); ?></button>
                <button type="button" id="wrer-resume-no" class="wrer-resume-btn wrer-resume-btn--secondary"><?php esc_html_e('Start Over', 'wrer'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php if ($first_epub && $reader_id !== '') : ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    if (typeof window.wrerInitReader === "function") {
        window.wrerInitReader("<?php echo esc_url($first_epub); ?>", "<?php echo esc_attr($reader_id); ?>", {
            bookId: "book-0",
            title: "<?php echo esc_js(isset($first_book['title']) ? (string) $first_book['title'] : ''); ?>",
            author: "<?php echo esc_js(isset($first_book['author']) ? (string) $first_book['author'] : ''); ?>",
            buyLink: "<?php echo esc_url($first_buy_link); ?>",
            autoOpen: false
        });
    }
});
</script>
<?php endif; ?>
