<?php
set_include_path('library' . PATH_SEPARATOR . get_include_path());

require_once 'DbHandler.php';
require_once 'Dictionary.php';
require_once 'converters/Sc/Load/Engine.php';
require_once 'converters/Dc/Store/Engine.php';
require_once 'Zend/Log/Writer/Stream.php';
require_once 'Zend/Log.php';
   
$writer = new Zend_Log_Writer_Stream('logs/conversion.log');
$writer->addFilter(Zend_Log::NOTICE);
$logger = new Zend_Log($writer);

function puntjes (&$puntenteller, $counter, $total, &$punten_per_regelteller,
                  $breakline = PHP_EOL, $aantal_per_punt = 1, $punten_per_regel = 10)
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


    $config = parse_ini_file('config/db.ini', true);
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
    $source = DbHandler::getInstance('source');
    $loader = new Sc_Load_Engine($source, $logger);
    
    $target = DbHandler::getInstance('target');
    $storer = new Dc_Store_Engine($target, $logger);
    
    $total = $loader->count('Database');
    
    echo 'Transferring databases' . PHP_EOL;
    $storer->clear('Database');
    $dbs = $loader->load('Database');
    foreach($dbs as $db) {
        $storer->store($db);
        Dictionary::add('dbs', $db->name, $db->id);
    }
    echo PHP_EOL . 'Done!' . PHP_EOL;
    
    echo 'Transferring specialists' . PHP_EOL;
    $storer->clear('Specialist');
    $specialists = $loader->load('Specialist');
    foreach($specialists as $specialist) {
        $storer->store($specialist);
        Dictionary::add('specialists', $specialist->name, $specialist->id);
    }
    echo PHP_EOL . 'Done!' . PHP_EOL;
    
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
    echo PHP_EOL . 'Done!';*/