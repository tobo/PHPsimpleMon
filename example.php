<?php
// Include SimpleMon class
require_once 'SimpleMon.php';

// Define config
$config = [
  'hosts' => [
    'google.com' => [
      'checks' => ['https', 'cert'],
    ],
    '127.0.0.1' => [
      'checks' => ['ping'],
    ],
  ]
];

// Create SimpleMon instance with config
$mon = new tobo\SimpleMon($config);

// Run monitoring checks
$out = null;
$ret = $mon->run($out);

echo $out;

// Set exit code for use on the command line depending on monitoring return
if ($ret) { // All checks were successful
  exit(0);
} elseif ($ret === null) { // No checks done
  exit(1);
} else { // Some checks failed
  exit(2);
}
