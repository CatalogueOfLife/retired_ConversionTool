<?php
require_once 'Interface.php';
require_once 'Abstract.php';

/**
 * Lifezone storer
 * 
 * @author N�ria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Lifezone extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function store(Model $lifezone)
    {
        if (empty($lifezone->lifezone)) {
            return $lifezone;
        }
        $this->_getLifezoneId($lifezone);
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `lifezone_to_taxon_detail` 
            (`lifezone_id`, `taxon_detail_id`) VALUES (?, ?)'
        );
        $stmt->execute(array(
            $lifezone->lifezoneId,
            $lifezone->taxonId)
        );
        return $lifezone;
    }
   
    public function _getLifezoneId(Model $lifezone) 
    {
        $cleanedLifezone = $this->_cleanLifezone($lifezone->lifezone);
        if ($lifezone->lifezoneId = Dictionary::get('lifezone', $cleanedLifezone)) {
            return $lifezone;
        }
        $lifezone->lifezoneId = $this->_recordExists(
            'id', 'lifezone', array('lifezone' => $cleanedLifezone)
        );
        if ($lifezone->lifezoneId) {
            Dictionary::add('lifezone', $cleanedLifezone, $lifezone->lifezoneId);
            return $lifezone;
        }
        return false;
    }
    
    private function _cleanLifezone($str)
    {
        $str = trim($str);
        $replacements = array(
            'fresh' => 'freshwater',
            'terrestial' => 'terrestrial'
        );
        if (array_key_exists($str, $replacements)) {
            return $replacements[$str];
        }
        return $str;
    }
}