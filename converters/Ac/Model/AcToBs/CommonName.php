<?php
require_once 'model/AcToBs/CommonName.php';

/**
 * Extended properties of CommonName
 *
 * Properties in this class cannot be extracted directly from the source
 * database but are derived and used to store data needed specifically for
 * the AcToBs storer
 *
 * @author Nuria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Model_AcToBs_CommonName extends CommonName
{
    public $commonNameElementId;
    public $taxonId;
	public $countryIso;
    public $languageIso;
    public $referenceId;
    public $referenceTitle;
    public $referenceAuthors;
    public $referenceYear;
    public $referenceText;
    public $regionFreeTextId;
}