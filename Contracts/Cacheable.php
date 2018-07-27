<?php

namespace  Layout\Core\Contracts;

interface Cacheable
{
    public function get($key, $default = null);

    public function put($key, $data, $time, $tags = []);

    public function forever($key, $data, $tags = []);
}
