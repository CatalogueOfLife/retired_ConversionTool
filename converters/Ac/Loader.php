<?php
require_once 'Loader/Interface.php';

/**
 * Loader
 *
 * Dynamically loads the appropriate class. In the script that runs the
 * conversion, only Class has to be given rather than Ac_Loader_Class
 *
 * @author Nuria Torrescasana Aloy
 */
class Ac_Loader
{
    protected $_dbh;
    protected $_logger;
    protected $_loaders = array();

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
        if(!include_once('Loader/' . $name . '.php')) {
            $e = new Exception('Loader class file not found');
            $this->_logger->err($e);
            throw $e;
        }
        if(!class_exists($class)) {
            $e = new Exception('Loader class undefined');
            $this->_logger->err($e);
            throw $e;
        }
        if (isset($this->_loaders[$class])) {
            $loader = $this->_loaders[$class];
        } else {
            $loader = new $class($this->_dbh, $this->_logger);
            if(!$loader instanceof Ac_Loader_Interface) {
                $e = new Exception('Invalid loader instance');
                $this->_logger->err($e);
                unset($loader);
                throw $e;
            }
            $this->_loaders[$class] = $loader;
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