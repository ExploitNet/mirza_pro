<?php
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($scriptName));
if ($scriptDir === '.' || $scriptDir === '') {
    $scriptDir = '';
} elseif ($scriptDir !== '/') {
    $scriptDir = '/' . ltrim($scriptDir, '/');
    $scriptDir = rtrim($scriptDir, '/');
} else {
    $scriptDir = '/';
}
$basename = $scriptDir === '' ? '/' : $scriptDir;
$prefix = $basename === '/' ? '/' : $basename . '/';
$rootForApi = $basename === '/' ? '/' : rtrim(dirname($basename), '/');
if ($rootForApi === '' || $rootForApi === '.') {
    $rootForApi = '/';
}
$apiPath = $rootForApi === '/' ? '/api' : $rootForApi . '/api';
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
if (is_string($forwardedProto) && $forwardedProto !== '') {
    $scheme = explode(',', $forwardedProto)[0];
} elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
    $scheme = $_SERVER['REQUEST_SCHEME'];
} else {
    $https = $_SERVER['HTTPS'] ?? '';
    $scheme = (!empty($https) && $https !== 'off') ? 'https' : 'http';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$apiUrl = rtrim($scheme . '://' . $host, '/') . $apiPath;
$config = [
    'basename' => $basename,
    'prefix' => $prefix,
    'apiUrl' => $apiUrl,
];
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mirza Web App</title>
    <script src="./js/telegram-web-app.js"></script>
    <script>
      window.__APP_CONFIG__ = <?php echo json_encode($config, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script type="module" crossorigin src="./assets/index-C-2a0Dur.js"></script>
    <link rel="modulepreload" crossorigin href="./assets/vendor-CIGJ9g2q.js">
    <link rel="stylesheet" crossorigin href="./assets/index-BoHBsj0Z.css">
  </head>
  <body>
    <div id="root"></div>
  </body>
</html>