<?php
require_once 'Interface.php';
require_once 'Abstract.php';

class Dc_Storer_Distribution extends Dc_Storer_Abstract
    implements Dc_Storer_Interface
{
	protected $_query = 
	   'INSERT INTO `distribution` (name_code, distribution) VALUES (?, ?)';
	
    public function clear()
    {
        $stmt = $this->_dbh->prepare('TRUNCATE `distribution`');
        $stmt->execute();
        unset($stmt);
    }
    
    public function store(Model $distribution)
    {
        $stmt = $this->_dbh->prepare($this->_query);
        $stmt->execute(
            array($distribution->nameCode, 
            $distribution->distribution)
        );
        $distribution->id = $this->_dbh->lastInsertId();
        return $distribution;
    }
    
    public function storeAll (array $dists)
    {
    	$total = count($dists);
    	$query = $this->_query . str_repeat(', (?, ?)', $total - 1);
    	$this->_logger->debug($query);
    	$values = array();
    	foreach ($dists as $dist) {
    		$values = array_merge(
    		  $values, array($dist->nameCode, $dist->distribution)
    		);
    	}
    	$stmt = $this->_dbh->prepare($query);
    	$stmt->execute($values);
    	unset($values, $dists, $stmt);
    }
}