<?php
/**
 * Dictionary
 * 
 * Associative array to store previously inserted elements. This alleviates the
 * need for MySQL lookup queries but note that a large array gobbles up a lot
 * of memory! Use only for small data sets.
 * 
 * @author N�ria Torrescasana Aloy
 */
class Dictionary
{
    protected static $concepts = array();
    
    /**
     * Set method
     */
    public static function set($name, array $values) {
        self::$concepts[$name] = $values;
    }
    
    /**
     * Add element method
     */
    public static function add($name, $key, $value) {
        self::$concepts[$name][$key] = $value;
    }
    
    public static function get($name, $key) {
        return isset(self::$concepts[$name][$key]) ?
            self::$concepts[$name][$key] : false;
    }
    
    public static function exists($name, $value) {
        return in_array($value, self::$concepts[$name]);
    }
    
    public static function dump($name) {
        var_dump(
            isset(self::$concepts[$name]) ? self::$concepts[$name] : false
        );
    }

    public static function dumpAll() {
        var_dump(self::$concepts);
    }
}