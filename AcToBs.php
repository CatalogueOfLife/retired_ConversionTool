<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<title>Annual Checklist to Base Scheme</title>
</head>
<body style="font: 12px verdana;">
<h3>Annual Checklist to Base Scheme</h3>

<?php
ini_set('memory_limit', '512M');
set_include_path('library' . PATH_SEPARATOR . get_include_path());

require_once 'library.php';
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
$writer = new Zend_Log_Writer_Stream('logs/conversion.log');
$writer->addFilter(Zend_Log::WARN);
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
for ($limit = 10000, $offset = 0; $offset < $total; $offset += $limit) {
    try {
        $taxa = $loader->load('HigherTaxon', $offset, $limit);
        foreach ($taxa as $taxon) {
            $storer->store($taxon);
        }
        unset($taxa);
        //        Dictionary::dumpAll();
    }
    catch (PDOException $e) {
        echo '<pre>';
        print_r($taxon);
        echo '</pre>';
        echo formatException($e);
    }
}
echo '<br>Done!</p>';

// Taxa
// Needs about 350MB memory for 3000 records per loop, 
// decrease if less is available!
echo '<p>Preparing species and infraspecies...<br>';
$total = $loader->count('Taxon');
$ind->init($total, 100, 30);
echo "Transferring $total taxa<br>";
for ($limit = 3000, $offset = 0; $offset < $total; $offset += $limit) {
    try {
        $taxa = $loader->load('Taxon', $offset, $limit);
        //echo showMemoryUse().' memory used<br>';
        foreach ($taxa as $taxon) {
            $storer->store($taxon);
        }
        unset($taxa);
    }
    catch (PDOException $e) {
        echo '<pre>';
        print_r($taxon);
        echo '</pre>';
        echo formatException($e);
    }
}
echo '<br>All records imported. Next step is to <a href="BsOptimizer.php">
    create the denormalized search tables</a>.</p>';
?>
</body>
</html>