<?php

namespace  Layout\Core\Contracts;

interface Cacheable
{
    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * Store an item in the cache.
     *
     * @param  string  $key
     * @param  mixed   $data
     * @param  \DateTimeInterface|\DateInterval|float|int  $minutes
     * @param array    $tags
     * @return void
     */
    public function put($key, $data, $time, $tags = []);

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed   $data
     * @param array    $tags
     * @return void
     */
    public function forever($key, $data, $tags = []);
}
