<?php

namespace Tests\Unit;

use App\Enums\ActivityModule;
use App\Support\ActivityModules\ActivityModuleRegistry;
use PHPUnit\Framework\TestCase;

class ActivityModuleRegistryTest extends TestCase
{
    public function test_every_enum_case_has_a_registered_definition(): void
    {
        foreach (ActivityModule::cases() as $module) {
            $definition = ActivityModuleRegistry::definition($module);

            $this->assertSame($module, $definition->module);
            $this->assertNotSame('', $definition->label);
            $this->assertStringStartsWith('bi-', $definition->icon);
        }
    }

    public function test_keys_returns_every_module_value_in_display_order(): void
    {
        $this->assertSame(
            array_map(fn (ActivityModule $module) => $module->value, ActivityModule::cases()),
            ActivityModuleRegistry::keys()
        );
    }

    public function test_enum_label_and_icon_delegate_to_the_registry(): void
    {
        $definition = ActivityModuleRegistry::definition(ActivityModule::Lead);

        $this->assertSame($definition->label, ActivityModule::Lead->label());
        $this->assertSame($definition->icon, ActivityModule::Lead->icon());
    }

    public function test_every_registered_model_class_has_exactly_one_logging_registration_or_more(): void
    {
        $registeredModules = collect(ActivityModuleRegistry::loggingRegistrations())
            ->map(fn ($registration) => $registration->module->value)
            ->unique();

        // Every logging registration's module must itself be a known,
        // registered module definition — a typo'd module here would
        // otherwise silently log entries the feed can never render/link.
        foreach ($registeredModules as $moduleValue) {
            $this->assertContains($moduleValue, ActivityModuleRegistry::keys());
        }
    }
}
