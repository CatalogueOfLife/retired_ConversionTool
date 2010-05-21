<?php
require_once 'model/HigherTaxon.php';

class SpiceCache_HigherTaxon extends HigherTaxon
{
    public static $ranks = array(
        'family',
        'superfamily',
        'order',
        'class',
        'phylum',
        'kingdom'
    );
    
    public function __set($name, $value) {
        $fieldMap = array(
            ''
        );
        if(!isset($fieldMap[$name])) {
            throw new Exception('Property not allowed');
        }
    }
}