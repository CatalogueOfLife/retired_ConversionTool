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
    
    public function getDatabaseNameFromNameCode($nameCode)
    {    
        $taxonIdParts = explode('_', $nameCode);
        return $taxonIdParts[0];
    }
}