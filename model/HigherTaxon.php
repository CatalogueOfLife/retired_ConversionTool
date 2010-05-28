<?php
require_once 'Model.php';

class HigherTaxon implements Model
{
    public $id;
    public $kingdom;
    public $phylum;
    public $class;
    public $order;
    public $superfamily;
    public $family;
    public $familyCommonName;
    public $databaseId;/*Database*/
    public $isAcceptedName;
}