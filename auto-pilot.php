<?php
header("Content-type: text/plain");
alwaysFlush();

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
output($fp, "Starting CoL+ conversion at " . date('d-m-Y H:i:s') . "\n\n");

output($fp, "Step 1: download and import data from CoL+ server\n");
$output = shell_exec("$phpExec AdOptimizer.php 2>&1");
$step1 = microtime(true);
file_put_contents('logs/' . date('Y-m-d') . '-step-1-log.htm', $output);
output($fp, "Ready in " . round($step1 - $start) . " seconds\n\n");

// Step 2
output($fp, "Step 2: copy data to Annual Checklist database\n");
$output = shell_exec("$phpExec AcToBs.php 2>&1");
$step2 = microtime(true);
file_put_contents('logs/' . date('Y-m-d') . '-step-2-log.htm', $output);
output($fp, "Ready in " . round($step2 - $step1) . " seconds\n\n");

// Step 3
fwrite($fp, "Step 3: create auxiliary tables in Annual Checklist database\n");
$output = shell_exec("$phpExec BsOptimizer.php 2>&1");
$step3 = microtime(true);
file_put_contents('logs/' . date('Y-m-d') . '-step-3-log.htm', $output);
output($fp, "Ready in " . round($step3 - $step2) . " seconds\n\n");

// Step 4
output($fp, "Step 4: create sitemap files\n");
$output = shell_exec("$phpExec sitemaps.php 2>&1");
$step4 = microtime(true);
file_put_contents('logs/' . date('Y-m-d') . '-step-4-log.htm', $output);
output($fp, "Ready in " . round($step4 - $step3) . " seconds\n\n");

output($fp, "Conversion ready!\nTotal running time: " . round($step4 - $start) . " seconds\n\n");

$output = file_get_contents($pid);
unlink($pid);
//die($output);

function alwaysFlush () {
    // Turn off output buffering
    ini_set('output_buffering', 'off');
    // Turn off PHP output compression
    ini_set('zlib.output_compression', false);
    // Implicitly flush the buffer(s)
    ini_set('implicit_flush', true);
    ob_implicit_flush(true);
    // Clear, and turn off output buffering
    while (ob_get_level() > 0) {
        // Get the curent level
        $level = ob_get_level();
        // End the buffering
        ob_end_clean();
        // If the current level has not changed, abort
        if (ob_get_level() == $level) break;
    }
    // Disable apache output buffering/compression
    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', '1');
        apache_setenv('dont-vary', '1');
    }
}

function output ($fp, $message) {
    echo $message;
    fwrite($fp, $message);
}