<?php
require_once 'model/ScToDc/CommonName.php';

class Sc_Model_ScToDc_CommonName extends CommonName
{   
    public $refYear;
    public $refAuthor;
    public $refTitle;
    public $refDetails;
    
	public function hasReference() {
    	return ($this->refAuthor || $this->refTitle || $this->refYear || $this->refDetails); 
    }
}