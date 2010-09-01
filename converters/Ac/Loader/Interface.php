<?php
/**
 * Loader interface
 * 
 * Required methods for each loader object
 * 
 * @author N�ria Torrescasana Aloy
 */
interface Ac_Loader_Interface
{
    public function count();
    public function load($offset, $limit);
}