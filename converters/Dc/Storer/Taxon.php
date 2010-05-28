<?php
require_once 'Abstract.php';

class Dc_Storer_Taxon extends Dc_Storer_Abstract
{
    public function clear()
    {
        $stmt = $this->_dbh->prepare('TRUNCATE `scientific_names`');
        $stmt->execute();
        unset($stmt);
    }
    
    public function store(Model $taxon)
    {
       $stmt = $this->_dbh->prepare(
            'INSERT INTO `scientific_names` SET name_code = ?, web_site = ?,
            genus = ?, species = ?, infraspecies = ?, infraspecies_marker = ?,
            author = ?, accepted_name_code = ?, comment = ?, scrutiny_date = ?,
            sp2000_status_id = ?, database_id = ?, specialist_id = ?,
            is_accepted_name = ?, family_id =
                (SELECT record_id FROM families WHERE family = ? LIMIT 1)'
       );
       $stmt->execute(array(
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
        return true;
    }
}