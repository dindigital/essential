<?php

namespace Din\Essential\Models;

use Din\Essential\Models\BaseModelAdm;
use dinsocial\SaveHandler\FileSystem;
use dinsocial\SocialKeys;
use Exception;
use Facebook;
use Din\Essential\Models\SocialmediaCredentialsModel;
use Din\Filters\Date\DateFormat;
use Din\TableFilter\TableFilter;
use Din\InputValidator\InputValidator;
use dinsocial\Facebook\Auth as FacebookAuth;

/**
 *
 * @package app.models
 */
class FacepostModel extends BaseModelAdm
{

  protected $_model;
  protected $_facebook;
  protected $_sm_credentials;

  public function __construct ( $section, $id )
  {
    parent::__construct();
    $this->setTable('facepost');
    $this->setModel($section, $id);
    $this->_sm_credentials = new SocialmediaCredentialsModel();
    $this->_sm_credentials->fetchAll();

  }

  protected function setModel ( $section, $id )
  {
    $entity = $this->_entities->getEntity($section);

    $this->_model = $entity->getModel();
    $this->_model->setId($id);

  }

  protected function setFacebook ()
  {
    $this->_facebook = new Facebook(array(
        'appId' => $this->_sm_credentials->row['fb_app_id'],
        'secret' => $this->_sm_credentials->row['fb_app_secret'],
    ));

    //_# IF USER IS LOGGED ON BROWSER
    if ( $this->_facebook->getUser() ) {
      // EXTEND AND STORE THE ACCESS TOKEN
      //$this->_facebook->setExtendedAccessToken();
      $access_token_request = $this->_facebook->api('/' . $this->_sm_credentials->row['fb_page'] . '?fields=access_token');

      $fb_access_token = $access_token_request['access_token'];

      $this->_sm_credentials->updateFbAccessToken($fb_access_token);
      $this->_facebook->setAccessToken($fb_access_token);
    } else {
      $this->_facebook->setAccessToken($this->_sm_credentials->row['fb_access_token']);
    }

  }

  public function getFacebookLogin ()
  {
    $this->setFacebook();

    try {
      // WILL THROW EXCEPTION IF CURRENT ACCESS TOKEN IS NOT VALID
      // THIS WAY I CAN SEND THE USER TO LOGIN URL TO GET A NEW ACCESS TOKEN
      $this->_facebook->api('/me');
    } catch (Exception $e) {
      return $this->_facebook->getLoginUrl(array(
                  'scope' => array(
                      'publish_actions',
                      'manage_pages'
                  )
      ));
    }

  }

  public function generatePost ()
  {
    $post = $this->_model->generatePost();

    return $post;

  }

  public function post ( $input )
  {

    try {

      $v = new InputValidator($input);
      $v->string()->validate('name', 'Nome');
      $v->string()->validate('link', 'Link');
      $v->throwException();
      //

      $fileSystem = new FileSystem('tokens');

      $facebookKey = new SocialKeys();
      $facebookKey->setClientId(FACEBOOK_CLIENT_ID);
      $facebookKey->setClientSecret(FACEBOOK_CLIENT_SECRET);
      $facebookKey->setRedirectUri(FACEBOOK_REDIRECT_URL);

      $facebookAuth = new FacebookAuth($facebookKey, $fileSystem);

      $fb = new \Facebook\Facebook([
          'app_id' => FACEBOOK_CLIENT_ID,
          'app_secret' => FACEBOOK_CLIENT_SECRET,
          'default_graph_version' => 'v2.7',
          'default_access_token' => $facebookAuth->getToken()['access_token']
      ]);

      $facebookPost = new \dinsocial\Facebook\PageFeed($fb);
      $id_post = $facebookPost->postPageFeed(
          FACEBOOK_PAGE_ID,
          $input['link'],
          $input['name'],
          $input['picture'],
          $input['description'],
          $input['message']
      );

      if ($id_post) {
        $f = new TableFilter($this->_table, $input);
        $f->newId()->filter('id_facepost');
        $f->timestamp()->filter('date');
        $f->string()->filter('name');
        $f->string()->filter('link');
        $f->string()->filter('picture');
        $f->string()->filter('description');
        $f->string()->filter('message');
        //
        $this->_dao->insert($this->_table);
        //_# AVISA O MODEL
        $this->_model->sentPost($this->_table->id_facepost);
      }

    } catch (Exception $e) {
      return $e->getMessage();
    }

  }

  public function getPosts ()
  {
    $tweets = $this->_model->getPosts();

    foreach ( $tweets as $i => $row ) {
      $tweets[$i]['date'] = DateFormat::filter_dateTimeExtensive($row['date']);
    }

    return $tweets;

  }

}
