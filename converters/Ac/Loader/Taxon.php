<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'converters/Ac/Model/AcToBs/Taxon.php';
require_once 'model/AcToBs/Reference.php';
require_once 'model/AcToBs/Distribution.php';
require_once 'converters/Ac/Model/AcToBs/CommonName.php';
require_once 'converters/Ac/Model/AcToBs/Synonym.php';

/**
 * 
 * @author Nœria Torrescasana Aloy, Ruud Altenburg
 *
 */
class Ac_Loader_Taxon extends Ac_Loader_Abstract
    implements Ac_Loader_Interface
{
    /**
     * Count number of (infra)species in Annual Checklist
     * 
     * @return int
     */
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
    
    /**
     * Load taxa from Annual Checklist
     * 
     * Iterates through all higher taxa and fetches objects in batches
     * 
     * @param int $offset offset value for LIMIT in query
     * @param int $limit number of rows to be returned in query
     * @return array $taxa array of Taxon objects
     */
    public function load($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT t1.`record_id` AS id, t1.`taxon` AS taxonomicRank, '.
            't1.`name`, t2.`genus`, t2.`species`, t2.`infraspecies`, '.
            't2.`infraspecies_marker` AS infraSpecificMarker, t1.`lsid`, '.
            't1.`parent_id` AS parentId, '.
            't1.`database_id` AS sourceDatabaseId, '.
            't1.`name_code` AS originalId, t2.`author` AS authorString, '.
            't1.`sp2000_status_id` AS scientificNameStatusId,'.
            't2.`web_site` AS uri, t2.`comment` AS additionalData, '.
            't2.`scrutiny_date` AS scrutinyDate, '.
            't2.`specialist_id` AS specialistId '.
            'FROM taxa t1, scientific_names t2 '.
            'WHERE t1.`is_accepted_name` = 1 '.
            'AND t1.`taxon` LIKE "%species" ' .
            'AND t1.`name_code` = t2.`name_code` '.
            'LIMIT :offset, :limit'
        );
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
            $this->_setTaxonSynonyms($taxon);
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
        return $taxon;
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
        return $taxon;
    }

    protected function _setTaxonReferences(Model $taxon)
    {
        $stmt = $this->_dbh->prepare(
			'SELECT `author` AS authors, `year`, `title`, `source` AS text '.
            'FROM `references` t1, `scientific_name_references` t2 '.
            'WHERE t2.`name_code` = ? AND t1.`record_id` = t2.`reference_id` '.
            'AND t2.`reference_type` != "ComNameRef"'
		);
		$stmt->execute(array($taxon->originalId));
		$taxon->references = $stmt->fetchAll(PDO::FETCH_CLASS, 'Reference');
		unset($stmt);
        return $taxon;
    }

    protected function _setTaxonDistribution(Model $taxon)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT `distribution` AS freeText FROM `distribution` '.
            'WHERE `name_code` = ?'
        );
        $stmt->execute(array($taxon->originalId));
        $taxon->distribution = $stmt->fetchAll(PDO::FETCH_CLASS, 'Distribution');
        unset($stmt);
        return $taxon;
    }

    protected function _setTaxonCommonNames(Model $taxon)
    {
		$stmt = $this->_dbh->prepare(
			'SELECT t1.`common_name` AS commonNameElement, t1.`language`, '.
			't1.`country`, t2.`author` as referenceAuthors, '.
			't2.`year` AS referenceYear, t2.`title` AS referenceTitle, '.
			't2.`source` AS referenceText '.
			'FROM `common_names` t1, `references` t2 '.
			'WHERE t1.`reference_id` = t2.`record_id` AND t1.`name_code` = ?'
		);
		$stmt->execute(array($taxon->originalId));
		$taxon->commonNames = $stmt->fetchAll(
		    PDO::FETCH_CLASS, 'Bs_Model_AcToBs_CommonName'
		);
		unset($stmt);
        return $taxon;
    }

    protected function _setTaxonSynonyms(Model $taxon)
    {
    	$stmt = $this->_dbh->prepare(
    	   'SELECT t1.`record_id` AS id, t1.`name_code` AS originalId, '.
    	   't1.`genus`, t1.`species`, t1.`infraspecies`, '.
    	   't1.`author` AS authorString, t1.`web_site` AS uri, '.
    	   't1.`infraspecies_marker` AS infraSpecificMarker, '.
    	   't2.`sp2000_status` AS scientificNameStatus, '.
    	   'IF (t1.`infraspecies_marker` = "" OR t1.`infraspecies_marker` '.
    	   'IS NULL AND t1.`infraspecies` = "" OR t1.`infraspecies` IS NULL, '.
    	   '"Species", "Infraspecies") AS taxonomicRank '.
           'FROM `scientific_names` t1, `sp2000_statuses` t2 WHERE '.
           't1.`sp2000_status_id` = t2.`record_id` AND '.
    	   't1.`accepted_name_code` = ? AND t1.`is_accepted_name` = 0 AND '.
    	   't1.`name_code` != t1.`accepted_name_code`'
    	);
        $stmt->execute(array($taxon->originalId));
        $taxon->synonyms = $stmt->fetchAll(
            PDO::FETCH_CLASS, 'Bs_Model_AcToBs_Synonym'
        );
        foreach ($taxon->synonyms as $synonym) {
        	$this->_setTaxonReferences($synonym);
        }
        unset($stmt);
        return $taxon;
    }
}