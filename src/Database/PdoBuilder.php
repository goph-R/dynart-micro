<?php

namespace Dynart\Micro\Database;

class PdoBuilder {

    /** @var string */
    protected $dsn;
    /** @var string */
    protected $username;
    /** @var string */
    protected $password;
    /** @var array */
    protected $options;

    public function dsn(string $value): PdoBuilder {
        $this->dsn = $value;
        return $this;
    }

    public function username(string $value): PdoBuilder {
        $this->username = $value;
        return $this;
    }

    public function password(string $value): PdoBuilder {
        $this->password = $value;
        return $this;
    }

    public function options(array $value): PdoBuilder {
        $this->options = $value;
        return $this;
    }

    public function build(): \PDO {
        return new \PDO($this->dsn, $this->username, $this->password, $this->options);
    }

}