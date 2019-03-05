<?php

$pid = 'tmp/monitor.pid';
$phpExec = '/usr/bin/php';

// Get settings from config.ini
if (!file_exists('config/AcToBs.ini')) {
    die('Cannot locate config.ini!');
}
$config = parse_ini_file('config/AcToBs.ini', true);
foreach ($config['col_plus'] as $k => $v) {
    $cfg[$k] = $v;
}

// Get security key from GET
$key = isset($_GET['p']) ? $_GET['p'] : false;
// ... or from command line
if (!$key) {
    $options = getopt("p:");
    //die(print_r($options));
    $key = isset($options['p']) ? $options['p'] : false;
}

// Basic security
if (!$key || $key !== $cfg['key']) {
    die("You did not say the magic word, bye bye...\n\n");
}

// Conversion still running?
if (file_exists($pid)) {
    // Check if script is running less than the maximum running time
    if (time() - filemtime($pid) < $cfg['runtime']) {
        $progress = file_get_contents($pid);
        echo "Conversion in progress:\n";
        die($progress . "\n\n");
    }
    // Process got stuck; delete pid and continue
    unlink($pid);
}

// Keep running even if user disconnects
ignore_user_abort(true);
set_time_limit($cfg['runtime']);

// Create pid
$fp = fopen($pid, 'w+');
$start = microtime(true);

// Step 1
fwrite($fp, "Starting CoL+ conversion at " . date('d-m-Y H:i:s') . "\n\n");
fwrite($fp, "Step 1: download and import data from CoL+ server\n");
exec("$phpExec AdOptimizer.php >/dev/null 2>/dev/null");
$step1 = microtime(true);
fwrite($fp, "Ready in " . round($step1 - $start) . " seconds\n\n");

// Step 2
fwrite($fp, "Step 2: copy data to Annual Checklist database\n");
exec("$phpExec AcToBs.php >/dev/null 2>/dev/null");
$step2 = microtime(true);
fwrite($fp, "Ready in " . round($step2 - $step1) . " seconds\n\n");

// Step 3
fwrite($fp, "Step 3: create auxiliary tables in Annual Checklist database\n");
exec("$phpExec BsOptimizer.php >/dev/null 2>/dev/null");
$step3 = microtime(true);
fwrite($fp, "Ready in " . round($step3 - $step2) . " seconds\n\n");

// Step 4
fwrite($fp, "Step 4: create sitemap files\n");
exec("$phpExec sitemaps.php >/dev/null 2>/dev/null");
$step4 = microtime(true);
fwrite($fp, "Ready in " . round($step4 - $step3) . " seconds\n\n");

fwrite($fp, "Conversion ready!\nTotal running time: " . round($step4 - $start) . " seconds\n\n");

$output = file_get_contents($pid);
unlink($pid);
die($output);