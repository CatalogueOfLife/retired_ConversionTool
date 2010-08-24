<?php
require_once 'model/AcToBs/CommonName.php';

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
}