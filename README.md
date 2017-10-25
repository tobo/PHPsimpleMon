# PHPsimpleMon
Simple to use PHP based monitoring tool. Allows to monitor HTTP, HTTPS, SSL Certificates, TCP Ports and PING.

## Example script

```php
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
```

### Sample output

```
$ php example.php
google.com
  OK | HTTPS: URL = https://google.com | REDIRECTED_TO = https://www.google.de/?gfe_rd=asd | HTTP_CODE = 200 | IP = 74.125.205.94
  OK | CERT: VALID_TO = 2017-12-29 00:12:00 UTC | 64 days | ISSUER = Google Internet Authority G2 | CNS = *.google.com,*.android.com,*.appengine.google.com,*.cloud.google.com,*.db833953.google.cn,*.g.co,*.gcp.gvt2.com,*.google-analytics.com,*.google.ca,*.google.cl,*.google.co.in,*.google.co.jp,*.google.co.uk,*.google.com.ar,*.google.com.au,*.google.com.br,*.google.com.co,*.google.com.mx,*.google.com.tr,*.google.com.vn,*.google.de,*.google.es,*.google.fr,*.google.hu,*.google.it,*.google.nl,*.google.pl,*.google.pt,*.googleadapis.com,*.googleapis.cn,*.googlecommerce.com,*.googlevideo.com,*.gstatic.cn,*.gstatic.com,*.gvt1.com,*.gvt2.com,*.metric.gstatic.com,*.urchin.com,*.url.google.com,*.youtube-nocookie.com,*.youtube.com,*.youtubeeducation.com,*.yt.be,*.ytimg.com,android.clients.google.com,android.com,developer.android.google.cn,developers.android.google.cn,g.co,goo.gl,google-analytics.com,google.com,googlecommerce.com,source.android.google.cn,urchin.com,www.goo.gl,youtu.be,youtube.com,youtubeeducation.com,yt.be
127.0.0.1
  OK | PING: PING 127.0.0.1 (127.0.0.1): 56 data bytes | 64 bytes from 127.0.0.1: icmp_seq=0 ttl=64 time=0.039 ms | 1 packets transmitted, 1 packets received, 0.0% packet loss
$ echo $?
0
$
```

## Configuration

Following checks are possible:
* HTTP
* HTTPS
* TCP Port
* SSL Certificate validity
* PING

### More complex config example

```php
$config = [
  'hosts' => [
    'mywebsite.com' => [
      'checks' => [
        'http', // to use default settings a simple string can be used; HTTP port 80
        'https', // HTTPS port 443
        'cert' // Port 443
      ],
    ],
    'mysecondsite.com' => [
      'checks' => [
        ['type' => 'http',  'expect_content' => 'hello world'],     // HTTP on port 80; success if resulting HTML content contains "hello world"
        ['type' => 'http',  'port' => 8080, 'no_redirect' => 1, 'expect_httpcode' => [301, 302]],     // HTTP on port 8080; do not follow redirects; expect HTTP code 301 or 302 (which is a redirect)
        ['type' => 'https', 'port' => 4431, 'expect_httpcode' => 200],     // HTTPS on port 4431; success if return code is 200 (after potential redirects)
        ['type' => 'https', 'port' => 4432, 'ssl_no_verifiy' => 1],     // HTTPS on port 4432; no SSL verification
        ['type' => 'cert',  'port' => 4431, 'days' => 14]     // Check SSL connect on port 4431 and check certificate validity; check will fail if certificate is not valid for more than 14 days (default value)
      ],
    ],
    '192.168.0.123' => [
      'checks' => [
        ['type' => 'ping'],     // String notation 'ping' instead of the array could be used
        ['type' => 'port', 'port' => 22]     // Check connect on TCP port 22
      ],
    ],
  ]
];
```

## Open topics

* Finish config load from JSON encoded configuration file
* Make timeouts configurable
