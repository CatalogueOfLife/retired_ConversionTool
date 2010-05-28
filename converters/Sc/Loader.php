<?php
class Sc_Loader
{
    protected $_dbh;
    protected $_logger;
    
    public function __construct(PDO $dbh, Zend_Log $logger)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
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
        return new $class($this->_dbh, $this->_logger);
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