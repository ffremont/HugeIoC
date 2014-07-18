<?php

namespace Huge\IoC\SubClassCase\Data;

use Huge\IoC\Annotations\Component;

/**
 * @Component
 */
class Client extends Person{ 

    public function __construct() {
        parent::__construct();
    }

}

