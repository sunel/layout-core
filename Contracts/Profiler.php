<?php

namespace  Layout\Core\Contracts;

interface Profiler
{
    /**
     * Starts a measure
     *
     * @param string $key
     */
    public function start($key);

    /**
     * Stops a measure
     *
     * @param string $key
     */
    public function stop($key);
}
