<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function numbers(): array
    {
        return [
            'national number' => ['70123456', '+22670123456'],
            'national number with spaces' => ['70 12 34 56', '+22670123456'],
            'old leading zero' => ['070123456', '+22670123456'],
            'country code with plus' => ['+226 70 12 34 56', '+22670123456'],
            'country code with 00' => ['00226 70 12 34 56', '+22670123456'],
            'country code without plus' => ['22670123456', '+22670123456'],
            'landline' => ['25 30 60 00', '+22625306000'],
            'foreign number keeps its country code' => ['+225 07 12 34 56 78', '+2250712345678'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_numbers_are_normalized_to_the_burkina_faso_format(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public function test_incomplete_numbers_do_not_pass_validation(): void
    {
        $this->assertDoesNotMatchRegularExpression(PhoneNumber::VALIDATION_REGEX, PhoneNumber::normalize('70 12 34'));
        $this->assertMatchesRegularExpression(PhoneNumber::VALIDATION_REGEX, PhoneNumber::normalize('70 12 34 56'));
    }
}
