<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Services\WhatsApp\WhitelistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhitelistServiceTest extends TestCase
{
    use RefreshDatabase;

    private WhitelistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WhitelistService;
    }

    public function test_normalization_works()
    {
        $this->assertEquals('628123456789', $this->service->normalizePhoneNumber('08123456789'));
        $this->assertEquals('628123456789', $this->service->normalizePhoneNumber('628123456789@s.whatsapp.net'));
        $this->assertEquals('628123456789', $this->service->normalizePhoneNumber('+62 812-3456-789'));
    }

    public function test_super_admin_always_allowed()
    {
        $superAdmin = settings('notifications.whatsapp.admin_number', '6285956592404');
        $this->assertTrue($this->service->isAllowed($superAdmin));
        $this->assertTrue($this->service->isSuperAdmin($superAdmin));
    }

    public function test_whitelisted_number_is_allowed()
    {
        $phone = '08123456789';
        $normalized = '628123456789';

        $this->assertFalse($this->service->isAllowed($phone));

        $this->service->add($phone, 'Test User');

        $this->assertTrue($this->service->isAllowed($phone));
        $this->assertTrue($this->service->isAllowed($normalized));
        $this->assertFalse($this->service->isSuperAdmin($phone));
    }

    public function test_remove_whitelist()
    {
        $phone = '08123456789';
        $this->service->add($phone);
        $this->assertTrue($this->service->isAllowed($phone));

        $this->service->remove($phone);
        $this->assertFalse($this->service->isAllowed($phone));
    }
}
