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

$reader_name = isset($reader['name']) ? (string) $reader['name'] : '';
$first_buy_link = '';
if (!empty($books) && isset($books[0]['buy_link'])) {
    $first_buy_link = (string) $books[0]['buy_link'];
}
?>

<div class="wrer-reader-container">
    <div class="wrer-header">
        <h2><?php echo esc_html($reader_name); ?></h2>
        <button class="wrer-buy-btn" onclick="window.open('<?php echo esc_url($first_buy_link ?: '#'); ?>','_blank')">
            <?php esc_html_e('Buy Now', 'wrer'); ?>
        </button>
    </div>

    <div class="wrer-language-filter">
        <select id="wrer-language">
            <option value="all"><?php esc_html_e('All Languages', 'wrer'); ?></option>
            <?php foreach ($languages as $lang) :
                $slug = isset($lang['slug']) ? (string) $lang['slug'] : '';
                $name = isset($lang['name']) ? (string) $lang['name'] : '';
                ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($name); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="wrer-books" class="wrer-books-grid">
        <?php foreach ($books as $book) :
            $language = isset($book['language']) ? (string) $book['language'] : '';
            $image = isset($book['image']) ? (string) $book['image'] : '';
            $title = isset($book['title']) ? (string) $book['title'] : '';
            $author = isset($book['author']) ? (string) $book['author'] : '';
            ?>
            <div class="wrer-book" data-language="<?php echo esc_attr($language); ?>">
                <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>">
                <h4><?php echo esc_html($title); ?></h4>
                <p><?php echo esc_html($author); ?></p>
                <div class="wrer-epub-area">
                    <?php esc_html_e('📖 EPUB Reader will appear here...', 'wrer'); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
