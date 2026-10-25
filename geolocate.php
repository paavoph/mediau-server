<?php

// This script provides geographical location, timezone and DST information to radios
//
// The output is a binary record: 
//
// typedef struct {
//   uint8_t  header[4];      // Format identifier: 6A 96 01 01
//   char     country[2];     // Two-letter ISO 3166 country code, eg "EE"
//   char     region[41];     // Region name, UTF-8
//   char     city[41];       // City name, UTF-8
// 
//   uint16_t offset_summer;  // DST offset in summer, minutes, eg 180=+3 hours
//   uint16_t offset_winter;  // DST offset in winter, minutes, eg 120=+2 hours
//
//   uint32_t dst_start;      // DST start, unix timestamp
//   uint32_t dst_end;        // DST end, unix timestamp
// 
//   char     timezone[105];  // IANA timezone identifier, eg "Europe/Tallinn"
// };


require_once("settings.php");
require_once("util.php");


error_handler(function(){

  // Check country code length
  if(strlen(GEOLOCATION_COUNTRY) !== 2){
    throw new Exception("Country code must be 2 bytes long");
  }

  // Construct the binary record
  $binary = hex2bin("6A960101")
          . GEOLOCATION_COUNTRY
          . pad_string(GEOLOCATION_REGION, 41)
          . pad_string(GEOLOCATION_CITY, 41)
          . pack("vvVV", ...dst_profile(GEOLOCATION_TIMEZONE))
          . pad_string(GEOLOCATION_TIMEZONE, 105);

  // Verify record length
  if(strlen($binary) !== 205){
    throw new Exception("Invalid binary record length: " . strlen($binary));
  }

  // Set headers for the response and output the record
  header("Content-Type: application/octet-stream");
  header("Content-Length: " . strlen($binary));
  echo $binary;


});



// Pad string with NULLs to the right to the desired length
function pad_string(string $value, int $length): string{
  if(strlen($value) > ($length-1)){
    throw new Exception("'{$value}' exceeds the max allowed length of {$length} bytes");
  }
  return str_pad($value, $length - 1, "\0", STR_PAD_RIGHT) . "\0";
}



// Auto-detect DST-related values for the provided timezone
function dst_profile(string $tz_id): array{

  $tz   = new DateTimeZone($tz_id);
  $year = (int)(new DateTimeImmutable("now", $tz))->format("Y");
  $utc  = new DateTimeZone("UTC");

  $start = (new DateTimeImmutable("$year-01-01", $utc))->getTimestamp();
  $end   = (new DateTimeImmutable(($year + 1) . "-01-01", $utc))->getTimestamp();


  // Get timezone transitions for the current year
  $transitions = $tz->getTransitions($start, $end);

  if($transitions === false || $transitions === []){
    throw new Exception("Timezone must provide transition data: {$tz_id}");
  }

  // Init variables for transition detection
  $dst_summer = null;
  $dst_winter = null;
  $dst_start = 0;
  $dst_end = 0;
  $previous = $transitions[0];

  // Loop through transitions
  foreach($transitions as $index => $transition){

    // Record the offsets for DST and standard time (minutes)
    if($transition["isdst"]){
      $dst_summer ??= intdiv($transition["offset"], 60);
    }else{
      $dst_winter ??= intdiv($transition["offset"], 60);
    }

    // The initial state is not a transition, so skip it
    if($index === 0){
      continue;
    }

    // Transition into DST
    if(!$previous["isdst"] && $transition["isdst"]){
      $dst_winter = intdiv($previous["offset"],   60);
      $dst_summer = intdiv($transition["offset"], 60);
      $dst_start  = $transition["ts"];
      
    // Transition back to standard time
    }elseif($previous["isdst"] && !$transition["isdst"]){
      $dst_summer = intdiv($previous["offset"],   60);
      $dst_winter = intdiv($transition["offset"], 60);
      $dst_end = $transition["ts"];
    }

    $previous = $transition;
  }

  // Use the available offset for both fields if DST is not observed
  $dst_summer ??= $dst_winter ?? 0;
  $dst_winter ??= $dst_summer;

  // Check timestamps for sanity
  foreach([$dst_start, $dst_end] as $timestamp){
    if($timestamp < 0 || $timestamp > 0xFFFFFFFF){
      if($timestamp !== 0){
        throw new Exception("DST timestamp exceeds the unsigned 32-bit range");
      }
    }
  }

  return [$dst_summer, $dst_winter, $dst_start, $dst_end];
}
