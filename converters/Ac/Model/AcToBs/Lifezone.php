<?php
/**
 * Extended properties of Lifezone
 * 
 * Properties in this class cannot be extracted directly from the source
 * database but are derived and used to store data needed specifically for
 * the AcToBs storer
 * 
 * @author N�ria Torrescasana Aloy, Ruud Altenburg
 */
require_once 'model/AcToBs/Lifezone.php';

class Bs_Model_AcToBs_Lifezone extends Lifezone
{
    public $lifezoneId;
    public $taxonId;
}