<?php
require_once 'Interface.php';
require_once 'Abstract.php';

class Dc_Storer_Database extends Dc_Storer_Abstract
    implements Dc_Storer_Interface
{
    public function clear()
    {
        $stmt = $this->_dbh->prepare('TRUNCATE `databases`');
        $stmt->execute();
        unset($stmt);
    }
    
    public function store(Model $db)
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
}