<?php

require_once("settings.php");


// Get all JPG files in the folder
$images = glob(LOGO_CACHE_DIR . "/*.jpg");

// Sort filenames numerically
usort($images, function ($a, $b) {
  $a_int = (int)basename($a, ".jpg");
  $b_int = (int)basename($b, ".jpg");
  return $a_int - $b_int;
});


// Start the HTML document
echo '<!DOCTYPE html>';
echo '<html lang="en">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>Cached channels</title>';
echo '<link href="assets/bootstrap.min.css" rel="stylesheet">';

echo '<style>';
echo'  .card {';
echo '    min-width: 250px;';
echo '  }';
echo '  .card-header {';
echo '    display: flex;';
echo '    justify-content: center;';
echo '  }';
echo '</style>';

echo '</head>';
echo '<body class="bg-dark">';

echo '<div class="container mt-4">';
echo '<h1 class="text-center text-white mb-4">Cached channels</h1>';
echo '<div class="row">';


// Check if there are images
if($images){

  // Loop through each image and display it
  foreach($images as $image){
    $filename = basename($image, ".jpg");
    $filesize = filesize($image) / 1024; // in KB
    $modified = date("F j, Y, g:i a", filemtime($image));

    echo '<div class="col-sm-6 col-md-4 col-lg-3 mb-4">';
    echo '<div class="card">';

    // Card header (image part)
    echo '<div class="card-header">';
    echo '<img src="' . $image . '" class="bg-secondary border border-dark" width="208" height="160">';
    echo '</div>';

    // Card content (metadata part)
    echo '<div class="card-body pt-3">';
    echo '<div class="row">';
    echo '<div class="col-5"><strong>ID:</strong> ' . $filename . '</div>';
    echo '<div class="col-7 text-right"><strong>Size:</strong> ' . number_format($filesize, 2) . ' KB</div>';
    echo '</div>';
//    echo '<div class="row mt-2">';
//    echo '<div class="col-12"><strong>Last Modified:</strong><br/>' . $modified . '</div>';
//    echo '</div>';
    echo '</div>';  // End of card-body

    echo '</div>';  // End of card
    echo '</div>';  // End of column
  }
}else{
  echo '<p class="bg-warning px-2">No images found</p>';
}

echo '</div>';
echo '</div>';
echo '</body>';
echo '</html>';
