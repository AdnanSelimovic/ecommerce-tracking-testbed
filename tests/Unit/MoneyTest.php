<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_formats_minor_units_without_floating_point(): void
    {
        $this->assertSame('0.00', Money::toDecimalString(0));
        $this->assertSame('0.05', Money::toDecimalString(5));
        $this->assertSame('1.00', Money::toDecimalString(100));
        $this->assertSame('129.00', Money::toDecimalString(12900));
        $this->assertSame('39.99', Money::toDecimalString(3999));
        $this->assertSame('-2.50', Money::toDecimalString(-250));
    }
}
