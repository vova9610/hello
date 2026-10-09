<?php

/**
 * Минимальный клиент Ozon Seller API (https://docs.ozon.ru/api/seller/).
 * Авторизация — заголовки Client-Id и Api-Key из личного кабинета:
 * Настройки → API ключи.
 */
class OzonSellerClient
{
	const BASE_URL = 'https://api-seller.ozon.ru';

	private $clientId;
	private $apiKey;

	public function __construct($clientId, $apiKey)
	{
		if ($clientId === '' || $apiKey === '') {
			throw new InvalidArgumentException('Не заданы OZON_CLIENT_ID и OZON_API_KEY');
		}
		$this->clientId = $clientId;
		$this->apiKey = $apiKey;
	}

	public static function fromEnv()
	{
		self::loadEnvFile(__DIR__ . '/../.env');
		return new self((string) getenv('OZON_CLIENT_ID'), (string) getenv('OZON_API_KEY'));
	}

	public function post($method, array $body = array())
	{
		$ch = curl_init(self::BASE_URL . $method);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTPHEADER => array(
				'Client-Id: ' . $this->clientId,
				'Api-Key: ' . $this->apiKey,
				'Content-Type: application/json',
			),
			CURLOPT_POSTFIELDS => json_encode((object) $body),
		));
		$response = curl_exec($ch);
		if ($response === false) {
			$error = curl_error($ch);
			curl_close($ch);
			throw new RuntimeException('Ошибка соединения с Ozon: ' . $error);
		}
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$data = json_decode($response, true);
		if ($status >= 400) {
			$message = isset($data['message']) ? $data['message'] : $response;
			throw new RuntimeException("Ozon API {$method} вернул HTTP {$status}: {$message}");
		}
		return $data;
	}

	public function getSellerInfo()
	{
		return $this->post('/v1/seller/info');
	}

	public function getProductList($limit = 10, $lastId = '')
	{
		return $this->post('/v3/product/list', array(
			'filter' => array('visibility' => 'ALL'),
			'last_id' => $lastId,
			'limit' => $limit,
		));
	}

	public static function loadEnvFile($path)
	{
		if (!is_readable($path)) {
			return;
		}
		foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			$line = trim($line);
			if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
				continue;
			}
			list($key, $value) = array_map('trim', explode('=', $line, 2));
			if (getenv($key) === false) {
				putenv($key . '=' . trim($value, "\"'"));
			}
		}
	}
}
