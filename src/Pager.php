<?php

namespace Dynart\Micro;

class Pager {

    protected int $page = 0;
    protected int $limit = 25;
    protected int $count;
    protected int $max;
    protected bool $next;
    protected bool $prev;
    protected int $start;
    protected int $end;
    protected bool $hideLeft;
    protected bool $hideRight;
    protected array $params;
    protected string $route;

    public function __construct(string $route, array $params, int $count, int $pagerLimit = 7) {
        $this->route = $route;
        $this->params = $params;
        $this->page = isset($params['page']) ? (int)$params['page'] : 0;
        $this->count = $count;
        $pageSize = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $this->max = (int)ceil($this->count / $pageSize) - 1;
        if ($this->page > $this->max) {
            $this->page = $this->max;
        }
        if ($this->page < 0) {
            $this->page = 0;
        }
        $this->prev = $this->page != 0;
        $this->next = $this->page != $this->max;
        $this->calculateStartAndEnd($pagerLimit);
        $this->hideLeft = $this->start > 1;
        $this->hideRight = $this->end < $this->max - 1;
    }

    protected function calculateStartAndEnd(int $pagerLimit): void {
        $limit = (int)floor($pagerLimit / 2);
        $this->start = $this->page - $limit;
        $add = 0;
        if ($this->start < 0) {
            $add = $limit - $this->page;
            $this->start = 0;
        }
        $this->end = $this->page + $limit + $add;
        $sub = 0;
        if ($this->end > $this->max) {
            $sub = $this->end - $this->max;
            $this->end = $this->max;
        }
        $this->start -= $sub;
        if ($this->start < 0) {
            $this->start = 0;
        }
    }

    public function route(): string {
        return $this->route;
    }

    public function paramsForPage(int $page): array {
        $params = $this->params;
        $params['page'] = $page;
        return $params;
    }

    public function hasLeftHidden(): bool {
        return $this->hideLeft;
    }

    public function hasRightHidden(): bool {
        return $this->hideRight;
    }

    public function start(): int {
        return $this->start;
    }

    public function end(): int {
        return $this->end;
    }

    public function page(): int {
        return $this->page;
    }

    public function max(): int {
        return $this->max;
    }

    public function prev(): bool {
        return $this->prev;
    }

    public function next(): bool {
        return $this->next;
    }

    public function params(): array {
        return $this->params;
    }
}
