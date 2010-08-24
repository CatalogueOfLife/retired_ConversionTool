<?php
require_once 'converters/Bs/Storer/Taxon.php';

class Bs_Storer_Synonym extends Bs_Storer_Taxon
    implements Bs_Storer_Interface
{
   public function store(Model $synonym)
    {
     	if ($synonym->infraSpecificMarker == '' && $synonym->infraspecies == '') {
    		$this->_setTaxonomicRankId($synonym);
     	} else {
            $this->_setInfraSpecificMarkerId($synonym);
    	}
    	$this->_getScientificNameStatusId($synonym);
    	$this->_setTaxon($synonym);
    	$this->_setScientificNameElements($synonym);
    	if ($synonym->specialistId != '') {
    		$this->_setTaxonScrutiny($synonym);
    	}
    	if (is_array($synonym->references) && count($synonym->references) > 0) {
            $this->_setTaxonReferences($synonym);
    	}
    	$this->_setTaxonLsid($synonym);
        $this->_setTaxonDetail($synonym);
        $this->_setTaxonDistribution($synonym);
        $this->_setTaxonCommonName($synonym);
        
$this->printObject($synonym); 
    	
//        $this->_setTaxonNameElement($synonym);  // Needs parent_id!
    }
    
    protected function _setTaxonSynonyms(Model $synonym)
    {
        $storer = new Bs_Storer_Synonym($this->_dbh, $this->_logger);
        foreach ($synonym->synonyms as $synonym) {
            $synonym->taxonId = $synonym->id;
            $storer->store($synonym);
        }
    }
}