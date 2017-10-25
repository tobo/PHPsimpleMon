<?php
namespace tobo;

class SimpleMon {
  private $nl = "\n";
  private $config = [];
  
  public function __construct($config = []) {
    $this->setConfig($config);
  }
  
  public function setConfig($config = []) {
    $this->config = $config;
    
    if (isset($config['config_file'])) {
      $this->loadConfigFromFile($config['config_file']);
    }
  }
  
  private function loadConfigFromFile($file) {
    if (! file_exists($file)) { return null; }
    try {
      $json = file_get_contents($file);
      $data = json_decode($json, true);
      if ($data) {
        // todo
      }
    } catch (Exception $ex) {
      // cannot read/json decode config file
    }
  }
  
  public function run(&$out = '') {
    if (! isset($this->config['hosts'])) { $out = 'No hosts' . $this->nl; return null; }
    $ret = true;
    ob_start();
    
    foreach ($this->config['hosts'] as $hostName => $hostConfig) {
      if (isset($hostConfig['disabled']) && $hostConfig['disabled']) {
        continue;
      }
      
      echo $hostName . $this->nl;
      
      $globalHostConfig = (isset($hostConfig['global']) && is_array($hostConfig['global'])) ? $hostConfig['global'] : [];
      
      if (isset($hostConfig['checks'])) {
        foreach ($hostConfig['checks'] as $check) {
          $_ret = $this->check($check, $hostName, $globalHostConfig);
          if (! $_ret) { $ret = false; }
        }
      }
    }
    
    $out = ob_get_clean();
    return $ret;
  }
  
  private function check($check, $target, $globalConfig) {
    if (is_string($check)) {
      $config = $globalConfig;
      $type   = strtolower($check);
    } elseif (is_array($check)) {
      $config = array_merge($globalConfig, $check);
      $type   = (isset($check['type']) && is_string($check['type'])) ? strtolower($check['type']) : null;
    } else {
      echo '  Check type not defined' . $this->nl;
      return null;
    }
    
    switch($type) {
      case 'http':
        return $this->checkCurl('http', $target, $config);
        break;
      case 'https':
        return $this->checkCurl('https', $target, $config);
        break;
      case 'cert':
        return $this->checkCert($target, $config);
        break;
      case 'tcp':
      case 'port':
        return $this->checkTcpPort($target, $config);
        break;
      case 'ping':
        return $this->checkPing($target, $config);
        break;
    }
    
    echo '  Unknown check type >' . $type . '<' . $this->nl;
    return null;
  }
  
