<?php
require_once 'Interface.php';
require_once 'Abstract.php';

/**
 * Distribution storer
 * 
 * @author N�ria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Distribution extends Bs_Storer_Abstract implements Bs_Storer_Interface
{

    public function store (Model $distribution)
    {
        if (empty($distribution->freeText)) {
            return $distribution;
        }
        $id = $this->_recordExists('id', 'region_free_text', 
            array(
                'free_text' => $distribution->freeText
            ));
        if ($id) {
            $distribution->id = $id;
        }
        else {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `region_free_text` (`free_text`) VALUE (?)');
            $stmt->execute(array(
                $distribution->freeText
            ));
            $distribution->id = $this->_dbh->lastInsertId();
        }
        $this->_getDistributionStatusId($distribution);
        
        $this->_setDistributionFreeText($distribution);
        if ($this->_isExistingRegion($distribution)) {
            $this->_setDistribution($distribution);
        }
        return $distribution;
    }

    // Functions to extract the distribution data info chunks that can be stored
    // in `distribution` and associated tables should be written here. Currently
    // the distribution is set only if region_free_text exactly matches an entry
    // in the region_standard table
    

    private function _getDistributionStatusId (Model $distribution)
    {
        if (empty($distribution->status)) {
            return $distribution;
        }
        $status = strtolower($distribution->status);
        if ($status == 'introduced' || $status == 'exotic') {
            $status = 'alien';
        }
        if ($statusId = Dictionary::get('distribution_statuses', $status)) {
            $distribution->statusId = $statusId;
            return $distribution;
        }
        $statusId = $this->_recordExists('id', 'distribution_status', array(
            'status' => $status
        ));
        if ($statusId) {
            Dictionary::add('distribution_statuses', $status, $statusId);
            $distribution->statusId = $statusId;
            return $distribution;
        }
        return null;
    }

    private function _setDistributionFreeText (Model $distribution)
    {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `distribution_free_text` (`taxon_detail_id`, ' . 
            '`region_free_text_id`, `distribution_status_id`) VALUES (?, ?, ?)');
        $stmt->execute(array(
            $distribution->taxonId, 
            $distribution->id, 
            $distribution->statusId
        ));
        return $distribution;
    }

    private function _isExistingRegion (Model $distribution)
    {
        if ($id = Dictionary::get('regions', $distribution->freeText)) {
            $distribution->regionId = $id;
            return $distribution;
        }
        $id = $this->_recordExists('id', 'region', array(
            'name' => $distribution->freeText
        ));
        if ($id) {
            Dictionary::add('regions', $distribution->freeText, $id);
            $distribution->regionId = $id;
            return $distribution;
        }
        return false;
    }

    private function _setDistribution (Model $distribution)
    {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `distribution` (`taxon_detail_id`, ' . 
            '`region_id`, `distribution_status_id`) VALUES (?, ?, ?)');
        $stmt->execute(array(
            $distribution->taxonId, 
            $distribution->regionId, 
            $distribution->statusId
        ));
        return $distribution;
    }
}