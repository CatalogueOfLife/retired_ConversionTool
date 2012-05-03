<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'converters/Ac/Model/AcToBs/Taxon.php';
require_once 'model/AcToBs/Reference.php';
require_once 'model/AcToBs/Distribution.php';
require_once 'converters/Ac/Model/AcToBs/Lifezone.php';
require_once 'converters/Ac/Model/AcToBs/Distribution.php';
require_once 'converters/Ac/Model/AcToBs/CommonName.php';
require_once 'converters/Ac/Model/AcToBs/Synonym.php';

/**
 * 
 * @author N�ria Torrescasana Aloy, Ruud Altenburg
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
            'SELECT COUNT(1) FROM `taxa`  '.
            'WHERE `is_accepted_name` = 1 '.
            'AND `parent_id` != 0 '.
            'AND (`taxon` = "species" OR `taxon` = "infraspecies") '
        );
        $stmt->execute();
        $res = $stmt->fetchColumn(0);
        unset($stmt);
        return $res;
    }
    
    /**
     * Load taxa from Annual Checklist
     * 
     * Iterates through all taxa and fetches objects in batches
     * 
     * @param int $offset offset value for LIMIT in query
     * @param int $limit number of rows to be returned in query
     * @return array $taxa array of Taxon objects
     * 
     * Memory may not exceed xx percentage of total memory (defined in $this->_maxMemoryUse)
     */
    public function load($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT `record_id` AS id, `taxon` AS taxonomicRank, '.
            '`name`, `lsid`, `parent_id` AS parentId, '.
            '`database_id` AS sourceDatabaseId, '.
            '`name_code` AS originalId,  '.
            '`sp2000_status_id` AS scientificNameStatusId '.
            'FROM `taxa` '.
            'WHERE `is_accepted_name` = 1 '.
            'AND `parent_id` != 0 '.
            'AND (`taxon` = "species" OR  `taxon` = "infraspecies") '.
            'LIMIT :offset, :limit '
        );
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $taxa = array();
        $memLimit = 0;
        while($taxon = $stmt->fetchObject('Bs_Model_AcToBs_Taxon')) {
            $this->_setTaxonDetails($taxon);
            $this->_setTaxonScientificNameStatus($taxon);
            $this->_setTaxonSpecialistName($taxon);
        	$this->_setTaxonReferences($taxon);
            $this->_setTaxonDistribution($taxon);
            $this->_setTaxonCommonNames($taxon);
            $this->_setTaxonSynonyms($taxon);
            $this->_setTaxonLifezones($taxon);
            $taxa[] = $taxon;
            
            $memLimit++;
            if (self::memoryUse() > $this->_maxMemoryUse) {
                return array($taxa, $memLimit);
            }
        }
        unset($stmt);
        return array($taxa, $limit);
    }
    
    protected function _setTaxonDetails(Model $taxon) 
    {
        $stmt = $this->_dbh->prepare(
            'SELECT `genus`, `species`, `infraspecies`, '.
            '`infraspecies_marker` AS infraSpecificMarker,  '.
            '`author` AS authorString, '.
            '`web_site` AS uri, `comment` AS additionalData, '.
            '`scrutiny_date` AS scrutinyDate, '.
            '`specialist_id` AS specialistId, '.
            '`GSDTaxonGUID` AS taxonGuid, '.
            '`GSDNameGUID` AS nameGuid '.
            'FROM `scientific_names` '.
            'WHERE `record_id` = ? '
        );
        //$stmt->setFetchMode(PDO::FETCH_INTO);
        $stmt->execute(array($taxon->id));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        foreach ($data as $propName => $propValue) {
            $taxon->{$propName} = $propValue;
        }
        unset($stmt);
        return $taxon;
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
            'SELECT t1.`author` AS authors, t1.`year`, t1.`title`, 
            t1.`source` AS text, t2.`reference_type` AS type 
            FROM `references` t1 
            LEFT JOIN `scientific_name_references` AS t2 ON t1.`record_id` = t2.`reference_id` 
            WHERE t2.`name_code` = ? AND 
            (t2.`reference_type` != "ComNameRef" OR t2.`reference_type` IS NULL)'
		);
		$stmt->execute(array($taxon->originalId));
		$taxon->references = $stmt->fetchAll(PDO::FETCH_CLASS, 'Reference');
		unset($stmt);
        return $taxon;
    }

    protected function _setTaxonDistribution(Model $taxon)
    {
        // Order by distributions which have been assigned a status/
        // Duplicates are removed at the storage stage; distributions with a status
        // get precedence.
        $stmt = $this->_dbh->prepare(
            'SELECT `distribution` AS freeText, `DistributionStatus` AS status '.
            'FROM `distribution` WHERE `name_code` = ? '.
            'ORDER BY IF (status = "" OR status IS NULL, 1, 0), status'
        );
        $stmt->execute(array($taxon->originalId));
        $taxon->distribution = $stmt->fetchAll(PDO::FETCH_CLASS, 
            'Bs_Model_AcToBs_Distribution');
        unset($stmt);
        return $taxon;
    }

    protected function _setTaxonLifezones(Model $taxon)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT `lifezone` FROM `lifezone` WHERE `name_code` = ?'
        );
        $stmt->execute(array($taxon->originalId));
        $taxon->lifezones = $stmt->fetchAll(PDO::FETCH_CLASS, 
            'Bs_Model_AcToBs_Lifezone');
        unset($stmt);
        return $taxon;
    }

    protected function _setTaxonCommonNames(Model $taxon)
    {
		$stmt = $this->_dbh->prepare(
			'SELECT t1.`common_name` AS commonNameElement, t1.`language`, '.
		    't1.`country`, t2.`author` as referenceAuthors, '.
		    't2.`year` AS referenceYear, t2.`title` AS referenceTitle, '.
		    't2.`source` AS referenceText, t1.`transliteration`, t1.`area` AS region '.
		    'FROM `common_names` t1 '.
		    'LEFT JOIN `references` t2 ON t1.`reference_id` = t2.`record_id` '.
		    'WHERE t1.`name_code` = ?'
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
    	   'IF (t1.`infraspecies` = "" OR t1.`infraspecies` IS NULL, '.
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