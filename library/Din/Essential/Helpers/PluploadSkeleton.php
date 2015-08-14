<?php

namespace Din\Essential\Helpers;

use Din\Form\Upload\iUploadBuilder;
use Din\Form\Upload\Plupload\Plupload;

class PluploadSkeleton implements iUploadBuilder
{

  /**
   *
   * @param string $field_name
   * @param string $type
   * @param bool $obg
   * @param bool $multiple
   * @return string
   */
  public static function getButton ( $field_name, $type, $obg = false, $multiple = false, $uploader = null, $runtime = null )
  {
    if ( is_null($uploader) ) {
      $uploader = '/backend/plupload/upload.php';
    }

    if (is_null($runtime)) {
      $runtime = "'flash,gears,silverlight,html5,browserplus'";
    }

    $Upl = new Plupload($field_name);
    $class = 'pupload';
    if ( $obg )
      $class .= ' obg';

    $Upl->setClass($class);
    $Upl->setMultiple($multiple);
    $Upl->setType($type);
    $Upl->setOpt('runtimes', $runtime);
    $Upl->setOpt('url', "'{$uploader}'");
    $Upl->setOpt('flash_swf_url', "'/backend/plupload/plupload.flash.swf'");
    $Upl->setOpt('silverlight_xap_url', "'/backend/plupload/plupload.silverlight.xap'");
    $Upl->setOpt('unique_names ', "true");

    $r = $Upl->getButton();

    return $r;

  }

}
