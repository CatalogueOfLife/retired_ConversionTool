<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'converters/Sc/Model/CommonName.php';

class Sc_Loader_CommonName extends Sc_Loader_Abstract
    implements Sc_Loader_Interface
{
    public function count()
    {
        $stmt = $this->_dbh->prepare('SELECT COUNT(1) FROM CommonNameWithRefs');
        $stmt->execute();
        return $stmt->fetchColumn(0);
    }
    
    public function load ($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT c.commonNamenumber AS commonNameCode,
                    c.avcNameCode AS nameCode,
                    c.vernName AS name,
                    TRIM(TRAILING "#" FROM c.placeNames) AS country
            FROM CommonNameWithRefs c
            LIMIT :offset, :limit'
        );
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $cn = $stmt->fetchAll(PDO::FETCH_CLASS, 'Sc_Model_CommonName');
        unset($stmt);
        return $cn;
    }
}