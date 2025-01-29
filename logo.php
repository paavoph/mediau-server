<?php

// Usage: logo.php?id=[channel_id]
// The purpose of this script is to download channel logos from MediaU (now Airable),
// convert them into a format suitable for older radios, and store the logos in local cache,
// where they can be customized if desired.

require_once("settings.php");
require_once("util.php");


error_handler(function(){

  // Check if the id parameter is provided
  if(!isset($_GET["id"])){
    http_response_code(400);
    throw new Exception("Please provide a channel ID using the 'id' GET parameter");
  }

  // Get the channel ID
  if(($id = filter_var($_GET["id"], FILTER_VALIDATE_INT)) === false){
    http_response_code(400);
    throw new Exception("Invalid channel ID");
  }

  // Check if cache folder exists and is writable
  if(!is_dir(LOGO_CACHE_DIR) || !is_writable(LOGO_CACHE_DIR)){
    http_response_code(500);
    throw new Exception("Logo cache folder does not exist or is not writable: " . LOGO_CACHE_DIR);
  }

  // Init cached logo path
  $logo = LOGO_CACHE_DIR . "/{$id}.jpg";

  // Check if the logo is already in the cache
  if(!file_exists($logo)){

    // Retrieve the logo
    $data = curl_get("http://file.mediayou.net/logo/radio_TFT/{$id}.jpg");
    $data = process_image($data);

    // Save to cache
    if(file_put_contents($logo, $data) === false){
      http_response_code(500);
      throw new Exception("Failed to save logo image into cache folder: " . LOGO_CACHE_DIR);
    }

  }

  // Serve the cached image
  header("Content-Type: image/jpeg");
  header("Last-Modified: " . gmdate(DATE_RFC7231, filemtime($logo)));
  header("Content-Length: " . filesize($logo));
  readfile($logo);

});
