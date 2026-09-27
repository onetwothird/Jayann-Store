<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/app/config.php';

if (!isset($config) || !is_array($config)) {
    fwrite(STDERR, "app/config.php did not return an array\n");
    exit(2);
}

$expected = static function (array $keys) use (&$config): bool {
    $node = $config;
    foreach ($keys as $k) {
        if (!is_array($node) || !array_key_exists($k, $node)) {
            return false;
        }
        $node = $node[$k];
    }
    return true;
};

$rootPath = $root . DIRECTORY_SEPARATOR;
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {

    $path = $file->getPathname();
    if ($file->getExtension() !== 'php') {
        continue;
    }
    if (str_contains($path, DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR)
        || basename($path) === 'config_audit.php'
    ) {
        continue;
    }
    $files[] = $path;
}
sort($files);

$problems = [];

$resolveChain = static function (string $expr, $value) {
    if (!preg_match_all("/\[['\"]([a-zA-Z0-9_]+)['\"]\]/", $expr, $m)) {
        return null;
    }
    foreach ($m[1] as $k) {
        if (!is_array($value) || !array_key_exists($k, $value)) {
            return false;
        }
        $value = $value[$k];
    }
    return true;
};

foreach ($files as $file) {
    $rel  = substr($file, strlen($rootPath));
    $code = (string) file_get_contents($file);
    $tokens = token_get_all($code);

    for ($i = 0, $n = count($tokens); $i < $n; $i++) {
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_VARIABLE || $t[1] !== '$config') {
            continue;
        }

        $line   = $t[2];
        $chain  = '';
        $cursor = $i + 1;

        while ($cursor < $n) {
            $u = $tokens[$cursor];
            if (is_array($u) && in_array($u[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $cursor++;
                continue;
            }
            if ($u === '[') {
                $expr = '';
                $depth = 0;
                for ($j = $cursor; $j < $n; $j++) {
                    $v = $tokens[$j];
                    if ($v === '[') {
                        $depth++;
                        if ($depth === 1) {
                            continue;
                        }
                    }
                    if ($v === ']') {
                        $depth--;
                        if ($depth === 0) {
                            break;
                        }
                    }
                    if (is_array($v) && in_array($v[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    $expr .= is_array($v) ? $v[1] : $v;
                }
                $chain .= $expr;
                $cursor = $j + 1;
                continue;
            }
            break;
        }

        if ($chain === '') {
            continue;
        }

        $ok = $resolveChain($chain, $config);
        if ($ok === null) {
            continue;
        }
        if ($ok === false) {
            $keys = [];
            preg_match_all("/['\"]([a-zA-Z0-9_]+)['\"]\]/", $chain, $km);
            $keys = $km[1];
            $problems[] = [
                'file' => $rel,
                'line' => $line,
                'expr' => "\$config" . $chain,
                'fix'  => "no '" . implode('', $keys) . "' path in the config",
            ];
        }
    }

    if (preg_match_all(
        "/config_value\(\s*'([a-z0-9_]+)'\s*,\s*'([a-z0-9_]+)'\s*\)/i",
        $code,
        $mm,
        PREG_OFFSET_CAPTURE
    )) {
        foreach ($mm[1] as $idx => $secMatch) {
            $sec  = $secMatch[0];
            $key  = $mm[2][$idx][0];
            $line = substr_count(substr($code, 0, $mm[0][$idx][1]), "\n") + 1;

            if (!isset($config[$sec]) || !is_array($config[$sec]) || !array_key_exists($key, $config[$sec])) {
                $problems[] = [
                    'file' => $rel,
                    'line' => $line,
                    'expr' => "config_value('$sec', '$key')",
                    'fix'  => "section '" . $sec . "' has no key '" . $key . "'",
                ];
            }
        }
    }
}

echo "\n  Config key audit - " . count($files) . " files, " . count($config) . " top-level sections\n\n";

if ($problems === []) {
    echo "  Every config path resolves.\n\n";
    exit(0);
}

usort($problems, static fn(array $a, array $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);

foreach ($problems as $p) {
    printf("  %s:%d\n", $p['file'], $p['line']);
    printf("      %s\n", $p['expr']);
    printf("      %s\n", $p['fix']);
}

printf("\n  %d unresolved config path(s).\n\n", count($problems));
exit(1);
