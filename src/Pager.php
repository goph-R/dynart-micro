<?php

namespace Dynart\Micro;

class Pager {
    
    protected $page = 0;
    protected $limit = 25;
    protected $count;
    protected $max;
    protected $next;
    protected $prev;
    protected $start;
    protected $end;
    protected $hideLeft;
    protected $hideRight;
    protected $params;
    protected $route;

    public function __construct(string $route, array $params, int $count, int $pagerLimit=7) {
        $this->route = $route;
        $this->params = $params;
        $this->page = isset($params['page']) ? (int)$params['page'] : 0;
        $this->count = $count;
        $pageSize = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $this->max = ceil($this->count / $pageSize) - 1;
        if ($this->page > $this->max) {
            $this->page = $this->max;
        }
        if ($this->page < 0) {
            $this->page = 0;
        }
        $this->prev = $this->page != 0;
        $this->next = $this->page != $this->max;
        $this->calculateStartAndEnd($pagerLimit);
        $this->hideRight = $this->end < $this->max - 1;
        $this->hideLeft = $this->start > 1;  
    }
    
    protected function calculateStartAndEnd($pagerLimit) {
        $limit = floor($pagerLimit / 2);
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

    public function route() {
        return $this->route;
    }

    public function paramsForPage(int $page) {
        $params = $this->params;
        $params['page'] = $page;
        return $params;
    }    
        
    public function hasLeftHidden() {
        return $this->hideLeft;
    }
    
    public function hasRightHidden() {
        return $this->hideRight;
    }

    public function start() {
        return $this->start;
    }
    
    public function end() {
        return $this->end;
    }
    
    public function page() {
        return $this->page;
    }
    
    public function max() {
        return $this->max;
    }
    
    public function prev() {
        return $this->prev;
    }
    
    public function next() {
        return $this->next;
    }

    public function limit() {
        return $this->limit;
    }

    public function params() {
        return $this->params;
    }

}
