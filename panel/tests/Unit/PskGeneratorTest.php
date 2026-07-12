<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\PskGenerator;
use PHPUnit\Framework\TestCase;

// Section 14 generation standard: 24 chars, A-Za-z0-9, ambiguous
// characters excluded.
class PskGeneratorTest extends TestCase
{
    public function test_generates_24_characters(): void
    {
        $psk = (new PskGenerator)->generate();

        $this->assertSame(PskGenerator::LENGTH, strlen($psk->value));
    }

    public function test_output_is_alphanumeric_only(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $psk = (new PskGenerator)->generate();

            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{24}$/', $psk->value);
        }
    }

    public function test_excludes_ambiguous_characters(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $psk = (new PskGenerator)->generate();

            foreach (['0', 'O', '1', 'l', 'I'] as $ambiguous) {
                $this->assertStringNotContainsString($ambiguous, $psk->value);
            }
        }
    }

    public function test_charset_itself_contains_no_ambiguous_characters(): void
    {
        foreach (['0', 'O', '1', 'l', 'I'] as $ambiguous) {
            $this->assertStringNotContainsString($ambiguous, PskGenerator::CHARSET);
        }
    }

    public function test_generates_distinct_values(): void
    {
        $a = (new PskGenerator)->generate()->value;
        $b = (new PskGenerator)->generate()->value;

        $this->assertNotSame($a, $b);
    }
}
