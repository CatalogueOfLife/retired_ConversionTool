<?php
/**
 * Loader interface
 * 
 * Required methods for each loader object
 * 
 * @author Nœria Torrescasana Aloy
 */
interface Ac_Loader_Interface
{
    public function count();
    public function load($offset, $limit);
}