<?php
require_once 'Interface.php';
require_once 'Abstract.php';

/**
 * Taxon storer abstract
 * 
 * Second abstract class for HigherTaxon, Taxon and Synonym
 * 
 * @author Nœria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_TaxonAbstract extends Bs_Storer_Abstract
{
    public static $hybridMarkers = array(
        'x ', 
        ' x '
    );
    // Overview of infraspecific markers in Sp2010ac database that can be
    // mapped to predefined markers taken from TDWG. Any markers that are not 
    // present will be added to the `taxonomic_rank` table with standard = 0
    public static $markerMap = array(
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

    protected function _setTaxonomicRankId (Model $taxon)
    {
        if ($id = Dictionary::get('ranks', $taxon->taxonomicRank)) {
            $taxon->taxonomicRankId = $id;
            return $taxon;
        }
        $id = $this->_recordExists('id', 'taxonomic_rank', 
            array(
                'rank' => $taxon->taxonomicRank
            ));
        if ($id) {
            Dictionary::add('ranks', $taxon->taxonomicRank, $id);
            $taxon->taxonomicRankId = $id;
            return $taxon;
        }
        throw new Exception('Taxonomic rank id could not be set!');
    }

    protected function _setInfraSpecificMarkerId (Model $taxon)
    {
        $marker = trim($taxon->infraSpecificMarker);
        // If infraSpecificMarker is empty, but infraspecies is not, set
        // marker to unknown
        if ($marker == '' && $taxon->infraspecies !=
             '') {
                $taxon->infraSpecificMarker = $marker = 'not assigned';
        }
        if (array_key_exists($marker, self::$markerMap)) {
            $marker = self::$markerMap[$marker];
        }
        if ($markerId = Dictionary::get('ranks', $marker)) {
            $taxon->taxonomicRankId = $markerId;
            return $taxon;
        }
        $markerId = $this->_recordExists('id', 'taxonomic_rank', 
            array(
                'rank' => $marker
            ));
        if ($markerId) {
            Dictionary::add('ranks', $marker, $markerId);
            $taxon->taxonomicRankId = $markerId;
            return $taxon;
        }
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `taxonomic_rank` (`rank`, `marker_displayed`, `standard`) VALUE (?, ?, ?)');
        $stmt->execute(array(
            $marker, 
            $marker, 
            0
        ));
        $markerId = $this->_dbh->lastInsertId();
        Dictionary::add('ranks', $marker, $markerId);
        $taxon->taxonomicRankId = $markerId;
        return $taxon;
    }

    protected function _getScientificNameStatusId (Model $taxon)
    {
        if ($id = Dictionary::get('statuses', $taxon->scientificNameStatus)) {
            // Reset scientific name status id
            $taxon->scientificNameStatusId = $id;
            return $taxon;
        }
        $id = $this->_recordExists('id', 'scientific_name_status', 
            array(
                'name_status' => $taxon->scientificNameStatus
            ));
        if ($id) {
            Dictionary::add('statuses', $taxon->scientificNameStatusId, $id);
            $taxon->scientificNameStatusId = $id;
            return $taxon;
        }
        throw new Exception('Scientific name status could not be set!');
    }

    protected function _isHybrid ($nameElement)
    {
        foreach ($this->hybridMarkers as $marker) {
            $parts = explode($marker, $nameElement);
            if (count($parts) > 1) {
                return $parts;
            }
        }
        return false;
    }

    // Two slightly modified methods that take a single parameter 
    // rather than the entire object
    protected function _getTaxonomicRankId ($rank)
    {
        if ($id = Dictionary::get('ranks', $rank)) {
            return $id;
        }
        $id = $this->_recordExists('id', 'taxonomic_rank', array(
            'rank' => $rank
        ));
        if ($id) {
            Dictionary::add('ranks', $rank, $id);
            return $id;
        }
        return false;
    }

    protected function _getScientificNameElementId ($nameElement)
    {
        $name = strtolower($nameElement);
        $nameElementId = $this->_recordExists('id', 'scientific_name_element', 
            array(
                'name_element' => $nameElement
            ));
        if (!$nameElementId && trim($name) != '') {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `scientific_name_element` ' . '(`name_element`) VALUE (?)');
            $stmt->execute(array(
                $name
            ));
            $nameElementId = $this->_dbh->lastInsertId();
        }
        return $nameElementId;
    }
}