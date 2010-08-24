<?php
require_once 'model/AcToBs/Taxon.php';

class Bs_Model_AcToBs_Synonym extends Taxon
{
    public $taxonomicRankId;
    public $scientificNameStatus;
    public $nameElementIds = array();
    public $references = array();
    public $hybridOrder = array();
}