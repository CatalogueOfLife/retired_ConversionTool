<?php
require_once 'Interface.php';
require_once 'Abstract.php';

/**
 * Lifezone storer
 *
 * @author Nuria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Lifezone extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function store(Model $lifezone)
    {
        if (empty($lifezone->lifezone)) {
            return $lifezone;
        }
        $pts = explode(',', $lifezone->lifezone);
        foreach ($pts as $p) {
            $lifezone->lifezone = $p;
            $this->_getLifezoneId($lifezone);
            if (!empty($lifezone->lifezoneId)) {
                $stmt = $this->_dbh->prepare(
                    'INSERT INTO `lifezone_to_taxon_detail`
                    (`lifezone_id`, `taxon_detail_id`) VALUES (?, ?)'
                );
                $stmt->execute(array(
                    $lifezone->lifezoneId,
                    $lifezone->taxonId)
                );
            }
        }
        return $lifezone;
    }

    public function _getLifezoneId(Model $lifezone)
    {
        $this->_cleanLifezone($lifezone);
        if ($lifezone->lifezoneId = Dictionary::get('lifezone', $lifezone->lifezone)) {
            return $lifezone;
        }
        $lifezone->lifezoneId = $this->_recordExists(
            'id', 'lifezone', array('lifezone' => $lifezone->lifezone)
        );
        if ($lifezone->lifezoneId) {
            Dictionary::add('lifezone', $lifezone->lifezone, $lifezone->lifezoneId);
            return $lifezone;
        }
        // Fallback for undetermined lifezones; these will be skipped in store process
        $lifezone->lifezoneId = null;
        return false;
    }

    private function _cleanLifezone(Model $lifezone)
    {
        $lifezone->lifezone = trim($lifezone->lifezone);
        $replacements = array(
            'fresh' => 'freshwater',
            'terrestial' => 'terrestrial'
        );
        if (array_key_exists($lifezone->lifezone, $replacements)) {
            $lifezone->lifezone = $replacements[$lifezone->lifezone];
        }
        return $lifezone;
    }
}