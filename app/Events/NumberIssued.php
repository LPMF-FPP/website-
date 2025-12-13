<?php

namespace App\Events;

class NumberIssued
{
    public function __construct(
        public readonly string $scope,
        public readonly string $number,
        public readonly array $ctx = [],
    ) {
    }
}

