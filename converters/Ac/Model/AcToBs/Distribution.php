<?php
/**
 * Extended properties of Distribution
 * 
 * Properties in this class cannot be extracted directly from the source
 * database but are derived and used to store data needed specifically for
 * the AcToBs storer
 * 
 * @author Nœria Torrescasana Aloy, Ruud Altenburg
 */
require_once 'model/AcToBs/Distribution.php';

class Bs_Model_AcToBs_Distribution extends Distribution
{
    public $taxonId;
    public $regionId;
}