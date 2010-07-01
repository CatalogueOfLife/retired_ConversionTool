<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'model/CommonName.php';
require_once 'model/Reference.php';

class Bs_Storer_CommonName extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function clear()
    {
        $stmt = $this->_dbh->prepare('TRUNCATE `common_name`');
        $stmt->execute();
        $stmt = $this->_dbh->prepare('TRUNCATE `common_name_element`');
        $stmt->execute();
        unset($stmt);
    }
    
    public function store(Model $commonName)
    {	
        if ($commonName->referenceId === null) {
	    	if ($commonName->reference instanceof Reference) {
	    		require_once 'converters/Bs/Storer/Reference.php';
	    		$storer = new Bs_Storer_Reference($this->_dbh, $this->_logger);
	    		$ref = $storer->store($commonName->reference);
	    		$commonName->referenceId = $ref->id;
	    		unset($storer);
	    	} else {
	    		$commonName->referenceId = 0;
	    	}
    	}
    	
        $stmt = $this->_dbh->prepare(
            'SELECT id FROM `common_name_element` WHERE name = ?'
        );
        $stmt->execute(array(
            $commonName->name)
        );
        $common_name_element_id = $stmt->fetchColumn(0);
        
        $stmt = $this->_dbh->prepare(
            'SELECT iso FROM `language` WHERE name = ?'
        );
        $stmt->execute(array(
            $commonName->language)
        );
        $language_iso = $stmt->fetchColumn(0);
        
        $stmt = $this->_dbh->prepare(
            'SELECT iso FROM `country` WHERE name = ?'
        );
        $stmt->execute(array(
            $commonName->country)
        );
        $country_iso = $stmt->fetchColumn(0);
        
        if(!$common_name_element_id)
        {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `common_name_element` (name)
                VALUES (?)'
            );
            $stmt->execute(array(
                $commonName->name)
            );
            $common_name_element_id = $this->_dbh->lastInsertId();
        }
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `common_name` (taxon_id, common_name_element_id,
             language_iso, country_iso)
            VALUES (?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $commonName->acceptedNameCode,
            $common_name_element_id,
            $language_iso,
            $country_iso)
        );
        $commonName->id = $this->_dbh->lastInsertId();
        
        return $commonName;
    }
}