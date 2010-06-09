<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'converters/Sc/Model/CommonName.php';
require_once 'model/Reference.php';

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
                    c.language,
                    TRIM(TRAILING "#" FROM c.placeNames) AS country,
                    r.author AS refAuthor,
                    r.title AS refTitle,
                    r.year AS refYear,
                    r.details AS refSource
            FROM CommonNameWithRefs c
            LEFT JOIN Reference r 
            	ON c.commonNamenumber = r.commonNameWithRefsCode
            GROUP BY c.commonNamenumber
            LIMIT :offset, :limit'
        );
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $commonNames = array();
        while($cn = $stmt->fetchObject('Sc_Model_CommonName')) {
            $cn->databaseId = Dictionary::get(
            	'dbs', $this->getDatabaseNameFromNameCode($cn->nameCode)
            );
            if ($cn->author) {
            	$ref = new Reference();
            	$ref->author  = $cn->refAuthor;
            	$ref->title   = $cn->refTitle;
            	$ref->year    = $cn->refYear;
            	$ref->source = $cn->refSource;
            }
            $cn->reference = $ref;
            $commonNames[] = $cn;
        }
        unset($stmt);
        return $commonNames;
    }
}