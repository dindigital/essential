<?php

namespace Din\Essential\Helpers;

class Entity
{

  protected $_tbl;
  protected $_entity;

  public function __construct ( $tbl, $array )
  {
    $this->_tbl = $tbl;
    $this->setEntity($array);

  }

  protected function setEntity ( $array )
  {
    $this->_entity = $array;

  }

  protected function returnField ( $field )
  {
    if ( array_key_exists($field, $this->_entity) ) {
      return $this->_entity[$field];
    }

  }

  public function getModel ()
  {
    $namespace = 'Admin\Models\\' . $this->returnField('model');

    return new $namespace;

  }

  public function getSection ()
  {
    return $this->returnField('section');

  }

  public function getTitle ()
  {
    return $this->returnField('title');

  }

  public function hasTrash ()
  {
    return $this->returnField('trash') == true;

  }

  public function isArchivable ()
  {
    return $this->returnField('is_archivable') == true;

  }

  public function getId ()
  {
    return $this->returnField('id');

  }

  public function getChildren ()
  {
    return (array) $this->returnField('children');

  }

  public function getParent ()
  {
    return $this->returnField('parent');

  }

  public function getSequence ()
  {
    return (array) $this->returnField('sequence');

  }

  public function getTbl ()
  {
    return $this->_tbl;

  }

}
