<?php

namespace Huge\IoC\Factory;

use Huge\IoC\Scope;
use Huge\IoC\RefBean;

class ConstructFactory implements IFactory {

    private $scope;
    private $args;

    public function __construct($args = array(), $scope = Scope::LAZY) {
        $this->scope = $scope;
        $this->args = $args;
    }

    public function create($ioc, $classname) {
        $bean = null;
        $values = array();
        foreach ($this->args as $arg) {
            if ($arg instanceof RefBean) {
                $values[] = $arg->getBean($ioc);
            } else {
                $values[] = $arg;
            }
        }

        if (empty($values)) {
            $bean = new $classname();
        } else {
            $nbArgs = count($values);
            switch ($nbArgs) {
                case 1 :
                    $bean = new $classname($values[0]);
                    break;
                case 2 :
                    $bean = new $classname($values[0], $values[1]);
                    break;
                case 3 :
                    $bean = new $classname($values[0], $values[1], $values[2]);
                    break;
                default:
                    $reflection_class = new \ReflectionClass($classname);
                    $bean = $reflection_class->newInstanceArgs($values);
            }
        }

        return $bean;
    }

    public function getScope() {
        return $this->scope;
    }

}
