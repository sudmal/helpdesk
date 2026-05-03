<?php

namespace App\Services;

use Illuminate\Support\Facades\{Http, Cache, Log};

class LanBillingService
{
    private string $url;
    private string $login;
    private string $password;

    public function __construct()
    {
        $this->url      = config('lanbilling.url');
        $this->login    = config('lanbilling.login');
        $this->password = config('lanbilling.password');
    }

    /**
     * Поиск абонента по номеру телефона
     */
    public function findByPhone(string $phone): ?array
    {
        $phone = preg_replace('/\D/', '', $phone);
        return $this->request('getAccountByPhone', ['phone' => $phone]);
    }

    /**
     * Поиск абонента по номеру договора
     */
    public function findByContract(string $contract): ?array
    {
        return $this->request('getAccountByContract', ['contract' => $contract]);
    }

    /**
     * Запрос к API LANBilling (JSON-RPC)
     */
    private function request(string $method, array $params): ?array
    {
        $cacheKey = "lanbilling:{$method}:" . md5(serialize($params));

        return Cache::remember($cacheKey, 300, function () use ($method, $params) {
            try {
                $response = Http::timeout(10)
                    ->withBasicAuth($this->login, $this->password)
                    ->post($this->url, [
                        'jsonrpc' => '2.0',
                        'method'  => $method,
                        'params'  => $params,
                        'id'      => 1,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['result'] ?? null;
                }
            } catch (\Exception $e) {
                Log::warning("LANBilling API error [{$method}]: " . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Форматировать ответ биллинга в структуру адреса
     */
    public function mapToAddress(array $data): array
    {
        return [
            'subscriber_name' => $data['fio'] ?? $data['name'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'contract_no'     => $data['agreement'] ?? $data['contract'] ?? null,
            'lanbilling_id'   => (string) ($data['id'] ?? ''),
            'city'            => $data['address']['city'] ?? null,
            'street'          => $data['address']['street'] ?? null,
            'building'        => $data['address']['house'] ?? null,
            'apartment'       => $data['address']['flat'] ?? null,
            'lanbilling_data' => $data,
        ];
    }
}
