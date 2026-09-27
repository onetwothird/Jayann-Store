<?php

declare(strict_types=1);

$root  = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);

$skipDirs = ['.git', 'uploads', 'libs'];

$targets = [
    'php' => [],
    'css' => [],
    'js'  => [],
];

function collect(string $dir, array $skipDirs, array &$targets): array
{
    $found = [];

    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $f) use ($skipDirs): bool {
                if ($f->isDir()) {
                    return !in_array($f->getFilename(), $skipDirs, true);
                }
                return true;
            }
        )
    );

    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (isset($targets[$ext])) {
            $targets[$ext][] = $file->getPathname();
        }
    }

    sort($targets['php']);
    sort($targets['css']);
    sort($targets['js']);

    return $found;
}

collect($root, $skipDirs, $targets);

function strip(string $src, string $lang, bool $hashComments): array
{
    $len     = strlen($src);
    $out     = '';
    $i       = 0;
    $removed = 0;

    $state = 'code';

    while ($i < $len) {
        $ch   = $src[$i];
        $next = $i + 1 < $len ? $src[$i + 1] : '';

        switch ($state) {

            case 'code':

                if (($lang === 'php' || $lang === 'js') && $ch === "'") {
                    $out .= $ch;
                    $i++;
                    $state = 'sq';
                    continue 2;
                }
                if ($ch === '"') {
                    $out .= $ch;
                    $i++;
                    $state = 'dq';
                    continue 2;
                }
                if ($lang === 'js' && $ch === '`') {
                    $out .= $ch;
                    $i++;
                    $state = 'tpl';
                    continue 2;
                }

                if ($lang === 'php' && $ch === '<' && $next === '<') {
                    $label = heredoc_label($src, $i);
                    if ($label !== null) {
                        $out .= substr($src, $i, $label['len']);
                        $i   += $label['len'];
                        $state = 'heredoc_' . ($label['nowdoc'] ? 'now' : 'doc');
                        continue 2;
                    }
                }

                if ($ch === '/' && $next === '*') {
                    $end = strpos($src, '*/', $i + 2);
                    $i = $end === false ? $len : $end + 2;
                    $removed++;
                    continue 2;
                }
                if ($ch === '/' && $next === '/') {
                    $end = strpos($src, "\n", $i);
                    $i = $end === false ? $len : $end;
                    $removed++;
                    continue 2;
                }
                if ($hashComments && $ch === '#') {

                    if ($next !== '[') {
                        $end = strpos($src, "\n", $i);
                        $i = $end === false ? $len : $end;
                        $removed++;
                        continue 2;
                    }
                }

                if ($lang === 'js' && $ch === '/') {
                    $prev = last_significant($out);
                    if ($prev === '' || str_contains('(,=:[!&|?{};+-*%~^', $prev)) {
                        if (is_regex_literal($src, $i)) {
                            $end = $i;
                            $inClass = false;
                            while ($end < $len) {
                                $c = $src[$end];
                                if ($c === '\\') {
                                    $end += 2;
                                    continue;
                                }
                                if ($c === "\n") {
                                    break;
                                }
                                if ($c === '[') {
                                    $inClass = true;
                                } elseif ($c === ']') {
                                    $inClass = false;
                                } elseif ($c === '/' && !$inClass) {
                                    break;
                                }
                                $end++;
                            }
                            if ($end < $len && $src[$end] === '/') {
                                $out .= substr($src, $i, $end - $i + 1);
                                $i = $end + 1;
                                $state = 'regex';
                                continue 2;
                            }
                        }
                    }
                }

                $out .= $ch;
                $i++;
                continue 2;

            case 'regex':
                if ($ch === '\\') {
                    $out .= substr($src, $i, 2);
                    $i += 2;
                    continue 2;
                }
                if ($ch === '/') {
                    $out .= $ch;
                    $i++;

                    while ($i < $len && ctype_alpha($src[$i])) {
                        $out .= $src[$i];
                        $i++;
                    }
                    $state = 'code';
                    continue 2;
                }
                if ($ch === "\n") {
                    $state = 'code';
                    continue 2;
                }
                $out .= $ch;
                $i++;
                continue 2;

            case 'sq':
            case 'dq':
            case 'tpl':
                $quote = $state === 'sq' ? "'" : ($state === 'dq' ? '"' : '`');

                if ($ch === '\\') {
                    $out .= substr($src, $i, min(2, $len - $i));
                    $i += 2;
                    continue 2;
                }
                if ($ch === $quote) {
                    $out .= $ch;
                    $i++;
                    $state = 'code';
                    continue 2;
                }
                $out .= $ch;
                $i++;
                continue 2;

            case 'heredoc_doc':
            case 'heredoc_now':
            {
                $state = 'code';

                $at = strrpos($out, '<<<');

                $terminator = $at === false
                    ? ''
                    : trim(substr($out, $at + 3), " \t\r\n'\"");

                $pattern = '/^[ \t]*' . preg_quote($terminator, '/')
                    . '(?![A-Za-z0-9_\x80-\xff])/m';

                if ($terminator !== ''
                    && preg_match($pattern, $src, $m, PREG_OFFSET_CAPTURE, $i)
                ) {
                    $stop = $m[0][1];

                    $lineEnd = strpos($src, "\n", $stop);
                    $end    = $lineEnd === false ? $len : $lineEnd;

                    $out .= substr($src, $i, $end - $i);
                    $i    = $end;
                } else {
                    $out .= substr($src, $i);
                    $i    = $len;
                }
                continue 2;
            }
        }
    }

    return [$out, $removed];
}

