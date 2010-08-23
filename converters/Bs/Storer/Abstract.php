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
    
    protected function _recordExists($id_field, $table, array $where)
    {
    	$query = 'SELECT '.$id_field.' FROM `'.$table.'` WHERE ';
    	foreach ($where as $field => $value) {
    		if ($value == NULL) {
    			$query .= '`'.$field.'` IS :'.$field.' AND ';
    		} else {
    		    $query .= '`'.$field.'` = :'.$field.' AND ';
    		}
    	}
		$stmt = $this->_dbh->prepare(substr($query, 0, -5));
		$result = $stmt->execute($where);
		if ($result && $stmt->rowCount() == 1) {
            return $stmt->fetchColumn(0);
		}
	    return false;
    }
    
    public function clearDb(array $tables)
    {
        foreach (self::$dbTables as $table) {
            $stmt = $this->_dbh->prepare('TRUNCATE `'.$table.'`');
            $stmt->execute();
            $stmt = $this->_dbh->prepare(
                'ALTER TABLE `'.$table.'` AUTO_INCREMENT = 1'
            );
            $stmt->execute();
        }
        unset($stmt);
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