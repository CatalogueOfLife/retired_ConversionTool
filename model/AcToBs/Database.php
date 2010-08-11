<?php
require_once 'Model.php';

class Database implements Model
{
    public $id;
    public $name;
    public $fullName;
    public $shortName;
    public $url;
    public $organization;
    public $contactPerson;
    public $abstract;
    public $version;
    public $releaseDate;
    public $authorsAndEditors;
}