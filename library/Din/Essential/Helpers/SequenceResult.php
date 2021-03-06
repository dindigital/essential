<?php

namespace Din\Essential\Helpers;

use Din\Essential\Helpers\Entity;
use Din\DataAccessLayer\DAO;
use Din\DataAccessLayer\Select;

class SequenceResult
{

  protected $_entity;
  protected $_dao;

  public function __construct ( Entity $entity, DAO $dao )
  {
    $this->_entity = $entity;
    $this->_dao = $dao;

  }

  public function filterResult ( $result, $arrCriteria )
  {
    $entity_sequence = $this->_entity->getSequence();

    $dependenceCriteria = array();
    if ( isset($entity_sequence['dependence']) ) {
      $dependence_field = $entity_sequence['dependence'];

      if ( is_array($dependence_field) ) {
        foreach ( $dependence_field as $dp ) {
          foreach ( $arrCriteria as $field => $value ) {
            if ( strpos($field, $dp) !== false && strpos($field, 'IN') === false ) {
              $dependenceCriteria[$field] = $value;
              //break;
            }
          }
        }


        if ( count($dependence_field) != count($dependenceCriteria) )
          return $result;
      } else {
        foreach ( $arrCriteria as $field => $value ) {
          if ( strpos($field, $dependence_field) !== false ) {
            $dependenceCriteria[$field] = $value;
            break;
          }
        }

        if ( !count($dependenceCriteria) )
          return $result;
      }
    }

    $total = $this->getMaxSequence($dependenceCriteria);
    $optional = $entity_sequence['optional'];

    $options = array();

    if ( $optional ) {
      $options[0] = '';
    }

    for ( $i = 1; $i <= $total; $i++ ) {
      $options[$i] = $i;
    }

    foreach ( $result as $i => $row ) {
      if ( $optional && $row['sequence'] == 0 ) {
        $options2 = $options;

        // implementação de maximo
        $addone = true;
        if ( isset($entity_sequence['maximum']) ) {

          $maximo = is_array($entity_sequence['maximum']) ?
            $entity_sequence['maximum'][$row[$entity_sequence['dependence']]] :
            $entity_sequence['maximum'];

          if ( $total == $maximo ) {
            $addone = false;
          }
        }

        if ( $addone ) {
          $options2[(string) $total + 1] = (string) $total + 1;
        }

        $result[$i]['sequence_list_array'] = $options2;
      } else {
        $result[$i]['sequence_list_array'] = $options;
      }
    }

    return $result;

  }

  protected function getMaxSequence ( $arrCriteria )
  {
    $entity_tbl = $this->_entity->getTbl();
    $entity_sequence = $this->_entity->getSequence();

    $select = new Select($entity_tbl);

    if ( $this->_entity->hasTrash() ) {
      $arrCriteria['is_del = 0'] = null;
    }

    if ( isset($entity_sequence['optional']) && $entity_sequence['optional'] ) {
      $arrCriteria['sequence > ?'] = '0';
    }

    $select->where($arrCriteria);

    return $this->_dao->select_count($select);

  }

}
