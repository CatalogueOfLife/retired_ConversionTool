<?php
require_once 'Dictionary.php';
require_once 'converters/Bs/Storer/HigherTaxon.php';
require_once 'converters/Bs/Storer/Reference.php';
require_once 'model/AcToBs/Specialist.php';
require_once 'converters/Bs/Storer/Specialist.php';
require_once 'model/AcToBs/Author.php';
require_once 'converters/Bs/Storer/Author.php';
require_once 'converters/Bs/Storer/Distribution.php';
require_once 'converters/Bs/Storer/CommonName.php';

class Bs_Storer_Taxon extends Bs_Storer_HigherTaxon
    implements Bs_Storer_Interface
{
	// Overview of infraspecific markers in Sp2010ac database that can be
	// mapped to predefined markers taken from TDWG. Any markers that are not 
	// present will be added to the `taxonomic_rank` table with standard = 0
	private static $markerMap = array (
		'convar.' => 'convar',
		'cv.' => 'cultivar',
		'f.' => 'form',
		'forma' => 'form',
		'm.' => 'morph',
		'monst.' => 'monster',
		'monstr.' => 'monster',
		'mut.' => 'mutant',
		'prol.' => 'prole',
		'proles' => 'prole',
		'raca' => 'race',
		'ssp.' => 'subspecies',
		'subf.' => 'subform',
		'subforma' => 'subform',
		'subs.' => 'subspecies',
		'subsp,' => 'subspecies',
		'subsp.' => 'subspecies',
		'subsp..' => 'subspecies',
		'subvar.' => 'sub-variety',
		'susbp.' => 'subspecies',
		'susp.' => 'subspecies',
		'var' => 'variety',
		'var,' => 'variety',
		'var.' => 'variety'
	);
	
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
    	$this->_setTaxon($taxon);
    	$this->_setScientificNameElements($taxon);
    	if ($taxon->specialistId != '') {
    		$this->_setTaxonScrutiny($taxon);
    	}
    	if (is_array($taxon->references) && count($taxon->references) > 0) {
            $this->_setTaxonReferences($taxon);
    	}
    	$this->_setTaxonLsid($taxon);
        $this->_setTaxonDetail($taxon);
        $this->_setTaxonDistribution($taxon);
        $this->_setTaxonCommonName($taxon);
        
//$this->printObject($taxon); 
    	
//        $this->_setTaxonNameElement($taxon);  // Needs parent_id!
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
    
    // Slightly different from HigherTaxon as multiple elements are set
    protected function _setScientificNameElements(Model $taxon) 
    {
        $nameElements = array($taxon->genus, $taxon->species);
        if ($taxon->infraspecies != '') {
        	$nameElements[] = $taxon->infraspecies;
        }
        
        foreach ($nameElements as $nameElement) {
	        $name = strtolower($nameElement);
	        $stmt = $this->_dbh->prepare(
	            'SELECT id FROM `scientific_name_element` WHERE `name_element` = ?'
	        );
	        $result = $stmt->execute(array($name));
	        if ($result && $stmt->rowCount() == 1) {
	            $nameElementId =  $stmt->fetchColumn(0);
	        } else {
	            $stmt = $this->_dbh->prepare(
	                'INSERT INTO `scientific_name_element` '.
	                '(`name_element`) VALUE (?)'
	            );
	            $stmt->execute(array($name));
	            $nameElementId =  $this->_dbh->lastInsertId();
	        }
	        if (isset($nameElementId)) {
	            $taxon->nameElementIds[] = $nameElementId;
	        }
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
    
    protected function _setTaxonCommonName(Model $taxon)
    {
        $storer = new Bs_Storer_CommonName($this->_dbh, $this->_logger);
    	foreach ($taxon->commonNames as $commonName) {
    		// All cross-tables are handled in Bs_Storer_CommonName
    		// Need to pass taxon_id before storing
    		$commonName->taxonId = $taxon->id;
    		$storer->store($commonName);
    	}
    }
}