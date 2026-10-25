<?php

// Usage: favorites.php
// This script returns manually configured favorites, formatted for radios with MediaU connection.
// At the moment, it contains no customizations to return different favorites for different devices,
// but such functionality can be implemented by observing the MAC addresses of quering devices
// present in the GET parameters of incoming rquests.

require_once("settings.php");
require_once("util.php");


error_handler(function(){

  // TODO: the radio provides at least language and its MAC address too as GET parameters:
  // LAN=eng&ID=&SER1=&SER2=A09F10D5B5DB

  // Load and decode the JSON file
  if(($json = json_decode(file_get_contents(FAVORITES_FILE), true)) === null){
    http_response_code(500);
    throw new Exception("Failed to read favorites JSON file: " . json_last_error_msg());
  }

  // Start output buffering
  ob_start();

  // Loop through the groups and their items
  foreach($json as $group => $items){
    echo "T,O,0,*{*{" . $group . "}*}*,<br>";

    foreach($items as $title => $url){
      echo "S,O,0,*{*{" . $title . "}*}*," . $url . "<br>";
    }
  }

  // Output the data
  header("Content-Type: text/html");
  header("Content-Length: " . ob_get_length());
  ob_end_flush();

});
