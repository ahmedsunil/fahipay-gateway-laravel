<?php

namespace Fahipay\Gateway\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case UNKNOWN = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::UNKNOWN => 'Unknown',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isFailed(): bool
    {
        return in_array($this, [self::FAILED, self::CANCELLED]);
    }

    public static function fromString(string $status): self
    {
        return self::tryFrom(strtolower($status)) ?? self::UNKNOWN;
    }
}
