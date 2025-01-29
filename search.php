<?php

// Usage: search.php?term=...&...
// Performs a search request to iTunes search API with the same GET parameters as provided.
// This bypasses the newly-introduced HTTPS and gives us an option to perform requests over plain HTTP.
// For supported parameters, see: https://performance-partners.apple.com/search-api

require_once("settings.php");
require_once("util.php");


error_handler(function(){

  // Return HTTP status 404 Not Found if artwork has been disabled
  if(!SHOW_ARTWORK){
    http_response_code(404);
    throw new Exception("Artwork has been disabled with SHOW_ARTWORK setting");
  }

  // Perform iTunes search with GET parameters
  $response = search_itunes($_GET);

  // Loop through the keys of "results" and redirect artwork URLs to local server
  if(isset($response["results"]) && is_array($response["results"])){
    foreach($response["results"] as $num => $result){
      foreach($result as $key => $value){
        if(str_starts_with($key, "artworkUrl")){
          $response["results"][$num][$key] = ARTWORK_URL_PREFIX . $value;
        }
      }
    }
  }

  // Convert the data back to JSON
  $output = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

  // Prevent PHP from appending charset to Content-Type header
  ini_set("default_charset", "");

  // Output the response as JSON
  header("Content-Type: application/octet-stream");
  header("Content-Length: " . strlen($output));
  echo $output;

});



function search_itunes($params){

  // Initialize a curl session and set options
  $ch = curl_init("https://itunes.apple.com/search?" . build_itunes_query($params));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);

  // Execute the request and capture the response
  $response = curl_exec($ch);
  $errno = curl_errno($ch);
  $error = curl_error($ch);
  curl_close($ch);

  // Check for errors
  if($errno){
    http_response_code(502);
    throw new Exception("Failed to search iTunes: {$error}");
  }

  // Decode the JSON string into a PHP array
  if(($json = json_decode($response, true)) === null){
    http_response_code(500);
    throw new Exception("Failed to decode JSON: " . json_last_error_msg());
  }

  return $json;
}



function build_itunes_query($params){

  // Remove problematic symbols from the term
  if(isset($params["term"])){
    $params["term"] = str_replace(["'", "\""], "", $params["term"]);
  }

  // Known parameters and their respective sanitization/validation rules
  // See: https://performance-partners.apple.com/search-api
  $known_params = [
    "term"      => FILTER_SANITIZE_STRING,
    "country"   => FILTER_SANITIZE_STRING,
    "media"     => FILTER_SANITIZE_STRING,
    "entity"    => FILTER_SANITIZE_STRING,
    "limit"     => FILTER_VALIDATE_INT,
    "lang"      => FILTER_SANITIZE_STRING,
    "version"   => FILTER_VALIDATE_INT,
    "explicit"  => FILTER_SANITIZE_STRING
  ];

  // Sanitize inputs
  $sanitized = filter_var_array($params, $known_params);

  // Remove invalid or null parameters
  $sanitized = array_filter($sanitized, function ($value){
    return $value !== null && $value !== false;
  });

  // Build and return the query string
  return http_build_query($sanitized);
}
