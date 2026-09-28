<?php declare(strict_types=1);

if (!function_exists('admin_url')) {

    function admin_script(): string
    {
        return basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');
    }

    function admin_url(array $overrides = []): string
    {
        $params = $_GET;
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
            } else {
                $params[$key] = $value;
            }
        }
        $qs = http_build_query($params);
        return admin_script() . ($qs !== '' ? '?' . $qs : '');
    }

    function admin_q(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    function admin_qint(string $key, int $default = 0): int
    {

        $v = $_GET[$key] ?? $_POST[$key] ?? null;
        return is_numeric($v) ? max(0, (int) $v) : $default;
    }

    function admin_page_no(int $total, int $perPage = 12): int
    {
        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        return min(admin_qint('page', 1), $pages);
    }

    function admin_pager(int $current, int $total, int $perPage = 12): string
    {
        $pages = (int) ceil($total / max(1, $perPage));
        if ($pages <= 1) {
            return '';
        }

        $btn = static fn(string $label, int $page, string $class = '', string $aria = '')
            => '<a class="pager__btn ' . $class . '" href="' . e(admin_url(['page' => $page])) . '"'
             . ($aria !== '' ? ' aria-label="' . e($aria) . '"' : '')
             . ($page === $current ? ' aria-current="page"' : '') . '>' . $label . '</a>';

        $window = [];
        for ($p = 1; $p <= $pages; $p++) {
            if ($p === 1 || $p === $pages || abs($p - $current) <= 1) {
                $window[] = $p;
            }
        }

        $html = '<nav class="pager" aria-label="Pagination">';
        $html .= $current > 1 ? $btn('<i class="fa-solid fa-chevron-left" aria-hidden="true"></i>', $current - 1, '', 'Previous page') : '';
        $prev = 0;
        foreach ($window as $p) {
            if ($prev && $p - $prev > 1) {
                $html .= '<span class="pager__gap" aria-hidden="true">&hellip;</span>';
            }
            $html .= $btn((string) $p, $p, $p === $current ? 'is-current' : '');
            $prev = $p;
        }
        $html .= $current < $pages ? $btn('<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>', $current + 1, '', 'Next page') : '';
        return $html . '</nav>';
    }

    function admin_product_filter(string $search, string $filter = ''): array
    {
        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]  = '(p.name LIKE ? OR p.category LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where[] = match ($filter) {
            'sale' => '(p.discount > 0 AND p.discount_price > 0 AND p.discount_price < p.price)',
            'out'  => '(p.stock = 0)',
            'low'  => '(p.stock > 0 AND p.stock <= 5)',
            default => '1 = 1',
        };

        return [implode(' AND ', $where), $params];
    }

    function admin_empty(string $icon, string $title, string $text, string $action = ''): string
    {
        $html  = '<div class="empty">';
        $html .= '<span class="empty__icon"><i class="fa-solid ' . e($icon) . '" aria-hidden="true"></i></span>';
        $html .= '<p class="empty__title">' . e($title) . '</p>';
        $html .= '<p class="empty__text">' . e($text) . '</p>';
        if ($action !== '') {
            $html .= $action;
        }
        return $html . '</div>';
    }

    function admin_status_options(string $current = 'pending'): string
    {
        $out = '';
        foreach (['pending' => 'Pending', 'paid' => 'Paid', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label) {
            $out .= '<option value="' . e($value) . '"' . ($value === $current ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        return $out;
    }

    /**
     * Largest upload this server will actually accept.
     *
     * The 4M/8M values in the root .htaccess sit inside <IfModule mod_php.c>,
     * and shared hosts such as InfinityFree run PHP through CGI rather than
     * mod_php — so those directives never apply and the hosting defaults are
     * what count. Ask PHP rather than hardcoding a number that may be a lie.
     */
    function admin_upload_limit(): int
    {
        if (function_exists('ini_parse_bytes')) {
            $bytes = (int) ini_parse_bytes((string) ini_get('upload_max_filesize'));
            if ($bytes > 0) {
                return $bytes;
            }
        }
        return 4 * 1024 * 1024;
    }

    function admin_upload_limit_label(): string
    {
        return rtrim(rtrim(number_format(admin_upload_limit() / 1048576, 1), '0'), '.') . ' MB';
    }

    /**
     * Reduce a submitted name to a bare, safe image filename, or null.
     *
     * basename() discards any directory component, so "../../shell.png" and
     * "/etc/passwd" become plain names before the whitelist ever sees them.
     */
    function admin_image_filename(string $raw): ?string
    {
        $name = basename(trim($raw));

        if ($name === '' || !preg_match('#\A[A-Za-z0-9._\[\]\- ]+\.(?:png|jpe?g|gif|webp|avif|svg)\z#i', $name)) {
            return null;
        }

        return $name;
    }

    /**
     * Every image sitting in uploads/products/, for the filename picker.
     * Read-only, so a directory the web server cannot list just yields an
     * empty list and the text box still accepts a typed name.
     */
    function admin_uploads_index(): array
    {
        $out = [];

        foreach (@scandir(UPLOAD_PATH) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!is_file(UPLOAD_PATH . '/' . $entry)) {
                continue;
            }
            if (admin_image_filename($entry) === null) {
                continue;
            }
            $out[] = $entry;
        }

        natcasesort($out);

        return array_values($out);
    }

    function admin_handle_upload(string $field = 'image'): array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return [null, null];
        }

        $file  = $_FILES[$field];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }

        if ($error !== UPLOAD_ERR_OK) {
            // Name the actual reason. One generic "could not be uploaded" made a
            // permissions fault, an oversized file and a blocked extension look
            // identical, which is why "it does not update" could not be
            // diagnosed from the browser.
            $why = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                    'That image is %s MB and this server accepts up to %s. Resize it, or raise '
                    . 'the limit in the hosting control panel.',
                    rtrim(rtrim(number_format((int) ($file['size'] ?? 0) / 1048576, 1), '0'), '.'),
                    admin_upload_limit_label()
                ),
                UPLOAD_ERR_PARTIAL    => 'The upload was cut short. Check your connection and try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder available, so nothing can be uploaded.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file to its temp folder.',
                UPLOAD_ERR_EXTENSION  => 'A server extension stopped the upload.',
                default               => 'The image could not be uploaded. Please try again.',
            };

            return [null, $why];
        }

        $tmp   = (string) ($file['tmp_name'] ?? '');
        $size  = (int) ($file['size'] ?? 0);
        $limit = admin_upload_limit();

        if ($size > $limit) {
            return [null, sprintf(
                'That image is %s MB. The limit is %s.',
                rtrim(rtrim(number_format($size / 1048576, 1), '0'), '.'),
                admin_upload_limit_label()
            )];
        }

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return [null, 'The upload was rejected as an invalid file. Please try again.'];
        }

        $allowed = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
        ];
        if (defined('IMAGETYPE_WEBP')) {
            $allowed[IMAGETYPE_WEBP] = 'webp';
        }
        if (defined('IMAGETYPE_AVIF')) {
            $allowed[IMAGETYPE_AVIF] = 'avif';
        }

        $info = @getimagesize($tmp);

        if (!$info || !isset($allowed[$info[2]])) {
            return [null, 'Only JPG, PNG, GIF, WEBP and AVIF images are accepted. '
                . 'A HEIC photo from an iPhone has to be converted to JPG first.'];
        }

        if (!is_dir(UPLOAD_PATH)) {
            return [null, 'The folder uploads/products/ does not exist. Create it in the '
                . 'InfinityFree file manager, then try again.'];
        }

        if (!is_writable(UPLOAD_PATH)) {
            return [null, 'The folder uploads/products/ is not writable. In the InfinityFree '
                . 'file manager open uploads > products, then set Permissions to 755.'];
        }

        $name   = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) pathinfo((string) ($file['name'] ?? 'product'), PATHINFO_FILENAME));
        $name   = trim((string) $name, '-') ?: 'product';
        $final  = time() . '-' . substr(md5($name . $size), 0, 6) . '.' . $allowed[$info[2]];
        $target = UPLOAD_PATH . '/' . $final;

        if (!move_uploaded_file($tmp, $target)) {
            return [null, 'The file arrived but could not be moved into uploads/products/. '
                . 'Check that the folder is writable (Permissions 755) and try again.'];
        }

        @chmod($target, 0644);

        return [$final, null];
    }

    /**
     * Decide the new image value for a product from the submitted form.
     *
     * A freshly uploaded file wins. Otherwise a name chosen from
     * uploads/products/ is used, which is the workflow for copying images into
     * the hosting file manager by hand and then pointing the product at the
     * file from here.
     */
    function admin_resolve_image_input(): array
    {
        [$uploaded, $error] = admin_handle_upload('image');

        if ($error !== null) {
            return [null, $error];
        }

        if ($uploaded !== null) {
            return [$uploaded, null];
        }

        $raw = trim((string) ($_POST['image_name'] ?? ''));

        if ($raw === '') {
            return [null, null];
        }

        $name = admin_image_filename($raw);

        if ($name === null) {
            return [null, '"' . $raw . '" is not a valid image filename. Use a plain name such as '
                . 'summit.webp — no folders, and the extension has to be a real image type.'];
        }

        if (!is_file(UPLOAD_PATH . '/' . $name)) {
            return [null, 'There is no file called "' . $name . '" in uploads/products/. '
                . 'Copy the image into that folder in the file manager first, then pick it here.'];
        }

        return [$name, null];
    }

    function admin_delete_upload(?string $filename): void
    {
        $filename = trim((string) $filename);

        if ($filename === '' || admin_image_filename($filename) === null) {
            return;
        }

        // Only remove a file this application generated (the time() prefix from
        // admin_handle_upload). Images copied into the folder by hand are left
        // alone: they may be shared with other products, and a save button
        // silently deleting someone's file out of the hosting file manager is
        // not reasonable behaviour.
        if (!preg_match('/\A\d{10}-/', $filename)) {
            return;
        }

        $path = UPLOAD_PATH . '/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

