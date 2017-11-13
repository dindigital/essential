<?php

namespace Din\Essential\Models;

use Din\DataAccessLayer\DAO;
use Din\DataAccessLayer\PDO\PDOBuilder;
use Din\Essential\Helpers\Entities;
use Din\DataAccessLayer\Select;
use Din\File\Folder;
use Exception;
use Din\Essential\Models\LogMySQLModel as log;
use Din\DataAccessLayer\Table\Table;
use Din\Essential\Helpers\Entity;
use Din\TableFilter\TableFilter;
use Din\Essential\Models\SequenceModel;

class BaseModelAdm
{
    protected $_dao;
    protected $_paginator = null;
    protected $_itens_per_page = 20;
    protected $_table;
    protected $_filters;
    protected $_id;
    protected $_entities;
    public $_entity;

    public function __construct()
    {
        $this->_dao = new DAO(PDOBuilder::build(DB_TYPE, DB_HOST, DB_SCHEMA,
                DB_USER, DB_PASS));
        $this->_entities = new Entities('config/entities.php');
        $this->setUploadRestrictions();
    }
    /*
     * ===========================================================================
     * TABLE
     * ===========================================================================
     */

    public function setEntity($tablename)
    {
        $this->_entity = $this->_entities->getEntity($tablename);
        $this->setTable($tablename);
    }

    public function setTable($tablename)
    {
        $this->_table = new Table($tablename);
    }

    public function getTable()
    {
        return $this->_table;
    }

    public function getIdName()
    {
        $property = $this->_entity->getId();

        return $property;
    }

    public function getTableName()
    {
        return $this->_table->getName();
    }

    public function setId($id)
    {
        $this->_table->{$this->getIdName()} = $id;
    }

    public function getId()
    {
        return $this->_table->{$this->getIdName()};
    }
    /*
     * ===========================================================================
     * PAGED
     * ===========================================================================
     */

    public function setPaginationSelect($select)
    {
        $total = $this->_dao->select_count($select);
        $offset = $this->_paginator->getOffset($total);
        $select->limit($this->_itens_per_page, $offset);
    }

    public function getPaginator()
    {
        return $this->_paginator;
    }
    /*
     * ===========================================================================
     * DATABASE CRUD
     * ===========================================================================
     */

    public function deleteChildren(Entity $entity, $id)
    {
        $entity_id = $entity->getId();
        $children = $entity->getChildren();

        foreach ($children as $child) {
            $child_entity = $this->_entities->getEntity($child);
            $child_tbl = $child_entity->getTbl();
            $child_id = $child_entity->getId();
            $child_model = $child_entity->getModel();

            $select = new Select($child_tbl);
            $select->addField($child_id, 'id_children');
            $select->where(array(
                $entity_id.' = ? ' => $id
            ));
            $result = $this->_dao->select($select);

            $arr_delete = array();
            foreach ($result as $row) {
                $arr_delete[] = array(
                    'name' => $child_tbl,
                    'id' => $row['id_children'],
                );
            }

            $child_model->delete($arr_delete);
        }
    }

    public function delete($itens)
    {
        foreach ($itens as $item) {
            $id = $this->_entity->getId();
            $tbl = $this->_entity->getTbl();
            $title = $this->_entity->getTitle();
            $entity_sequence = $this->_entity->getSequence();

            if (count($entity_sequence)) {
                $seq = new SequenceModel();
                $seq->changeSequence(array(
                    'tbl' => $tbl,
                    'id' => $item['id'],
                    'sequence' => 0
                ));
            }

            $tableHistory = $this->getById($item['id']);

            $this->beforeDelete($tableHistory);

            $this->deleteChildren($this->_entity, $item['id']);

            Folder::delete("public/system/uploads/{$tbl}/{$item['id']}");
            $this->_dao->delete($tbl, array($id.' = ?' => $item['id']));
            if (isset($title)) {
                $this->log('D', $tableHistory[$title], $this->_table,
                    $tableHistory);
            }
        }
    }

    public function beforeDelete($tableHistory)
    {
        //
    }

