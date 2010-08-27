<?php
require_once 'converters/Bs/Storer/TaxonAbstract.php';
require_once 'model/AcToBs/Uri.php';
require_once 'converters/Bs/Storer/Uri.php';

class Bs_Storer_HigherTaxon extends Bs_Storer_TaxonAbstract
    implements Bs_Storer_Interface
{
    public function store(Model $taxon)
    {
//$this->printObject($taxon);
    	// Source database id is NULL for all higher taxa
        $this->_setTaxonomicRankId($taxon);
        $this->_setTaxon($taxon);
    	$this->_setScientificNameElement($taxon);
        $this->_setTaxonNameElement($taxon);
        $this->_setTaxonLsid($taxon);
    }
    
    protected function _setTaxon(Model $taxon)
    {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `taxon` (`id`, `taxonomic_rank_id`, '.
            '`source_database_id`, `original_id`) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $taxon->id,
            $taxon->taxonomicRankId,
            $taxon->sourceDatabaseId,
            $taxon->originalId)
        );
        return $taxon;
    }
    
    protected function _setScientificNameElement(Model $taxon) 
    {
        // All names are stored in lower case
        $name = strtolower($taxon->name);
        $stmt = $this->_dbh->prepare(
            'SELECT id FROM `scientific_name_element` WHERE `name_element` = ?'
        );
        $result = $stmt->execute(array($name));
        if ($result && $stmt->rowCount() == 1) {
            $nameElementId = $stmt->fetchColumn(0);
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
            return $taxon;
        }
        throw new Exception('Scientific name element could not be set!');
        return false;
    }
    
    protected function _setTaxonNameElement(Model $taxon) 
    {
        // Top level(s)
        if ($taxon->parentId == '' || $taxon->parentId == 0) {
            $taxon->parentId = NULL;
        }
        // Verify for infraspecies if parent is present and matches 
        // record in the checklist; if not abort
        if (isset($taxon->infraspecies) && $taxon->infraspecies != '') {
            $stmt = $this->_dbh->prepare(
                'SELECT `taxonomic_rank_id` FROM `taxon` WHERE `id` = ?'
            );
            $result = $stmt->execute(array($taxon->parentId));
            $parentRankId = $stmt->fetchColumn(0);
            if ($parentRankId != $this->_getTaxonomicRankId('species')) {
                return false;
            }
        }
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `taxon_name_element` (`taxon_id`, '.
            '`scientific_name_element_id`, `parent_id`) VALUES (?, ?, ?)'
        );
        $stmt->execute(array(
           $taxon->id, $nameElementId, $taxon->parentId
           )
        );
        return $taxon;
    }

    protected function _setTaxonLsid(Model $taxon)
    {
    	$uri = new Uri();
        $uri->resourceIdentifier = $taxon->lsid;
    	$storer = new Bs_Storer_Uri($this->_dbh, $this->_logger);
        $uri->uriSchemeId = $storer->getUriSchemeIdByScheme('lsid');
        $storer->store($uri);
        
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `uri_to_taxon` (uri_id, taxon_id) VALUES (?, ?)'
        );
        $stmt->execute(array(
            $uri->id,
            $taxon->id)
        );
        unset($storer, $uri);
    }
}