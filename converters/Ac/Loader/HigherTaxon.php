<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'converters/Ac/Model/AcToBs/HigherTaxon.php';

/**
 * HigherTaxon loader
 * 
 * @author N�ria Torrescasana Aloy, Ruud Altenburg
 *
 */
class Ac_Loader_HigherTaxon extends Ac_Loader_Abstract
    implements Ac_Loader_Interface
{
    
    /**
     * Count number of higher taxa in Annual Checklist
     * 
     * @return int
     */
    public function count()
    {
        $stmt = $this->_dbh->prepare(
            'SELECT COUNT(1) FROM `taxa` WHERE `taxon` != "species" AND '.
            '`taxon` != "infraspecies" AND `is_accepted_name` = 1'
        );
        $stmt->execute();
        $res = $stmt->fetchColumn(0);
        unset($stmt);
        return $res;
    }
    
    /**
     * Load higher taxa from Annual Checklist
     * 
     * Iterates through all higher taxa and fetches objects in batches
     * 
     * @param int $offset offset value for LIMIT in query
     * @param int $limit number of rows to be returned in query
     * @return array $taxa array of HigherTaxon objects
     * 
     * Memory may not exceed xx percentage of total memory (defined in $this->_maxMemoryUse)
     */
    public function load($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT `record_id` as id, LOWER(`taxon`) as taxonomicRank, `name`, '.
            '`lsid`, `parent_id` as parentId FROM `taxa` WHERE '.
            '`taxon` != "species" AND `taxon` != "infraspecies" AND '.
            '`is_accepted_name` = 1 '.
            'ORDER BY `record_id` LIMIT :offset, :limit'
        );
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $taxa = array();
        $memLimit = 0;
        while($taxon = $stmt->fetchObject('Bs_Model_AcToBs_HigherTaxon')) {
            $taxa[] = $taxon;
            $memLimit++;
        	if (self::memoryUsePercentage() > $this->_maxMemoryUse) {
        	    return array($taxa, $memLimit);
        	}
        }
        unset($stmt);
        return array($taxa, $limit);
    }
}