<?php
interface Dc_Storer_Interface
{
    public function clear();
    public function store(Model $object);
}