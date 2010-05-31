<?php
interface Sc_Loader_Interface
{
    public function count();
    public function load($offset, $limit);
}