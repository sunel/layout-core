<?php

namespace  Layout\Core\Contracts;

interface EventsDispatcher
{
    public function listen($event, $listener, $priority = 0);

    public function fire($event, array $payload = []);
}
