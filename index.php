<?php
set_include_path('library' . PATH_SEPARATOR . get_include_path());

require_once 'DbHandler.php';
require_once 'Dictionary.php';
require_once 'converters/Sc/Loader.php';
require_once 'converters/Dc/Storer.php';
require_once 'Zend/Log/Writer/Stream.php';
require_once 'Zend/Log.php';
   
/**
 * Logger initialization
 */
$writer = new Zend_Log_Writer_Stream('logs/conversion.log');
$writer->addFilter(Zend_Log::DEBUG);
$logger = new Zend_Log($writer);

/**
 * Puntjes!
 */
function puntjes (&$puntenteller, $counter, $total, &$punten_per_regelteller,
                  $breakline = PHP_EOL, $aantal_per_punt = 1, $punten_per_regel = 1)
{
    if ($aantal_per_punt == 0) {
        $puntenteller = $punten_per_regelteller = 0;
        return;
    }
    $puntenteller ++;
    if ($puntenteller >= $aantal_per_punt) {
        echo "."; flush();
        $puntenteller = 0;
        $punten_per_regelteller ++;
        if ($punten_per_regelteller >= $punten_per_regel) {
            if ($counter > 0 && $total > 0) {
                $current_percentage_done = round(($counter / $total * 100), 1);
                 echo " " . $current_percentage_done . "% done";
            }
            echo $breakline; flush();
            $punten_per_regelteller = 0;
        }
    }
}

/**
 * Configuration
 */
$config = parse_ini_file('config/db.ini', true);
// extract db options
foreach ($config as $k => $v) {
    $o = array();
    if (isset($config["options"])) {
        $options = explode(",", $config["options"]);
        foreach ($options as $option) {
            $parts = explode("=", trim($option));
            $o[$parts[0]] = $parts[1];
        }
    }
    DbHandler::createInstance($k, $v, $o);
}
// initialize loader (Sc - SPICE) and storer (Dc - Dynamic Checklist)
$loader = new Sc_Loader(DbHandler::getInstance('source'), $logger);
$storer = new Dc_Storer(DbHandler::getInstance('target'), $logger);

/**
 * Conversion
 */
// Clear references
$storer->clear('Reference');

// Databases
echo 'Transferring databases' . PHP_EOL;
$storer->clear('Database');
$dbs = $loader->load('Database');
foreach($dbs as $db) {
    $storer->store($db);
    Dictionary::add('dbs', $db->name, $db->id);
}
echo 'Done!' . PHP_EOL;

// Specialists
echo 'Transferring specialists' . PHP_EOL;
$storer->clear('Specialist');
$specialists = $loader->load('Specialist');
foreach($specialists as $specialist) {
    $storer->store($specialist);
    Dictionary::add('specialists', $specialist->name, $specialist->id);
}
echo 'Done!' . PHP_EOL;

// Higher Taxa
/*$total = $loader->count('HigherTaxon');
echo "Transferring $total higher taxa" . PHP_EOL;
$storer->clear('HigherTaxon');

$puntenteller = $punten_per_regelteller = 0;
    
for ($limit = 1000, $offset = 0; $offset < $total; $offset += $limit) {
    puntjes($puntenteller, $offset, $total, $punten_per_regelteller);
    try {
        $taxa = $loader->load('HigherTaxon', $offset, $limit);
        foreach($taxa as $taxon) {
            $storer->store($taxon);
        }
        unset($taxa);
    } catch (PDOException $e) {
        $logger->warn('Store query failed: ' . $e->getMessage());
    }
}
echo 'Done!' . PHP_EOL;*/


// Taxa
$total = $loader->count('Taxon');
echo "Transferring $total taxa" . PHP_EOL;
$storer->clear('Taxon');

$puntenteller = $punten_per_regelteller = 0;
    
for ($limit = 1000, $offset = 0; $offset < $total; $offset += $limit) {
    puntjes($puntenteller, $offset, $total, $punten_per_regelteller);
    try {
        $taxa = $loader->load('Taxon', $offset, $limit);
        foreach($taxa as $taxon) {
            $storer->store($taxon);
        }
        unset($taxa);
    } catch (PDOException $e) {
        $logger->warn('Store query failed: ' . $e->getMessage());
    }
}
echo 'Done!' . PHP_EOL;

// Common Names
$total = $loader->count('CommonName');
echo "Transferring $total common names" . PHP_EOL;
$storer->clear('CommonName');

$puntenteller = $punten_per_regelteller = 0;
    
for ($limit = 100, $offset = 0; $offset < $total; $offset += $limit) {
    puntjes($puntenteller, $offset, $total, $punten_per_regelteller);
    try {
        $commonNames = $loader->load('CommonName', $offset, $limit);
        foreach($commonNames as $cn) {                    
            $storer->store($cn);
        }
        unset($commonNames);
    } catch (PDOException $e) {
        $logger->warn('Store query failed: ' . $e->getMessage());
    }
}
echo 'Done!' . PHP_EOL;