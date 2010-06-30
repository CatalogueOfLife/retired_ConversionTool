<?php
require_once 'Interface.php';
require_once 'Abstract.php';

class Bs_Storer_Database extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function clear()
    {
      $stmt = $this->_dbh->prepare('TRUNCATE `source_database`');
        $stmt->execute();
      $stmt = $this->_dbh->prepare('DELETE FROM `uri` WHERE `id` IN ' .
        '(SELECT `uri_id` FROM `uri_to_source_database`)');
        $stmt->execute();
      $stmt = $this->_dbh->prepare('TRUNCATE `uri_to_source_database`');
        $stmt->execute();
        unset($stmt);
    }
    
    public function store(Model $db)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT id FROM `source_database` WHERE name = ?'
        );
        $stmt->execute(array(
            $db->name)
        );
        $source_database_id = $stmt->fetchColumn(0);
        
        if(!$source_database_id)
        {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `source_database` (name, abbreviated_name, ' .
                'group_name_in_english, authors_and_editors, organisation, ' .
                'contact_person, version, release_date, abstract' .
                ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute(array(
                $db->name,
                $db->shortName,
                $db->fullName,
                $db->authorsAndEditors,
                $db->organization,
                $db->contactPerson,
                $db->version,
                $db->releaseDate,
                $db->abstract)
            );
            $db->id = $this->_dbh->lastInsertId();
        } else {
            $db->id = $source_database_id;
        }
        
        if(!$db->url)
        {
            return $db;
        }
        
        $stmt = $this->_dbh->prepare(
            'SELECT id FROM `uri` WHERE resource_identifier = ?'
        );
        $stmt->execute(array(
            $db->url)
        );
        $uri_id = $stmt->fetchColumn(0);
        
        if(!$uri_id)
        {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `uri` (resource_identifier, uri_scheme_id' .
                ') VALUES (?, ?)'
            );
            $stmt->execute(array(
                $db->url,
                $this->_getUriSchemeIdFromUri($db->url))
            );
            $uri_id = $this->_dbh->lastInsertId();
        }
        
        $stmt = $this->_dbh->prepare(
            'SELECT uri_id FROM `uri_to_source_database` WHERE uri_id = ? ' .
            'AND source_database_id = ?'
        );
        $stmt->execute(array(
            $uri_id,
            $db->id)
        );
        $uri_to_source_database = $stmt->fetchColumn(0);
        
        if(!$uri_to_source_database)
        {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `uri_to_source_database` (uri_id, ' .
                'source_database_id' .
                ') VALUES (?, ?)'
            );
            $stmt->execute(array(
                $uri_id,
                $db->id)
            );
        }
        
        return $db;
    }
    
    private function _getUriSchemeIdFromUri($uri)
    {
        $stmt = $this->_dbh->prepare(
                'SELECT id FROM `uri_scheme` WHERE scheme = SUBSTRING_INDEX( ? , "://", 1 )'
        );
        $stmt->execute(array(
            $uri)
        );
        return $stmt->fetchColumn(0);
    }
}