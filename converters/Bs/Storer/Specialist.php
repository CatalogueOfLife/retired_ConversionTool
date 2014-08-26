<?php
require_once 'Interface.php';
require_once 'Abstract.php';

/**
 * Specialist storer
 *
 * @author Nuria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Specialist extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function store(Model $specialist)
    {
        if (empty($specialist->name)) {
            return $specialist;
        }
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
        try {
            $stmt->execute(array($specialist->name));
            $specialist->id = $this->_dbh->lastInsertId();
        } catch (PDOException $e) {
            $this->_handleException("Store error specialist", $e);
        }
        return $specialist;
    }

}