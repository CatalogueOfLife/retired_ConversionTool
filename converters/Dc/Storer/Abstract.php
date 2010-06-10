<?php
abstract class Dc_Storer_Abstract
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
}