    public function onGetById(Select $select)
    {
        //
    }

    public function getNewUsingRecord($id)
    {
        $row = $this->formatTable($this->getById($id), true);

        return $row;
    }

    public function getById($id = null)
    {
        if ($id) {
            $this->setId($id);
        }

        $arrCriteria = array(
            'a.'.$this->getIdName().' = ?' => $this->getId()
        );

        $select = new Select($this->getTableName());
        $select->addAllFields();
        $select->where($arrCriteria);

        $this->onGetById($select);

        $result = $this->_dao->select($select);

        if (!count($result)) throw new Exception('Registro não encontrado.');

        return $result[0];
    }

    public function getRow($id = null)
    {
        $row = $this->formatTable($this->getById($id));

        return $row;
    }

    public function getNew()
    {
        $SQL = "DESCRIBE `{$this->getTableName()}`";

        $result = $this->_dao->execute($SQL, array(), true);

        $arr_return = array();

        foreach ($result as $row) {
            $arr_return[$row['Field']] = $row['Default'];
        }

        $table = $this->formatTable($arr_return);

        return $table;
    }

    protected function formatTable($table, $exclude_fields = false)
    {
        if ($exclude_fields) {
            // set the upload fields to null
        }

        // do normal formating and return

        return $table;
    }

    public function save($info)
    {
        if (!$this->getId()) {
            $this->insert($info);
        } else {
            $this->update($info);
        }

        return $this->getId();
    }

    protected function dao_insert($log = true)
    {
        $this->_dao->insert($this->_table);

        if ($log) {
            $title = $this->_entity->getTitle();
            $msg = $this->_table->{$title};

            $this->log('C', $msg, $this->_table);
        }
    }

    public function dao_update($log = true)
    {
        $tableHistory = $this->getById();

        $this->beforeUpdate($tableHistory);

        $this->_dao->update($this->_table,
            array("{$this->getIdName()} = ?" => $this->getId()));

        if ($log) {
            $title = $this->_entity->getTitle();
            $msg = $this->_table->{$title};

            $this->log('U', $msg, $this->_table, $tableHistory);
        }
    }

    public function beforeUpdate($tableHistory)
    {
        $entity_sequence = $this->_entity->getSequence();
        if (count($entity_sequence) && isset($entity_sequence['dependence'])) {
            $dependence_field_itens = $entity_sequence['dependence'];

            if (!is_array($dependence_field_itens)) {
                $dependence_field_itens = array(0 => $dependence_field_itens);
            }

            foreach ($dependence_field_itens as $dependence_field) {

                $dependence_value = $this->_table->{$dependence_field};

                if ($tableHistory[$dependence_field] != $dependence_value) {
                    $seq = new SequenceModel();
                    $seq->changeSequence(array(
                        'tbl' => $this->getTableName(),
                        'id' => $this->getId(),
                        'sequence' => 0
                    ));

                    $f = new TableFilter($this->_table, array());
                    $f->sequence($this->_dao, $this->_entity)->filter('sequence');
                }
            }
        }
    }
    /*
     * ===========================================================================
     * LOGGABLE
     * ===========================================================================
     */

    protected function log($action, $msg, Table $table, $tableHistory = null)
    {
        $adminAuth = new AdminAuthModel();
        $admin = $adminAuth->getUser();

        log::save($this->_dao, $admin, $action, $msg, $table, $tableHistory);
    }

    public function setFilters(Array $filters)
    {
        $this->_filters = $filters;
    }

    public function setUploadRestrictions()
    {
        $adminAuth = new AdminAuthModel();
        $admin = $adminAuth->getUser();
        // peguei o usuário X que pode gravar no diretório Y e Z
        // agora vou setar isso
        $result = [
            'SP', 'MG'
        ];

        $basePath = '../../../../../system/uploads/ck';
        $dirs = [];
        foreach ($result as $row) {
            $dirs[] = "{$row}={$basePath}/{$row}";
        }

        $_SESSION['moxiemanager.filesystem.rootpath'] = implode(';', $dirs);
    }
}