<?php
abstract class Bs_Storer_Abstract
{
    protected $_dbh;
    protected $_logger;

    public function __construct(PDO $dbh, Zend_Log $logger)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
    }
    
    protected function _recordExists($field, $table, array $where)
    {
    	$query = 'SELECT `'.$field.'` FROM `'.$table.'` WHERE ';
    	foreach ($where as $field => $value) {
		    $query .= ' (`'.$field.'` = :'.$field;
            if ($value == NULL) {
                $query .= ' OR `'.$field.'` IS NULL';
            }
            $query .= ') AND ';
     	}
		$stmt = $this->_dbh->prepare(substr($query, 0, -5));
		if ($stmt->execute($where) && $stmt->rowCount() == 1) {
            return $stmt->fetchColumn(0);
		}
	    return false;
    }
    
    public function getLastKeyArray($array)
    {
        end($array);
        $key = key($array);
        reset($array);
        return $key;
    }
    
    public function printObject($object)
    {
        echo '<pre>';
        print_r($object);
        echo '</pre>';
    }
    
    public function parseAcDate($date) {
    	$date = trim($date);
    	// First strip off FishBase string if it is present
    	if (strpos($date, 'FishBase') !== false) {
    		$date = str_replace('Data last modified by FishBase ', '', $date);
        	// Also strip off time in some of these...
        	if (strlen($date) > 11) {
    		    $parts = explode($date, ' ');
    		    $date = $parts[0];
    	   }
    	}
        // Only dates of the type 5-Mar-1990 or 05-Mar-1990 are considered valid
        if (strpos($date,'-') !== false && in_array(strlen($date),array(10, 11))) {
    	    $dateToTimestamp = strtotime($date);
    		return date('Y-m-d', $dateToTimestamp);
    	}
    	return NULL;
    }
}