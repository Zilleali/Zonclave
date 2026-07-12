<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Psk;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

// Section 14 validation boundary: the single place that owns "what is a
// legal PSK" (WPA2 personal, 8 to 63 characters).
class PskTest extends TestCase
{
    public function test_accepts_minimum_length(): void
    {
        $this->assertSame('abcdefgh', Psk::fromString('abcdefgh')->value);
    }

    public function test_accepts_maximum_length(): void
    {
        $value = str_repeat('a', 63);

        $this->assertSame($value, Psk::fromString($value)->value);
    }

    public function test_rejects_below_minimum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Psk::fromString('abcdefg');
    }

    public function test_rejects_above_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Psk::fromString(str_repeat('a', 64));
    }

    public function test_rejects_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Psk::fromString('');
    }
}
