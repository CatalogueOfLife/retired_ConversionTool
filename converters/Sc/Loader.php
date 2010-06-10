<?php
require_once 'Loader/Interface.php';

class Sc_Loader
{
    protected $_dbh;
    protected $_logger;
    protected $_indicator;
    
    public function __construct(PDO $dbh, Zend_Log $logger, Indicator $indicator)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
        $this->_indicator = $indicator;
    }
    
    private function _getLoader($name)
    {
        $class = 'Sc_Loader_' . $name;
        if(!@include_once('Loader/' . $name . '.php')) {
            throw new Exception('Loader class file not found');
        }
        if(!class_exists($class)) {
            throw new Exception('Loader class undefined');
        }
        $loader = new $class($this->_dbh, $this->_logger, $this->_indicator);
        if(!$loader instanceof Sc_Loader_Interface) {
            unset($loader);
            throw new Exception('Invalid loader instance');
        }
        return $loader;
    }
    
    public function load($what, $offset = 0, $limit = 100)
    {
        return $this->_getLoader($what)->load($offset, $limit);
    }
    
    public function count($what)
    {
        return $this->_getLoader($what)->count();
    }
}