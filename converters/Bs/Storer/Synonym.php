<?php
require_once 'converters/Bs/Storer/TaxonAbstract.php';
require_once 'model/AcToBs/Author.php';
require_once 'converters/Bs/Storer/Author.php';
require_once 'converters/Bs/Storer/Reference.php';

/**
 * Synonym storer
 * 
 * @author N�ria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Synonym extends Bs_Storer_TaxonAbstract implements Bs_Storer_Interface
{

    public function store (Model $synonym)
    {
        if (empty($synonym->id)) {
            return $synonym;
        }
        // Exit if id already exists; test needed because error occurred 
        // during import.
        $config = parse_ini_file('config/AcToBs.ini', true);
        if (isset($config['checks']['synonym_ids']) && $config['checks']['synonym_ids'] == 1) {
            if ($this->_recordExists(
                'id', 'synonym', array(
                    'id' => $synonym->id
                ))) {
                $name = trim(
                    $synonym->genus . ' ' . $synonym->species . ' ' . $synonym->infraspecies);
                $this->writeToErrorTable($synonym->id, $name, 'Synonym already exists');
                return $synonym;
            }
        }
        if (strtolower($synonym->taxonomicRank) == 'infraspecies') {
            $this->_setInfraSpecificMarkerId($synonym);
        }
        else {
            $this->_setTaxonomicRankId($synonym);
        }
        $this->_getScientificNameStatusId($synonym);
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
            'INSERT INTO `synonym` (`id`, `taxon_id`, `author_string_id`, `scientific_name_status_id`, `original_id`) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute(
            array(
                $synonym->id, 
                $synonym->taxonId, 
                $author->id, 
                $synonym->scientificNameStatusId, 
                $synonym->originalId
            ));
        $synonym->id = $this->_dbh->lastInsertId();
        unset($author);
        return $synonym;
    }

    protected function _setSynonymNameElements (Model $synonym)
    {
        $nameElements = array(
            $this->_getTaxonomicRankId('genus') => $synonym->genus, 
            $this->_getTaxonomicRankId('species') => $synonym->species
        );
        if ($synonym->infraspecies != '') {
            $nameElements[$synonym->taxonomicRankId] = $synonym->infraspecies;
        }
        $synonym->nameElementIds = $nameElements;
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `synonym_name_element` (' . '`taxonomic_rank_id`, `scientific_name_element_id`, `synonym_id`, `hybrid_order`) VALUES (?, ?, ?, ?)');
        /*      foreach ($synonym->nameElementIds as $rankId => $nameElement) {
            if ($hybridElements = $this->_isHybrid($nameElement)) {
                foreach ($hybridElements as $hybridOrder => $hybridElement) {
                    // Start order with 1 rather than 0
                    $hybridOrder++;
                    $hybridElement = 
                        trim(str_replace('x ', '', $hybridElement));
                    $nameElementId = 
                        $this->_getScientificNameElementId($hybridElement);
                    $stmt->execute(array(
                        $rankId, $nameElementId, $synonym->id, $hybridOrder)
                    );
                }
            } else {
                $nameElementId = 
                        $this->_getScientificNameElementId($nameElement);
                $stmt->execute(array(
                    $rankId, $nameElementId, $synonym->id, NULL)
                );
            }
        }
*/
        // Hybrid synonyms are currently stored as regular taxa as
        // accepted hybrids cannot be stored according to the rules either
        foreach ($synonym->nameElementIds as $rankId => $nameElement) {
            $nameElementId = $this->_getScientificNameElementId($nameElement);
            $stmt->execute(
                array(
                    $rankId, 
                    $nameElementId, 
                    $synonym->id, 
                    NULL
                ));
        }
        return $synonym;
    }

    protected function _setSynonymReferences (Model $synonym)
    {
        $referenceIds = array();
        $storer = new Bs_Storer_Reference($this->_dbh, $this->_logger);
        foreach ($synonym->references as $reference) {
            $storer->store($reference);
            if (!in_array($reference->id, $referenceIds)) {
                $referenceIds[] = $reference->id;
                $stmt = $this->_dbh->prepare(
                    'INSERT INTO `reference_to_synonym` (`reference_id`, `synonym_id`, '.
                    '`reference_type_id`) VALUES (?, ?, ?)');
                $stmt->execute(
                    array(
                        $reference->id, 
                        $synonym->id,
                        $reference->typeId
                    )
                );
            }
        }
        unset($storer);
        return $synonym;
    }
}