<?php
/**
 * Loader interface
 * 
 * Required methods for each storer object
 * 
 * @author Nœria Torrescasana Aloy, Ruud ALtenburg
 */
interface Bs_Storer_Interface
{
    public function store(Model $object);
}