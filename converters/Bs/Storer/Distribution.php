<?php
require_once 'Interface.php';
require_once 'Abstract.php';

class Bs_Storer_Distribution extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function store(Model $distribution)
    {
        $distributionId = $this->_recordExists('id', 'region_free_text', 
            array(
                'free_text' => $distribution->freeText,
            )
        );
        if ($distributionId) {
            $distribution->id = $distributionId;
            return $distribution;
        }
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `region_free_text` (`free_text`) VALUE (?)'
        );
        $stmt->execute(array($distribution->freeText));
        $distribution->id = $this->_dbh->lastInsertId();
        return $distribution;
    }
    
    // Functions to extract the distribution data info chunks that can be stored
    // in `distribution` and associated tables should be written here.
}