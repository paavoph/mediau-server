<?php

// Usage: play.php?id=<num>&sc=<id>

// This script takes channel ID and chipset ID, and retrieves stream title and URL,
// which are returned as an ASX file.

// Stream lookup order:
// 1. Use the stream URL in STREAM_CONFIG_FILE if an URL has been defined there.
// 2. Call MediaU service to get the URL for the stream.
// 3. If MediaU call fails, use the cached stream URL in STREAM_CACHE_FILE.

// Chipset ID seems to have no effect at the moment.


require_once("settings.php");
require_once("util.php");


error_handler(function(){

  // Read GET params
  $params = read_GET_params();

  // Read stream config file
  $stream_conf = read_stream_conf(STREAM_CONFIG_FILE);

  // Get stream data from either the config (if found), or retrieve from MediaU, or read from cache
  if(isset($stream_conf[$params["id"]])){
    $stream = $stream_conf[$params["id"]];
  }else{
    try{
      if(PREFER_CACHED_STREAM_URL){
        $stream = get_stream_from_cache($params["id"], $params["sc"]);
      }else{
        $stream = get_stream_from_mediau($params["id"], $params["sc"]);
      }
    }catch(Exception $e){
      if(!PREFER_CACHED_STREAM_URL){
        $stream = get_stream_from_cache($params["id"], $params["sc"]);
      }else{
        $stream = get_stream_from_mediau($params["id"], $params["sc"]);
      }
    }
  }

  // Save stream in cache
  save_stream($params["id"], $params["sc"], $stream["title"], $stream["url"]);

  // Follow redirects and update stream URL to the final URL
  $stream["url"] = follow_url($stream["url"]);

  // Output the response as ASX file
  header("Content-Type: video/x-ms-asf");
  echo create_asx($stream["title"], $stream["url"]);

});



// Read and sanitize GET parameters 'id' and 'sc'
function read_GET_params(){

  // Check if the id parameter was provided
  if(!isset($_GET["id"])){
    http_response_code(400);
    throw new Exception("Please provide a channel ID using the 'id' GET parameter");
  }

  // Get the channel ID
  if(($id = filter_var($_GET["id"], FILTER_VALIDATE_INT)) === false){
    http_response_code(400);
    throw new Exception("Invalid ID");
  }


  // Check if the chipset id parameter was provided
  if(!isset($_GET["sc"])){
    http_response_code(400);
    throw new Exception("Please provide a chipset ID using the 'sc' GET parameter");
  }

  // Get the chipset ID
  $sc = filter_var($_GET["sc"], FILTER_VALIDATE_REGEXP, [
    "options" => [
      "regexp" => "/^[0-9a-z_-]+$/i"
    ]
  ]);

  if($sc === false){
    http_response_code(400);
    throw new Exception("Invalid chipset ID");
  }

  return array("id" => $id, "sc" => $sc);
}



// Read stream configuration YAML file into an array
function read_stream_conf($path){

  // Check if the file exists
  if(!file_exists($path)){
    http_response_code(500);
    throw new Exception("Stream URL configuration file not found: {$path}");
  }

  // Parse the JSON file and read stream URLs into a PHP array
  if(($conf = json_decode(file_get_contents($path), true)) === null){
    http_response_code(500);
    throw new Exception("Failed to parse the stream configuration file: {$path}. Error: " . json_last_error_msg());
  }

  return $conf;
}



// Retrieves stream URL from cache
function get_stream_from_cache($id, $sc){

  // Read stream cache
  if(($contents = file_get_contents(STREAM_CACHE_FILE)) === false){
    http_response_code(500);
    throw new Exception("Failed to read stream cache file: " . STREAM_CACHE_FILE);
  }

  // Parse into array
  if(($config = json_decode($contents, true)) === null){
    http_response_code(500);
    throw new Exception("Failed to parse stream cache file: " . json_last_error_msg());
  }

  // Return title and URL for the requested stream ID and chipset ID
  if(isset($config[$sc][$id])){
    return array(
      "title" => $config[$sc][$id]["title"],
      "url"   => $config[$sc][$id]["url"]
    );
  }

  // Raise 404 Not Found exception when URL was not found
  http_response_code(404);
  throw new Exception("Stream URL not found in cache");
}



// Get stream URL and title from MediaU
function get_stream_from_mediau($channel_id, $chipset_id, $mediau_api="http://file.mediayou.net/playURL.php", $timeout=10){

  // Initialize a curl session and set options
  $ch = curl_init($mediau_api . "?" . http_build_query(array("id"=>$channel_id, "sc"=>$chipset_id)));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

  // Execute the request and capture the response
  $response = curl_exec($ch);
  $errno = curl_errno($ch);
  $error = curl_error($ch);
  curl_close($ch);

  // Check for errors
  if($errno){
    http_response_code(502);
    throw new Exception("Failed to retrieve stream URL from MediaU: {$error}");
  }

  // Parse the XML
  if(($xml = simplexml_load_string($response)) === false){
    http_response_code(500);
    throw new Exception("Failed to parse stream ASX retrieved from MediaU");
  }

  // Read title and URL
  try{
    $title = (string)$xml->TITLE;
    $url = (string)$xml->Entry->ref["href"];
  }catch(Exception $e){
    http_response_code(500);
    throw new Exception("Failed to read stream title and URL from ASX retrieved from MediaU: {$e->getMessage()}");
  }

  return array("title" => $title, "url" => $url);
}



// Follow redirects to resolve the final URL
function follow_url($url, $max_redirects=3, $timeout=10){

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
  curl_setopt($ch, CURLOPT_MAXREDIRS, $max_redirects);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  curl_exec($ch);

  $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
  curl_close($ch);

  if($final_url === false){
    http_response_code(502);
    throw new Exception("Failed resolve {$url} to final URL");
  }

  return $final_url;
}



// Convert stream title and URL into an ASX file
function create_asx($title, $url){
  return '<ASX version="3.0"><TITLE>' . htmlspecialchars($title) . '</TITLE><Entry><ref href="' . htmlspecialchars($url) . '"/></Entry></ASX>';
}



// Save stream in cache
function save_stream($id, $sc, $title, $url){
  $data = [];

  // Open the cache file in read/write mode; create if it doesn't exist
  if(($file = fopen(STREAM_CACHE_FILE, "c+")) === false){
    http_response_code(500);
    throw new RuntimeException("Failed to open stream cache file: " . STREAM_CACHE_FILE);
  }

  // Acquire an exclusive lock
  if(flock($file, LOCK_EX) === false){
    fclose($file);
    http_response_code(500);
    throw new RuntimeException("Failed to lock stream cache file: " . STREAM_CACHE_FILE);
  }

  try{

    // Read the existing cache file
    if(($size = filesize(STREAM_CACHE_FILE)) > 0){
      if(($data = json_decode(fread($file, $size), true)) === null){
        http_response_code(500);
        throw new Exception("Failed to read stream cache JSON file: " . json_last_error_msg());
      }
    }

    // Ensure the chipset hierarchy exists
    if(!isset($data[$sc])){
      $data[$sc] = [];
    }

    // Add or update the entry for this ID
    $data[$sc][$id] = ["title" => $title, "url" => $url];

    // Truncate the file and write the updated data
    ftruncate($file, 0);
    rewind($file);

    if(fwrite($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) === false){
      http_response_code(500);
      throw new RuntimeException("Failed to write stream cache file: " . STREAM_CACHE_FILE);
    }

  }finally{

    // Release the lock and close the file
    flock($file, LOCK_UN);
    fclose($file);

  }
}
