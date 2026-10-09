<?php

/**
 * Минимальный клиент Ozon Seller API.
 * Документация: https://docs.ozon.ru/api/seller/
 */
class OzonSellerClient
{
	private const BASE_URL = 'https://api-seller.ozon.ru';

	private string $clientId;
	private string $apiKey;
	private int $timeout;

	public function __construct(string $clientId, string $apiKey, int $timeout = 30)
	{
		if ($clientId === '' || $apiKey === '') {
			throw new InvalidArgumentException('Не заданы Client-Id и/или Api-Key для Ozon Seller API');
		}
		$this->clientId = $clientId;
		$this->apiKey = $apiKey;
		$this->timeout = $timeout;
	}

	/**
	 * Создаёт клиент из переменных окружения OZON_CLIENT_ID / OZON_API_KEY
	 * либо из файла config.php (см. config.example.php).
	 */
	public static function fromConfig(?string $configFile = null): self
	{
		$clientId = getenv('OZON_CLIENT_ID') ?: '';
		$apiKey = getenv('OZON_API_KEY') ?: '';

		$configFile = $configFile ?? __DIR__ . '/../config.php';
		if (($clientId === '' || $apiKey === '') && is_file($configFile)) {
			$config = require $configFile;
			$clientId = $clientId ?: (string)($config['ozon']['client_id'] ?? '');
			$apiKey = $apiKey ?: (string)($config['ozon']['api_key'] ?? '');
		}

		return new self($clientId, $apiKey);
	}

	/**
	 * Выполняет POST-запрос к методу API и возвращает декодированный JSON.
	 */
	public function request(string $method, array $body = []): array
	{
		$ch = curl_init(self::BASE_URL . $method);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode((object)$body, JSON_UNESCAPED_UNICODE),
			CURLOPT_HTTPHEADER => [
				'Client-Id: ' . $this->clientId,
				'Api-Key: ' . $this->apiKey,
				'Content-Type: application/json',
			],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $this->timeout,
		]);

		$response = curl_exec($ch);
		if ($response === false) {
			$error = curl_error($ch);
			curl_close($ch);
			throw new RuntimeException('Ошибка соединения с Ozon API: ' . $error);
		}
		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);

		$data = json_decode($response, true);
		if ($status >= 400) {
			$message = is_array($data) ? ($data['message'] ?? $response) : $response;
			throw new RuntimeException("Ozon API вернул HTTP $status: $message", $status);
		}

		return is_array($data) ? $data : [];
	}

	/**
	 * Список товаров продавца (/v3/product/list).
	 */
	public function productList(int $limit = 100, string $lastId = '', array $filter = []): array
	{
		return $this->request('/v3/product/list', [
			'filter' => (object)$filter,
			'last_id' => $lastId,
			'limit' => $limit,
		]);
	}
}
