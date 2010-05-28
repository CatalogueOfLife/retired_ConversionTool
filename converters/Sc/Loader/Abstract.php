<?php
abstract class Sc_Loader_Abstract
{
    protected $_dbh;
    protected $_logger;
    
    public function __construct(PDO $dbh, Zend_Log $logger)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
    }
    
    public function count() {
        throw new Exception(__METHOD__ . ' not implemented');
    }
    
    public function load($offset, $limit) {
        throw new Exception(__METHOD__ . ' not implemented');
    }
}