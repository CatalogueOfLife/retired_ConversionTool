<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'model/AcToBs/Database.php';

class Ac_Loader_Database extends Ac_Loader_Abstract
    implements Ac_Loader_Interface
{
    public function count()
    {
        $stmt = $this->_dbh->prepare('SELECT COUNT(1) FROM `databases`');
        $stmt->execute();
        return $stmt->fetchColumn(0);
    }
    
    public function load($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT record_id as id, '.
            'database_name_displayed AS name, '.
            'database_name AS abbreviatedName, '.
            'contact_person AS contactPerson, '.
            'taxa AS groupNameInEnglish, '.
            'authors_editors AS authorsAndEditors, '.
            'release_date AS releaseDate, '.
            'abstract, ' .
            'web_site AS uri,' .
	        'version FROM `databases`'
        );
        
        // TODO: implement $offset and $limit
        //$stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_CLASS, 'Database');
        unset($stmt);
        return $res;
    }
}