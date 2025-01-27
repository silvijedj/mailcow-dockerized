<?php
// File size is limited by Nginx site to 10M
// To speed things up, we do not include prerequisites
header('Content-Type: text/plain');
require_once "vars.inc.php";
// Do not show errors, we log to using error_log
ini_set('error_reporting', 0);
// Init database
//$dsn = $database_type . ':host=' . $database_host . ';dbname=' . $database_name;
$dsn = $database_type . ":unix_socket=" . $database_sock . ";dbname=" . $database_name;
$opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
  $pdo = new PDO($dsn, $database_user, $database_pass, $opt);
}
catch (PDOException $e) {
  error_log("FOOTER: " . $e . PHP_EOL);
  http_response_code(501);
  exit;
}

if (!function_exists('getallheaders'))  {
  function getallheaders() {
    if (!is_array($_SERVER)) {
      return array();
    }
    $headers = array();
    foreach ($_SERVER as $name => $value) {
      if (substr($name, 0, 5) == 'HTTP_') {
        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
      }
    }
    return $headers;
  }
}

// Read headers
$headers = getallheaders();
// Get Domain
$domain = $headers['Domain'];
// Get Username
$username = $headers['Username'];
// Get From
$from = $headers['From'];
// define empty footer
$empty_footer = json_encode(array(
  'html' => '',
  'plain' => '',
  'vars' => array()
));

error_log("FOOTER: checking for domain " . $domain . ", user " . $username . " and address " . $from . PHP_EOL);

try {
  $stmt = $pdo->prepare("SELECT `plain`, `html`, `mbox_exclude` FROM `domain_wide_footer` 
    WHERE `domain` = :domain");
  $stmt->execute(array(
    ':domain' => $domain
  ));
  $footer = $stmt->fetch(PDO::FETCH_ASSOC);
  if (in_array($from, json_decode($footer['mbox_exclude']))){
    $footer = false;
  }
  if (empty($footer)){
    echo $empty_footer;
    exit;
  }
  error_log("FOOTER: " . json_encode($footer) . PHP_EOL);

  $stmt = $pdo->prepare("SELECT `custom_attributes` FROM `mailbox` WHERE `username` = :username");
  $stmt->execute(array(
    ':username' => $username
  ));
  $custom_attributes = $stmt->fetch(PDO::FETCH_ASSOC)['custom_attributes'];
  if (empty($custom_attributes)){
    $custom_attributes = (object)array();
  }
}
catch (Exception $e) {
  error_log("FOOTER: " . $e->getMessage() . PHP_EOL);
  http_response_code(502);
  exit;
}


// return footer
$footer["vars"] = $custom_attributes;
echo json_encode($footer);
