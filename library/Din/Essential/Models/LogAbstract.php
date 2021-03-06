<?php

namespace Din\Essential\Models;

abstract class LogAbstract
{

  protected $_dao;
  protected $admin;
  protected $msg;
  protected $table;
  protected $tableHistory;

  public function logicSave ( $action )
  {
    switch ($action) {
      case 'C':
        $this->insert();
        break;
      case 'U':
        $this->update();
        break;
      case 'D':
      case 'T':
      case 'R':
      case 'I':
      case 'E':
        $this->deleteRestore($action);
        break;
    }

  }

}
