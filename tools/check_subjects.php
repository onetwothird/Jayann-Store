<?php

$log = shell_exec("php tools/commit_per_file.php --dry-run 2>&1");
$lines = array_filter(explode("\n", $log));
$subs = [];
foreach ($lines as $l) {
    if (preg_match("/^\s{2}(.+)$/", $l, $m)) {
        $subs[] = $m[1];
    }
}
$dupes = array_filter(array_count_values($subs), fn($c) => $c > 1);
echo "total subjects: " . count($subs) . "\n";
echo "unique subjects: " . count(array_unique($subs)) . "\n";
if ($dupes) {
    echo "DUPLICATES:\n";
    foreach ($dupes as $subj => $count) {
        echo "  x$count  $subj\n";
    }
} else {
    echo "No duplicates - all subjects are unique.\n";
}