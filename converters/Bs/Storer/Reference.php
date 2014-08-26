<?php
require_once 'Interface.php';
require_once 'Abstract.php';

/**
 * Reference storer
 *
 * @author Nuria Torrescasana Aloy, Ruud Altenburg
 */
class Bs_Storer_Reference extends Bs_Storer_Abstract implements Bs_Storer_Interface
{

    public function store (Model $reference)
    {
        if (empty($reference->title) && empty($reference->authors) &&
            empty($reference->year) && empty($reference->text)) {
            return $reference;
        }
        $referenceId = $this->_recordExists('id', 'reference',
            array(
                'authors' => $reference->authors,
                'year' => $reference->year,
                'title' => $reference->title,
                'text' => $reference->text
            ));
        if ($referenceId) {
            $reference->id = $referenceId;
        }
        else {
            $stmt = $this->_dbh->prepare(
                'INSERT INTO `reference` (`title`, `authors`, `year`, `text`) VALUES (?, ?, ?, ?)'
            );
            try {
                $stmt->execute(array(
                    $reference->title,
                    $reference->authors,
                    $reference->year,
                    $reference->text
                ));
                $reference->id = $this->_dbh->lastInsertId();
            } catch (PDOException $e) {
                $this->_handleException("Store error reference", $e);
            }
        }
        $this->_setReferenceTypeId($reference);
        return $reference;
    }

    private function _setReferenceTypeId (Model $reference)
    {
        if (empty($reference->type)) {
            return $reference;
        }
        $lookup = array(
            'nomref' => 'Nomenclatural Reference',
            'taxaccref' => 'Taxonomic Acceptance Reference',
            'comnameref' => 'Common Name Reference'
        );
        $type = $lookup[strtolower($reference->type)];
        if ($reference->typeId = Dictionary::get('reference_types', $type)) {
            return $reference;
        }
        $reference->typeId = $this->_recordExists('id', 'reference_type',
            array(
                'type' => $type
            ));
        if ($reference->typeId) {
            Dictionary::add('reference_types', $type, $reference->typeId);
            return $reference;
        }
    }
}