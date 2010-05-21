<?php
require_once 'model/Database.php';

class DynamicChecklistStorer
{
    protected $_dbh;
    
    public function __construct(PDO $dbh)
    {
        $this->_dbh = $dbh;
    }
    
    public function store($object)
    {
        if($object instanceof Database) {
            return $this->_storeDatabase($object);
        } /*else if ($object instanceof Taxon) {
            return $this->_storeTaxon($object);
        }*/
        throw new Exception('Unknown data object');
    }
    
    public function clear($what) {
        switch($what) {
            case 'Database':
                $query = 'TRUNCATE `databases`';
                break;
            case 'HigherTaxon':
                $query = 'TRUNCATE `families`';
                break;
            default:
                throw new Exception('Unknown data object');
        }
        $stmt = $this->_dbh->prepare($query);
        $stmt->execute();
    }
    
    protected function _storeDatabase(Database $db)
    {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `databases` (database_name, database_full_name, ' .
            'database_name_displayed, web_site, organization, ' .
            'contact_person, abstract, version, release_date, ' .
            'authors_editors) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $db->shortName,
            $db->fullName,
            $db->name,
            $db->url,
            $db->organization,
            $db->contactPerson,
            $db->abstract,
            $db->version,
            $db->releaseDate,
            $db->authorsAndEditors)
        );
    }
}