  private function checkCurl($scheme, $host, $config) {
    $port = (isset($config['port'])) ? $config['port'] : false;
    $path = (isset($config['path'])) ? $config['path'] : false;
    $get  = (isset($config['get']))  ? $config['get']  : false;
    $post = (isset($config['post'])) ? $config['post'] : false;
    $sslNoVerify    = (isset($config['ssl_no_verifiy']))  ? boolval($config['ssl_no_verifiy']) : false;
    $noRedirect     = (isset($config['no_redirect']))     ? boolval($config['no_redirect'])    : false;
    $expectHttpCode = (isset($config['expect_httpcode'])) ? $config['expect_httpcode']         : (($noRedirect) ? [200, 301, 302] : [200]);
    $expectContent  = (isset($config['expect_content']))  ? $config['expect_content']          : false;
    $ua = (isset($config['user_agent'])) ? $config['user_agent'] : ((isset($config['ua'])) ? $config['ua'] : false);
    
    $url = $scheme . '://' . $host;
    
    if ($port) {
      $port = intval($port);
      if ($port > 0 && $port <= 65535) {
        $url .= ':' . $port;
      }
    }
    
    if ($path) {
      $url .= ((strpos($path, '/') !== 0) ? '/' : '') . $path;
    }
    
    $mainUrl = $url;
    
    $text = strtoupper($scheme) . ': URL = ' . $url;
    
    if (is_array($get)) {
      $url .= ((strpos($url, '?') === false) ? '?' : '') . http_build_query($get, null, '&', PHP_QUERY_RFC3986); // RFC3986: space="%20" and not "+"
    }
    
    $opts = [
      CURLOPT_URL => $url,
      CURLOPT_HEADER => 1,
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_FRESH_CONNECT => 1,
      CURLOPT_FORBID_REUSE => 1,
      CURLOPT_TIMEOUT => 8,
      CURLOPT_CONNECTTIMEOUT => 3
    ];
    
    if (! $noRedirect) {
      $opts = $opts + [ CURLOPT_FOLLOWLOCATION => 1 ];
    }
    
    if ($ua && is_string($ua) && ! empty($ua)) {
      $opts = $opts + [ CURLOPT_USERAGENT => $ua ];
    }
    
    if ($post) {
      $opts = $opts + [ CURLOPT_POST => 1, CURLOPT_POSTFIELDS => (is_array($post)) ? http_build_query($post) : $post ];
    }
    
    if ($sslNoVerify) {
      $opts = $opts + [ CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_SSL_VERIFYHOST => 0 ];
    } else {
      $opts = $opts + [ CURLOPT_SSL_VERIFYPEER => 1, CURLOPT_SSL_VERIFYHOST => 2 ];
    }
    
    $ch = curl_init();

    curl_setopt_array($ch, $opts);

    $error  = false;
    $info   = null;
    $result = curl_exec($ch);
    
    if (! empty($result)) {
      $info = curl_getinfo($ch);
      
      curl_close($ch);
      
      $ret = true;
      
      if (isset($info['url']) && ! empty($info['url']) && stripos($info['url'], $mainUrl) === false) {
        $text .= ' | REDIRECTED_TO = ' . $info['url'];
      }
      
      if (isset($info['redirect_url']) && ! empty($info['redirect_url'])) {
        $text .= ' | REDIRECT_TO = ' . $info['redirect_url'];
      }
      
      $httpCode = (isset($info['http_code'])) ? $info['http_code'] : false;
      if ($httpCode) {
        $text .= ' | HTTP_CODE = ' . $httpCode;
        
        if ($expectHttpCode) {
          if (is_string($expectHttpCode)) { $expectHttpCode = [$expectHttpCode]; }
          $_ret = false;
          foreach ($expectHttpCode as $code) {
            if ($code == $httpCode) { $_ret = true; break; }
          }
          if (! $_ret) {
            $ret = false;
            
            $text .= ' - FAIL - expected ' . implode(',', $expectHttpCode);
          }
        }
      }
      
      if ($expectContent && ! empty($expectContent)) {
        if (is_string($expectContent)) { $expectContent = [$expectContent]; }
        $_ret = false;
        $found = '';
        $eca = [];
        foreach ($expectContent as $content) {
          $c  = substr($content, 0, 20);
          $cs = '"' . $c . ((strlen($content) > strlen($c)) ? '...' : '') . '"';
          if (! $_ret) {
            if (stripos($result, $content) !== false) { 
              $found = $cs;
              $_ret = true;
            }
          }
          $eca[] = $cs;
        }
        if ($_ret) {
          $text .= ' | Content OK: found ' . $found;
        } else {
          $ret = false;

          $text .= ' | Content FAIL: expected ' . implode(',', $eca);
        }
      }
      
      $ip = (isset($info['primary_ip']) && ! empty($info['primary_ip'])) ? $info['primary_ip'] : false;
      if ($ip && strpos($host, $ip) === false) {
        $text .= ' | IP = ' . $ip;
      }
    } else {
      $error = curl_error($ch);

      curl_close($ch);
      
      $ret = false;
      
      $text .= ' | ' . $error;
    }
    
    if ($ret) {
      echo '  OK | ' . $text . $this->nl;
      
      return true;
    } else {
      echo '  FAIL | ' . $text . $this->nl;
      
      return false;
    }
  }
  
  private function checkTcpPort($host, $config) {
    $port = (isset($config['port'])) ? intval($config['port']) : false;
    
    if ($port && ($port <= 0 || $port > 65535)) { $port = false; }
    if (! $port) { echo '  FAIL | TCP_PORT: Invalid port' . $this->nl; return false; }
    
    $connectTimeout = 3;
    
    if($fp = @fsockopen($host, $port, $errCode, $errStr, $connectTimeout)) {
      fclose($fp);
      
      echo '  OK | TCP_PORT = ' . $port . $this->nl;
      
      return true;
    } else {
      echo '  FAIL | TCP_PORT = ' . $port . ' | ' . $errStr . ' (' . $errCode . ')' . $this->nl;
      
      return false;
    }
  }
  
