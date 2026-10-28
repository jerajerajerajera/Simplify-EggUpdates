<?php

declare(strict_types=1);

namespace Simplify\EggUpdates\Services;

/** How an action on one egg ended, with a sentence for the admin. */
final readonly class Result
{
    public function __construct(
        public bool $ok,
        public string $message,
    ) {}
}
