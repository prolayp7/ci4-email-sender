<?php

namespace Tests\Services;

use App\Services\SmtpConfigService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class SmtpConfigServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = null;

    private function payload(): array
    {
        return [
            'label' => 'Gmail', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls',
            'username' => 'me@gmail.com', 'password' => 'app-password-secret', 'from_email' => 'me@gmail.com', 'from_name' => 'Me',
        ];
    }

    public function testSaveEncryptsPasswordAtRest(): void
    {
        (new SmtpConfigService())->save($this->payload());

        $row = $this->db->table('smtp_settings')->get()->getRowArray();
        $this->assertStringNotContainsString('app-password-secret', $row['password_encrypted']);
    }

    public function testGetActiveDecryptsPassword(): void
    {
        (new SmtpConfigService())->save($this->payload());

        $active = (new SmtpConfigService())->getActive();
        $this->assertSame('app-password-secret', $active['password']);
    }

    public function testGetActiveMaskedNeverExposesPassword(): void
    {
        (new SmtpConfigService())->save($this->payload());

        $masked = (new SmtpConfigService())->getActiveMasked();
        $this->assertArrayNotHasKey('password_encrypted', $masked);
        $this->assertSame('••••••••', $masked['password']);
    }

    public function testSavingNewConfigDeactivatesOldOne(): void
    {
        $service = new SmtpConfigService();
        $service->save($this->payload());
        $service->save(array_merge($this->payload(), ['label' => 'Custom', 'host' => 'smtp.other.com']));

        $this->assertSame(1, $this->db->table('smtp_settings')->where('is_active', 1)->countAllResults());
    }
}
