<?php

namespace  Layout\Core\Contracts;

interface EventsDispatcher
{
    /**
     * Register an event listener with the dispatcher.
     *
     * @param  string $events
     * @param  mixed  $listener
     * @param  int    $priority
     * @return void
     */
    public function listen($event, $listener, $priority = 0);

    /**
     * Dispatch an event and call the listeners.
     *
     * @param  string $event
     * @param  mixed  $payload
     * @return void
     */
    public function fire($event, array $payload = []);
}
