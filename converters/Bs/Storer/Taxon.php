<?php
require_once 'converters/Bs/Storer/HigherTaxon.php';
require_once 'converters/Bs/Storer/Reference.php';
require_once 'model/AcToBs/Specialist.php';
require_once 'converters/Bs/Storer/Specialist.php';
require_once 'model/AcToBs/Author.php';
require_once 'converters/Bs/Storer/Author.php';
require_once 'converters/Bs/Storer/Distribution.php';
require_once 'converters/Bs/Storer/Lifezone.php';
require_once 'converters/Bs/Storer/CommonName.php';
require_once 'converters/Bs/Storer/Synonym.php';
require_once 'model/AcToBs/Uri.php';
require_once 'converters/Bs/Storer/Uri.php';

/**
 * Taxon storer
 *
 * @author Nuria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Taxon extends Bs_Storer_HigherTaxon implements Bs_Storer_Interface
{

    public function store (Model $taxon)
    {
        // Check if taxon_id already exist. Some stray taxa appear twice
        // in the loader because either status or record_id are duplicated.
        // It is faster to skip them in the storer than in the loader.
        $config = parse_ini_file('config/AcToBs.ini', true);
        if (isset($config['checks']['taxon_ids']) && $config['checks']['taxon_ids'] == 1) {
            if ($this->_recordExists(
                'id', 'taxon', array(
                    'id' => $taxon->id
                ))) {
                $this->writeToErrorTable($taxon->id, $taxon->name, 'Taxon already exists',
                    $taxon->originalId);
                return $taxon;
            }
        }

        // Check if parent of infraspecies really is a species
        if (isset($config['checks']['infraspecies_parent_ids']) &&
            $config['checks']['infraspecies_parent_ids'] == 1 &&
            !$this->_checkInfraspeciesParent($taxon)) {
            $this->writeToErrorTable($taxon->id, $taxon->name,
                'Parent of infraspecies not a(n accepted) species', $taxon->originalId);
            return $taxon;
        }

        // Species rank id
        if ($taxon->infraspecies == '') {
            $this->_setTaxonomicRankId($taxon);
        // Infraspecies rank id
        }
        else {
            $this->_setInfraSpecificMarkerId($taxon);
        }
        $this->_getScientificNameStatusId($taxon);

        $this->_setTaxon($taxon);
        $this->_setScientificNameElements($taxon);
        $this->_setTaxonNameElement($taxon);
        $this->_setTaxonScrutiny($taxon);
        $this->_setTaxonReferences($taxon);
        $this->_setTaxonLsid($taxon);
        $this->_setTaxonUri($taxon);
        $this->_setTaxonDetail($taxon);
        $this->_setTaxonDistribution($taxon);
        $this->_setTaxonCommonNames($taxon);
        $this->_setTaxonSynonyms($taxon);
        $this->_setTaxonLifezones($taxon);
    }

    protected function _checkInfraspeciesParent ($taxon) {
        if (isset(
            $taxon->infraspecies) && $taxon->infraspecies != '') {
            $parentRankId = $this->_recordExists('taxonomic_rank_id', 'taxon',
                array(
                    'id' => $taxon->parentId
                ));
            if ($parentRankId != $this->_getTaxonomicRankId('species')) {
                return false;
            }
        }
        return true;
    }

    protected function _setScientificNameElements (Model $taxon)
    {
        foreach (array('genus', 'subgenus', 'species', 'infraspecies') as $ne) {
        	$name = trim($taxon->{$ne});
        	if (!empty($name)) {
        		$nameElements[$this->_getTaxonomicRankId($ne)] = $name;
        	}
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
        if (empty($taxon->specialistId)) {
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

    protected function _setTaxonDetail (Model $taxon)
    {
        $author = new Author();
        $author->authorString = $taxon->authorString;
        $storer = new Bs_Storer_Author($this->_dbh, $this->_logger);
        $storer->store($author);

        $stmt = $this->_dbh->prepare(
            'INSERT INTO `taxon_detail` (`taxon_id`, `author_string_id`, `scientific_name_status_id`,
            `additional_data`, `scrutiny_id`, `taxon_guid`, `name_guid`) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(
            array(
                $taxon->id,
                $author->id,
                $taxon->scientificNameStatusId,
                $taxon->additionalData,
                $taxon->scrutinyId,
                $taxon->taxonGuid,
                $taxon->nameGuid
            ));
        unset($storer, $author);
        return $taxon;
    }

    protected function _setTaxonUri (Model $taxon)
    {
        if (empty($taxon->uri)) {
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

    protected function _setTaxonReferences (Model $taxon)
    {
        $referenceIds = array();
        $storer = new Bs_Storer_Reference($this->_dbh, $this->_logger);
        foreach ($taxon->references as $reference) {
            $storer->store($reference);
            if (!empty($reference->id) && !in_array($reference->id, $referenceIds)) {
                $referenceIds[] = $reference->id;
                $stmt = $this->_dbh->prepare(
                    'INSERT INTO `reference_to_taxon` (`reference_id`, `taxon_id`, '.
                    '`reference_type_id`) VALUES (?, ?, ?)');
                $stmt->execute(
                    array(
                        $reference->id,
                        $taxon->id,
                        empty($reference->typeId) ? null : $reference->typeId
                    )
                );
            }
        }
        unset($storer, $referenceIds);
        return $taxon;
    }

    protected function _setTaxonDistribution (Model $taxon)
    {
        $storer = new Bs_Storer_Distribution($this->_dbh, $this->_logger);
        // Assembly database may contain duplicates; probably fastest to eradicate
        // these at storage stage, obviates DISTINCT query
        $storedDistributions = array();
        foreach ($taxon->distribution as $distribution) {
            $check = strtolower($distribution->freeText);
            if (!in_array($check, $storedDistributions)) {
                $distribution->taxonId = $taxon->id;
                $storer->store($distribution);
                $storedDistributions[] = $check;
            }
        }
        unset($storer, $storedDistributions);
        return $taxon;
    }

    protected function _setTaxonCommonNames (Model $taxon)
    {
        $storer = new Bs_Storer_CommonName($this->_dbh, $this->_logger);
        foreach ($taxon->commonNames as $commonName) {
            $commonName->taxonId = $taxon->id;
            $storer->store($commonName);
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

    protected function _setTaxonLifezones (Model $taxon)
    {
        $storer = new Bs_Storer_Lifezone($this->_dbh, $this->_logger);
        $storedLifezones = array();
        foreach ($taxon->lifezones as $lifezone) {
            if (!in_array($lifezone->lifezone, $storedLifezones)) {
                $lifezone->taxonId = $taxon->id;
                $storer->store($lifezone);
                $storedLifezones[] = $lifezone->lifezone;
            }
        }
        unset($storer, $storedLifezones);
    }
}