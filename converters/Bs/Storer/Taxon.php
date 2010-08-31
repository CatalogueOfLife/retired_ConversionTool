<?php
require_once 'converters/Bs/Storer/HigherTaxon.php';
require_once 'converters/Bs/Storer/Reference.php';
require_once 'model/AcToBs/Specialist.php';
require_once 'converters/Bs/Storer/Specialist.php';
require_once 'model/AcToBs/Author.php';
require_once 'converters/Bs/Storer/Author.php';
require_once 'converters/Bs/Storer/Distribution.php';
require_once 'converters/Bs/Storer/CommonName.php';
require_once 'converters/Bs/Storer/Synonym.php';

class Bs_Storer_Taxon extends Bs_Storer_HigherTaxon
    implements Bs_Storer_Interface
{
    public function store(Model $taxon)
    {
     	// Species rank id
     	if ($taxon->infraSpecificMarker == '' && $taxon->infraspecies == '') {
    		$this->_setTaxonomicRankId($taxon);
    	// Infraspecies rank id
     	} else {
            $this->_setInfraSpecificMarkerId($taxon);
    	}
    	$this->_getScientificNameStatusId($taxon);
    	$this->_setScientificNameElements($taxon);
        $this->_setTaxon($taxon);
        // Abort if parent taxon does not match for infraspecies
        if (!$this->_setTaxonNameElement($taxon)) {
    	    $this->_logger->debug(
    	       'SKIPPED '.$taxon->name.': parent incorrectly set'
    	    );
    	    return;
    	}
    	if ($taxon->specialistId != '') {
    		$this->_setTaxonScrutiny($taxon);
    	}
    	if (count($taxon->references) > 0) {
            $this->_setTaxonReferences($taxon);
    	}
    	$this->_setTaxonLsid($taxon);
        $this->_setTaxonDetail($taxon);
        $this->_setTaxonDistribution($taxon);
        $this->_setTaxonCommonNames($taxon);
        $this->_setTaxonSynonyms($taxon);
        
//$this->printObject($taxon); 
    	
    }
    
    protected function _setScientificNameElements(Model $taxon) 
    {
        $nameElements = array(
            $this->_getTaxonomicRankId('genus') => $taxon->genus, 
            $this->_getTaxonomicRankId('species') => $taxon->species 
        );
        if ($taxon->infraSpecificMarker != '') {
        	$nameElements[$taxon->taxonomicRankId] = $taxon->infraspecies;
        }
        foreach ($nameElements as $rankId => $nameElement) {
	        $name = strtolower($nameElement);
            $nameElementId = $this->_recordExists(
                'id', 'scientific_name_element',
                array('name_element' => $name)
            );
	        if (!$nameElementId) {
	            $stmt = $this->_dbh->prepare(
	                'INSERT INTO `scientific_name_element` '.
	                '(`name_element`) VALUE (?)'
	            );
	            $stmt->execute(array($name));
	            $nameElementId =  $this->_dbh->lastInsertId();
	        }
	        $taxon->nameElementIds[$rankId] = $nameElementId;
        }
        // At least two elements should have been set for (infra)species
        if (count($taxon->nameElementIds) >= 2) {
            return $taxon;
        }
        throw new Exception('Scientific name element could not be set!');
        return false;
    }

    protected function _setTaxonScrutiny(Model $taxon)
    {
        $specialist = new Specialist();
        $specialist->name = $taxon->specialistName;
        $storer = new Bs_Storer_Specialist($this->_dbh, $this->_logger);
        $storer->store($specialist);
        // Reset specialist id to new value
        $taxon->specialistId = $specialist->id;
        
        $date = $this->parseAcDate($taxon->scrutinyDate);
        $scrutinyId = $this->_recordExists('id', 'scrutiny', array(
            'specialist_id' => $taxon->specialistId,
            'scrutiny_date' => $date,
            'original_scrutiny_date' => $taxon->scrutinyDate)
        );
        if ($scrutinyId) {
            $taxon->scrutinyId = $scrutinyId;
        } else {
	        $stmt = $this->_dbh->prepare(
	            'INSERT INTO `scrutiny` (`specialist_id`, `scrutiny_date`, '.
	            '`original_scrutiny_date`) VALUES (?, ?, ?)'
	        );
	        $stmt->execute(array(
	            $taxon->specialistId, $date, $taxon->scrutinyDate)
	        );
	        $taxon->scrutinyId = $this->_dbh->lastInsertId();
        }
        unset($storer, $specialist);
        return $taxon;
    }
    
    protected function _setTaxonReferences(Model $taxon)
    {
        $referenceIds = array();
        $storer = new Bs_Storer_Reference($this->_dbh, $this->_logger);
        foreach ($taxon->references as $reference) {
            $storer->store($reference);
            if (!in_array($reference->id, $referenceIds)) {
            	$referenceIds[] = $reference->id;
            }
        }
        foreach ($referenceIds as $referenceId) {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `reference_to_taxon` (`reference_id`, `taxon_id`) '.
                'VALUES (?, ?)'
            );
            $stmt->execute(array($referenceId, $taxon->id));
        }
        unset($storer);
        return $taxon;
    }
    
    protected function _setTaxonDetail(Model $taxon)
    {
     	$author = new Author();
        $author->authorString = $taxon->authorString;
        $storer = new Bs_Storer_Author($this->_dbh, $this->_logger);
        $storer->store($author);
        
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `taxon_detail` (`taxon_id`, `author_string_id`, '.
            '`scientific_name_status_id`, `additional_data`, `scrutiny_id`) '.
            'VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $taxon->id, 
            $author->id, 
            $taxon->scientificNameStatusId,
            $taxon->additionalData,
            $taxon->scrutinyId)
        );
        unset($storer, $author);
        return $taxon;
    }
    
    protected function _setTaxonDistribution(Model $taxon)
    {
        $storer = new Bs_Storer_Distribution($this->_dbh, $this->_logger);
    	foreach ($taxon->distribution as $distribution) {
	        $storer->store($distribution);
	        $stmt = $this->_dbh->prepare(
	            'INSERT INTO `distribution_free_text` (`taxon_detail_id`, '.
	            '`region_free_text_id`) VALUES (?, ?)'
	        );
	        $stmt->execute(array(
	            $taxon->id, 
	            $distribution->id)
	        );
    	}
        unset($storer, $distribution);
    	return $taxon;
    }
    
    protected function _setTaxonCommonNames(Model $taxon)
    {
        $storer = new Bs_Storer_CommonName($this->_dbh, $this->_logger);
        foreach ($taxon->commonNames as $commonName) {
            $commonName->taxonId = $taxon->id;
            $storer->store($commonName);
        }
    }
    
    protected function _setTaxonSynonyms(Model $taxon)
    {
        $storer = new Bs_Storer_Synonym($this->_dbh, $this->_logger);
        foreach ($taxon->synonyms as $synonym) {
            $synonym->taxonId = $taxon->id;
            $storer->store($synonym);
        }
    }
}