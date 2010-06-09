<?php
require_once 'Interface.php';
require_once 'Abstract.php';

class Dc_Storer_Taxon extends Dc_Storer_Abstract implements Dc_Storer_Interface
{
    public function clear()
    {
        $stmt = $this->_dbh->prepare('TRUNCATE `scientific_names`');
        $stmt->execute();
        unset($stmt);
    }
    
    public function store(Model $taxon)
    {
    	// store references (if any)
        if (is_array($taxon->references)) {
      	    require_once 'converters/Dc/Storer/Reference.php';
            $storer = new Dc_Storer_Reference($this->_dbh, $this->_logger);
            foreach ($taxon->references as &$ref) {
            	$ref = $storer->store($ref);
            }
            unset($storer);
        }
        $stmt = $this->_dbh->prepare(
             'INSERT INTO `scientific_names` SET name_code = ?, web_site = ?,
             genus = ?, species = ?, infraspecies = ?, infraspecies_marker = ?,
             author = ?, accepted_name_code = ?, comment = ?, scrutiny_date = ?,
             sp2000_status_id = ?, database_id = ?, specialist_id = ?,
             is_accepted_name = ?, family_id =
                 (SELECT record_id FROM families WHERE family = ? LIMIT 1)'
        );
        $res = $stmt->execute(array(
            $taxon->nameCode,
            $taxon->webSite,
            $taxon->genus,
            $taxon->species,
            $taxon->infraspecies,
            $taxon->infraspeciesMarker,
            $taxon->author,
            $taxon->acceptedNameCode,
            $taxon->comment,
            $taxon->scrutinyDate,
            $taxon->nameStatusId,
            $taxon->databaseId,
            $taxon->specialistId,
            $taxon->isAcceptedName,
            $taxon->familyName)
         );
         // store links from scientific names to references
         if ($res && is_array($taxon->references)) {
	         $stmt = $this->_dbh->prepare(
	             'INSERT INTO `scientific_name_references`
	             (name_code, reference_id) VALUES (?, ?)'
	         );
	         foreach($taxon->references as $ref) {
	             $stmt->execute(array($taxon->nameCode, $ref->id));
	         }
         }
         return true;
    }
}