<?php
require_once 'model/Database.php';

class Dc_Store_Engine
{
    protected $_dbh;
    protected $_logger;
    
    public function __construct(PDO $dbh, Zend_Log $logger)
    {
        $this->_dbh = $dbh;
        $this->_logger = $logger;
    }
    
    public function store($object)
    {
        if($object instanceof Database) {
            return $this->_storeDatabase($object);
        } else if ($object instanceof Specialist) {
            return $this->_storeSpecialist($object);
        } else if ($object instanceof HigherTaxon) {
            return $this->_storeHigherTaxon($object);
        } else if ($object instanceof Taxon) {
            return $this->_storeTaxon($object);
        }
        throw new Exception('Unknown data object');
    }
    
    public function clear($what) {
        switch($what) {
            case 'Database':
                $query = 'TRUNCATE `databases`';
                break;
            case 'Specialist':
                $query = 'TRUNCATE `specialists`';
                break;
            case 'HigherTaxon':
                $query = 'TRUNCATE `families`';
                break;
            case 'Taxon':
                $query = 'TRUNCATE `scientific_names`';
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
        $db->id = $this->_dbh->lastInsertId();
        return $db;
    }
    
    protected function _storeSpecialist(Specialist $specialist)
    {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `specialists` (specialist_name) VALUES (?)'
        );
        $stmt->execute(array($specialist->name));
        $specialist->id = $this->_dbh->lastInsertId();
        return $specialist;
    }
    
    protected function _storeHigherTaxon(HigherTaxon $taxon) {
       /*$stmt = $this->_dbh->prepare(
           'SELECT COUNT(1) FROM `families` WHERE `kingdom` = ? AND ' .
           '`phylum` = ? AND `class` = ? AND `order` = ? AND ' .
           '`family` = ?'
       );
       $stmt->execute(array(
           $taxon->kingdom,
           $taxon->phylum ? $taxon->phylum : 'Not assigned',
           $taxon->class ? $taxon->class : 'Not assigned',
           $taxon->order ? $taxon->order : 'Not assigned',
           $taxon->family ? $taxon->family : 'Not assigned')
       );
       // TODO: make it nicer ...
       
       if($stmt->fetchColumn(0) > 0) {
           return false;
       }*/
       $stmt = $this->_dbh->prepare(
            'INSERT INTO `families` (`kingdom`, `phylum`, `class`, `order`, ' .
            '`superfamily`, `family`, `database_id`, ' .
            '`is_accepted_name`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
       );
       $stmt->execute(array(
           $taxon->kingdom,
           $taxon->phylum,
           $taxon->class,
           $taxon->order,
           $taxon->superfamily,
           $taxon->family,
           $taxon->databaseId,
           $taxon->isAcceptedName)
        );
        return true;
    }
    
    protected function _storeTaxon(Taxon $taxon) {
       $stmt = $this->_dbh->prepare(
            'INSERT INTO `scientific_names` SET name_code = ?, web_site = ?,
            genus = ?, species = ?, infraspecies = ?, infraspecies_marker = ?,
            author = ?, accepted_name_code = ?, comment = ?, scrutiny_date = ?,
            sp2000_status_id = ?, database_id = ?, specialist_id = ?,
            is_accepted_name = ?, family_id =
                (SELECT record_id FROM families WHERE family = ? LIMIT 1)'
       );
       $stmt->execute(array(
           $taxon->nameCode,
           $taxon->webSite,
           $taxon->genus,
           $taxon->species,
           $taxon->infraspecies,
           $taxon->infraspeciesMarker,
           $taxon->author,
           $taxon->acceptedNameCode,
           $taxon->comment,
           $taxon->scrutinyDate,
           $taxon->nameStatusId,
           $taxon->databaseId,
           $taxon->specialistId,
           $taxon->isAcceptedName,
           $taxon->familyName)
        );
        return true;
    }
}