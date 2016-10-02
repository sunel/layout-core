<?php

namespace  Layout\Core\Contracts;

interface ConfigResolver
{
    public function get($key, $default = null);
}
