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

    function admin_handle_upload(string $field = 'image'): array
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return [null, 'The image could not be uploaded. Please try again.'];
        }
        if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
            return [null, 'The upload was rejected. Please try again.'];
        }

        $size = (int) $_FILES[$field]['size'];
        if ($size > 2 * 1024 * 1024) {
            return [null, 'That image is larger than 2 MB. Please choose a smaller file.'];
        }

        $info = @getimagesize($_FILES[$field]['tmp_name']);
        $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($allowed[$info[2]])) {
            return [null, 'Only JPG, PNG, GIF and WEBP images are accepted.'];
        }

        $name   = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) pathinfo((string) $_FILES[$field]['name'], PATHINFO_FILENAME));
        $name   = trim((string) $name, '-') ?: 'product';
        $final  = time() . '-' . substr(md5($name . $size), 0, 6) . '.' . $allowed[$info[2]];
        $target = UPLOAD_PATH . '/' . $final;

        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
            return [null, 'The image could not be saved. Check that uploads/products/ is writable.'];
        }
        return [$final, null];
    }

    function admin_delete_upload(?string $filename): void
    {
        $filename = trim((string) $filename);
        if ($filename === '' || !preg_match('#^[A-Za-z0-9._\[\]\- ]+$#', $filename)) {
            return;
        }
        $path = UPLOAD_PATH . '/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

