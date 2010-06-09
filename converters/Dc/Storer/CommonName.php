<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'model/CommonName.php';
require_once 'model/Reference.php';

class Dc_Storer_CommonName extends Dc_Storer_Abstract
    implements Dc_Storer_Interface
{
    public function clear()
    {
        $stmt = $this->_dbh->prepare('TRUNCATE `common_names`');
        $stmt->execute();
        unset($stmt);
    }
    
    public function store(Model $commonName)
    {	
    	if ($commonName->referenceId === null) {
	    	if ($commonName->reference instanceof Reference) {
	    		require_once 'converters/Dc/Storer/Reference.php';
	    		$storer = new Dc_Storer_Reference($this->_dbh, $this->_logger);
	    		$ref = $storer->store($commonName->reference);
	    		$commonName->referenceId = $ref->id;
	    		unset($storer);
	    	} else {
	    		$commonName->referenceId = 0;
	    	}
    	}
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `common_names` (name_code, common_name, language,
            country, reference_id, database_id)
            VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $commonName->acceptedNameCode,
            $commonName->name,
            $commonName->language,
            $commonName->country,
            $commonName->referenceId,
            $commonName->databaseId)
        );
        $commonName->id = $this->_dbh->lastInsertId();
        return $commonName;
    }
}