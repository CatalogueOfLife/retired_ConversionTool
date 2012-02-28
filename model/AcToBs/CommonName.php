<?php
require_once 'Model.php';

class CommonName implements Model
{
    public $id;
    public $commonNameElement;
    public $language;
    public $country;
    public $transliteration;
    public $region;
}