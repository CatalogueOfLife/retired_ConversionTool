<?php require_once 'library/BsOptimizerLibrary.php'; ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>Taylor &amp; Francis csv export</title>
</head> 
<body style="font: 12px verdana; width: 800px;">
<h3>Taylor &amp; Francis csv export</h3>

<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');
set_include_path('library' . PATH_SEPARATOR . get_include_path());

// Input file
$in = 'taylor/input.csv';
$out = 'taylor/matches.csv';
$ex = 'taylor/excluded.csv';
$rootUrl = 'http://www.catalogueoflife.org/annual-checklist/2019/';

require_once 'library/bootstrap.php';
require_once 'library/BsOptimizerLibrary.php';
require_once 'DbHandler.php';
require_once 'Indicator.php';
require_once 'library/Zend/Log/Writer/Stream.php';
require_once 'library/Zend/Log.php';
alwaysFlush();

$config = isset($argv) && isset($argv[1]) ?
    parse_ini_file($argv[1], true) : parse_ini_file('config/AcToBs.ini', true);
$options = explode(",", $config['target']['options']);
foreach ($options as $option) {
    $parts = explode("=", trim($option));
    $o[$parts[0]] = $parts[1];
}
DbHandler::createInstance('target', $config['target'], $o);
$pdo = DbHandler::getInstance('target');
$indicator = new Indicator();

// Bootstrap
if (!file_exists($in)) {
    die('Cannot open input file, make sure the path is set correctly!');
}
$fpIn = fopen($in, "r");
if (!$fpIn) {
    die('Cannot read input file, make sure the path is set correctly!');
}
$data = fgetcsv($fpIn, 1000, ",");
if ($data[0] != 'genus' || $data[1] != 'species') {
    die('Csv file should contain genus and species columns');
}
$fpOut = fopen($out, 'w');
if (!$fpOut) {
    die('Cannot write output input file');
}
$fpEx = fopen($ex, 'w');

echo "Processing $in...<br>";

// Write header
fputcsv($fpOut, array('id', 'genus', 'species', 'genusURL', 'speciesURL', 'status'));
fputcsv($fpEx, array('genus', 'species'));

// Loop through file
$stmt = $pdo->prepare('SELECT t1.`accepted_species_id`, t1.`status`, t2.`hash`
    FROM `_search_scientific` AS t1
    LEFT JOIN `_natural_keys` AS t2 ON t1.`id` = t2.`id`
    WHERE t1.`genus` = ? AND t1.`species` = ? AND t1.`infraspecies` = ""');
$stmt2 = $pdo->prepare('SELECT `hash` FROM `_natural_keys` WHERE `id` = ?');
$i = 0;
while (($data = fgetcsv($fpIn, 1000, ",")) !== false) {
    // Get genus hash
    $stmt->execute(array($data[0], ''));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
       continue;
    }
    $genusUrl = $rootUrl . 'browse/tree/id/' . $row['hash'];

    // Get species hash if species is provided
    $status = 'accepted name';
    $speciesUrl = '';
    if (!empty($data[1])) {
        $stmt->execute($data);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // Accepted name
            if ($row['status'] == 1 || $row['status'] == 4) {
                $speciesUrl = $rootUrl . 'details/species/id/' . $row['hash'];
            // Synonym
            } else {
                $stmt2->execute(array($row['accepted_species_id']));
                $status = 'synonym';
                $speciesUrl = $rootUrl . 'details/species/id/' .
                    $stmt2->fetchColumn() . '/synonym/' . $row['hash'];
            }
        }
    }
    $i++;

    // Only write if a) only genus was present or b) species was present and url found
    if (empty($data[1]) || !empty($data[1]) && !empty($speciesUrl)) {
        fputcsv($fpOut, array($i, $data[0], $data[1], $genusUrl, $speciesUrl, $status));
    } else if (!empty($data[1]) && empty($speciesUrl)) {
        fputcsv($fpEx, array($i, $data[0], $data[1]));
    }
}

foreach (array($fpIn, $fpOut, $fpEx) as $fp) {
    fclose($fp);
}

echo 'Done!</p>';
?>