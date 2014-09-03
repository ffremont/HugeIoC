<?php

namespace Huge\IoC\Factory;

use Huge\IoC\Scope;

interface IFactory {    
    public function __construct($className, $scope = Scope::LAZY);
    public function create($ioc, $classname);
    public function getScope();
}

