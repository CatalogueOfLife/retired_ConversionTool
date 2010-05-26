<?php
require_once 'model/Database.php';
require_once 'model/Specialist.php';
require_once 'converters/Sc/Model/HigherTaxon.php';

class Sc_Load_Engine
{
    protected $_dbh;
    protected $_logger;
    
    public function __construct(PDO $dbh, Zend_Log $logger)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
    }
    
    public function load($what, $offset = 0, $limit = 100)
    {
        switch($what) {
            case 'Database':
                return $this->_loadDatabase($offset, $limit);
            case 'Specialist':
                return $this->_loadSpecialist($offset, $limit);
            case 'HigherTaxon':
                return $this->_loadHigherTaxon($offset, $limit);
            case 'Taxon':
                return $this->_loadTaxon($offset, $limit);
            default:
                throw new Exception('No data object');
        }
    }
    
    public function count($what)
    {
        switch($what) {
            case 'Database':
                return $this->_countDatabases();
            case 'Specialist':
                return $this->_countSpecialists();
            case 'HigherTaxon':
                return $this->_countHigherTaxa();
            case 'Taxon':
                return $this->_countTaxa();
            default:
                throw new Exception('No data object');
        }
    }
    
    protected function _loadDatabase($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT gsdID AS name, contactLink AS contactPerson, ' .
            'lastUpdateDate AS releaseDate, description AS abstract, ' .
            'gsdShortName AS shortName, gsdTitle AS fullName, homeLink AS url,' .
            'version FROM Type3Cache'
        );
        // TODO: implement $offset and $limit
        //$stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_CLASS, 'Database');
        unset($stmt);
        return $res;
    }
    
    protected function _countDatabases()
    {
        $stmt = $this->_dbh->prepare('SELECT COUNT(1) FROM Type3Cache');
        $stmt->execute();
        return $stmt->fetchColumn(0);
    }
    
    protected function _loadSpecialist($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT DISTINCT scrutinyPerson AS name
            FROM StandardDataCache
            WHERE LENGTH(TRIM(scrutinyPerson)) > 0'
        );
        // TODO: implement $offset and $limit
        //$stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_CLASS, 'Specialist');
        unset($stmt);
        return $res;
    }
    
    protected function _countSpecialists()
    {
        $stmt = $this->_dbh->prepare(
            'SELECT COUNT(DISTINCT scrutinyPerson) FROM StandardDataCache
            WHERE LENGTH(TRIM(scrutinyPerson)) > 0'
        );
        $stmt->execute();
        return $stmt->fetchColumn(0);
    }
    
    protected function _loadHigherTaxon($offset, $limit)
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
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        unset($stmt);
        return $res;
    }
    
  protected function _countHigherTaxa()
    {
        $stmt = $this->_dbh->prepare(
            'SELECT COUNT(1) FROM HierarchyCache WHERE LENGTH(TRIM(rank)) > 0'
        );
        $stmt->execute();
        $res = $stmt->fetchColumn(0);
        unset($stmt);
        return $res;
    }
    
  protected function _countTaxa()
    {
        $stmt = $this->_dbh->prepare(
            'SELECT COUNT(1) FROM Type1Cache WHERE specificEpithet != \'\''
        );
        $stmt->execute();
        $res = $stmt->fetchColumn(0);
        unset($stmt);
        return $res;
    }
    
    
    protected function _loadTaxon($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            "SELECT
            t1.taxoncode as nameCode,
            t2.datalink as webSite,
            t1.genus,
            t1.specificepithet as species,
            t1.infraspecificepithet as infraspecies,
            t1.infraspecificmarker as infraspeciesMarker,
            t1.authority as author,
            t1.taxoncode as acceptedNameCode,
            t2.`comment`,
            if (t2.scrutinyyear > 0,
               concat(
                   t2.scrutinyday, '-',
                   t2.scrutinymonth, '-',
                   t2.scrutinyyear
                   ), NULL)
               as scrutinyDate,
            case t1.status
                when 'accepted' then 1
                when 'provisional' then 4
                when 'synonym' then 5
                when 'ambiguous' then 2
                when 'misapplied' then 3
                end as nameStatusId,
            t1.source as databaseName,
            t2.scrutinyperson as specialist,
            t2.family,
            IF (t1.status = 'accepted' or t1.status = 'provisional', 1, 0)
                as isAcceptedName
            FROM `Type1Cache` t1
            left join `StandardDataCache` t2 on t1.TaxonCode = t2.taxoncode
            WHERE t1.specificEpithet != '' LIMIT :offset, :limit"
        );
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $taxa = array();
        
        while($taxon = $stmt->fetchObject('Sc_Model_Taxon')) {
            $this->_logger->debug('Processing taxon ' . $taxon['taxonID']);
            $taxon->databaseId = Dictionary::get('dbs', $taxon->databaseName);
        }
        unset($stmt);
        return $taxa;
    }
}