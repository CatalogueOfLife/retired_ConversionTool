<?php
require_once 'converters/Bs/Storer/TaxonAbstract.php';
require_once 'model/AcToBs/Uri.php';
require_once 'converters/Bs/Storer/Uri.php';

/**
 * HigherTaxon storer
 *
 * @author Nuria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_HigherTaxon extends Bs_Storer_TaxonAbstract implements Bs_Storer_Interface
{

    public function store (Model $taxon)
    {
        // Source database id is NULL for all higher taxa
        $this->_setTaxonomicRankId(
            $taxon);
        $this->_setScientificNameElement($taxon);
        $this->_setTaxon($taxon);
        $this->_setTaxonNameElement($taxon);
        //$this->_setTaxonLsid($taxon);
    }

    protected function _setTaxon (Model $taxon)
    {
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `taxon` (`id`, `taxonomic_rank_id`, `source_database_id`, `original_id`)' .
            'VALUES (?, ?, ?, ?)');
        try {
            $stmt->execute(array(
                $taxon->id,
                $taxon->taxonomicRankId,
                $taxon->sourceDatabaseId,
                $taxon->originalId
            ));
        } catch (PDOException $e) {
            $this->_handleException("Store error taxon", $e);
        }
        return $taxon;
    }

    protected function _setScientificNameElement (Model $taxon)
    {
        // All names are stored in lower case
        $name = mb_strtolower($taxon->name);
        $nameElementId = $this->_recordExists('id', 'scientific_name_element',
            array(
                'name_element' => $name
            ));
        if (!$nameElementId) {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `scientific_name_element` (`name_element`) VALUE (?)');
            try {
                $stmt->execute(array($name));
                $nameElementId = $this->_dbh->lastInsertId();
            } catch (PDOException $e) {
                $this->_handleException("Store error scientific name element", $e);
            }
        }
        $taxon->nameElementIds[] = $nameElementId;
        return $taxon;
    }

    protected function _setTaxonNameElement (Model $taxon)
    {
        // Top level(s)
        if ($taxon->parentId == '' || $taxon->parentId == 0) {
            $taxon->parentId = null;
        }
        $stmt = $this->_dbh->prepare(
            'INSERT INTO `taxon_name_element` (`taxon_id`, `scientific_name_element_id`, `parent_id`)
            VALUES (?, ?, ?)');
        try {
            $stmt->execute(array(
                $taxon->id,
                end($taxon->nameElementIds),
                $taxon->parentId
            ));
        } catch (PDOException $e) {
            $this->_handleException("Store error taxon name element", $e);
        }
        return $taxon;
    }

    protected function _setTaxonLsid (Model $taxon)
    {
        $uri = new Uri();
        $uri->resourceIdentifier = $taxon->lsid;
        $storer = new Bs_Storer_Uri($this->_dbh, $this->_logger);
        $uri->uriSchemeId = $storer->getUriSchemeIdByScheme('lsid');
        $storer->store($uri);

        $stmt = $this->_dbh->prepare('INSERT INTO `uri_to_taxon` (uri_id, taxon_id) VALUES (?, ?)');
        try {
            $stmt->execute(array(
                $uri->id,
                $taxon->id
            ));
        } catch (PDOException $e) {
            $this->_handleException("Store error taxon lsid", $e);
        }
        unset($storer, $uri);
    }
}