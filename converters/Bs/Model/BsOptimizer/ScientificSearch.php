<?php
require_once 'Model.php';

class Taxon implements Model
{
    public $id;
    public $kingdom;
    public $phylum;
    public $class;
    public $order;
    public $superfamily;
    public $family;
    public $genus;
    public $subgenus;
    public $species;
    public $infraspecies;
    public $author;
    public $status;
    public $acceptedSpeciesId;
    public $acceptedSpeciesName;
    public $acceptedSpeciesAuthor;
    public $sourceDatabaseId;
    public $sourceDatabaseName;
}