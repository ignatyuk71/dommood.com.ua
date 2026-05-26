<?php

namespace Tests\Unit;

use App\Support\DateTime\KyivDateTime;
use Tests\TestCase;

class KyivDateTimeTest extends TestCase
{
    public function test_admin_datetime_input_is_treated_as_kyiv_time(): void
    {
        config([
            'app.timezone' => 'UTC',
            'app.display_timezone' => 'Europe/Kyiv',
        ]);

        $stored = KyivDateTime::fromAdminInput('2026-05-26T11:18');

        $this->assertSame('2026-05-26 08:18:00', $stored?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-26T11:18', KyivDateTime::input($stored));
    }
}
