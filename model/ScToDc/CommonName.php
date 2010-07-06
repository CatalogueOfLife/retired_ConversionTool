<?php
require_once 'Model.php';

class CommonName implements Model
{
    public $id;
    public $nameCode;
    public $acceptedNameCode;
    public $name;
    public $language;
    public $country;
    public $reference; // Reference
    public $referenceId;
    public $databaseId;
}