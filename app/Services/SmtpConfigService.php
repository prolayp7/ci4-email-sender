<?php

namespace App\Services;

class SmtpConfigService
{
    private function encrypter()
    {
        return \Config\Services::encrypter();
    }

    public function save(array $data): int
    {
        $db = db_connect();
        $provider = $data['provider'] ?? 'custom';

        $db->table('smtp_settings')->where('is_active', 1)->update(['is_active' => 0]);

        $encrypted = base64_encode($this->encrypter()->encrypt($data['password']));

        $row = [
            'label'              => $data['label'],
            'host'               => $data['host'],
            'port'               => (int) $data['port'],
            'encryption'         => $data['encryption'],
            'username'           => $data['username'],
            'password_encrypted' => $encrypted,
            'from_email'         => $data['from_email'],
            'from_name'          => $data['from_name'],
            'is_active'          => 1,
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        $existing = $db->table('smtp_settings')->where('provider', $provider)->get()->getRowArray();
        if ($existing) {
            $db->table('smtp_settings')->where('id', $existing['id'])->update($row);
            return (int) $existing['id'];
        }

        $row['provider']   = $provider;
        $row['created_at'] = date('Y-m-d H:i:s');
        $db->table('smtp_settings')->insert($row);

        return (int) $db->insertID();
    }

    public function getByProviderMasked(string $provider): ?array
    {
        $row = db_connect()->table('smtp_settings')->where('provider', $provider)->get()->getRowArray();
        if (! $row) {
            return null;
        }

        unset($row['password_encrypted']);
        $row['password'] = '••••••••';

        return $row;
    }

    public function getAllMasked(): array
    {
        $providers = ['gmail', 'microsoft365', 'custom'];
        $result    = [];
        foreach ($providers as $provider) {
            $result[$provider] = $this->getByProviderMasked($provider);
        }

        return $result;
    }

    public function getActive(): ?array
    {
        $row = db_connect()->table('smtp_settings')->where('is_active', 1)->get()->getRowArray();
        if (! $row) {
            return null;
        }

        $row['password'] = $this->encrypter()->decrypt(base64_decode($row['password_encrypted']));
        unset($row['password_encrypted']);

        return $row;
    }

    public function getActiveMasked(): ?array
    {
        $row = db_connect()->table('smtp_settings')->where('is_active', 1)->get()->getRowArray();
        if (! $row) {
            return null;
        }

        unset($row['password_encrypted']);
        $row['password'] = '••••••••';

        return $row;
    }
}
