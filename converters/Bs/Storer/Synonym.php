<?php
require_once 'converters/Bs/Storer/TaxonAbstract.php';
require_once 'model/AcToBs/Author.php';
require_once 'converters/Bs/Storer/Author.php';
require_once 'converters/Bs/Storer/Reference.php';

/**
 * Synonym storer
 *
 * @author Nuria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Synonym extends Bs_Storer_TaxonAbstract implements Bs_Storer_Interface
{

    public function store (Model $synonym)
    {
        if (empty($synonym->id)) {
            return $synonym;
        }
        // Exit if id already exists; test needed because error occurred during import.
        $config = parse_ini_file('config/AcToBs.ini', true);
        if (isset($config['checks']['synonym_ids']) && $config['checks']['synonym_ids'] == 1) {
            if ($this->_recordExists(
                'id', 'synonym', array(
                    'id' => $synonym->id
                ))) {
                $name = trim(
                    $synonym->genus . ' ' .
                    (!empty($synonym->subgenus) ? '(' . $synonym->subgenus. ') ' : '') .
                    $synonym->species . ' ' .
                    (!empty($synonym->infraSpecificMarker) ? $synonym->infraSpecificMarker. ' ' : '') .
                    (!empty($synonym->infraspecies) ? $synonym->infraspecies : '')
                );
                $this->writeToErrorTable($synonym->id, $name, 'Synonym already exists',
                    $synonym->originalId);
                return $synonym;
            }
        }
        // Exit if status is accepted/provisionally accepted
        $this->_getScientificNameStatusId($synonym);
        if (in_array($synonym->scientificNameStatus, array(1, 4))) {
            $this->writeToErrorTable($synonym->id, $name, 'Synonym with accepted name status',
                $synonym->originalId);
            return $synonym;
        }

        if (strtolower($synonym->taxonomicRank) == 'infraspecies') {
            $this->_setInfraSpecificMarkerId($synonym);
        }
        else {
            $this->_setTaxonomicRankId($synonym);
        }

        $this->_setSynonym($synonym);
        $this->_setSynonymNameElements($synonym);
        if (is_array($synonym->references) && count($synonym->references) > 0) {
            $this->_setSynonymReferences($synonym);
        }
        //$this->printObject($synonym);
    }

    protected function _setSynonym (Model $synonym)
    {
        $author = new Author();
        $author->authorString = $synonym->authorString;
        $storer = new Bs_Storer_Author($this->_dbh, $this->_logger);
        $storer->store($author);

        $stmt = $this->_dbh->prepare(
            'INSERT INTO `synonym` (`id`, `taxon_id`, `author_string_id`, `scientific_name_status_id`,
        	`original_id`, `taxon_guid`, `name_guid`) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        try {
            $stmt->execute(
                array(
                    $synonym->id,
                    $synonym->taxonId,
                    $author->id,
                    $synonym->scientificNameStatusId,
                    $synonym->originalId,
                    $synonym->taxonGuid,
                    $synonym->nameGuid
                ));
            $synonym->id = $this->_dbh->lastInsertId();
        } catch (PDOException $e) {
            $this->_handleException("Store error synonym", $e);
        }
        unset($author);
        return $synonym;
    }

    protected function _setSynonymNameElements (Model $synonym)
    {
        foreach (array('genus', 'subgenus', 'species') as $ne) {
            $name = trim($synonym->{$ne});
        	if (!empty($name)) {
        		$nameElements[$this->_getTaxonomicRankId($ne)] = $name;
        	}
        }
        $name = trim($synonym->infraspecies);
        if (!empty($name)) {
            $nameElements[$synonym->taxonomicRankId] = $name;
        }
        $synonym->nameElementIds = $nameElements;
        
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `synonym_name_element` (`taxonomic_rank_id`, `scientific_name_element_id`,
    	   `synonym_id`, `hybrid_order`) VALUES (?, ?, ?, ?)'
        );
        // Hybrid synonyms are currently stored as regular taxa as
        // accepted hybrids cannot be stored according to the rules either
        foreach ($synonym->nameElementIds as $rankId => $nameElement) {
            $nameElementId = $this->_getScientificNameElementId($nameElement);
            try {
                $stmt->execute(array(
                    $rankId,
                    $nameElementId,
                    $synonym->id,
                    null
                ));
            } catch (PDOException $e) {
                $this->_handleException("Store error synonym name element", $e);
            }
        }
        return $synonym;
    }

    protected function _setSynonymReferences (Model $synonym)
    {
        $referenceIds = array();
        $storer = new Bs_Storer_Reference($this->_dbh, $this->_logger);
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `reference_to_synonym` (`reference_id`, `synonym_id`, '.
            '`reference_type_id`) VALUES (?, ?, ?)'
        );
        foreach ($synonym->references as $reference) {
            $storer->store($reference);
            if (!empty($reference->id) && !in_array($reference->id, $referenceIds)) {
                $referenceIds[] = $reference->id;
                try {
                    $stmt->execute(
                        array(
                            $reference->id,
                            $synonym->id,
                            empty($reference->typeId) ? null : $reference->typeId
                        )
                    );
                } catch (PDOException $e) {
                    $this->_handleException("Store error synonym reference", $e);
                }
            }
        }
        unset($storer);
        return $synonym;
    }
}