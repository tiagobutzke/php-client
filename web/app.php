<?php

use Symfony\Component\ClassLoader\ApcClassLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if ($_SERVER['REQUEST_URI'] === '/health/check/lb') {
    exit(0);
}

$loader = require_once __DIR__.'/../app/bootstrap.php.cache';

// Enable APC for autoloading to improve performance.
// You should change the ApcClassLoader first argument to a unique prefix
// in order to prevent cache key conflicts with other applications
// also using APC.
/*
$apcLoader = new ApcClassLoader(sha1(__FILE__), $loader);
$loader->unregister();
$apcLoader->register(true);
*/

require_once __DIR__.'/../app/AppKernel.php';
//require_once __DIR__.'/../app/AppCache.php';

$kernel = new AppKernel('prod', false);
$kernel->loadClassCache();
//$kernel = new AppCache($kernel);

// When using the HttpCache, you need to call the method in your front controller instead of relying on the configuration parameter
//Request::enableHttpMethodParameterOverride();
$request = Request::createFromGlobals();

//for forcing ssl
Request::setTrustedProxies([$request->server->get('REMOTE_ADDR')]);

$response = $kernel->handle($request);

if (Response::HTTP_NOT_FOUND === $response->getStatusCode()) {
    require __DIR__.'/../app/seo-redirects.php';
}

$response->send();
$kernel->terminate($request, $response);
