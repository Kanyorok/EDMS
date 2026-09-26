<?php

namespace Tests\Unit\Core;

use App\Services\Core\ModuleService;
use PHPUnit\Framework\TestCase;

class ModuleServiceIconTest extends TestCase
{
    public function test_it_wraps_legacy_icon_classes_in_an_icon_element(): void
    {
        $this->assertSame(
            '<i class="fa fa-circle" aria-hidden="true"></i>',
            ModuleService::renderModuleIcon('fa fa-circle-o')
        );
    }

    public function test_it_provides_a_contextual_fallback_icon(): void
    {
        $this->assertSame(
            '<i data-feather="folder" aria-hidden="true"></i>',
            ModuleService::renderModuleIcon(null, 'Document Management')
        );
    }
}
