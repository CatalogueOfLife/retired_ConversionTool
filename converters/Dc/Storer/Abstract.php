<?php
abstract class Dc_Storer_Abstract
{
    protected $_dbh;
    protected $_logger;
    
    public function __construct(PDO $dbh, Zend_Log $logger)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
    }
    
    public function clear() {
        throw new Exception(__METHOD__ . ' not implemented');
    }
    
    public function store(Model $object) {
        throw new Exception(__METHOD__ . ' not implemented');
    }
}