<?php
define('WITXVFB', "/usr/local/bin/wit-capture-xvfb");
define('CONVERT', "/usr/bin/convert");

$dst_path = "/tmp/";
$key = "dcc42e8e-11ca-11e2-83d9-63d8f8d29f01";

/**
 *
 * @param unknown_type $path
 */
function output_image($path) {
  header('Content-type: image/png');
  header('Content-Disposition: attachment; filename="' . basename($path). '"');
  header('Content-Transfer-Encoding: binary');
  header('Accept-Ranges: bytes');
  print file_get_contents($path);
}


function resize_image($path, $size) {
  $cmd = CONVERT . ' ' . $path . ' -resize ' . $size . '% ' . $path;
  exec($cmd);
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

$args = $_GET;
$headers = apache_request_headers();

if (!array_key_exists('key', $args)) {
  header('404 Not Found');
  exit;
}

if ($args['key'] != $key) {
  header('404 Not Found');
  exit;
}

if (array_key_exists('u', $args)) {
  $url = $args['u'];
  $file_name = $url;

  $format = 'desktop';
  if (isset($args['format'])) {
    $format = $args['format'];
  }
  $file_name .= $format;

  $width = 1024;
  if (isset($args['width'])) {
    $width = $args['width'];
  }
  $file_name .= $width;

  $height = NULL;
  if (isset($args['height'])) {
    $height = $args['height'];
    $file_name .= $height;
  }

  if (isset($args['resize'])) {
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
    header('404 Not Found');
  }
  exit;
}
