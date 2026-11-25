<?php

namespace Tests;

use App\Models\ManagerSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function designatePrimaryManagerForLegacyTests(User $manager): void
    {
        ManagerSettings::query()
            ->whereKey(ManagerSettings::SINGLETON_ID)
            ->whereNull('principal_user_id')
            ->update(['principal_user_id' => $manager->getKey()]);
    }
}
