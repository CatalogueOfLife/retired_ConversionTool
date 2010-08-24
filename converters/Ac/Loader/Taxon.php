<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'converters/Ac/Model/AcToBs/Taxon.php';
require_once 'model/AcToBs/Reference.php';
require_once 'model/AcToBs/Distribution.php';
require_once 'converters/Ac/Model/AcToBs/CommonName.php';

class Ac_Loader_Taxon extends Ac_Loader_Abstract
    implements Ac_Loader_Interface
{
    public function count()
    {
        $stmt = $this->_dbh->prepare(
            'SELECT COUNT(1) FROM taxa t1, scientific_names t2 WHERE '.
            't1.`is_accepted_name` = 1 AND t1.`taxon` LIKE "%species" AND '.
            't1.`name_code` = t2.`name_code`'
        );
        $stmt->execute();
        $res = $stmt->fetchColumn(0);
        unset($stmt);
        return $res;
    }
    
    public function load($offset, $limit)
    {
        // Statement that includes proper examples
        $stmt = $this->_dbh->prepare(
            'SELECT distinct t1.`record_id` as id, t1.`taxon` as taxonomicRank, '.
            't1.`name`, t2.`genus`, t2.`species`, t2.`infraspecies`, '.
            't2.`infraspecies_marker` as infraSpecificMarker, t1.`lsid`, '.
            't1.`parent_id` as parentId, t1.`database_id` as sourceDatabaseId, '.
            't1.`name_code` as originalId, t2.`author` as authorString, '.
            't1.`sp2000_status_id` as scientificNameStatusId,'.
            't2.`web_site` as uri, t2.`comment` as additionalData, '.
            't2.`scrutiny_date` as scrutinyDate, t2.`specialist_id` as specialistId '.
            'FROM taxa t1, scientific_names t2, common_names t3 WHERE t1.`is_accepted_name` = 1 '.
            'AND t1.`taxon` LIKE "%species" AND t1.`name` NOT LIKE "% x %" '.
            'AND t2.`specialist_id` IS NOT NULL AND t1.name_code = t3.name_code '.
            'AND t2.`scrutiny_date` IS NOT NULL AND t1.`name_code` = t2.`name_code` '.
            'LIMIT :offset, :limit'
        );
/*        $stmt = $this->_dbh->prepare(
            'SELECT t1.`record_id` as id, t1.`taxon` as taxonomicRank, '.
            't1.`name`, t2.`genus`, t2.`species`, t2.`infraspecies`, '.
            't2.`infraspecies_marker` as infraSpecificMarker, t1.`lsid`, '.
            't1.`parent_id` as parentId, t1.`database_id` as sourceDatabaseId, '.
            't1.`name_code` as originalId, t2.`author` as authorString, '.
            't1.`sp2000_status_id` as scientificNameStatusId,'.
            't2.`web_site` as uri, t2.`comment` as additionalData, '.
            't2.`scrutiny_date` as scrutinyDate, t2.`specialist_id` as specialistId '.
            'FROM taxa t1, scientific_names t2 WHERE t1.`is_accepted_name` = 1 '.
            'AND t1.`taxon` LIKE "%species" AND t1.`name` NOT LIKE "% x %" '.
            'AND t1.`name_code` = t2.`name_code` '.
            'LIMIT :offset, :limit'
        );
        */
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $taxa = array();
        while($taxon = $stmt->fetchObject('Bs_Model_AcToBs_Taxon')) {
        	$this->_setTaxonScientificNameStatus($taxon);
        	$this->_setTaxonSpecialistName($taxon);
        	$this->_setTaxonReferences($taxon);
            $this->_setTaxonDistribution($taxon);
            $this->_setTaxonCommonNames($taxon);
            
            
            
            
            
            
            $taxa[] = $taxon;
        }
        unset($stmt);
        return $taxa;
    }
    
    protected function _setTaxonScientificNameStatus(Model $taxon)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT `sp2000_status` FROM `sp2000_statuses` WHERE ' .
            'record_id = ?' 
        );
        $stmt->execute(array($taxon->scientificNameStatusId));
        $taxon->scientificNameStatus = $stmt->fetchColumn(0);
        unset($stmt);
    }
    
    protected function _setTaxonSpecialistName(Model $taxon)
    {
		$stmt = $this->_dbh->prepare(
		'SELECT `specialist_name` FROM `specialists` WHERE ' .
		'record_id = ?' 
		);
		$stmt->execute(array($taxon->specialistId));
		$taxon->specialistName = $stmt->fetchColumn(0);
		unset($stmt);
    }

    protected function _setTaxonReferences(Model $taxon)
    {
        $stmt = $this->_dbh->prepare(
			'SELECT `author` as authors, `year`, `title`, `source` as text '.
            'FROM `references` t1, `scientific_name_references` t2 WHERE ' .
			't2.`name_code` = ? AND t1.`record_id` = t2.`reference_id` AND '.
			't2.`reference_type` != "ComNameRef"'
		);
		$stmt->execute(array($taxon->originalId));
		$taxon->references = $stmt->fetchAll(PDO::FETCH_CLASS, 'Reference');
		unset($stmt);
    }

    protected function _setTaxonDistribution(Model $taxon)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT `distribution` as freeText FROM `distribution` WHERE ' .
            '`name_code` = ?'
        );
        $stmt->execute(array($taxon->originalId));
        $taxon->distribution = $stmt->fetchAll(PDO::FETCH_CLASS, 'Distribution');
        unset($stmt);
    }

    protected function _setTaxonCommonNames(Model $taxon)
    {
		$stmt = $this->_dbh->prepare(
			'SELECT t1.`common_name` as commonNameElement, t1.`language`, '.
			't1.`country`, t2.`author` as referenceAuthors, '.
			't2.`year` as referenceYear, t2.`title` as referenceTitle, '.
			't2.`source` as referenceText '.
			'FROM `common_names` t1, `references` t2 '.
			'WHERE t1.`reference_id` = t2.`record_id` AND t1.`name_code` = ?'
		);
		$stmt->execute(array($taxon->originalId));
		$taxon->commonNames = $stmt->fetchAll(
		    PDO::FETCH_CLASS, 'Bs_Model_AcToBs_CommonName'
		);
		unset($stmt);
    }

}