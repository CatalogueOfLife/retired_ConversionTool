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
        return (memory_get_usage() / (ini_get('memory_limit') * 1048576)) * 100;
    }

    public function printObject ($object)
    {
        echo '<pre>';
        print_r($object);
        echo '</pre>';
    }
}