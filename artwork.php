<?php

// Usage: artwork.php?url=<url>
// This script downloads the image from the provided URL, processes it, and outputs as a JPG file.

require_once("settings.php");
require_once("util.php");


error_handler(function(){

  // Get image URL
  if(($url = filter_var($_GET["url"], FILTER_SANITIZE_URL)) === false){
    http_response_code(400);
    throw new Exception("Invalid URL");
  }

  // Extract host from the URL
  $host = parse_url($url, PHP_URL_HOST);

  // Only allow mzstatic.com and its subdomains
  if($host === false || !preg_match("/^([a-z0-9-]+\.)*mzstatic\.com$/i", $host)){
    http_response_code(400);
    throw new Exception("Invalid domain, only mzstatic.com and its subdomains are allowed");
  }

  // Download the image and process it
  $data = curl_get($url, false);
  $data = process_image($data, true);

  // Output the image
  header("Content-Type: image/jpeg");
  header("Content-Length: " . strlen($data));
  echo $data;

});
