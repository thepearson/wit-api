<?php
define('WITXVFB', "/usr/local/bin/wit-capture-xvfb");
define('CONVERT', "/usr/bin/convert");

$dst_path = "/tmp/";
$key = "dcc42e8e-11ca-11e2-83d9-63d8f8d29f01";

/**
 * Outputs an image
 *
 * @param unknown_type $path
 */
function output_image($path, $in_broswer = TRUE) {
  header('Content-type: image/png');
  if ($in_broswer !== TRUE) {
    header('Content-Disposition: attachment; filename="' . basename($path). '"');
  }
  header('Content-Transfer-Encoding: binary');
  header('Accept-Ranges: bytes');
  print file_get_contents($path);
}

/**
 * Resizes an image
 *
 * @param unknown_type $path
 * @param unknown_type $size
 */
function resize_image($path, $size, $method = 'imagick') {
  if ($method == 'imagick') {
    $cmd = CONVERT . ' ' . $path . ' -resize ' . $size . '% ' . $path;
    exec($cmd);
    return TRUE;
  }
}

/**
 *
 * @param unknown_type $url
 * @param unknown_type $file
 * @param unknown_type $options
 */
function get_site_image($url, $file, $options = array()) {
  $cmd = WITXVFB . ' -s ' . escapeshellarg($url) . ' -o ' . escapeshellarg($file);

  if (isset($options['format'])) {
    $cmd .= ' -p ' . escapeshellarg($options['format']);
  }

  $dim = '';
  if (isset($options['width'])) {
    $dim = $options['width'];
  }

  if (isset($options['height'])) {
    $dim .= 'x' . $options['height'];
  }

  if ($dim != '') {
    $cmd .= ' -d ' . escapeshellarg($dim);
  }

  exec($cmd);
}

/**
 * Output 404 Not Found
 *
 * @param String $message
 */
function not_found($message = '') {
  header('HTTP/1.0 404 Not Found');
  print $message;
  exit;
}

$args = $_GET;
$headers = apache_request_headers();

if (!array_key_exists('key', $args)) {
  not_found('Invalid client key');
}

if ($args['key'] != $key) {
  not_found('Invalid client key');
}

if (array_key_exists('u', $args)) {
  $url = $args['u'];

  if (trim($url) == '') {
    not_found("Invalid URL");
  }
  $file_name = $url;


  $format = 'desktop';
  if (isset($args['format'])) {
    if (!in_array($args['format'], array('desktop', 'mobile'))) {
      not_found("Unknown format: " . $args['format']);
    }
    $format = $args['format'];
  }
  $file_name .= $format;


  $width = 1024;
  if (isset($args['width'])) {
    if (!is_numeric($args['width']) || ($args['width'] > 1600)) {
      not_found("Width is either invalid or too large");
    }
    $width = $args['width'];
  }
  $file_name .= $width;



  $height = NULL;
  if (isset($args['height'])) {
    if (!is_numeric($args['height'])) {
      not_found("Height is invalid");
    }
    $height = $args['height'];
  }
  $file_name .= $height;

  if (isset($args['resize'])) {
    if (!is_numeric($args['resize']) || ($args['resize'] > 200 || $args['resize'] < 1)) {
      not_found("Resize percentage is invalid must be between 1 and 200");
    }
    $file_name .= $args['resize'];
  }

  $options = array(
    'format' => $format,
    'width' => $width,
    'height' => $height,
  );

  $file = $dst_path . md5($file_name) . '.png';
  if (!file_exists($file)) {
    get_site_image($url, $file, $options);
    if (isset($args['resize'])) {
      resize_image($file, $args['resize']);
    }
  }
  else {
    if (filemtime($file) < (time()-(60*60))) {
      get_site_image($url, $file, $options);
      if (isset($args['resize'])) {
        resize_image($file, $args['resize']);
      }
    }
  }

  if (file_exists($file)) {
    output_image($file);
  }
  else {
    not_found("Error generating a Screenshot of the uri: [" . $url . "]");
  }
  exit;
}
