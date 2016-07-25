<?php
set_include_path('library' . PATH_SEPARATOR . get_include_path());
require_once 'library/bootstrap.php';
require_once 'library/BsOptimizerLibrary.php';
require_once 'DbHandler.php';

$config = isset($argv) && isset($argv[1]) ?
    parse_ini_file($argv[1], true) : parse_ini_file('config/AcToBs.ini', true);
$options = explode(",", $config['target']['options']);
foreach ($options as $option) {
    $parts = explode("=", trim($option));
    $o[$parts[0]] = $parts[1];
}
DbHandler::createInstance('target', $config['target'], $o);
$pdo = DbHandler::getInstance('target');
setCredits();
echo 'Credits updated';
?>