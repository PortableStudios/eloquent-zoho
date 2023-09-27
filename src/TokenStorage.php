<?php

namespace Portable\EloquentZoho;

class TokenStorage
{
    protected mixed $driver = null;
    protected array $drivers = [
        'cache' => TokenStorage\Cache::class,
        'file' => TokenStorage\File::class,
    ];

    public function registerTokenDriver(string $alias, string $class)
    {
        $this->drivers[$alias] = $class;
    }

    public function get(): ?string
    {
        return $this->getDriver()->get();
    }

    public function set(string $token): void
    {
        $this->getDriver()->set($token);
    }

    protected function getDriver()
    {
        if (!$this->driver) {
            if (!isset($this->drivers[config('eloquent-zoho.token_driver')])) {
                throw new \Exception('Invalid token driver ' . config('eloquent-zoho.token_driver'));
            }

            $driverClass = $this->drivers[config('eloquent-zoho.token_driver')];
            $this->driver = new $driverClass();
        }

        return $this->driver;
    }
}
