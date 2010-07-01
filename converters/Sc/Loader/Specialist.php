<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'model/ScToDc/Specialist.php';

class Sc_Loader_Specialist extends Sc_Loader_Abstract
    implements Sc_Loader_Interface
{
    public function count()
    {
        $stmt = $this->_dbh->prepare(
            'SELECT COUNT(DISTINCT scrutinyPerson) FROM StandardDataCache
            WHERE LENGTH(TRIM(scrutinyPerson)) > 0'
        );
        $stmt->execute();
        return $stmt->fetchColumn(0);
    }
    
    public function load($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT DISTINCT scrutinyPerson AS name
            FROM StandardDataCache
            WHERE LENGTH(TRIM(scrutinyPerson)) > 0'
        );
        // TODO: implement $offset and $limit
        //$stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_CLASS, 'Specialist');
        unset($stmt);
        return $res;
    }
}