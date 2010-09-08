<?php
require_once 'Interface.php';
require_once 'Abstract.php';

/**
 * Author storer
 * 
 * @author Nœria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Author extends Bs_Storer_Abstract
    implements Bs_Storer_Interface
{
    public function store(Model $author)
    {
        if ($author->authorString == '') {
            $author->id = NULL;
            return $author;
        }
        $authorId = $this->_recordExists('id', 'author_string', array(
            'string' => $author->authorString)
        );
        if ($authorId) {
            $author->id = $authorId;
            return $author;
        }
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `author_string` (`string`) VALUE (?)'
        );
        $stmt->execute(array($author->authorString));
        $author->id = $this->_dbh->lastInsertId();
        return $author;
    }
   
}