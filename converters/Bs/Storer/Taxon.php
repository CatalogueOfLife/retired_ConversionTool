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
require_once 'model/AcToBs/Uri.php';
require_once 'converters/Bs/Storer/Uri.php';

/**
 * Taxon storer
 * 
 * @author Nœria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Taxon extends Bs_Storer_HigherTaxon implements Bs_Storer_Interface
{

    public function store (Model $taxon)
    {
        // Check if taxon_id already exist. Some stray taxa appear twice 
        // in the loader because either status or record_id are duplicated.
        // It is faster to skip them in the storer than in the loader.
        if ($this->_recordExists(
            'id', 'taxon', array(
                'id' => $taxon->id
            ))) {
            $this->writeToErrorTable($taxon->id, $taxon->name, 'Taxon already exists');
            return $taxon;
        }
        
        // Species rank id
        if ($taxon->infraSpecificMarker == '' && $taxon->infraspecies ==
             '') {
                $this->_setTaxonomicRankId($taxon);
            // Infraspecies rank id
        }
        else {
            $this->_setInfraSpecificMarkerId($taxon);
        }
        $this->_getScientificNameStatusId($taxon);
        $this->_setScientificNameElements($taxon);
        $this->_setTaxon($taxon);
        // Abort if parent taxon does not match for infraspecies
        if (!$this->_setTaxonNameElement($taxon)) {
            $this->writeToErrorTable($taxon->id, $taxon->name, 
                'Parent of infraspecies not a species');
            return false;
        }
        $this->_setTaxonScrutiny($taxon);
        $this->_setTaxonReferences($taxon);
        $this->_setTaxonLsid($taxon);
        $this->_setTaxonUri($taxon);
        $this->_setTaxonDetail($taxon);
        $this->_setTaxonDistribution($taxon);
        $this->_setTaxonCommonNames($taxon);
        $this->_setTaxonSynonyms($taxon);
    }

    protected function _setScientificNameElements (Model $taxon)
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
            $nameElementId = $this->_recordExists('id', 'scientific_name_element', 
                array(
                    'name_element' => $name
                ));
            if (!$nameElementId) {
                $stmt = $this->_dbh->prepare(
                    'INSERT INTO `scientific_name_element` (`name_element`) VALUE (?)');
                $stmt->execute(
                    array(
                        $name
                    ));
                $nameElementId = $this->_dbh->lastInsertId();
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

    protected function _setTaxonScrutiny (Model $taxon)
    {
        if ($taxon->specialistId == '') {
            return $taxon;
        }
        $specialist = new Specialist();
        $specialist->name = $taxon->specialistName;
        $storer = new Bs_Storer_Specialist($this->_dbh, $this->_logger);
        $storer->store($specialist);
        // Reset specialist id to new value
        $taxon->specialistId = $specialist->id;
        
        $date = $this->parseAcDate($taxon->scrutinyDate);
        $scrutinyId = $this->_recordExists('id', 'scrutiny', 
            array(
                'specialist_id' => $taxon->specialistId, 
                'scrutiny_date' => $date, 
                'original_scrutiny_date' => $taxon->scrutinyDate
            ));
        if ($scrutinyId) {
            $taxon->scrutinyId = $scrutinyId;
        }
        else {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `scrutiny` (`specialist_id`, `scrutiny_date`, `original_scrutiny_date`) VALUES (?, ?, ?)');
            $stmt->execute(
                array(
                    $taxon->specialistId, 
                    $date, 
                    $taxon->scrutinyDate
                ));
            $taxon->scrutinyId = $this->_dbh->lastInsertId();
        }
        unset($storer, $specialist);
        return $taxon;
    }

    protected function _setTaxonReferences (Model $taxon)
    {
        if (count($taxon->references) == 0) {
            return $taxon;
        }
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
                'INSERT INTO `reference_to_taxon` (`reference_id`, `taxon_id`) VALUES (?, ?)');
            $stmt->execute(
                array(
                    $referenceId, 
                    $taxon->id
                ));
        }
        unset($storer);
        return $taxon;
    }

    protected function _setTaxonDetail (Model $taxon)
    {
        $author = new Author();
        $author->authorString = $taxon->authorString;
        $storer = new Bs_Storer_Author($this->_dbh, $this->_logger);
        $storer->store($author);
        
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `taxon_detail` (`taxon_id`, `author_string_id`, `scientific_name_status_id`, 
            `additional_data`, `scrutiny_id`) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute(
            array(
                $taxon->id, 
                $author->id, 
                $taxon->scientificNameStatusId, 
                $taxon->additionalData, 
                $taxon->scrutinyId
            ));
        unset($storer, $author);
        return $taxon;
    }

    protected function _setTaxonUri (Model $taxon)
    {
        if ($taxon->uri == '') {
            return $taxon;
        }
        $uri = new Uri();
        $uri->resourceIdentifier = $taxon->uri;
        $storer = new Bs_Storer_Uri($this->_dbh, $this->_logger);
        $storer->store($uri);
        $stmt = $this->_dbh->prepare('INSERT INTO `uri_to_taxon` (uri_id, taxon_id) VALUES (?, ?)');
        $stmt->execute(array(
            $uri->id, 
            $taxon->id
        ));
        unset($storer, $uri);
    }

    protected function _setTaxonDistribution (Model $taxon)
    {
        $storer = new Bs_Storer_Distribution($this->_dbh, $this->_logger);
        foreach ($taxon->distribution as $distribution) {
            if ($distribution->freeText != '') {
                $distribution->taxonId = $taxon->id;
                $storer->store($distribution);
            }
        }
        unset($storer);
        return $taxon;
    }

    protected function _setTaxonCommonNames (Model $taxon)
    {
        $storer = new Bs_Storer_CommonName($this->_dbh, $this->_logger);
        foreach ($taxon->commonNames as $commonName) {
            if ($commonName->commonNameElement != '') {
                $commonName->taxonId = $taxon->id;
                $storer->store($commonName);
            }
        }
        unset($storer);
    }

    protected function _setTaxonSynonyms (Model $taxon)
    {
        $storer = new Bs_Storer_Synonym($this->_dbh, $this->_logger);
        foreach ($taxon->synonyms as $synonym) {
            $synonym->taxonId = $taxon->id;
            $storer->store($synonym);
        }
        unset($storer);
    }
}