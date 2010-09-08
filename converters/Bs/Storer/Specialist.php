<?php
require_once 'Interface.php';
require_once 'Abstract.php';

/**
 * Specialist storer
 * 
 * @author Nœria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Specialist extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function store(Model $specialist)
    {
        $specialistId = $this->_recordExists('id', 'specialist', array(
            'name' => $specialist->name)
        );
        if ($specialistId) {
            $specialist->id = $specialistId;
            return $specialist;
        }
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `specialist` (`name`) VALUE (?)'
        );
        $stmt->execute(array($specialist->name));
        $specialist->id = $this->_dbh->lastInsertId();
        return $specialist;
    }
   
}