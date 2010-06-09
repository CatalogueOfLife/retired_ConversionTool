<?php
require_once 'Model.php';

class Reference implements Model
{
    public $id;
    public $author;
    public $year;
    public $title;
    public $source;
    public $databaseId;
}