<?php
require_once 'Interface.php';
require_once 'Abstract.php';

class Bs_Storer_TaxonAbstract extends Bs_Storer_Abstract

{
    // Method used with just the rank, not the entire taxon object
    protected function _getTaxonomicRankId($rank) 
    {
        if ($id = Dictionary::get('ranks', $rank)) {
            return $id;
        }
        $stmt = $this->_dbh->prepare(
            'SELECT id FROM `taxonomic_rank` WHERE `rank` = ?'
        );
        $result = $stmt->execute(array($rank));
        if ($result && $stmt->rowCount() == 1) {
            $id = $stmt->fetchColumn(0);
            Dictionary::add('ranks', $rank, $id);
            return $id;
        }
        return false;
    }

    protected function _setTaxonomicRankId(Model $taxon) 
    {
        if ($id = Dictionary::get('ranks', $taxon->taxonomicRank)) {
            $taxon->taxonomicRankId = $id;
            return $taxon;
        }
        $stmt = $this->_dbh->prepare(
            'SELECT id FROM `taxonomic_rank` WHERE `rank` = ?'
        );
        $result = $stmt->execute(array($taxon->taxonomicRank));
        if ($result && $stmt->rowCount() == 1) {
            $id = $stmt->fetchColumn(0);
            Dictionary::add('ranks', $taxon->taxonomicRank, $id);
            $taxon->taxonomicRankId = $id;
            return $taxon;
        }
        throw new Exception('Taxonomic rank id could not be set!');
        return false;
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

    protected function _isHybrid($nameElement)
    {
        $hybridMarkers = array('x ', ' x ');
        foreach($hybridMarkers as $marker) {
            $parts = explode($marker, $nameElement);
            if (count($parts) > 1) {
                return $parts;
            }
        }
        return false;
    }

    protected function _getScientificNameElementId($nameElement)
    {
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
        return $nameElementId;
    }
}