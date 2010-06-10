<?php
require_once 'Interface.php';
require_once 'Abstract.php';

class Dc_Storer_Distribution extends Dc_Storer_Abstract
    implements Dc_Storer_Interface
{
    public function clear()
    {
        $stmt = $this->_dbh->prepare('TRUNCATE `distribution`');
        $stmt->execute();
        unset($stmt);
    }
    
    public function store(Model $distribution)
    {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `distribution` (name_code, distribution) VALUES (?, ?)'
        );
        $stmt->execute(
            array($distribution->nameCode, 
            $distribution->distribution)
        );
        $distribution->id = $this->_dbh->lastInsertId();
        return $distribution;
    }
}