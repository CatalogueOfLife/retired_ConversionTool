<?php
require_once 'Abstract.php';
require_once 'converters/Sc/Model/HigherTaxon.php';

class Sc_Loader_HigherTaxon extends Sc_Loader_Abstract
{
    
    public function count()
    {
        $stmt = $this->_dbh->prepare(
            'SELECT COUNT(1) FROM HierarchyCache WHERE LENGTH(TRIM(rank)) > 0'
        );
        $stmt->execute();
        $res = $stmt->fetchColumn(0);
        unset($stmt);
        return $res;
    }
    
    public function load($offset, $limit)
    {
        $this->_logger->debug('Start ' . __METHOD__);
        $stmt = $this->_dbh->prepare(
            'SELECT taxonID, rank, taxonName, parent ' .
            'FROM HierarchyCache WHERE LENGTH(TRIM(rank)) > 0 ' .
            'LIMIT :offset, :limit'
        );
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        $taxa = array();
        
        while($taxon = $stmt->fetch(PDO::FETCH_ASSOC)) {
            
            $this->_logger->debug('Processing taxon ' . $taxon['taxonID']);
        
            $higherTaxon = new Sc_Model_HigherTaxon();
            
            $higherTaxon->$taxon['rank'] = $taxon['taxonName'];
            $parentId = $taxon['parent'];
            
            do {
                // TODO: this method of walking up the hierarchy is terribly
                // uneffective because of the bad quality of the data
                // It seems to fail always for the sp2000 hierarchy, so in
                // that case (taxonID LIKE Sp2000Hierarchy_*) it should be
                // fetched from the first id, which contains all the information
                $taxon = $this->_fetchTaxonParent($parentId);
                if($taxon) {
                    $this->_logger->debug('Fetched parent ' . $taxon['taxonName']);
                    $parentId = $taxon['parent'];
                    
                    if($taxon['parent'] == 'Sp2000Hierarchy_!A') {
                        $this->_logger->debug('Fetched top level ' . $taxon['taxonName']);
                        $higherTaxon->kingdom = $taxon['taxonName'];
                        
                        // set the db
                        $taxonIdParts = explode('_', $taxon['taxonID']);
                        $dbId = Dictionary::get('dbs', $taxonIdParts[0]);
                        if($dbId) {
                            $higherTaxon->databaseId = $dbId;
                        }
                        
                        $taxa[] = $higherTaxon;
                        $taxon = false;
                    } else {
                        $higherTaxon->$taxon['rank'] = $taxon['taxonName'];
                    }
                }
            } while ($taxon && $taxon['parent'] != $taxon['taxonID']);
            unset($higherTaxon);
        }
        unset($stmt);
        $this->_logger->debug('End ' . __METHOD__);
        return $taxa;
    }
    
    private function _fetchTaxonParent($id)
    {
        if(!$id) {
            return false;
        }
        $stmt = $this->_dbh->prepare(
            'SELECT taxonID, rank, taxonName, parent ' .
            'FROM HierarchyCache WHERE taxonId = ?'
        );
        $stmt->execute(array($id));
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        unset($stmt);
        return $res;
    }
}