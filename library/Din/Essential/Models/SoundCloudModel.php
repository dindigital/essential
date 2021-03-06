<?php

namespace Din\Essential\Models;

use Din\Essential\Models\BaseModelAdm;
use Exception;
use Soundcloud\Service;
use Din\Essential\Models\SocialmediaCredentialsModel;
use Din\Http\Header;
use Din\TableFilter\TableFilter;
use Din\Session\Session;
use Din\Filters\Date\DateFormat;

/**
 *
 * @package app.models
 */
class SoundCloudModel extends BaseModelAdm
{

  protected $_api;
  protected $_sm_credentials;

  public function __construct ()
  {
    parent::__construct();
    $this->setTable('soundcloud');

    $this->_sm_credentials = new SocialmediaCredentialsModel();
    $this->_sm_credentials->fetchAll();

    $client_id = $this->_sm_credentials->row['sc_client_id'];
    $client_secret = $this->_sm_credentials->row['sc_client_secret'];
    $redirect_uri = URL . '/admin/sound_cloud/';

    $this->_api = new Service($client_id, $client_secret, $redirect_uri);

  }

  public function getIdName ()
  {
    return 'id_soundcloud';

  }

  public function makeLogin ()
  {
    $access_token = $this->_sm_credentials->row['sc_token'];
    if ( $access_token ) {
      $this->_api->setAccessToken($access_token);
    }

    try {
      $this->_api->get('me');
    } catch (Exception $e) {
      $authorize_url = $this->_api->getAuthorizeUrl(array(
          'scope' => 'non-expiring'
      ));

      $session = new Session('adm_session');
      $session->set('referer', Header::getUri());

      Header::redirect($authorize_url);
    }

  }

  public function saveToken ( $input )
  {
    $token = $this->_api->accessToken($input['code']);
    $access_token = $token['access_token'];
    $this->_api->setAccessToken($access_token);

    $this->_sm_credentials->updateSoundCoudAccessToken($access_token);

  }

  public function deletePrevious ( $id_soundcloud )
  {
    try {
      $row = $this->getById($id_soundcloud);
      try {
        $this->_api->delete("tracks/{$row['track_id']}");
      } catch (Exception $e) {
        //
      }

      $this->_dao->delete('soundcloud', array(
          'id_soundcloud = ?' => $id_soundcloud
      ));
    } catch (Exception $e) {
      //
    }

  }

  public function insertComplete ( $input )
  {
    if ( function_exists('curl_file_create') ) {
      $file = curl_file_create($input['file']);
    } else {
      $file = '@' . $input['file'];
    }

    $track = array(
        'track[title]' => $input['title'],
        'track[asset_data]' => $file,
        'track[downloadable]' => true,
        'track[streamable]' => true,
        'track[description]' => trim(strip_tags($input['description'])),
        'track[tag_list]' => $input['tag_list'],
    );

    if ( isset($input['date']) && DateFormat::validate($input['date']) ) {
      $d = DateFormat::filter_date($input['date'], 'd');
      $m = DateFormat::filter_date($input['date'], 'm');
      $y = DateFormat::filter_date($input['date'], 'Y');

      $track['track[release_day]'] = $d;
      $track['track[release_month]'] = $m;
      $track['track[release_year]'] = $y;
    }

    if ( isset($input['cover']) ) {
      if ( function_exists('curl_file_create') ) {
        $cover = curl_file_create($input['cover']);
      } else {
        $cover = '@' . $input['cover'];
      }

      $track['track[artwork_data]'] = $cover;
    }

    try {
      $response_text = $this->_api->post('tracks', $track);
    } catch (\Soundcloud\Exception\InvalidHttpResponseCodeException $e) {
      $error = json_decode($e->getHttpBody());
      $msg = $error->errors[0]->error_message;
      throw new \Exception($msg);
    }

    $response_json = json_decode($response_text);

    if ( json_last_error() )
      throw new Exception('Não foi possível converter pra JSON: ' . $response_text);

    $f = new TableFilter($this->_table, array(
        'track_id' => $response_json->id,
        'track_permalink' => $response_json->permalink_url
    ));
    $f->newId()->filter('id_soundcloud');
    $f->string()->filter('track_id');
    $f->string()->filter('track_permalink');

    $this->_dao->insert($this->_table);

    return $this->_table->id_soundcloud;

  }

  public function insertFromLink ( $link )
  {
    $response_text = file_get_contents("http://api.soundcloud.com/resolve.json?url={$link}&client_id={$this->_sm_credentials->row['sc_client_id']}");
    $response_json = json_decode($response_text);

    if ( json_last_error() )
      throw new Exception('Não foi possível converter pra JSON: ' . $response_text);

    $track_id = $response_json->id;
    $permalink_url = $response_json->permalink_url;

    $f = new TableFilter($this->_table, array(
        'track_id' => $track_id,
        'track_permalink' => $permalink_url
    ));
    $f->newId()->filter('id_soundcloud');
    $f->string()->filter('track_id');
    $f->string()->filter('track_permalink');

    $this->_dao->insert($this->
            _table);

    return $this->_table->id_soundcloud;

  }

  public function getEmbed ( $track_url )
  {
    $response_text = $this->_api->get('oembed', array(
        'url' => $track_url
    ));

    $response_json = json_decode($response_text);

    if ( json_last_error() )
      throw new Exception('Não foi possível converter pra JSON: ' . $response_text);

    return $response_json->html;

  }

}
