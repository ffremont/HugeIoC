<?php
    $loader = require(__DIR__.'/../../../vendor/autoload.php');
    
    $loader->add('Huge\IoC\\', 'src/test/php/');
    \Huge\IoC\Container\SuperIoC::registerLoader(array($loader, 'loadClass'));
    
    $GLOBALS['resourcesDir'] = __DIR__.'/../resources';
    