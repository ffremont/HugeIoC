<?php

namespace Huge\IoC\Factory;


interface ILogFactory {
    /**
     * 
     * @param string $name
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger($name);
}

