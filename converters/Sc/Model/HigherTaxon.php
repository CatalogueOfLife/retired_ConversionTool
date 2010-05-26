<?php
require_once 'model/HigherTaxon.php';

class Sc_Model_HigherTaxon extends HigherTaxon
{
    public $isAcceptedName = 1;
    
    public static $ranks = array(
        'family',
        'superfamily',
        'order',
        'class',
        'phylum',
        'kingdom'
    );
    
    protected static $fieldMap = array(
        'familia' => 'family',
        'superfamil' => 'superfamily'
    );
    
    public function __set($name, $value) {
        $name = strtolower($name);
        if(!in_array($name, self::$ranks)) {
            if(!isset(self::$fieldMap[$name])) {
                //throw new Exception('Property not allowed: ' . $name);
                return;
            }
            $name = self::$fieldMap[$name];
        }
        $this->$name = $value;
    }
}