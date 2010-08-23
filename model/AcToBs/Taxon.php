<?php
require_once 'Model.php';

class Taxon implements Model
{
    public $id;
    public $taxonomicRank;
    public $name;
    public $genus;
    public $species;
    public $infraspecies;
    public $infraSpecificMarker;
    public $lsid;
    public $parentId;
    public $sourceDatabaseId;
    public $originalId; // name code
    public $authorString;
    public $scientificNameStatusId;
    public $uri;
    public $additionalData;
    public $scrutinyDate;
    public $specialistId;
}