<?php
require_once 'Model.php';

class Database implements Model
{
    public $id;
    public $name;
    public $abbreviatedName;
    public $groupNameInEnglish;
    public $authorsAndEditors;
    public $organisation;
    public $contactPerson;
    public $abstract;
    public $version;
    public $releaseDate;
    public $uri;
    public $taxonomicCoverage;
}