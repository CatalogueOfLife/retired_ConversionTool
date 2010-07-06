<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'model/ScToDc/Distribution.php';

class Sc_Loader_Distribution extends Sc_Loader_Abstract
    implements Sc_Loader_Interface
{
    public function count()
    {
        $stmt = $this->_dbh->prepare(
            'SELECT COUNT(1) 
            FROM PlaceNames t1, StandardDataCache t2 
            WHERE t1.avcNameCode = t2.taxoncode'
        );
        $stmt->execute();
        return $stmt->fetchColumn(0);
    }
    
    public function load($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT t1.placeName AS distribution, t2.taxonid AS nameCode
            FROM PlaceNames t1, StandardDataCache t2 
            WHERE t1.avcNameCode = t2.taxoncode
            LIMIT :offset, :limit'
        );
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_CLASS, 'Distribution');
        unset($stmt);
        return $res;
    }
}