<?php
require_once 'Model.php';

class Reference implements Model
{
    public $id;
    public $author;
    public $year;
    public $title;
    public $source;
    
    public function getHash()
    {
    	return md5($this->author . $this->year . $this->title . $this->source);
    }
}