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

    protected function _setTaxonomicRankId(Model $taxon) 
    {
        if ($id = Dictionary::get('ranks', $taxon->taxonomicRank)) {
            $taxon->taxonomicRankId = $id;
            return $taxon;
        }
        $stmt = $this->_dbh->prepare(
            'SELECT id FROM `taxonomic_rank` WHERE `rank` = ?'
        );
        $result = $stmt->execute(array($taxon->taxonomicRank));
        if ($result && $stmt->rowCount() == 1) {
            $id = $stmt->fetchColumn(0);
            Dictionary::add('ranks', $taxon->taxonomicRank, $id);
            $taxon->taxonomicRankId = $id;
            return $taxon;
        }
        throw new Exception('Taxonomic rank id could not be set!');
        return false;
    }

    protected function _setInfraSpecificMarkerId(Model $taxon) 
    {
        $marker = $taxon->infraSpecificMarker;
        // If infraSpecificMarker is empty, but infraspecies is not, set
        // marker to unknown
        if ($marker == '' && $taxon->infraspecies != '') {
            $marker = 'unknown';
        }
        if (array_key_exists($marker, self::$markerMap)) {
            $marker = self::$markerMap[$marker];
        }
        if ($markerId = Dictionary::get('ranks', $marker)) {
            $taxon->taxonomicRankId = $markerId;
            return $taxon;
        }
        $stmt = $this->_dbh->prepare(
            'SELECT id FROM `taxonomic_rank` WHERE `rank` = ?'
        );
        $result = $stmt->execute(array($marker));
        if ($result && $stmt->rowCount() == 1) {
            $markerId = $stmt->fetchColumn(0);
            Dictionary::add('ranks', $marker, $markerId);
            $taxon->taxonomicRankId = $markerId;
            return $taxon;
        }
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `taxonomic_rank` (`rank`, `standard`) VALUE (?, ?)'
        );
        $stmt->execute(array($marker, 0));
        $markerId = $this->_dbh->lastInsertId();
        Dictionary::add('ranks', $marker, $markerId);
        $taxon->taxonomicRankId = $markerId;
        if ($taxon->infraSpecificMarker != $marker) {
            $taxon->infraSpecificMarker = $marker;
        }
        return $taxon;
    }

    protected function _getScientificNameStatusId(Model $taxon)
    {
        if ($id = Dictionary::get('statuses', $taxon->scientificNameStatus)) {
            // Reset scientific name status id
            $taxon->scientificNameStatusId = $id;
            return $taxon;
        }
        $stmt = $this->_dbh->prepare(
            'SELECT id FROM `scientific_name_status` WHERE `name_status` = ?'
        );
        $result = $stmt->execute(array($taxon->scientificNameStatus));
        if ($result && $stmt->rowCount() == 1) {
            $id = $stmt->fetchColumn(0);
            Dictionary::add('statuses', $taxon->scientificNameStatusId, $id);
            $taxon->scientificNameStatusId = $id;
            return $taxon;
        }
        throw new Exception('Scientific name status could not be set!');
        return false;
    }
    
}