<?php
/**
 * Abstract loader
 * 
 * @author N�ria Torrescasana Aloy
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
    
    public static function memoryUsePercentage ()
    {
        return (memory_get_usage() / (ini_get('memory_limit') * 1048576)) * 100;
    }
}