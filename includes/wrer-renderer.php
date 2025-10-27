<?php
if (!defined('ABSPATH')) exit;

class WRER_Renderer {

    public function __construct() {
        add_shortcode('wrer_reader', [$this, 'render_reader']);
    }

    public function render_reader($atts) {
        $atts = shortcode_atts(['id' => ''], $atts);
        $id = sanitize_text_field($atts['id']);
        if (!$id) return '<p>⚠️ Reader ID missing.</p>';

        $readers = get_option('wrer_readers', []);
        $reader = null;

        foreach ($readers as $r) {
            if ($r['id'] === $id) {
                $reader = $r;
                break;
            }
        }

        if (!$reader) return '<p>⚠️ Reader not found.</p>';
        if (empty($reader['books'])) return '<p>No books added to this reader yet.</p>';

        ob_start();

        echo '<div class="wrer-container" data-reader-id="' . esc_attr($id) . '">';
        echo '<div class="wrer-header">';
        echo '<h2>' . esc_html($reader['name']) . '</h2>';
        echo '<button class="wrer-buy">Buy Now</button>';
        echo '</div>';

        // Language Dropdown
        echo '<div class="wrer-lang-select">';
        echo '<select class="wrer-lang">';
        echo '<option value="all">All Languages</option>';
        $langs = [];
        foreach ($reader['books'] as $b) {
            if (!in_array($b['language'], $langs) && !empty($b['language'])) {
                $langs[] = $b['language'];
                echo '<option value="' . esc_attr($b['language']) . '">' . esc_html($b['language']) . '</option>';
            }
        }
        echo '</select>';
        echo '</div>';

        // Book List
        echo '<div class="wrer-book-list">';
        foreach ($reader['books'] as $b) {
            echo '<div class="wrer-book" data-lang="' . esc_attr($b['language']) . '">';
            echo '<img src="' . esc_url($b['image']) . '" alt="' . esc_attr($b['title']) . '" style="width:80px;height:80px;object-fit:cover;margin-right:10px;">';
            echo '<div><strong>' . esc_html($b['title']) . '</strong><br>';
            echo '<span>' . esc_html($b['author']) . ' • ' . esc_html($b['language']) . '</span></div>';
            echo '</div>';
        }
        echo '</div>';

        // Reader Placeholder
        echo '<div class="wrer-reader-area">';
        echo '<p>📖 EPUB Reader will appear here...</p>';
        echo '</div>';

        echo '</div>';

        // Basic filtering JS
        ?>
        <script>
        document.querySelectorAll('.wrer-lang').forEach(select => {
          select.addEventListener('change', e => {
            const lang = e.target.value;
            const container = e.target.closest('.wrer-container');
            container.querySelectorAll('.wrer-book').forEach(book => {
              book.style.display = (lang === 'all' || book.dataset.lang === lang) ? 'flex' : 'none';
            });
          });
        });
        </script>
        <style>
        .wrer-container{border:1px solid #eee;padding:20px;margin:20px 0;font-family:Inter, sans-serif;}
        .wrer-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
        .wrer-buy{background:#e60000;color:#fff;padding:6px 12px;border:none;border-radius:4px;cursor:pointer;}
        .wrer-book-list{display:flex;flex-direction:column;gap:10px;}
        .wrer-book{display:flex;align-items:center;border:1px solid #f2f2f2;padding:10px;border-radius:8px;}
        .wrer-reader-area{margin-top:20px;background:#fff;border:1px dashed #ccc;padding:30px;text-align:center;}
        </style>
        <?php
        return ob_get_clean();
    }
}
