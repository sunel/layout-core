<?php

namespace  Layout\Core\Contracts;

interface Profiler
{
    public function start($key);

    public function stop($key);
}
