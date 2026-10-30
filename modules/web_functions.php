<?php

class WebFunctions {
  function redireccionar($ruta){
    echo '<script type="text/javascript">';
    echo 'location.href ="'.$this->direct_sistema().'/'.$ruta.'";';
    echo '</script>';
  }

  function direct_sistema(){
    return app_base_path();
  }

  function directorio_carpetas(){
    return dirname(__DIR__);
  }

  function direct_paginas(){
    $directorio = $this->direct_sistema()."/views/";
    return $directorio;
  }
  
}
?>
