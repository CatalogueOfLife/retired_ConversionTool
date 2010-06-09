<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'model/Reference.php';

class Dc_Storer_Reference extends Dc_Storer_Abstract
    implements Dc_Storer_Interface
{
    public function clear()
    {
        $stmt = $this->_dbh->prepare('TRUNCATE `references`');
        $stmt->execute();
        unset($stmt);
    }
    
    public function store(Model $reference)
    {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `references` (author, year, title, source,
            database_id) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $reference->author,
            $reference->year,
            $reference->title,
            $reference->source,
            $reference->databaseId)
        );
        $reference->id = $this->_dbh->lastInsertId();
        return $reference;
    }
}