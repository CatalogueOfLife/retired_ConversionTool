<?php
require_once 'Model.php';

class Taxon implements Model
{
    public $id;
    public $nameCode;
    public $webSite;
    public $genus;
    public $species;
    public $infraspecies;
    public $infraspeciesMarker;
    public $author;
    public $acceptedNameCode;
    public $comment;
    public $scrutinyDate;
    public $nameStatusId;
    public $databaseId;
    public $specialistId;
    public $familyId;
    public $familyName; // if there's no id, set the name
    public $isAcceptedName;
    public $references; // array of Reference objs
}