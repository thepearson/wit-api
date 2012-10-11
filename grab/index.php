<?php
define('WITXVFB', "/usr/local/bin/wit-capture-xvfb");
define('CONVERT', "/usr/bin/convert");
define('DRUPAL_ROOT', '../../www.thepearson.co');

require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
drupal_bootstrap(DRUPAL_BOOTSTRAP_VARIABLES);

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
 * @param String $path
 *     Image path
 * @param Integer $size
 *     Percentage to resize
 */
function resize_image($path, $size, $method = 'imagick') {
  if ($method == 'imagick') {
    $cmd = CONVERT . ' ' . $path . ' -resize ' . $size . '% ' . $path;
    exec($cmd);
    return TRUE;
  }
}

/**
 * Grabs an image of a site
 *
 * @param String $url
 *     Website URI
 * @param Srtring $file
 *     File to save as
 * @param array $options
 *     Options array
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
 * Lets ensure that the provideed key
 * is active and able to be used to grab images
 *
 * @param string $key
 *     User UUID
 */
function is_key_valid($key) {
  $query = db_select('node', 'n');
  $query->condition('n.type', 'application', '=')
    ->condition('n.status', 1, '=')
    ->condition('n.uuid', $key, '=')
    ->fields('n', array('nid'));

  $result = $query->execute();
  if (count($result->fetchAll()) > 0) {
    return TRUE;
  }
  return FALSE;
}


/**
 * Output 404 Not Found
 *
 * @param String $message
 *     Message to print to the user
 */
function not_found($message = '') {
  header('HTTP/1.0 404 Not Found');
  print $message;
  exit;
}

$dst_path = variable_get('api_grab_temp_dir', '/tmp');

if (!(bool)variable_get('api_grab_enabled', 1)) {
  not_found('The API is currently disabled');
}

if (!is_dir($dst_path) && is_writable($dst_path)) {
  not_found('The temporary directory is not writable');
}

$args = $_GET;
$headers = apache_request_headers();

if (!array_key_exists('key', $args)) {
  not_found('Invalid client key');
}

if (!is_key_valid($args['key'])) {
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

  $output = 'output';
  if (isset($args['output'])) {
    if (!in_array($args['output'], array('png', 'jpg'))) {
      not_found("Unknown output format: " . $args['output']);
    }
    $output = $args['output'];
  }

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

  $file = $dst_path . '/' . md5($file_name) . '.' . $output;
  if (!file_exists($file)) {
    get_site_image($url, $file, $options);
    if (isset($args['resize'])) {
      resize_image($file, $args['resize']);
    }
  }
  else {
    if (filemtime($url) < (time()-(60*60))) {
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
    print $url . "\n";
    print $file;
    not_found("Error generating a Screenshot of the uri: [" . $url . "]");
  }
  exit;
}