  private function checkPing($host, $config) {
    $waittime = 1;
    
    $cmd = 'ping -c 1 -W ' . $waittime . ' ' . $host;
    
    $returnCode = $this->execOut($cmd, $out);
    
    //$out = trim(preg_replace('/[\r\n\s\t ]+/', ' ', trim($out)));
    
    $lines = explode("\n", trim($out));
    
    $out = '';
    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line)) { continue; }
      if (strpos($line, '---') !== false) { continue; }
      if (strpos($line, 'round-trip') !== false) { continue; }
      if (! empty($out)) { $out .= ' | '; }
      $out .= $line;
    }
    
    if ($returnCode === 0) {
      echo '  OK | PING: ' . $out . $this->nl;
      
      return true;
    } else {
      echo '  FAIL | PING: ' . $out . $this->nl;
      
      return false;
    }
  }
  
  private function checkCert($host, $config) {
    $port = (isset($config['port'])) ? intval($config['port']) : 443;
    
    if ($port && ($port <= 0 || $port > 65535)) { $port = false; }
    if (! $port) { echo '  FAIL | CERT: Invalid port' . $this->nl; return false; }
    
    $days = (isset($config['days'])) ? intval($config['days']) : 14;
    if ($days <= 0 || $days > 360 ) { $days = 14; }
    
    $timeout = 3;
    
    $context = stream_context_create(['ssl' => ['capture_peer_cert' => true]]);
    $client  = @stream_socket_client('ssl://' . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    
    // todo get warnings
    
    if (! $client) { 
      echo '  FAIL | CERT: ' . $errstr . ' (' . $errno . ')' . $this->nl; 
      var_dump($client); 
      return false;
    }
    
    $cert = stream_context_get_params($client);
    
    if (! $cert || ! isset($cert['options']) || ! isset($cert['options']['ssl']) || ! isset($cert['options']['ssl']['peer_certificate'])) { 
      echo '  FAIL | CERT: No certificate data' . $this->nl; return false; 
    }
    
    $a = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
    
    if (! $a) { echo '  FAIL | CERT: No certificate data' . $this->nl; return false; }
    
    date_default_timezone_set('UTC');
    
    $cert = [
      'serial'         => '',
      'hash'           => '',
      'cn'             => '',
      'cns'            => [], // cn + sans
      'sans'           => [], // als Subject Alternative Names
      'sans_org'       => '',
      'issuer'         => '',
      'valid_from'     => $a['validFrom_time_t'],
      'valid_to'       => $a['validTo_time_t'],
      'valid_from-iso' => date('Y-m-d H:m:s', $a['validFrom_time_t']),
      'valid_to-iso'   => date('Y-m-d H:m:s', $a['validTo_time_t'])
    ];
    
    if (isset($a['serialNumber'])) {
      $cert['serial'] = $a['serialNumber'];
    } else {
      // Log
    }
    
    if (isset($a['hash'])) {
      $cert['hash'] = $a['hash'];
    } else {
      // Log
    }
    
    if (isset($a['subject']['CN'])) {
      $cn = $a['subject']['CN'];
      $cert['cn'] = $cn;
      $cert['cns'][] = $cn;
    } else {
      // Log
    }
    
    if (isset($a['extensions']['subjectAltName'])) {
      $cert['sans_org'] = $a['extensions']['subjectAltName'];
      
      $ans = explode(',', $a['extensions']['subjectAltName']);
      foreach ($ans as $an) {
        // 1. Split by ":"
        $_an = explode(':', $an);
        if (count($_an) > 1) {
          $an = trim($_an[1]);
        } else {
          $an = trim($_an[0]);
        }
        
        // 2. Split by "="
        $_an = explode('=', $an);
        if (count($_an) > 1) {
          $an = trim($_an[1]);
        } else {
          $an = trim($_an[0]);
        }
        
        if (! in_array($an, $cert['sans'])) {
          $cert['sans'][] = $an;
        }
        if (! in_array($an, $cert['cns'])) {
          $cert['cns'][] = $an;
        }
      }
    } else {
      // Log
    }
    
    if (isset($a['issuer']['CN'])) {
      $cert['issuer'] = $a['issuer']['CN'];
    } else {
      // Log
    }
    
    $text = 'VALID_TO = ' . $cert['valid_to-iso'] . ' UTC';
    
    $now = time();
    $to  = $cert['valid_to'];
    
    $validDays = intval(($to - $now) / (3600*24));
    
    if ($validDays >= $days) {
      $ret = true;
      
      $text .= ' | ' . $validDays . ' days';
    } elseif ($validDays > 0) {
      $ret = false;
      
      $text .= ' | ' . $validDays . ' days (WARN=' . $days . ')';
    } else {
      $ret = false;
      
      $text .= ' | EXPIRED';
    }
    
    $text .= ' | ISSUER = ' . $cert['issuer'] . ' | CNS = ' . implode(',', $cert['cns']);
    
    if ($ret) {
      echo '  OK | CERT: ' . $text . $this->nl;
      
      return true;
    } else {
      echo '  FAIL | CERT: ' . $text . $this->nl;
      
      return false;
    }
  }
  
  private function execOut($cmd, &$out, $escape = true) {
    if ($escape) {
      $cmd = escapeshellcmd($cmd);
      $cmd = str_replace('!', '\!', $cmd);
    }

    ob_start();

    $returnCode = 0;
    $cmdRet = system($cmd, $returnCode);

    $out = ob_get_clean();

    return (($cmdRet !== false) ? $returnCode : false);
  }
}
