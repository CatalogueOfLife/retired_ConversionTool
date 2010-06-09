<?php
require_once 'model/CommonName.php';

class Sc_Model_CommonName extends CommonName
{   
    public $refYear;
    public $refAuthor;
    public $refTitle;
    public $refDetails;
    
	public function hasReference() {
    	return ($this->refAuthor || $this->refTitle || $this->refYear || $this->refDetails); 
    }
}