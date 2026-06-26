<?php

namespace Tests\Unit;

use App\Support\DisplayFormat;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DisplayFormatTest extends TestCase
{
    public function test_it_formats_dates_in_brazilian_format(): void
    {
        $this->assertSame('26/06/2026', DisplayFormat::date('2026-06-26'));
        $this->assertSame('26/06/2026 14:30', DisplayFormat::dateTime('2026-06-26 14:30:00'));
        $this->assertNull(DisplayFormat::date(null));
    }

    public function test_it_accepts_carbon_instances(): void
    {
        $date = Carbon::parse('2026-12-01');

        $this->assertSame('01/12/2026', DisplayFormat::date($date));
    }
}
