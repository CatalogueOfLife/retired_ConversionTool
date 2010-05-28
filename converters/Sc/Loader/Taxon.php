<?php
require_once 'Abstract.php';
require_once 'converters/Sc/Model/Taxon.php';

class Sc_Loader_Taxon extends Sc_Loader_Abstract
{
    public function count()
    {
        $stmt = $this->_dbh->prepare('SELECT COUNT(1) FROM Type1Cache');
        $stmt->execute();
        $res = $stmt->fetchColumn(0);
        unset($stmt);
        return $res;
    }
    
    public function load($offset, $limit)
    {
        $stmt = $this->_dbh->prepare(
            "SELECT
            t1.taxoncode as nameCode,
            t2.datalink as webSite,
            t1.genus,
            t1.specificepithet as species,
            t1.infraspecificepithet as infraspecies,
            t1.infraspecificmarker as infraspeciesMarker,
            t1.authority as author,
            t1.taxoncode as acceptedNameCode,
            t2.`comment`,
            if (t2.scrutinyyear > 0,
               concat(
                   t2.scrutinyday, '-',
                   t2.scrutinymonth, '-',
                   t2.scrutinyyear
                   ), NULL)
               as scrutinyDate,
            case t1.status
                when 'accepted' then 1
                when 'provisional' then 4
                when 'synonym' then 5
                when 'ambiguous' then 2
                when 'misapplied' then 3
                end as nameStatusId,
            t1.source as databaseName,
            t2.scrutinyperson as specialistName,
            t2.family AS familyName,
            IF (t1.status = 'accepted' or t1.status = 'provisional', 1, 0)
                as isAcceptedName
            FROM `Type1Cache` t1
            left join `StandardDataCache` t2 on t1.TaxonCode = t2.taxonID
            LIMIT :offset, :limit"
        );
        $stmt->bindParam('offset', $offset, PDO::PARAM_INT);
        $stmt->bindParam('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $taxa = array();
        
        while($taxon = $stmt->fetchObject('Sc_Model_Taxon')) {
            $taxon->databaseId = Dictionary::get('dbs', $taxon->databaseName);
            $specialistId = Dictionary::get(
                'specialists', $taxon->specialistName
            );
            if($specialistId) {
                $taxon->specialistId = $specialistId;
            }
            $taxa[] = $taxon;
        }
        unset($stmt);
        return $taxa;
    }
}