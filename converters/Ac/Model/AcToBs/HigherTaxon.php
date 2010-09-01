<?php
/**
 * Extended properties of HigherTaxon
 * 
 * Properties in this class cannot be extracted directly from the source
 * database but are derived and used to store data needed specifically for
 * the AcToBs storer
 * 
 * @author Nœria Torrescasana Aloy, Ruud Altenburg
 */
require_once 'model/AcToBs/HigherTaxon.php';

class Bs_Model_AcToBs_HigherTaxon extends HigherTaxon
{
    public $taxonomicRankId;
    public $nameElementIds = array();
    public $originalId = NULL;
}