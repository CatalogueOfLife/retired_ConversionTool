<?php
require_once 'Abstract.php';

class Dc_Storer_HigherTaxon extends Dc_Storer_Abstract
{
    public function clear()
    {
        $stmt = $this->_dbh->prepare('TRUNCATE `families`');
        $stmt->execute();
        unset($stmt);
    }
    
    public function store(Model $taxon)
    {
       /*$stmt = $this->_dbh->prepare(
           'SELECT COUNT(1) FROM `families` WHERE `kingdom` = ? AND ' .
           '`phylum` = ? AND `class` = ? AND `order` = ? AND ' .
           '`family` = ?'
       );
       $stmt->execute(array(
           $taxon->kingdom,
           $taxon->phylum ? $taxon->phylum : 'Not assigned',
           $taxon->class ? $taxon->class : 'Not assigned',
           $taxon->order ? $taxon->order : 'Not assigned',
           $taxon->family ? $taxon->family : 'Not assigned')
       );
       // TODO: make it nicer ...
       
       if($stmt->fetchColumn(0) > 0) {
           return false;
       }*/
       $stmt = $this->_dbh->prepare(
            'INSERT INTO `families` (`kingdom`, `phylum`, `class`, `order`, ' .
            '`superfamily`, `family`, `database_id`, ' .
            '`is_accepted_name`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
       );
       $stmt->execute(array(
           $taxon->kingdom,
           $taxon->phylum,
           $taxon->class,
           $taxon->order,
           $taxon->superfamily,
           $taxon->family,
           $taxon->databaseId,
           $taxon->isAcceptedName)
        );
        return true;
    }
}