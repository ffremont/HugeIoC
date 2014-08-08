<?php

namespace Huge\IoC\Container;


class DefaultIoC extends SuperIoC{
        public function __construct($name = '', $version = '1.0') {
            parent::__construct($name, $version);
        }
}


