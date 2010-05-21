<?php
require_once 'model/Database.php';
require_once 'model/HigherTaxon.php';

class SpiceCacheLoader
{
    protected $_dbh;
    
    public function __construct(PDO $dbh)
    {
        $this->_dbh = $dbh;
    }
    
    public function load($what, $offset = 0)
    {
        switch($what) {
            case 'Database':
                return $this->_loadDatabase($offset); // Database obj
            case 'HigherTaxon':
                return $this->_loadHigherTaxon($offset); // Database obj
            /*case 'LowerTaxon':
                return $this->_loadLowerTaxon();*/
            default:
                throw Exception('No data object');
        }
    }
    
    public function count($what)
    {
        switch($what) {
            case 'Database':
                return $this->_countDatabases();
            case 'HigherTaxon':
                return $this->_countHigherTaxa();
            default:
                throw Exception('No data object');
        }
    }
    
    protected function _loadDatabase($offset)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT gsdID AS name, contactLink AS contactPerson, ' .
            'lastUpdateDate AS releaseDate, description AS abstract, ' .
            'gsdShortName AS shortName, gsdTitle AS fullName, homeLink AS url,' .
            'version FROM Type3Cache LIMIT :offset, 1'
        );
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject('Database');
    }
    
    protected function _countDatabases()
    {
        $stmt = $this->_dbh->prepare('SELECT COUNT(1) FROM Type3Cache');
        $stmt->execute();
        return $stmt->fetchColumn(0);
    }
    
    protected function _loadHigherTaxon($offset)
    {
        echo '<br/>' . __METHOD__ . '<br/>';
        $stmt = $this->_dbh->prepare(
            'SELECT taxonID, rank, taxonName, parent ' .
            'FROM HierarchyCache WHERE LENGTH(TRIM(rank)) > 0 LIMIT :offset, 1'
        );
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $taxon = $stmt->fetch(PDO::FETCH_ASSOC);
        var_dump($taxon);
        $higherTaxon = new HigherTaxon();
        $higherTaxon->$taxon['rank'] = $taxon['taxonName'];
        $parentId = $taxon['parent'];
        do {
            $parent = $this->_fetchTaxonParent($parentId);
            if($parent) {
                if($parent['parent'] == 'Sp2000Hierarchy_!A') {
                    echo "Reached top!!<br/>";
                    $higherTaxon->kingdom = $parent['taxonName'];
                    return $higherTaxon;
                }
                $parentId = $parent['parent'];
                $higherTaxon->$parent['rank'] = $parent['taxonName'];
                echo "Adding $parent[rank] = $parent[taxonName]<br/>";
            }
        } while ($parent);
        return false;
    }
    
    protected function _fetchTaxonParent($id)
    {
        if(!$id) {
            return false;
        }
        $stmt = $this->_dbh->prepare(
            'SELECT taxonID, rank, taxonName, parent ' .
            'FROM HierarchyCache WHERE taxonId = ?'
        );
        $stmt->execute(array($id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    protected function _countHigherTaxa()
    {
        $stmt = $this->_dbh->prepare(
            'SELECT COUNT(1) FROM HierarchyCache WHERE taxonType = ?'
        );
        $stmt->execute(array('HigherTaxon'));
        return $stmt->fetchColumn(0);
    }
}