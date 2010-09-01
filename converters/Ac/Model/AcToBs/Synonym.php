<?php
require_once 'model/AcToBs/Taxon.php';

/**
 * Extended properties of Synonym
 * 
 * Properties in this class cannot be extracted directly from the source
 * database but are derived and used to store data needed specifically for
 * the AcToBs storer
 * 
 * @author Nœria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Model_AcToBs_Synonym extends Taxon
{
    public $taxonId;
    public $taxonomicRankId;
    public $scientificNameStatus;
    public $nameElementIds = array();
    public $references = array();
    public $hybridOrder = array();
}