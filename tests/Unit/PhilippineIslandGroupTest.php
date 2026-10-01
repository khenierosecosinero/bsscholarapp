<?php

namespace Tests\Unit;

use App\Support\PhilippineIslandGroup;
use PHPUnit\Framework\TestCase;

class PhilippineIslandGroupTest extends TestCase
{
    public function test_region_names_map_to_island_groups(): void
    {
        $this->assertSame(PhilippineIslandGroup::LUZON, PhilippineIslandGroup::fromRegionName('National Capital Region'));
        $this->assertSame(PhilippineIslandGroup::LUZON, PhilippineIslandGroup::fromRegionName('Central Luzon'));
        $this->assertSame(PhilippineIslandGroup::VISAYAS, PhilippineIslandGroup::fromRegionName('Central Visayas'));
        $this->assertSame(PhilippineIslandGroup::MINDANAO, PhilippineIslandGroup::fromRegionName('Caraga'));
        $this->assertSame(PhilippineIslandGroup::MINDANAO, PhilippineIslandGroup::fromRegionName('Northern Mindanao'));
    }

    public function test_psgc_prefixes_map_to_island_groups(): void
    {
        $this->assertSame(PhilippineIslandGroup::LUZON, PhilippineIslandGroup::fromPsgcCode('137404'));
        $this->assertSame(PhilippineIslandGroup::VISAYAS, PhilippineIslandGroup::fromPsgcCode('072217'));
        $this->assertSame(PhilippineIslandGroup::MINDANAO, PhilippineIslandGroup::fromPsgcCode('160209'));
    }
}
