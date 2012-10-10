<?php
require_once 'model/AcToBs/Taxon.php';

/**
 * Extended properties of Taxon
 * 
 * Properties in this class cannot be extracted directly from the source
 * database but are derived and used to store data needed specifically for
 * the AcToBs storer
 * 
 * @author Nuria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Model_AcToBs_Taxon extends Taxon
{
    public $taxonomicRankId;
    public $specialistName;
    public $scientificNameStatus;
    public $scrutinyId = NULL;
    public $nameElementIds = array();
    public $commonNames = array();
    public $synonyms = array();
    public $references = array();
    public $distribution = array();
    public $lifezones = array();
    
}