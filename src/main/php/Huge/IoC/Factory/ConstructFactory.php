<?php

namespace Huge\IoC\Factory;

use Huge\IoC\Scope;
use Huge\IoC\RefBean;

class ConstructFactory implements IFactory{

    private $scope;
    private $args;
 
    public function __construct($args = array(), $scope = Scope::LAZY) {
        $this->scope = $scope;
        $this->args = $args;
    }

    public function create($classname) {
        $bean = null;
        $values = array();
        foreach($this->args as $arg){
            if($arg instanceof RefBean){
                $values[] = $arg->getBean();
            }else{
                $values[] = $arg;
            }
        }
        
        if(empty($values)){
            $bean = new $classname();
        }else{
            $reflection_class = new \ReflectionClass($classname);
            return $reflection_class->newInstanceArgs($values);
        }
        
        return $bean;
    }

    public function getScope() {
        return $this->scope;
    }
}

