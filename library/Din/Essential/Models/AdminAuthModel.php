<?php

namespace Din\Essential\Models;

use Din\Crypt\Crypt;
use Din\Session\Session;
use Din\DataAccessLayer\PDO\PDOBuilder;
use Din\Auth\Auth;
use Din\Auth\AuthDataLayer\AuthDataLayer;
use Din\Exception\JsonException;

/**
 *
 * @package app.models
 */
class AdminAuthModel extends Auth
{

  private $_session_name = 'adm_session';

  public function __construct ()
  {
    $tbl = 'admin';
    $pk_field = 'id_admin';
    $login_field = 'email';
    $pass_field = 'password';
    $active_field = 'is_active';

    $PDO = PDOBuilder::build(DB_TYPE, DB_HOST, DB_SCHEMA, DB_USER, DB_PASS);
    $ADL = new AuthDataLayer($PDO, $tbl, $login_field, $pass_field, $pk_field, $active_field);

    parent::__construct($ADL, new Crypt(), new Session($this->_session_name));

  }

  public function login ( $email, $password, $is_crypted = false )
  {
    if ( !parent::login($email, $password, $is_crypted) ) {
      throw new JsonException("Dados inválidos. Usuário não encontrado.");
    }

    if ( !$this->is_active() ) {
      $this->logout();
      throw new JsonException("Sua conta ainda não foi ativada. Entre em contato com o Administrador.");
    }

  }

  public function getUser ()
  {
    $session = new Session($this->_session_name);
    return $session->get('user_table');

  }

}
