<?php
require_once 'Abstract.php';
require_once 'model/Database.php';

class Sc_Loader_Database extends Sc_Loader_Abstract
{
    public function count()
    {
        $stmt = $this->_dbh->prepare('SELECT COUNT(1) FROM Type3Cache');
        $stmt->execute();
        return $stmt->fetchColumn(0);
    }
    
    public function load ($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            'SELECT gsdID AS name, contactLink AS contactPerson, ' .
            'lastUpdateDate AS releaseDate, description AS abstract, ' .
            'gsdShortName AS shortName, gsdTitle AS fullName, homeLink AS url,' .
            'version FROM Type3Cache'
        );
        // TODO: implement $offset and $limit
        //$stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_CLASS, 'Database');
        unset($stmt);
        return $res;
    }
}