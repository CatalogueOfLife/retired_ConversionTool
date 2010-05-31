<?php
require_once 'Storer/Interface.php';

class Dc_Storer
{
    protected $_dbh;
    protected $_logger;
    
    public function __construct(PDO $dbh, Zend_Log $logger)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
    }
    
    private function _getStorer($name, $isClass = false)
    {
        if($isClass) {
            $class = $name;
            $parts = explode('_', $name);
            $name = current(array_reverse($parts));
        }
        $class = 'Dc_Storer_' . $name;
        
        if(!include_once('Storer/' . $name . '.php')) {
            throw new Exception('Storer class file not found');
        }
        if(!class_exists($class)) {
            throw new Exception('Storer class undefined');
        }
        $storer = new $class($this->_dbh, $this->_logger);
        if(!$storer instanceof Dc_Storer_Interface) {
            unset($storer);
            throw new Exception('Invalid storer instance');
        }
        return $storer;
    }
    
    public function clear($what) {
        return $this->_getStorer($what)->clear();
    }
    
    public function store(Model $object)
    {
        return $this->_getStorer(get_class($object), true)->store($object);
    }
}