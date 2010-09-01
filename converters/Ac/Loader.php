<?php
require_once 'Loader/Interface.php';

/**
 * Loader
 * 
 * Dynamically loads the appropriate class. In the script that runs the 
 * conversion, only Class has to be given rather than Ac_Loader_Class
 * 
 * @author Nœria Torrescasana Aloy
 */
class Ac_Loader
{
    protected $_dbh;
    protected $_logger;
    
    public function __construct(PDO $dbh, Zend_Log $logger)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
    }
    
    /**
     * Dynamically loads the appropriate loader class
     * 
     * Takes a simplified notation of the loader class that should be used
     * and dispatches the load() or count() methods to that class
     * 
     * @param string $name class name
     * @throws exception
     * @return class $loader loader class
     */
    private function _getLoader($name)
    {
        $class = 'Ac_Loader_' . $name;
        if(!@include_once('Loader/' . $name . '.php')) {
            throw new Exception('Loader class file not found');
        }
        if(!class_exists($class)) {
            throw new Exception('Loader class undefined');
        }
        $loader = new $class($this->_dbh, $this->_logger);
        if(!$loader instanceof Ac_Loader_Interface) {
            unset($loader);
            throw new Exception('Invalid loader instance');
        }
        return $loader;
    }
    
    /**
     * Passes load function on to appropriate loader class
     */
    public function load($what, $offset = 0, $limit = 100)
    {
        return $this->_getLoader($what)->load($offset, $limit);
    }
    
    /**
     * Passes count function on to appropriate loader class
     */
    public function count($what)
    {
         return $this->_getLoader($what)->count();
    }
}