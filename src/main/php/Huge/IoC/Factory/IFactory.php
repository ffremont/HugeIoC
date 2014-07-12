<?php

namespace Huge\IoC\Factory;

use Huge\IoC\Scope;

interface IFactory {    
    public function __construct($className, $scope = Scope::ON_LOAD);
    public function create($classname);
    public function getScope();
}

