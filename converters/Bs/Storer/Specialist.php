<?php
require_once 'Interface.php';
require_once 'Abstract.php';

class Dc_Storer_Specialist extends Dc_Storer_Abstract
    implements Dc_Storer_Interface
{
    public function clear()
    {
        $stmt = $this->_dbh->prepare('TRUNCATE `specialists`');
        $stmt->execute();
        unset($stmt);
    }
    
    public function store(Model $specialist)
    {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `specialists` (specialist_name) VALUES (?)'
        );
        $stmt->execute(array($specialist->name));
        $specialist->id = $this->_dbh->lastInsertId();
        return $specialist;
    }
}