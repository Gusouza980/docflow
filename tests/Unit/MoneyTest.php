<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('validMoneyProvider')]
    public function test_it_converts_reais_to_cents(null|string|int|float $input, ?int $expected): void
    {
        $this->assertSame($expected, Money::toCents($input));
    }

    /**
     * @return array<string, array{0: null|string|int|float, 1: ?int}>
     */
    public static function validMoneyProvider(): array
    {
        return [
            'null' => [null, null],
            'empty string' => ['', null],
            'whitespace' => ['   ', null],
            'whole reais string' => ['1250', 125000],
            'whole reais int' => [1250, 125000],
            'decimal dot' => ['1250.5', 125050],
            'decimal comma' => ['1250,50', 125050],
            'br thousands' => ['1.250,50', 125050],
            'us thousands' => ['1,250.50', 125050],
            'with currency symbol' => ['R$ 1.250,50', 125050],
            'with currency symbol tight' => ['R$1.250,50', 125050],
            'br thousands without decimals' => ['1.250', 125000],
            'zero' => ['0', 0],
            'zero decimals' => ['0,00', 0],
            'float' => [10.5, 1050],
            'small cents' => ['0,01', 1],
        ];
    }

    #[DataProvider('invalidMoneyProvider')]
    public function test_it_rejects_invalid_money(string|float $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toCents($input);
    }

    /**
     * @return array<string, array{0: string|float}>
     */
    public static function invalidMoneyProvider(): array
    {
        return [
            'letters' => ['abc'],
            'mixed' => ['12ab'],
            'too many decimals' => ['1.234,567'],
            'negative string' => ['-10,00'],
            'negative int' => [-1],
        ];
    }

    public function test_try_to_cents_returns_null_on_invalid_input(): void
    {
        $this->assertNull(Money::tryToCents('abc'));
        $this->assertSame(125050, Money::tryToCents('1.250,50'));
    }
}
