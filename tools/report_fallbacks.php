<?php

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

$src  = file_get_contents('tools/commit_per_file.php');
preg_match('/\$exact = \[(.*?)\n    \];/s', $src, $m);
preg_match_all("/'([^']+)'\s*=>/", $m[1] ?? '', $mm);
$exact = $mm[1];

$tracked = array_filter(array_map('trim', explode("\n", (string) shell_exec('git ls-files'))));

$fallback = array_values(array_diff($tracked, $exact));

printf("hand-written entries: %d\n", count($exact));
printf("tracked files:        %d\n", count($tracked));
printf("generic fallback:     %d\n\n", count($fallback));

foreach ($fallback as $f) {
    echo "  $f\n";
}
