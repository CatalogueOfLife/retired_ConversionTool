<?php
/**
 * Loader interface
 * 
 * Required methods for each storer object
 * 
 * @author N�ria Torrescasana Aloy, Ruud ALtenburg
 */
interface Bs_Storer_Interface
{
    public function store(Model $object);
}