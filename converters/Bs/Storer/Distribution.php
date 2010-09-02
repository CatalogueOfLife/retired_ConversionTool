<?php
require_once 'Interface.php';
require_once 'Abstract.php';

class Bs_Storer_Distribution extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function store(Model $distribution)
    {
        $id = $this->_recordExists('id', 'region_free_text', 
            array(
                'free_text' => $distribution->freeText
            )
        );
        if ($id) {
            $distribution->id = $id;
        } else {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `region_free_text` (`free_text`) VALUE (?)'
            );
            $stmt->execute(array($distribution->freeText));
            $distribution->id = $this->_dbh->lastInsertId();
        }
        $this->_setRegionFreeText($distribution);
        if ($this->_isExistingRegion($distribution)) {
            $this->_setDistribution($distribution);
        }
        return $distribution;
    }
    
    // Functions to extract the distribution data info chunks that can be stored
    // in `distribution` and associated tables should be written here. Currently
    // the distribution is set only if region_free_text exactly matches an entry
    // in the region_standard table
    
    private function _setRegionFreeText(Model $distribution)
    {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `distribution_free_text` (`taxon_detail_id`, '.
            '`region_free_text_id`) VALUES (?, ?)'
        );
        $stmt->execute(array($distribution->taxonId, $distribution->id));
        return $distribution;
    }
    
    private function _isExistingRegion(Model $distribution)
    {
        $regionId = $this->_recordExists('region_standard_id', 'region', 
            array('name' => $distribution->freeText)
        );
        if ($regionId) {
            $distribution->regionId = $regionId;
            return $distribution;
        }
        return false;
    }
    
    private function _setDistribution(Model $distribution) {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `distribution` (`taxon_detail_id`, '.
            '`region_id`) VALUES (?, ?)'
        );
        $stmt->execute(array($distribution->taxonId, $distribution->regionId));
        return $distribution;
    }
}