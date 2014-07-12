<?php

namespace Huge\IoC\Container;


interface IContainer {
        public function start();
        public function getBean($name);
}


