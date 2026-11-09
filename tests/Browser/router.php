<?php

// PHP's built-in server must return existing assets directly and route legacy
// .php URLs through Laravel. No reference PHP endpoints are served here.
$public = dirname(__DIR__, 2).'/public';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path !== '/' && is_file($public.$path)) {
    return false;
}
require $public.'/index.php';
