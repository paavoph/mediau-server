<?php

require_once("settings.php");



// Generic wrapper for each script to process exceptions
function error_handler(callable $callback){
  try{

    // Execute the script
    $callback();

  }catch(Exception $e){

    // Default to HTTP code 500 if no other error code has been set
    if(http_response_code() == 200){
      http_response_code(500);
    }

    // Print exception message
    die("Error: {$e->getMessage()}\n");
  }
}



// Perform HTTP GET request using curl, check for common errors,
// and return the response if the request succeeded
function curl_get($url, $accept_empty=true, $timeout=30){

  // Initialize a cURL session
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

  // Execute the request
  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  $errno = curl_errno($ch);
  curl_close($ch);

  // Check for curl errors
  if($errno){
    http_response_code(502);
    throw new Exception("curl error ($errno): {$error}");
  }

  // Check HTTP status codes
  if($http_code >= 400){
    http_response_code($http_code);
    throw new Exception("HTTP request failed with status code {$http_code}: $url");
  }

  // Treat empty response as error if not allowed
  if(empty($response) && !$accept_empty){
    http_response_code(502);
    throw new Exception("HTTP request returned no data");
  }

  return $response;
}



// Process raw JPG data using ImageMagick and returns updated binary data
function process_image($data, $add_itunes_icc=false){

  // Read the downloaded data into ImageMagick
  $imagick = new Imagick();
  $imagick->readImageBlob($data);

  // Set the JPEG sampling to H1V1 (4:4:4 chroma subsampling)
  $imagick->setImageProperty("jpeg:sampling-factor", "1x1,1x1,1x1");

  // Read ICC profile from file
  if(($profile = file_get_contents(ITUNES_ICC)) === false){
    throw new Exception("Failed to load ICC profile from: " . ITUNES_ICC);
  }

  // Embed the profile in the image
  if($add_itunes_icc){
    $imagick->profileImage("icc", $profile);
  }

  // Set output format to JPEG and quality to 30
  $imagick->setImageFormat("jpeg");
  $imagick->setImageCompressionQuality(30);

  // Get the image binary data
  $data = $imagick->getImageBlob();

  // Free ImageMagick resources
  $imagick->destroy();

  // Return modified data
  return $data;
}
