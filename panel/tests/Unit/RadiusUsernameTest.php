<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\RadiusUsername;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

// Section 6/14 validation boundary for a manually-entered RADIUS username:
// the single place that owns "what is a legal RADIUS username".
class RadiusUsernameTest extends TestCase
{
    public function test_accepts_minimum_length(): void
    {
        $this->assertSame('abc', RadiusUsername::fromString('abc')->value);
    }

    public function test_accepts_maximum_length(): void
    {
        $value = str_repeat('a', 64);

        $this->assertSame($value, RadiusUsername::fromString($value)->value);
    }

    public function test_accepts_letters_numbers_underscores_and_hyphens(): void
    {
        $this->assertSame('SancoUk1-test_2', RadiusUsername::fromString('SancoUk1-test_2')->value);
    }

    public function test_rejects_below_minimum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RadiusUsername::fromString('ab');
    }

    public function test_rejects_above_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RadiusUsername::fromString(str_repeat('a', 65));
    }

    public function test_rejects_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RadiusUsername::fromString('');
    }

    public function test_rejects_spaces(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RadiusUsername::fromString('has a space');
    }

    public function test_rejects_special_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RadiusUsername::fromString('user@name');
    }
}
