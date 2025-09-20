<?php
$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

$checks = [
    'admin/daily_list.php'  => ['admin/verify_daily.php'],
    'admin/weekly_list.php' => ['admin/verify_weekly.php'],
    'admin/leaves_list.php' => ['admin/verify_leaves.php'],
];

$missing = [];
foreach ($checks as $list => $targets) {
    $listPath = $root . '/' . $list;
    if (!is_file($listPath)) {
        $missing[] = "Missing page: {$list}";
        continue;
    }

    $content = file_get_contents($listPath) ?: '';
    foreach ($targets as $target) {
        $filePath = $root . '/' . $target;
        if (!is_file($filePath)) {
            $missing[] = "Missing verify page: {$target}";
            continue;
        }
        if (strpos($content, basename($target)) === false) {
            $missing[] = "List {$list} does not link to {$target}";
        }
    }
}

if ($missing) {
    foreach ($missing as $msg) {
        echo $msg, "\n";
    }
    exit(1);
}

echo "Admin verification routes are present.\n";
