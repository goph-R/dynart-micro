<?php

namespace Dynart\Micro;

class EventService {

    protected array $subscriptions = [];

    public function subscribeWithRef(string $event, &$callable): void {
        if (!array_key_exists($event, $this->subscriptions)) {
            $this->subscriptions[$event] = [];
        }
        $this->subscriptions[$event][] = $callable;
    }

    public function subscribe(string $event, $callable): void {
        $this->subscribeWithRef($event, $callable);
    }

    public function unsubscribe(string $event, &$callable): bool {
        if (!array_key_exists($event, $this->subscriptions)) {
            return false;
        }
        foreach ($this->subscriptions[$event] as $c) {
            if ($c === $callable) { // TODO: not working on arrays with same values
                $key = array_search($c, $this->subscriptions[$event]);
                unset($this->subscriptions[$event][$key]);
                if (empty($this->subscriptions[$event])) {
                    unset($this->subscriptions[$event]);
                }
                return true;
            }
        }
        return false;
    }

    public function emit(string $event, array $args): void {
        if (array_key_exists($event, $this->subscriptions)) {
            foreach ($this->subscriptions[$event] as $callable) {
                call_user_func_array(Micro::getCallable($callable), $args);
            }
        }
    }
}