<?php
require_once 'Interface.php';
require_once 'Abstract.php';

/**
 * Reference storer
 * 
 * @author Nœria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Reference extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function store(Model $reference)
    {
        $referenceId = $this->_recordExists('id', 'reference', array(
            'title' => $reference->title,
            'authors' => $reference->authors,
            'year' => $reference->year,
            'text' => $reference->text)
        );
        if ($referenceId) {
            $reference->id = $referenceId;
            return $reference;
        }
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `reference` (`title`, `authors`, `year`, `text`) '.
            ' VALUES (?, ?, ?, ?)'
        );
        $stmt->execute(array(
             $reference->title, 
             $reference->authors, 
             $reference->year, 
             $reference->text)
        );
        $reference->id = $this->_dbh->lastInsertId();
        return $reference;
    }
   
}