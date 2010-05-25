<?php
require_once 'DbHandler.php';
require_once 'converters/Sc/Load/Engine.php';
require_once 'converters/Dc/Store/Engine.php';

try {
    $config = parse_ini_file('config/db.ini', true);
    foreach($config as $k => $v) {
        $o = array();
        if(isset($config["options"])) {
            $options = explode(",", $config["options"]);
            foreach($options as $option) {
                $parts = explode("=", trim($option));
                $o[$parts[0]] = $parts[1];
            }
        }
        DbHandler::createInstance($k, $v, $o);
    }
    $source = DbHandler::getInstance('source');
    $loader = new Sc_Load_Engine($source);
    
    $target= DbHandler::getInstance('target');
    $storer = new Dc_Store_Engine($target);
    
    /*$total = $loader->count('Database');
    
    echo 'Transferring databases<br/>';
    for ($i = 0; $i < $total; $i++) {
        $storer->clear('Database');
        $storer->store($loader->load('Database', $i));
        echo '.';
    }
    echo '<br/>Done!';*/
    
    for ($i = 0; $i < 100; $i++) {
        var_dump($loader->load('HigherTaxon', 15000 + $i));
    }
}
catch(Exception $e) {
    echo '<br/>An error occured: ' . $e->getMessage();
}