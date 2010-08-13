<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'model/AcToBs/Uri.php';
require_once 'converters/Bs/Storer/Uri.php';

class Bs_Storer_Database extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function clear()
    {
        $tables = array('uri_to_source_database', 'source_database');
        foreach ($tables as $table) {
            $stmt = $this->_dbh->prepare('DELETE FROM `'.$table.'`');
            $stmt->execute();
        }
        unset($stmt);
    }
    
    public function store(Model $db)
    {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `source_database` (id, name, abbreviated_name, ' .
            'group_name_in_english, authors_and_editors, organisation, ' .
            'contact_person, abstract, version, release_date' .
            ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        
         $stmt->execute(array(
            $db->id,
            $db->name,
            $db->abbreviatedName,
            $db->groupNameInEnglish,
            $db->authorsAndEditors,
            $db->organisation,
            $db->contactPerson,
            $db->abstract,
            $db->version,
            $db->releaseDate)
        );
        
        if ($db->uri != "") {
            $uri = new Uri();
            $uri->resourceIdentifier = $db->uri;
            $storer = new Bs_Storer_Uri($this->_dbh, $this->_logger);
            $uri = $storer->store($uri);
            $db->uriId = $uri->id;
            unset($storer, $uri);
            
	        $stmt = $this->_dbh->prepare(
	            'INSERT INTO `uri_to_source_database` (uri_id, '.
	            'source_database_id) VALUES (?, ?)'
	        );
	        $stmt->execute(array(
	            $db->uriId,
	            $db->id)
	        );
        }
        
        return $db;
    }
}