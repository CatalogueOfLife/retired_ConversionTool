<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>Annual Checklist to Base Scheme</title>
</head>
<body style="font: 11px verdana;">
<h3>Annual Checklist to Base Scheme</h3>

<?php
ini_set('memory_limit', '1024M');
set_include_path('library' . PATH_SEPARATOR . get_include_path());

require_once 'library/BsOptimizerLibrary.php';
require_once 'DbHandler.php';
require_once 'Dictionary.php';
require_once 'converters/Ac/Loader.php';
require_once 'converters/Bs/Storer.php';
require_once 'library/Zend/Log/Writer/Stream.php';
require_once 'library/Zend/Log.php';
require_once 'Indicator.php';
alwaysFlush();

/**
 * Logger initialization
 */
$writer = new Zend_Log_Writer_Stream('logs/' . date("Y-m-d") . '-converter.log');
$logger = new Zend_Log($writer);
$ind = new Indicator();

/**
 * Configuration
 */
$config = parse_ini_file('config/AcToBs.ini', true);
// extract db options
foreach ($config as $k => $v) {
    $o = array();
    if (isset($v["options"])) {
        $options = explode(",", $v["options"]);
        foreach ($options as $option) {
            $parts = explode("=", trim($option));
            $o[$parts[0]] = $parts[1];
        }
        DbHandler::createInstance($k, $v, $o);
    }
}
$loader = new Ac_Loader(DbHandler::getInstance('source'), $logger);
$storer = new Bs_Storer(DbHandler::getInstance('target'), $logger, $ind);

echo '<p>Clearing old data...<br>';
$storer->clearDb();
echo 'Done!</p>';

echo '<p>Logging invalid taxa...<br>';
logInvalidRecords($logger);
echo 'Done!</p>';

// Databases
echo '<p>Transferring databases<br>';
$ind->init($loader->count('Database'));
$dbs = $loader->load('Database');
foreach ($dbs as $db) {
    $storer->store($db);
}
echo '<br>Done!</p>';

// Higher Taxa
echo '<p>Preparing higher taxa...<br>';
$total = $loader->count('HigherTaxon');
$ind->init($total, 100, 100);
echo "Transferring $total higher taxa<br>";
for ($limit = $memLimit = 100000, $offset = 0; $offset < $total; $offset += $memLimit) {
    try {
        list($taxa, $memLimit) = $loader->load('HigherTaxon', $offset, $limit);
        foreach ($taxa as $taxon) {
            try {
                $storer->store($taxon);
            }
            catch (PDOException $e) {
                $logger->err("\nStore error: " . formatException($e));
                echo '<pre>';
                print_r($taxon);
                echo '</pre>';
                echo 'Store error: '.formatException($e);
            }
        }
        unset($taxa);
    }
    catch (PDOException $e) {
        $logger->err("\nLoad error: " . formatException($e));
        echo 'Load error: '.formatException($e);
    }
}
echo '<br>Done!</p>';

// Taxa
echo '<p>Preparing species and infraspecies...<br>';
$total = $loader->count('Taxon');
$ind->init($total, 100, 100);
echo "Transferring $total taxa<br>";
for ($limit = $memLimit = 10000, $offset = 0; $offset < $total; $offset += $memLimit) {
    try {
        list($taxa, $memLimit) = $loader->load('Taxon', $offset, $limit);
        /*// Start debug
        if ($memLimit < $limit) {
            echo '<br><br>Memory use '.round(Ac_Loader_Taxon::memoryUse()).
                '%, limit capped at '.$memLimit.'<br><br>';
        }
        // End debug */
        foreach ($taxa as $taxon) {
            try {
                $storer->store($taxon);
            }
            catch (PDOException $e) {
                $logger->err("\nStore error: " . formatException($e));
                echo '<pre>';
                print_r($taxon);
                echo '</pre>';
                echo 'Store error: ' . formatException($e);
                //die();
            }
        }
        unset($taxa);
    }
    catch (PDOException $e) {
        $logger->err("\nLoad error: " . formatException($e));
        echo 'Load error: ' . formatException($e);
    }
}
?>
    </p><p>All records imported. Proceed to <b>Step 3</b>: <a href="BsOptimizer.php">
        Create the denormalized tables</a>.</p>
    </body>
</html>