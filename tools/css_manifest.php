<?php

declare(strict_types=1);

function css_manifest_files(string $bundle, string $root): array
{
    $dir  = $root . '/assets/css';
    $path = $dir . '/manifest.json';

    if (!is_file($path)) {
        return [$dir . '/' . ($bundle === 'admin' ? 'admin/responsive.css' : 'responsive.css')];
    }

    $manifest = json_decode((string) file_get_contents($path), true);
    $files    = is_array($manifest) && is_array($manifest[$bundle] ?? null) ? $manifest[$bundle] : [];

    $out = [];
    foreach ($files as $rel) {
        if (is_string($rel) && $rel !== '') {
            $out[] = $dir . '/' . $rel;
        }
    }

    return $out;
}

function css_bundle_text(string $bundle, string $root): string
{
    $out = '';
    foreach (css_manifest_files($bundle, $root) as $file) {
        $out .= (string) file_get_contents($file);
    }
    return $out;
}
