<?php
require_once 'Interface.php';
require_once 'Abstract.php';
require_once 'model/ScToDc/Reference.php';

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
    	//$this->_logger->debug('Trying to get reference with hash ' . $reference->getHash());
    	$refId = Dictionary::get('refs', $reference->getHash());
    	if($refId) {
    		//$this->_logger->debug('Got it! id is ' . $refId);
    		$reference->id = $refId;
    		return $reference;
    	}
    	//$this->_logger->debug('Not there, inserting');
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `references` 
            (author, year, title, source) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $reference->author,
            $reference->year,
            $reference->title,
            $reference->source)
        );
        $reference->id = $this->_dbh->lastInsertId();
    	Dictionary::add('refs', $reference->getHash(), $reference->id);
    	//$this->_logger->debug('Added reference ' . $reference->id . ' with hash ' . $reference->getHash());
        return $reference;
    }
}