function heredoc_label(string $src, int $i): ?array
{
    if (!preg_match('/\G<<<[ \t]*(?:"([A-Za-z_\x80-\xff][\w\x80-\xff]*)"|\'([A-Za-z_\x80-\xff][\w\x80-\xff]*)\'|([A-Za-z_\x80-\xff][\w\x80-\xff]*))\r?\n/', $src, $m, 0, $i)) {
        return null;
    }

    return [
        'len'    => strlen($m[0]),
        'nowdoc' => isset($m[2]) && $m[2] !== '',
    ];
}

function last_significant(string $out): string
{
    $t = rtrim($out);
    return $t === '' ? '' : $t[strlen($t) - 1];
}

function is_regex_literal(string $src, int $i): bool
{
    $end = $i + 1;
    $len = strlen($src);
    $closed = false;

    while ($end < $len) {
        $c = $src[$end];
        if ($c === '\\') {
            $end += 2;
            continue;
        }
        if ($c === "\n") {
            return false;
        }
        if ($c === '/') {
            $closed = true;
            break;
        }
        $end++;
    }

    return $closed;
}

function tidy(string $src): string
{
    $src = preg_replace("/[ \t]+$/m", '', $src) ?? $src;
    $src = preg_replace("/\n{3,}/", "\n\n", $src) ?? $src;

    return rtrim(ltrim($src, "\n")) . "\n";
}

$totalFiles = 0;
$totalRemoved = 0;
$summary = [];

foreach ($targets as $ext => $files) {
    foreach ($files as $file) {
        $src = (string) file_get_contents($file);

        [$stripped, $removed] = strip($src, $ext, $ext === 'php');

        if ($removed === 0) {
            continue;
        }

        $tidy = tidy($stripped);
        $rel  = str_replace($root . '/', '', $file);

        $check = tempnam(sys_get_temp_dir(), 'chk');
        file_put_contents($check, $tidy);
        exec(
            escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($check) . ' 2>&1',
            $out,
            $code
        );
        unlink($check);

        if ($ext === 'php' && $code !== 0) {
            printf("  SKIP  %s - stripping broke the syntax, left untouched\n", $rel);
            foreach ($out as $line) {
                printf("        %s\n", $line);
            }
            $summary['skipped'][] = $rel;
            continue;
        }

        $totalFiles++;
        $totalRemoved += $removed;
        $summary['stripped'][] = [$rel, $removed];

        if (!$dryRun) {
            file_put_contents($file, $tidy);
        }
    }
}

printf(
    "%s %d files, %d comments removed\n",
    $dryRun ? 'Would strip' : 'Stripped',
    $totalFiles,
    $totalRemoved
);

if (!empty($summary['skipped'])) {
    printf("%d files skipped:\n", count($summary['skipped']));
    foreach ($summary['skipped'] as $rel) {
        printf("  %s\n", $rel);
    }
}
