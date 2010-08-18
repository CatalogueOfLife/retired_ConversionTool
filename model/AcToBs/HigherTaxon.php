<?php
require_once 'Model.php';

class HigherTaxon implements Model
{
    public $id;
    public $taxonomicRank;
    public $name;
    public $lsid;
    public $parentId;
    public $databaseId;
}