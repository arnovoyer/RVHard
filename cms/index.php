    <?php
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');

    define('ROOT_DIR', dirname(__DIR__));
    define('DATA_FILE', ROOT_DIR . '/data/news/news-cms.json');
    define('NEWS_DIR', ROOT_DIR . '/news');
    define('LEGACY_SLUGS_FILE', ROOT_DIR . '/data/news/legacy-slugs.json');
    define('NEWS_IMAGE_DIR', ROOT_DIR . '/assets/img/news');
    define('NEWS_IMAGE_PUBLIC_PATH', '/assets/img/news');
    define('LOGIN_ATTEMPTS_FILE', ROOT_DIR . '/data/login-attempts.json');
    define('CONFIG_FILE', __DIR__ . '/config.php');
    define('GENERATOR_MARKER', '<!-- generated: simple-cms -->');

    function default_tag_options(): array
    {
    return [
        'Verein', 'MTB', 'Rennrad', 'Triathlon', 'Rennbericht', 'Rennen', 'Nachwuchs', 'HC', 'LM',
        'Cyclocross', 'AYC', 'Nightrace', 'SBS', 'ÖM', 'Deutschland', 'Österreich', 'Schweiz',
        'Tirol', 'Frankreich', 'EM', 'WM', 'Marathon', 'Laufen', 'Cup', 'Alpencup', 'Bikecup',
        'XCO', 'CrossCountry', 'Charity', 'Trainingslager', 'Training', 'Event', 'Challenge', '2026',
        'Moritz', 'Ralf', 'Andi', 'Ina', 'Abradeln', 'Gravel', 'Gran Fondo'
    ];
    }

    function default_sparte_options(): array
    {
        return ['Verein', 'MTB', 'Rennrad', 'Triathlon', 'Rennbericht', 'Rennen', 'Nachwuchs', 'HC', 'LM', 'Cyclocross', 'AYC', 'Nightrace', 'SBS', 'ÖM'];
    }

    function normalize_text(string $text): string
    {
        $value = trim($text);
        if ($value === '') {
        return '';
        }

        $map = [
        'Ã¤' => 'ä', 'Ã„' => 'Ä', 'Ã¶' => 'ö', 'Ã–' => 'Ö', 'Ã¼' => 'ü', 'Ãœ' => 'Ü',
        'ÃŸ' => 'ß', 'â€“' => '–', 'â€”' => '—', 'â€ž' => '„', 'â€œ' => '“', 'â€˜' => '‘',
        'â€™' => '’', 'Â ' => ' ', 'Â' => ''
        ];

        return strtr($value, $map);
    }

    function read_config(): ?array
    {
        if (!file_exists(CONFIG_FILE)) {
            return null;
        }

        $config = require CONFIG_FILE;
        if (!is_array($config)) {
            return null;
        }

        if (empty($config['username']) || empty($config['password_hash'])) {
            return null;
        }

        return $config;
    }

    function ensure_logged_in(): void
    {
        if (!empty($_SESSION['cms_logged_in'])) {
            return;
        }

        header('Location: /cms/index.php');
        exit;
    }

    function get_csrf_token(): string
    {
        if (empty($_SESSION['cms_csrf_token']) || !is_string($_SESSION['cms_csrf_token'])) {
            $_SESSION['cms_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['cms_csrf_token'];
    }

    function verify_csrf_token(?string $token): bool
    {
        $sessionToken = $_SESSION['cms_csrf_token'] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '' || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    function get_client_ip(): string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if ($ip === '') {
            return 'unknown';
        }
        return $ip;
    }

    function load_login_attempts(): array
    {
        if (!file_exists(LOGIN_ATTEMPTS_FILE)) {
            return [];
        }

        $json = file_get_contents(LOGIN_ATTEMPTS_FILE);
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : [];
    }

    function save_login_attempts(array $attempts): void
    {
        $dir = dirname(LOGIN_ATTEMPTS_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            LOGIN_ATTEMPTS_FILE,
            json_encode($attempts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            LOCK_EX
        );
    }

    function login_attempt_key(string $username, string $ip): string
    {
        return hash('sha256', mb_strtolower(trim($username)) . '|' . trim($ip));
    }

    function get_login_lockout_seconds(string $username, string $ip): int
    {
        $attempts = load_login_attempts();
        $key = login_attempt_key($username, $ip);
        $entry = $attempts[$key] ?? null;
        if (!is_array($entry)) {
            return 0;
        }

        $now = time();
        $blockedUntil = (int)($entry['blocked_until'] ?? 0);
        if ($blockedUntil > $now) {
            return $blockedUntil - $now;
        }

        return 0;
    }

    function record_failed_login_attempt(string $username, string $ip): int
    {
        $windowSeconds = 15 * 60;
        $maxAttempts = 5;
        $blockSeconds = 15 * 60;

        $attempts = load_login_attempts();
        $key = login_attempt_key($username, $ip);
        $now = time();

        foreach ($attempts as $k => $entry) {
            $blockedUntil = (int)($entry['blocked_until'] ?? 0);
            $fails = array_values(array_filter((array)($entry['fails'] ?? []), static fn($ts) => (int)$ts > ($now - $windowSeconds)));
            if (!$fails && $blockedUntil <= $now) {
                unset($attempts[$k]);
                continue;
            }
            $attempts[$k]['fails'] = $fails;
        }

        $entry = $attempts[$key] ?? ['fails' => [], 'blocked_until' => 0];
        $fails = array_values(array_filter((array)($entry['fails'] ?? []), static fn($ts) => (int)$ts > ($now - $windowSeconds)));
        $fails[] = $now;

        $blockedUntil = (int)($entry['blocked_until'] ?? 0);
        if (count($fails) >= $maxAttempts) {
            $blockedUntil = $now + $blockSeconds;
            $fails = [];
        }

        $attempts[$key] = [
            'fails' => $fails,
            'blocked_until' => $blockedUntil,
        ];

        save_login_attempts($attempts);
        return max(0, $blockedUntil - $now);
    }

    function clear_login_attempts(string $username, string $ip): void
    {
        $attempts = load_login_attempts();
        $key = login_attempt_key($username, $ip);
        if (isset($attempts[$key])) {
            unset($attempts[$key]);
            save_login_attempts($attempts);
        }
    }

    function normalize_items(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $entry = [
                'id' => (int)($item['id'] ?? 0),
            'slug' => normalize_text((string)($item['slug'] ?? '')),
            'title' => normalize_text((string)($item['title'] ?? '')),
            'date' => normalize_text((string)($item['date'] ?? '')),
            'content' => normalize_text((string)($item['content'] ?? '')),
            'body' => normalize_text((string)($item['body'] ?? '')),
            'tags' => array_values(array_filter(array_map(static fn($tag) => normalize_text((string)$tag), (array)($item['tags'] ?? [])), static fn($tag) => $tag !== '')),
            'author' => normalize_text((string)($item['author'] ?? '')),
                'image' => trim((string)($item['image'] ?? '')),
                'images' => [],
            'link' => normalize_text((string)($item['link'] ?? '')),
            ];

            $images = [];
            foreach ((array)($item['images'] ?? []) as $imgItem) {
                if (is_string($imgItem) && trim($imgItem) !== '') {
                    $images[] = ['image' => trim($imgItem)];
                } elseif (is_array($imgItem) && !empty($imgItem['image'])) {
                    $images[] = ['image' => trim((string)$imgItem['image'])];
                }
            }
            $entry['images'] = $images;

            if ($entry['body'] === '' && $entry['content'] !== '') {
                $entry['body'] = $entry['content'];
            }

            if ($entry['image'] === '' && !empty($images[0]['image'])) {
                $entry['image'] = $images[0]['image'];
            }

            if ($entry['id'] <= 0) {
                continue;
            }

            $normalized[] = $entry;
        }

        usort($normalized, static function (array $a, array $b): int {
            return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
        });

        return $normalized;
    }

    function load_items(): array
    {
        if (!file_exists(DATA_FILE)) {
            return [];
        }

        $json = file_get_contents(DATA_FILE);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            $items = $decoded['items'];
        } elseif (array_is_list($decoded)) {
            $items = $decoded;
        }

        return normalize_items($items);
    }

    function save_items(array $items): bool
    {
        $payload = ['items' => normalize_items($items)];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return false;
        }

        return file_put_contents(DATA_FILE, $json . PHP_EOL, LOCK_EX) !== false;
    }

    function slug_conflicts_with_existing_file(string $slug, string $currentSlug = ''): bool
    {
        $slug = strtolower(trim($slug));
        $currentSlug = strtolower(trim($currentSlug));

        if ($slug === '' || $slug === $currentSlug) {
            return false;
        }

        $targetFile = NEWS_DIR . '/' . $slug . '.html';
        return is_file($targetFile);
    }

    function get_next_id(array $items): int
    {
        $max = 0;
        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id > $max) {
                $max = $id;
            }
        }
        return $max + 1;
    }

    function markdown_to_html(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($markdown));
        if (!$lines) {
            return '';
        }

        $out = [];
        $inList = false;

        $flushList = static function () use (&$out, &$inList): void {
            if ($inList) {
                $out[] = '</ul>';
                $inList = false;
            }
        };

        foreach ($lines as $rawLine) {
            $line = trim((string)$rawLine);
            if ($line === '') {
                $flushList();
                continue;
            }

            if (str_starts_with($line, '### ')) {
                $flushList();
                $out[] = '<h3>' . htmlspecialchars(substr($line, 4), ENT_QUOTES, 'UTF-8') . '</h3>';
                continue;
            }
            if (str_starts_with($line, '## ')) {
                $flushList();
                $out[] = '<h2>' . htmlspecialchars(substr($line, 3), ENT_QUOTES, 'UTF-8') . '</h2>';
                continue;
            }
            if (str_starts_with($line, '# ')) {
                $flushList();
                $out[] = '<h1>' . htmlspecialchars(substr($line, 2), ENT_QUOTES, 'UTF-8') . '</h1>';
                continue;
            }

            if (str_starts_with($line, '- ') || str_starts_with($line, '* ')) {
                if (!$inList) {
                    $out[] = '<ul>';
                    $inList = true;
                }
                $out[] = '<li>' . htmlspecialchars(substr($line, 2), ENT_QUOTES, 'UTF-8') . '</li>';
                continue;
            }

            $flushList();
            $text = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
            $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
            $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
            $out[] = '<p>' . $text . '</p>';
        }

        $flushList();
        return implode("\n", $out);
    }

    function body_to_html(string $body, string $fallbackTeaser = ''): string
    {
        $content = trim($body);
        if ($content === '') {
        $content = trim($fallbackTeaser);
        }

        if ($content === '') {
        return '';
        }

        if (preg_match('/<\s*[a-z][^>]*>/i', $content)) {
        return sanitize_editor_html($content);
        }

        return markdown_to_html($content);
    }

    function sanitize_editor_html(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (!class_exists('DOMDocument')) {
            return strip_tags($html, '<p><br><strong><em><u><ul><ol><li><h1><h2><h3><h4><blockquote><a><span>');
        }

        $allowedTags = [
            'p' => [],
            'br' => [],
            'strong' => [],
            'em' => [],
            'u' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'blockquote' => [],
            'span' => [],
            'a' => ['href', 'target', 'rel'],
        ];

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="cms-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('cms-root');
        if (!$root) {
            return '';
        }

        sanitize_dom_children($root, $allowedTags);

        return dom_inner_html($root);
    }

    function sanitize_dom_children(DOMNode $node, array $allowedTags): void
    {
        if (!$node->hasChildNodes()) {
            return;
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = mb_strtolower($child->nodeName);

                if (!array_key_exists($tag, $allowedTags)) {
                    unwrap_dom_node($child);
                    continue;
                }

                sanitize_dom_attributes($child, $tag, $allowedTags[$tag]);
                sanitize_dom_children($child, $allowedTags);
                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    function sanitize_dom_attributes(DOMNode $node, string $tag, array $allowedAttributes): void
    {
        if (!$node instanceof DOMElement) {
            return;
        }

        $toRemove = [];
        foreach ($node->attributes as $attribute) {
            $name = mb_strtolower($attribute->name);
            if (str_starts_with($name, 'on') || !in_array($name, $allowedAttributes, true)) {
                $toRemove[] = $attribute->name;
            }
        }

        foreach ($toRemove as $attributeName) {
            $node->removeAttribute($attributeName);
        }

        if ($tag === 'a') {
            $href = trim((string)$node->getAttribute('href'));
            $isSafeHref = $href !== '' && (
                str_starts_with($href, '/')
                || str_starts_with($href, '#')
                || (bool)preg_match('/^(https?:|mailto:)/i', $href)
            );

            if (!$isSafeHref) {
                $node->removeAttribute('href');
            }

            if ($node->hasAttribute('target')) {
                $target = mb_strtolower(trim((string)$node->getAttribute('target')));
                if ($target !== '_blank') {
                    $node->removeAttribute('target');
                }
            }

            if ($node->hasAttribute('target') && $node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer');
            } else {
                $node->removeAttribute('rel');
            }
        }
    }

    function unwrap_dom_node(DOMNode $node): void
    {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    function dom_inner_html(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return trim($html);
    }

    function render_gallery(array $item): string
    {
        $images = [];
        if (!empty($item['image'])) {
            $images[] = trim((string)$item['image']);
        }

        foreach ((array)($item['images'] ?? []) as $img) {
            if (is_array($img) && !empty($img['image'])) {
                $images[] = trim((string)$img['image']);
            }
        }

        $images = array_values(array_unique(array_filter($images, static fn($i) => $i !== '')));
        if (!$images) {
            return '';
        }

        $slides = '';
        foreach ($images as $src) {
            $safeSrc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
            $slides .= '<div class="gallery-slide" style="background-image: url(\'' . $safeSrc . '\');"></div>';
        }

        return '<div class="news-detail-gallery-wrapper" data-aos="fade-up"><div class="news-detail-gallery">' . $slides . '</div><button class="gallery-control prev">&lt;</button><button class="gallery-control next">&gt;</button></div>';
    }

    function generate_article_html(array $item): bool
    {
        $slug = trim((string)($item['slug'] ?? ''));
        if ($slug === '') {
            return false;
        }

        $title = htmlspecialchars((string)($item['title'] ?? 'News'), ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars((string)($item['date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $bodyMarkdown = (string)($item['body'] ?? '');
        $bodyHtml = body_to_html($bodyMarkdown, (string)($item['content'] ?? ''));
        $galleryHtml = render_gallery($item);

        $html = "<!DOCTYPE html>\n"
            . GENERATOR_MARKER . "\n"
            . "<html lang=\"de\">\n"
            . "<head>\n"
            . "  <meta charset=\"UTF-8\" />\n"
            . "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />\n"
            . "  <title>{$title} | RV Hard</title>\n"
            . "  <meta name=\"description\" content=\"News und Rennberichte des RV Hard.\" />\n"
        . "  <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\" />\n"
        . "  <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin />\n"
        . "  <link rel=\"stylesheet\" href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap\" media=\"print\" onload=\"this.media='all'\" />\n"
        . "  <noscript><link rel=\"stylesheet\" href=\"https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap\" /></noscript>\n"
            . "  <link rel=\"stylesheet\" href=\"/assets/style.css\" />\n"
            . "  <link rel=\"stylesheet\" href=\"/assets/news/news.css\" />\n"
        . "  <link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/aos@3.0.0-beta.6/dist/aos.css\" />\n"
            . "</head>\n"
            . "<body>\n"
            . "  <header><nav id=\"navigation-container\"></nav></header>\n"
            . "  <main class=\"news-detail-page\">\n"
            . "    <section class=\"container\">\n"
            . "      <div class=\"news-layout\">\n"
            . "        <div id=\"news-detail-container\" data-aos=\"fade-up\">\n"
            . "          <h1 id=\"news-title\" data-aos=\"fade-up\">{$title}</h1>\n"
            . "          <p id=\"news-date\" class=\"date\" data-aos=\"fade-up\">{$date}</p>\n"
            . "          <div id=\"news-content\" class=\"news-content-detail\" data-aos=\"fade-up\">{$galleryHtml}{$bodyHtml}</div>\n"
            . "        </div>\n"
            . "        <div class=\"news-sidebar\" data-aos=\"fade-left\">\n"
            . "          <aside id=\"related-news\" class=\"more-news\">\n"
            . "            <h2>Weitere Neuigkeiten</h2>\n"
            . "            <div class=\"related-news-list\"></div>\n"
            . "          </aside>\n"
            . "        </div>\n"
            . "      </div>\n"
            . "    </section>\n"
            . "  </main>\n"
            . "  <footer id=\"site-footer\"></footer>\n"
            . "  <script src=\"/assets/navigation-loader.js\"></script>\n"
            . "  <script src=\"/assets/news/news.js\" defer></script>\n"
            . "  <script src=\"/assets/news/related-news-loader.js\" defer></script>\n"
            . "  <script src=\"/assets/nav.js\" defer></script>\n"
            . "  <script src=\"/assets/header-scroll.js\" defer></script>\n"
            . "  <script src=\"/assets/cookie-banner.js\" defer></script>\n"
            . "  <script src=\"https://cdn.jsdelivr.net/npm/aos@3.0.0-beta.6/dist/aos.js\"></script>\n"
            . "  <script>\n"
            . "    document.addEventListener('DOMContentLoaded', async () => {\n"
            . "      try {\n"
            . "        const navRes = await fetch('/assets/navigationneu.html');\n"
            . "        if (navRes.ok) {\n"
            . "          document.getElementById('navigation-container').innerHTML = await navRes.text();\n"
            . "          if (typeof initNavigationMenu === 'function') initNavigationMenu();\n"
            . "        }\n"
            . "        const footerRes = await fetch('/assets/footer.html');\n"
            . "        if (footerRes.ok) document.getElementById('site-footer').innerHTML = await footerRes.text();\n"
            . "        if (typeof window.initNewsGalleries === 'function') window.initNewsGalleries(document);\n"
            . "        if (window.AOS && typeof window.AOS.init === 'function') {\n"
            . "          window.AOS.init({ duration: 1000, once: true, mirror: false });\n"
            . "        }\n"
            . "      } catch (error) { console.error(error); }\n"
            . "    });\n"
            . "  </script>\n"
            . "</body>\n"
            . "</html>\n";

        $target = NEWS_DIR . '/' . $slug . '.html';
        return file_put_contents($target, $html, LOCK_EX) !== false;
    }

    function refresh_legacy_slugs(): void
    {
        $files = glob(NEWS_DIR . '/*.html') ?: [];
        $slugs = [];

        foreach ($files as $file) {
            $name = basename($file, '.html');
            if ($name === 'artikel') {
                continue;
            }
            $slugs[] = $name;
        }

        $slugs = array_values(array_unique($slugs));
        sort($slugs, SORT_NATURAL | SORT_FLAG_CASE);

        file_put_contents(
            LEGACY_SLUGS_FILE,
            json_encode($slugs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            LOCK_EX
        );
    }

    function generate_all_articles(array $items): int
    {
        $count = 0;
        foreach ($items as $item) {
            if (empty($item['slug'])) {
                continue;
            }
            if (generate_article_html($item)) {
                $count++;
            }
        }
        refresh_legacy_slugs();
        return $count;
    }

    function collect_item_image_paths(array $item): array
    {
    $paths = [];

    if (!empty($item['image']) && is_string($item['image'])) {
        $paths[] = trim($item['image']);
    }

    foreach ((array)($item['images'] ?? []) as $img) {
        if (is_array($img) && !empty($img['image']) && is_string($img['image'])) {
        $paths[] = trim($img['image']);
        }
    }

    return array_values(array_unique(array_filter($paths, static fn($p) => $p !== '')));
    }

    function convert_upload_to_webp(string $tmpPath, string $sourceExtension, string $targetPath): bool
    {
    if (!function_exists('imagewebp')) {
        return false;
    }

    $sourceExtension = strtolower($sourceExtension);
    $image = null;

    if ($sourceExtension === 'jpg' || $sourceExtension === 'jpeg') {
        if (function_exists('imagecreatefromjpeg')) {
        $image = @imagecreatefromjpeg($tmpPath);
        }
    } elseif ($sourceExtension === 'png') {
        if (function_exists('imagecreatefrompng')) {
        $image = @imagecreatefrompng($tmpPath);
        }
    } elseif ($sourceExtension === 'gif') {
        if (function_exists('imagecreatefromgif')) {
        $image = @imagecreatefromgif($tmpPath);
        }
    } elseif ($sourceExtension === 'webp') {
        if (function_exists('imagecreatefromwebp')) {
        $image = @imagecreatefromwebp($tmpPath);
        }
    }

    if (!$image) {
        return false;
    }

    if (function_exists('imagepalettetotruecolor')) {
        @imagepalettetotruecolor($image);
    }
    @imagealphablending($image, true);
    @imagesavealpha($image, true);

    $result = @imagewebp($image, $targetPath, 82);
    imagedestroy($image);
    return (bool)$result;
    }

    function store_uploaded_image(?array $file, string $slug = 'news'): ?string
    {
    if (!$file || !isset($file['error'])) {
        return null;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        return null;
    }

    if (!is_dir(NEWS_IMAGE_DIR)) {
        mkdir(NEWS_IMAGE_DIR, 0775, true);
    }

    $original = (string)($file['name'] ?? 'upload');
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($extension, $allowed, true)) {
        return null;
    }

    $safeSlug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug));
    $safeSlug = trim((string)$safeSlug, '-');
    if ($safeSlug === '') {
        $safeSlug = 'news';
    }

    $targetName = $safeSlug . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.webp';
    $targetPath = NEWS_IMAGE_DIR . '/' . $targetName;

    $tmpPath = (string)$file['tmp_name'];
    if (!convert_upload_to_webp($tmpPath, $extension, $targetPath)) {
        $targetName = $safeSlug . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
        $targetPath = NEWS_IMAGE_DIR . '/' . $targetName;
        if (!move_uploaded_file($tmpPath, $targetPath)) {
        return null;
        }
    }

    return NEWS_IMAGE_PUBLIC_PATH . '/' . $targetName;
    }

    function store_uploaded_images(?array $files, string $slug = 'news'): array
    {
    if (!$files || !isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $stored = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $single = [
        'name' => $files['name'][$i] ?? '',
        'type' => $files['type'][$i] ?? '',
        'tmp_name' => $files['tmp_name'][$i] ?? '',
        'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
        'size' => $files['size'][$i] ?? 0,
        ];
        $path = store_uploaded_image($single, $slug);
        if ($path) {
        $stored[] = $path;
        }
    }

    return $stored;
    }

    $config = read_config();
    $error = '';
    $notice = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $csrf = (string)($_POST['csrf_token'] ?? '');

        if ($action === 'login') {
            if (!$config) {
                $error = 'Konfiguration fehlt: /cms/config.php anlegen.';
            } elseif (!verify_csrf_token($csrf)) {
                $error = 'Ungültige Anfrage (CSRF). Bitte Seite neu laden.';
            } else {
                $username = trim((string)($_POST['username'] ?? ''));
                $password = (string)($_POST['password'] ?? '');
                $clientIp = get_client_ip();
                $lockout = get_login_lockout_seconds($username, $clientIp);

                if ($lockout > 0) {
                    $error = 'Zu viele Fehlversuche. Bitte in ' . $lockout . ' Sekunden erneut versuchen.';
                } else {
                    if ($username === $config['username'] && password_verify($password, $config['password_hash'])) {
                        session_regenerate_id(true);
                        $_SESSION['cms_logged_in'] = true;
                        clear_login_attempts($username, $clientIp);
                        header('Location: /cms/index.php');
                        exit;
                    }

                    $blockedFor = record_failed_login_attempt($username, $clientIp);
                    if ($blockedFor > 0) {
                        $error = 'Zu viele Fehlversuche. Bitte in ' . $blockedFor . ' Sekunden erneut versuchen.';
                    } else {
                        $error = 'Login fehlgeschlagen.';
                    }
                }
            }
        }

        if ($action === 'logout') {
            if (!verify_csrf_token($csrf)) {
                $error = 'Ungültige Anfrage (CSRF). Bitte Seite neu laden.';
            } else {
            session_destroy();
            header('Location: /cms/index.php');
            exit;
            }
        }

        if (in_array($action, ['save', 'delete', 'generate_all'], true)) {
            ensure_logged_in();
            if (!verify_csrf_token($csrf)) {
                $error = 'Ungültige Anfrage (CSRF). Bitte Seite neu laden.';
            } else {
            $items = load_items();

            if ($action === 'save') {
                $id = (int)($_POST['id'] ?? 0);
                $isNew = $id <= 0;
                if ($isNew) {
                    $id = get_next_id($items);
                }

            $existingRecord = null;
            foreach ($items as $item) {
                if ((int)$item['id'] === $id) {
                $existingRecord = $item;
                break;
                }
            }

                $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
                $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
                $slug = preg_replace('/-+/', '-', (string)$slug);
                $slug = trim((string)$slug, '-');

                $slugConflict = null;
                foreach ($items as $item) {
                    $itemId = (int)($item['id'] ?? 0);
                    $itemSlug = strtolower(trim((string)($item['slug'] ?? '')));
                    if ($itemId !== $id && $itemSlug !== '' && $itemSlug === $slug) {
                        $slugConflict = $item;
                        break;
                    }
                }

                $existingSlug = strtolower(trim((string)($existingRecord['slug'] ?? '')));
                $slugFileConflict = slug_conflicts_with_existing_file($slug, $existingSlug);

                if ($slug === '') {
                    $error = 'Ungültiger Slug. Bitte nur Buchstaben, Zahlen und Bindestriche verwenden.';
                } elseif ($slugConflict !== null) {
                    $error = 'Slug bereits vergeben: "' . (string)($slugConflict['title'] ?? 'Unbekannter Artikel') . '" verwendet diesen Slug bereits.';
                } elseif ($slugFileConflict) {
                    $error = 'Slug bereits durch eine vorhandene Datei belegt (/news/' . $slug . '.html). Bitte einen anderen Slug wählen.';
                } else {

                $tagsSelected = isset($_POST['tags']) && is_array($_POST['tags']) ? $_POST['tags'] : [];
                $tagsCustomRaw = (string)($_POST['tags_custom'] ?? '');
                $tagsCustom = array_values(array_filter(array_map('trim', explode(',', $tagsCustomRaw)), static fn($t) => $t !== ''));
                $tags = array_values(array_unique(array_filter(array_map('trim', array_merge($tagsSelected, $tagsCustom)), static fn($t) => $t !== '')));
                $yearTag = substr((string)($_POST['date'] ?? ''), 0, 4);
                if (preg_match('/^20\d{2}$/', $yearTag)) {
                $tags[] = $yearTag;
                }
                $tags = array_values(array_unique(array_map(static fn($t) => normalize_text((string)$t), $tags)));

                $images = [];
                $keepImages = isset($_POST['keep_images']) && is_array($_POST['keep_images']) ? $_POST['keep_images'] : [];
                foreach ($keepImages as $keepImagePath) {
                $path = trim((string)$keepImagePath);
                if ($path !== '') {
                    $images[] = ['image' => $path];
                }
                }

                $uploadedAdditional = store_uploaded_images($_FILES['images_upload'] ?? null, $slug);
                foreach ($uploadedAdditional as $uploadedPath) {
                $images[] = ['image' => $uploadedPath];
                }

                $record = [
                    'id' => $id,
                    'slug' => $slug,
                'title' => normalize_text((string)($_POST['title'] ?? '')),
                'date' => normalize_text((string)($_POST['date'] ?? '')),
                'content' => normalize_text((string)($_POST['content'] ?? '')),
                'body' => normalize_text((string)($_POST['body'] ?? '')),
                    'tags' => $tags,
                'author' => (string)($existingRecord['author'] ?? ''),
                'image' => '',
                    'images' => $images,
                'link' => (string)($existingRecord['link'] ?? ''),
                ];

                if ($record['body'] === '') {
                    $record['body'] = $record['content'];
                }
                if (!empty($images[0]['image'])) {
                    $record['image'] = $images[0]['image'];
                }

                $replaced = false;
                foreach ($items as $index => $item) {
                    if ((int)$item['id'] === $id) {
                        $items[$index] = $record;
                        $replaced = true;
                        break;
                    }
                }
                if (!$replaced) {
                    $items[] = $record;
                }

                if (!save_items($items)) {
                    $error = 'Speichern fehlgeschlagen: news-cms.json konnte nicht geschrieben werden.';
                } else {
                    $htmlGenerated = generate_article_html($record);
                    refresh_legacy_slugs();
                    if ($htmlGenerated) {
                        $notice = 'Artikel gespeichert und HTML-Datei erstellt.';
                    } else {
                        $error = 'Artikel gespeichert, aber HTML-Datei konnte nicht geschrieben werden.';
                    }
                }
                }
            }

            if ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $kept = [];
                $removedSlug = '';
                $removedImages = [];
                foreach ($items as $item) {
                    if ((int)$item['id'] === $id) {
                        $removedSlug = (string)($item['slug'] ?? '');
                    $removedImages = collect_item_image_paths($item);
                        continue;
                    }
                    $kept[] = $item;
                }

                if (!save_items($kept)) {
                    $error = 'Löschen fehlgeschlagen: news-cms.json konnte nicht geschrieben werden.';
                } else {

                $usedImages = [];
                foreach ($kept as $remainingItem) {
                foreach (collect_item_image_paths($remainingItem) as $path) {
                    $usedImages[$path] = true;
                }
                }

                foreach ($removedImages as $imagePath) {
                if (isset($usedImages[$imagePath])) {
                    continue;
                }
                if (!str_starts_with($imagePath, NEWS_IMAGE_PUBLIC_PATH . '/')) {
                    continue;
                }
                $absolutePath = ROOT_DIR . $imagePath;
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
                }

                if ($removedSlug !== '') {
                    $file = NEWS_DIR . '/' . $removedSlug . '.html';
                    if (file_exists($file)) {
                        $content = file_get_contents($file);
                        if (is_string($content) && str_contains($content, GENERATOR_MARKER)) {
                            @unlink($file);
                        }
                    }
                }
                refresh_legacy_slugs();
                $notice = 'Artikel gelöscht.';
                }
            }

            if ($action === 'generate_all') {
                $count = generate_all_articles($items);
                $notice = "{$count} Artikel als HTML erstellt/aktualisiert.";
            }
            }
        }
    }

    $loggedIn = !empty($_SESSION['cms_logged_in']);
    $items = $loggedIn ? load_items() : [];

    $tagOptions = default_tag_options();
    if ($loggedIn) {
    $tagsFromItems = [];
    foreach ($items as $item) {
        foreach ((array)($item['tags'] ?? []) as $tag) {
        $tag = trim((string)$tag);
        if ($tag !== '') {
            $tagsFromItems[] = $tag;
        }
        }
    }
    $tagOptions = array_values(array_unique(array_merge($tagOptions, $tagsFromItems)));
    sort($tagOptions, SORT_NATURAL | SORT_FLAG_CASE);
    }

    $sparteTagOptions = default_sparte_options();
    $yearTagOptions = array_values(array_filter($tagOptions, static fn($tag) => preg_match('/^20\d{2}$/', (string)$tag)));
    sort($yearTagOptions, SORT_NATURAL | SORT_FLAG_CASE);

    $normalizedSparteMap = [];
    foreach ($sparteTagOptions as $tag) {
        $normalizedSparteMap[mb_strtolower($tag)] = $tag;
    }

    $allTagsLower = [];
    foreach ($tagOptions as $tag) {
        $allTagsLower[mb_strtolower((string)$tag)] = (string)$tag;
    }

    $sparteAvailable = [];
    foreach ($normalizedSparteMap as $lower => $originalTag) {
        if (isset($allTagsLower[$lower])) {
        $sparteAvailable[] = $allTagsLower[$lower];
        }
    }

    $otherTagOptions = [];
    foreach ($tagOptions as $tag) {
        $lower = mb_strtolower((string)$tag);
        if (preg_match('/^20\d{2}$/', (string)$tag)) {
        continue;
        }
        if (isset($normalizedSparteMap[$lower])) {
        continue;
        }
        $otherTagOptions[] = (string)$tag;
    }

    $editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
    $view = $loggedIn ? (string)($_GET['view'] ?? 'dashboard') : '';
    if ($loggedIn && !in_array($view, ['dashboard', 'articles'], true)) {
        $view = 'dashboard';
    }
    if ($loggedIn && $editId > 0) {
        $view = 'articles';
    }

    $editItem = null;
    if ($loggedIn && $editId > 0) {
        foreach ($items as $item) {
            if ((int)$item['id'] === $editId) {
                $editItem = $item;
                break;
            }
        }
    }

    $csrfToken = get_csrf_token();

    ?><!doctype html>
    <html lang="de">
    <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>RV Hard CMS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f7f7f7; color: #222; }
        .wrap { max-width: 1180px; margin: 20px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 2px 10px rgba(0,0,0,.07); margin-bottom: 16px; }
        h1, h2 { margin-top: 0; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        label { font-size: 12px; color: #666; display:block; margin-bottom:4px; }
        input, textarea { width: 100%; box-sizing: border-box; padding: 9px; border: 1px solid #ccc; border-radius: 8px; }
        textarea { min-height: 90px; }
        .full { grid-column: 1 / -1; }
        .btn { border: 0; border-radius: 999px; padding: 10px 14px; cursor: pointer; font-weight: 700; }
        .btn-primary { background: #ffc107; }
        .btn-danger { background: #c62828; color: #fff; }
        .btn-muted { background: #e7e7e7; }
        .row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        .notice { background:#e8f5e9; color:#1b5e20; padding:10px 12px; border-radius:8px; margin-bottom:10px; }
        .error { background:#ffebee; color:#b71c1c; padding:10px 12px; border-radius:8px; margin-bottom:10px; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
        .tag-dropdown { border:1px solid #ccc; border-radius:8px; background:#fff; }
        .tag-dropdown > summary { list-style:none; cursor:pointer; padding:9px 12px; font-size:14px; }
        .tag-dropdown > summary::-webkit-details-marker { display:none; }
        .tag-dropdown-panel { max-height:280px; overflow:auto; border-top:1px solid #eee; padding:10px; display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:8px 14px; }
        .tag-col { min-width:0; }
        .tag-col h4 { margin:0 0 8px 0; font-size:13px; color:#444; }
        .tag-option { display:flex; align-items:center; gap:8px; font-size:14px; }
        .tag-option input { width:auto; }
        .editor-toolbar { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:6px; }
        .editor-toolbar button { border:1px solid #ccc; border-radius:8px; background:#fff; padding:5px 9px; cursor:pointer; transition:all .15s ease; }
        .editor-toolbar button:hover { border-color:#999; }
        .editor-toolbar button.active { background:#ffc107; border-color:#d9a100; color:#111; }
        .images-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap:10px; margin:8px 0; }
        .image-item { border:1px solid #e5e5e5; border-radius:8px; padding:8px; display:flex; gap:8px; align-items:flex-start; }
        .image-item img { width:64px; height:64px; object-fit:cover; border-radius:6px; }
        .hint { font-size:12px; color:#666; margin:6px 0 0 0; }
        .required-star { color:#c62828; font-weight:700; }
        .required-note { font-size:12px; color:#666; margin:0 0 10px 0; }
        .auth-shell { min-height: calc(100vh - 120px); display:flex; align-items:center; justify-content:center; }
        .auth-card { width:100%; max-width:460px; padding:24px; border-radius:14px; box-shadow: 0 12px 30px rgba(0,0,0,.08); }
        .auth-title { margin:0 0 6px 0; }
        .auth-subtitle { margin:0 0 16px 0; color:#666; font-size:14px; }
        .top-nav { display:flex; gap:8px; flex-wrap:wrap; }
        .dashboard-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
        .dash-box { border:1px solid #ececec; border-radius:10px; padding:14px; background:#fcfcfc; }
        .dash-box h3 { margin:0 0 8px 0; font-size:16px; }
        .dash-box p { margin:0; color:#666; font-size:14px; }
        .dash-kpi { font-size:28px; font-weight:700; line-height:1; margin-bottom:6px; }
        @media (max-width: 900px) { .tag-dropdown-panel { grid-template-columns: 1fr; } }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        @media (max-width: 900px) { .dashboard-grid { grid-template-columns: 1fr; } }
    </style>
    </head>
    <body>
    <div class="wrap">
    <h1>RV Hard CMS</h1>

    <?php if (!$config): ?>
        <div class="card error">
        <strong>Setup fehlt:</strong> Datei <span class="mono">/cms/config.php</span> ist nicht vorhanden.<br>
        Lege sie anhand von <span class="mono">/cms/config.php.example</span> an.
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($notice !== ''): ?><div class="notice"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <?php if (!$loggedIn): ?>
        <div class="auth-shell">
        <div class="card auth-card">
            <h2 class="auth-title">Login</h2>
            <p class="auth-subtitle">Melde dich an, um News zu erstellen und zu verwalten.</p>
            <form method="post">
            <input type="hidden" name="action" value="login" />
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
            <label>Benutzername</label>
            <input type="text" name="username" autocomplete="username" required />
            <label>Passwort</label>
            <input type="password" name="password" autocomplete="current-password" required />
            <div class="row" style="margin-top:12px;">
                <button class="btn btn-primary" type="submit">Einloggen</button>
            </div>
            </form>
        </div>
        </div>
    <?php else: ?>

        <div class="card">
        <div class="row" style="justify-content: space-between;">
            <h2 style="margin:0;">CMS Dashboard</h2>
            <div class="top-nav">
            <a class="btn btn-muted" href="/cms/index.php">Dashboard</a>
            <a class="btn btn-muted" href="/cms/index.php?view=articles">Artikel verwalten</a>
            <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="logout" />
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                <button class="btn btn-muted" type="submit">Logout</button>
            </form>
            </div>
        </div>
        </div>

        <?php if ($view === 'dashboard'): ?>
        <div class="card">
            <h2>Übersicht</h2>
            <div class="dashboard-grid">
            <div class="dash-box">
                <div class="dash-kpi"><?= count($items) ?></div>
                <h3>Artikel gesamt</h3>
                <p>Aktuell gespeicherte News-Einträge.</p>
            </div>
            <div class="dash-box">
                <h3>Schnellzugriff</h3>
                <p>Direkt zur Bearbeitung und Erstellung von Artikeln wechseln.</p>
                <div class="row" style="margin-top:10px;">
                <a class="btn btn-primary" href="/cms/index.php?view=articles">Zu den Artikeln</a>
                </div>
            </div>
            <div class="dash-box">
                <h3>HTML neu generieren</h3>
                <p>Alle generierten News-Seiten auf einmal aktualisieren.</p>
                <form method="post" style="margin-top:10px;">
                <input type="hidden" name="action" value="generate_all" />
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                <button class="btn btn-muted" type="submit">Alle als HTML generieren</button>
                </form>
            </div>
            <div class="dash-box">
                <h3>Platz für Erweiterungen</h3>
                <p>Hier werden möglicherweise noch andere Bereiche wie Termine in Zukunft ergänzt.</p>
            </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($view === 'articles'): ?>
        <div class="card">
        <h2><?= $editItem ? 'Artikel bearbeiten' : 'Neuer Artikel' ?></h2>
        <p class="required-note"><span class="required-star">*</span> Pflichtfelder</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save" />
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="id" value="<?= (int)($editItem['id'] ?? 0) ?>" />

            <div class="grid">
            <div>
                <label>Titel<span class="required-star">*</span> (Titel und slug sollten übereinstimmen oder ähnlich sein)</label>
                <input type="text" name="title" required value="<?= htmlspecialchars((string)($editItem['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
            </div>
            <div>
                <label>Slug<span class="required-star">*</span> (nur Kleinbuchstaben, Zahlen, Bindestriche & Unterstriche. Keine Sonderzeichen)</label>
                <input type="text" name="slug" required value="<?= htmlspecialchars((string)($editItem['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
            </div>
            <div>
                <label>Datum (DD-MM-YYYY) <span class="required-star">*</span></label>
                <input type="date" name="date" required value="<?= htmlspecialchars((string)($editItem['date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8') ?>" />
            </div>
            <div class="full">
                <label>Teaser <span class="required-star">*</span> (Kann auch der erste Satz vom Artikeltext sein)</label>
                <textarea name="content" required><?= htmlspecialchars((string)($editItem['content'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="full">
                <label>Artikeltext <span class="required-star">*</span></label>
                <div class="editor-toolbar" data-for="body-editor">
                <button type="button" data-cmd="bold" title="Fett"><strong>B</strong></button>
                <button type="button" data-cmd="italic" title="Kursiv"><em>I</em></button>
                <button type="button" data-cmd="underline" title="Unterstrichen"><u>U</u></button>
                <button type="button" data-cmd="formatBlock" data-value="H2" title="Überschrift">H2</button>
                <button type="button" data-cmd="insertUnorderedList" title="Liste">• Liste</button>
                <button type="button" data-cmd="createLink" title="Link">Link</button>
                <button type="button" data-cmd="removeFormat" title="Formatierung entfernen">Tx</button>
                </div>
                <div id="body-editor-view" contenteditable="true" style="min-height:220px; border:1px solid #ccc; border-radius:8px; padding:10px; background:#fff;"></div>
                <textarea id="body-editor" name="body" required style="display:none;"><?= htmlspecialchars((string)($editItem['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                <p class="hint"><!--Der Editor speichert HTML. Änderungen sind direkt sichtbar.--></p>
            </div>
            <div class="full">
                <label>Tags <span class="required-star">*</span> (Immer zumindest Jahr, Sparte und ein weiteres wählen. Optimal 5)</label>
                <?php $selectedTags = array_map('strval', (array)($editItem['tags'] ?? [])); ?>
                <details class="tag-dropdown" id="tag-dropdown">
                <summary id="tag-dropdown-summary">Tags auswählen</summary>
                <div class="tag-dropdown-panel">
                    <div class="tag-col">
                    <h4>Jahr</h4>
                    <?php foreach ($yearTagOptions as $tagOption): ?>
                        <?php $isSelected = in_array((string)$tagOption, $selectedTags, true); ?>
                        <label class="tag-option">
                        <input type="checkbox" name="tags[]" value="<?= htmlspecialchars((string)$tagOption, ENT_QUOTES, 'UTF-8') ?>" <?= $isSelected ? 'checked' : '' ?> />
                        <span><?= htmlspecialchars((string)$tagOption, ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                    <div class="tag-col">
                    <h4>Sparte</h4>
                    <?php foreach ($sparteAvailable as $tagOption): ?>
                        <?php $isSelected = in_array((string)$tagOption, $selectedTags, true); ?>
                        <label class="tag-option">
                        <input type="checkbox" name="tags[]" value="<?= htmlspecialchars((string)$tagOption, ENT_QUOTES, 'UTF-8') ?>" <?= $isSelected ? 'checked' : '' ?> />
                        <span><?= htmlspecialchars((string)$tagOption, ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                    <div class="tag-col">
                    <h4>Weitere</h4>
                    <?php foreach ($otherTagOptions as $tagOption): ?>
                        <?php $isSelected = in_array((string)$tagOption, $selectedTags, true); ?>
                        <label class="tag-option">
                        <input type="checkbox" name="tags[]" value="<?= htmlspecialchars((string)$tagOption, ENT_QUOTES, 'UTF-8') ?>" <?= $isSelected ? 'checked' : '' ?> />
                        <span><?= htmlspecialchars((string)$tagOption, ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </div>
                </details>
                <label style="margin-top:8px;">Zusätzliche Tags (kommagetrennt, optional)</label>
                <input type="text" name="tags_custom" value="" placeholder="z. B. Trainingscamp, Saisonstart" />
            </div>
            <div class="full">
                <label>Bilder (falls vorhanden hier hochladen)</label>
                <?php
                $existingImages = [];
                foreach ((array)($editItem['images'] ?? []) as $img) {
                    if (is_array($img) && !empty($img['image'])) {
                    $existingImages[] = (string)$img['image'];
                    }
                }
                if (!$existingImages && !empty($editItem['image'])) {
                    $existingImages[] = (string)$editItem['image'];
                }
                $existingImages = array_values(array_unique(array_filter($existingImages, static fn($img) => trim((string)$img) !== '')));
                ?>
                <?php if (!empty($existingImages)): ?>
                <div class="images-grid">
                    <?php foreach ($existingImages as $imagePath): ?>
                    <label class="image-item">
                        <input type="checkbox" name="keep_images[]" value="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>" checked />
                        <img src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>" alt="Bild" />
                        <span class="mono" style="word-break:break-all;"><?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p class="hint">Haken entfernen, um ein Bild beim Speichern aus dem Artikel zu entfernen.</p>
                <?php endif; ?>
                <label style="margin-top:8px;"><!--Neue Bilder hochladen--></label>
                <input type="file" name="images_upload[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,image/*" />
                <p class="hint"><!--Upload wird automatisch als WebP gespeichert (wenn GD/WebP verfügbar ist).--></p>
            </div>
            </div>

            <div class="row" style="margin-top:10px;">
            <button class="btn btn-primary" type="submit">Speichern</button>
            <?php if ($editItem): ?>
                <a class="btn btn-muted" href="/cms/index.php?view=articles">Abbrechen</a>
            <?php endif; ?>
            </div>
        </form>
        </div>

        <div class="card">
        <h2>Alle Artikel (<?= count($items) ?>)</h2>
        <table>
            <thead><tr><th>ID</th><th>Datum</th><th>Titel</th><th>Slug</th><th>Aktion</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= (int)$item['id'] ?></td>
                <td><?= htmlspecialchars((string)$item['date'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="mono"><?= htmlspecialchars((string)$item['slug'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                <div class="row">
                    <a class="btn btn-muted" href="/cms/index.php?view=articles&edit=<?= (int)$item['id'] ?>">Bearbeiten</a>
                    <a class="btn btn-muted" target="_blank" href="/news/<?= rawurlencode((string)$item['slug']) ?>.html">Öffnen</a>
                    <form method="post" onsubmit="return confirm('Artikel wirklich löschen?');" style="display:inline;">
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>" />
                    <button class="btn btn-danger" type="submit">Löschen</button>
                    </form>
                </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
    <script>
    (function () {
        const summary = document.getElementById('tag-dropdown-summary');
        const dropdown = document.getElementById('tag-dropdown');
        if (!summary || !dropdown) return;

        function updateTagSummary() {
        const checked = dropdown.querySelectorAll('input[type="checkbox"][name="tags[]"]:checked');
        if (!checked.length) {
            summary.textContent = 'Tags auswählen';
            return;
        }
        if (checked.length <= 3) {
            summary.textContent = Array.from(checked).map(c => c.value).join(', ');
            return;
        }
        summary.textContent = checked.length + ' Tags ausgewählt';
        }

        dropdown.addEventListener('change', updateTagSummary);
        updateTagSummary();
    })();

    (function () {
        const toolbar = document.querySelector('.editor-toolbar');
        const editorView = document.getElementById('body-editor-view');
        const hiddenField = document.getElementById('body-editor');
        if (!toolbar || !editorView || !hiddenField) return;

        function markdownToHtmlClient(markdown) {
        const text = String(markdown || '').replace(/\r\n/g, '\n').trim();
        if (!text) return '<p></p>';

        const lines = text.split('\n');
        const html = [];
        let inList = false;

        const flush = () => {
            if (inList) {
            html.push('</ul>');
            inList = false;
            }
        };

        for (const raw of lines) {
            const line = raw.trim();
            if (!line) { flush(); continue; }
            if (line.startsWith('### ')) { flush(); html.push('<h3>' + line.slice(4) + '</h3>'); continue; }
            if (line.startsWith('## ')) { flush(); html.push('<h2>' + line.slice(3) + '</h2>'); continue; }
            if (line.startsWith('# ')) { flush(); html.push('<h1>' + line.slice(2) + '</h1>'); continue; }
            if (line.startsWith('- ') || line.startsWith('* ')) {
            if (!inList) { html.push('<ul>'); inList = true; }
            html.push('<li>' + line.slice(2) + '</li>');
            continue;
            }
            flush();
            html.push('<p>' + line + '</p>');
        }
        flush();
        return html.join('');
        }

        const initialValue = hiddenField.value.trim();
        if (initialValue && /<\s*[a-z][^>]*>/i.test(initialValue)) {
        editorView.innerHTML = initialValue;
        } else {
        editorView.innerHTML = markdownToHtmlClient(initialValue);
        }

        function syncToHidden() {
        hiddenField.value = editorView.innerHTML.trim();
        }

        function updateActiveButtons() {
        const buttons = toolbar.querySelectorAll('button[data-cmd]');
        buttons.forEach((button) => {
            const cmd = button.getAttribute('data-cmd');
            if (!cmd) return;
            const isToggle = cmd === 'bold' || cmd === 'italic' || cmd === 'underline' || cmd === 'insertUnorderedList';
            if (!isToggle) {
            button.classList.remove('active');
            return;
            }
            try {
            const active = document.queryCommandState(cmd);
            button.classList.toggle('active', !!active);
            } catch (_) {
            button.classList.remove('active');
            }
        });
        }

        toolbar.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-cmd]');
        if (!button) return;
        const cmd = button.getAttribute('data-cmd');
        const value = button.getAttribute('data-value') || null;
        editorView.focus();

        if (cmd === 'createLink') {
            const url = prompt('URL eingeben:', 'https://');
            if (!url) return;
            let selection = window.getSelection();
            const selectedText = selection ? String(selection.toString() || '').trim() : '';
            const defaultText = selectedText !== '' ? selectedText : url;
            const linkTextInput = prompt('Linktext (sichtbarer Text):', defaultText);
            if (linkTextInput === null) return;
            const linkText = linkTextInput.trim() !== '' ? linkTextInput.trim() : defaultText;

            if (selection && !selection.isCollapsed) {
            document.execCommand('insertText', false, linkText);
            selection = window.getSelection();
            }

            if (selection && selection.isCollapsed) {
            document.execCommand('insertText', false, linkText);
            selection = window.getSelection();
            }

            if (selection && selection.anchorNode) {
            const node = selection.anchorNode;
            if (node.nodeType === Node.TEXT_NODE) {
                const end = selection.anchorOffset;
                const start = Math.max(0, end - linkText.length);
                const range = document.createRange();
                range.setStart(node, start);
                range.setEnd(node, end);
                selection.removeAllRanges();
                selection.addRange(range);
            }
            }

            document.execCommand('createLink', false, url);
            const anchors = editorView.querySelectorAll('a');
            anchors.forEach(a => {
            a.setAttribute('target', '_blank');
            a.setAttribute('rel', 'noopener noreferrer');
            });
        } else if (cmd === 'formatBlock' && value) {
            let currentBlock = '';
            try {
            currentBlock = (document.queryCommandValue('formatBlock') || '').replace(/[<>]/g, '').toUpperCase();
            } catch (_) {
            currentBlock = '';
            }
            if (currentBlock === value.toUpperCase()) {
            document.execCommand('formatBlock', false, 'P');
            } else {
            document.execCommand('formatBlock', false, value);
            }
        } else {
            document.execCommand(cmd, false, value);
        }

        syncToHidden();
        updateActiveButtons();
        });

        editorView.addEventListener('input', () => {
        syncToHidden();
        updateActiveButtons();
        });

        document.addEventListener('selectionchange', () => {
        const sel = window.getSelection();
        if (!sel || !sel.anchorNode) return;
        if (!editorView.contains(sel.anchorNode)) return;
        updateActiveButtons();
        });

        const form = editorView.closest('form');
        if (form) {
        form.addEventListener('submit', syncToHidden);
        }

        syncToHidden();
        updateActiveButtons();
    })();
    </script>
    </body>
    </html>
