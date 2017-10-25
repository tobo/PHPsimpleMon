<?php
require_once 'SimpleMon.php';

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

$mon = new tobo\SimpleMon($config);

$out = null;
$ret = $mon->run($out);

echo $out;

if ($ret) {
  exit(0);
} elseif ($ret === null) {
  exit(1);
} else {
  exit(2);
}
