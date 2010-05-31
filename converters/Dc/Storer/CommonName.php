<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'model/CommonName.php';

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
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `common_names` (name_code, common_name, language,
            country, reference_id, database_id, is_infraspecies,
            reference_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $commonName->nameCode,
            $commonName->name,
            $commonName->language,
            $commonName->country,
            $commonName->referenceId,
            $commonName->databaseId,
            $commonName->isInfraspecies,
            $commonName->referenceCode)
        );
        $commonName->id = $this->_dbh->lastInsertId();
        return $commonName;
    }
}