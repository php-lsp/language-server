<?php

declare(strict_types=1);

namespace Playground;

/**
 * Status enum for testing enum completion and references.
 */
enum StatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
    case Suspended = 'suspended';

    /**
     * Check if the status is active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
