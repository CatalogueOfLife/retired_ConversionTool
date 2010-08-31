<?php
class Dictionary
{
    protected static $concepts = array();
    
    public static function set($name, array $values) {
        self::$concepts[$name] = $values;
    }
    
    public static function add($name, $key, $value) {
        self::$concepts[$name][$key] = $value;
    }
    
    public static function get($name, $key) {
        return isset(self::$concepts[$name][$key]) ?
            self::$concepts[$name][$key] : false;
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