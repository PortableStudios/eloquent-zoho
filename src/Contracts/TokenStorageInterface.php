<?php

namespace Portable\EloquentZoho\Contracts;

interface TokenStorageInterface
{
    public function get(): ?string;
    public function set(string $token): void;
}
