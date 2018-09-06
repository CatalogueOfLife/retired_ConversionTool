<?php
/**
 * Abstract loader
 *
 * @author Nuria Torrescasana Aloy
 */
abstract class Ac_Loader_Abstract
{
    protected $_dbh;
    protected $_logger;
    protected $_maxMemoryUse = 75; // Percentage of memory limit

    public function __construct(PDO $dbh, Zend_Log $logger)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
    }

    public static function memoryUse ()
    {
        return (memory_get_usage() / self::unitToInt(ini_get('memory_limit'))) * 100;
    }

    public static function unitToInt ($s)
    {
        return (int)preg_replace_callback('/(\-?\d+)(.?)/', function ($m) {
            return $m[1] * pow(1024, strpos('BKMG', $m[2]));
        }, strtoupper($s));
    }
    
    public function printObject ($object)
    {
        echo '<pre>';
        print_r($object);
        echo '</pre>';
    }
    
     
}