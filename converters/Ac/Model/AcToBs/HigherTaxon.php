<?php
require_once 'model/AcToBs/HigherTaxon.php';

class Bs_Model_AcToBs_HigherTaxon extends HigherTaxon
{
    public $taxonomicRankId;
    public $nameElementIds = array();
    public $originalId = NULL;
}