<?php
require_once 'library.php';
alwaysFlush();
set_include_path('library' . PATH_SEPARATOR . get_include_path());

require_once 'DbHandler.php';
require_once 'Dictionary.php';
require_once 'converters/Sc/Loader.php';
require_once 'converters/Dc/Storer.php';
require_once 'Zend/Log/Writer/Stream.php';
require_once 'Zend/Log.php';
require_once 'Indicator.php';
   
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
$config = parse_ini_file('config/ScToDc.ini', true);
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
$storer = new Dc_Storer(DbHandler::getInstance('target'), $logger, $ind);




/**
 * Conversion
 */
// Clear references
$storer->clear('Reference');

// Databases
$ind->init($loader->count('Database'));
echo '<p>Transferring databases<br>';
$storer->clear('Database');
$dbs = $loader->load('Database');
foreach($dbs as $db) {
    $storer->store($db);
    Dictionary::add('dbs', $db->name, $db->id);
}
echo '<br>Done!</p>';

// Specialists
$ind->init($loader->count('Specialist'));
echo '<p>Transferring specialists<br>';
$storer->clear('Specialist');
$specialists = $loader->load('Specialist');
foreach($specialists as $specialist) {
    $storer->store($specialist);
    Dictionary::add('specialists', $specialist->name, $specialist->id);
}
echo '<br>Done!</p>';

// Higher Taxa
$total = $loader->count('HigherTaxon');
$ind->init($total);
echo "<p>Transferring $total higher taxa<br>";
$storer->clear('HigherTaxon');

for ($limit = 1000, $offset = 0; $offset < $total; $offset += $limit) {    
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
echo '<br>Done!</p>';

// Taxa
$total = $loader->count('Taxon');
$ind->init($total, null, 25);
echo "<p>Transferring $total taxa (slow!)<br>";
$storer->clear('Taxon');
   
for ($limit = 5000, $offset = 0; $offset < $total; $offset += $limit) {
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
echo '<br>Done!</p>';

// Common Names
$total = $loader->count('CommonName');
$ind->init($total);
echo "Transferring $total common names" . '<br>';
$storer->clear('CommonName');
    
for ($limit = 100, $offset = 0; $offset < $total; $offset += $limit) {
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
echo '<br>Done!</p>';

// Distribution 
$total = $loader->count('Distribution');
$limit = 500;
$ind->init($total / $limit, 1);
echo "<p>Transferring $total distribution<br>";
$storer->clear('Distribution');
    
for ($offset = 0; $offset < $total; $offset += $limit) {    
    try {
        $storer->storeAll($loader->load('Distribution', $offset, $limit));
    } catch (PDOException $e) {
        $logger->warn('Store query failed: ' . $e->getMessage());
    }
}
echo '<br>Done!' . '</p>';