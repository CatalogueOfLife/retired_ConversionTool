<?php
require_once 'model/AcToBs/Taxon.php';

class Bs_Model_AcToBs_Taxon extends Taxon
{
    public $taxonomicRankId;
    public $specialistName;
    public $scientificNameStatus;
    public $scrutinyId = NULL;
    public $nameElementIds = array();
    public $synonyms = array();
    public $references = array();
    public $distribution = array();
    
}