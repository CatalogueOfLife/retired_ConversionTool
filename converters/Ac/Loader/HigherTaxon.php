<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'converters/Ac/Model/AcToBs/HigherTaxon.php';

class Ac_Loader_HigherTaxon extends Ac_Loader_Abstract
    implements Ac_Loader_Interface
{
    
    public function count()
    {
        $stmt = $this->_dbh->prepare(
            'SELECT COUNT(1) FROM `taxa` WHERE `taxon` NOT LIKE "%species" '.
            'AND `is_accepted_name` = 1'
        );
        $stmt->execute();
        $res = $stmt->fetchColumn(0);
        unset($stmt);
        return $res;
    }
    
    public function load($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT record_id as id, LOWER(taxon) as taxonomicRank, name, '.
            'lsid, parent_id as parentId FROM `taxa` WHERE '.
            'taxon NOT LIKE "%species" AND is_accepted_name = 1 '.
            'ORDER BY record_id  LIMIT :offset, :limit'
        );
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $taxa = array();
        while($taxon = $stmt->fetchObject('Bs_Model_AcToBs_HigherTaxon')) {
        	$taxa[] = $taxon;
        }
        unset($stmt);
        return $taxa;
    }
}