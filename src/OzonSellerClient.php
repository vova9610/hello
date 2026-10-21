<?php

/**
 * Минимальный клиент Ozon Seller API (https://docs.ozon.ru/api/seller/).
 * Авторизация — заголовки Client-Id и Api-Key из личного кабинета продавца
 * (Настройки → Seller API).
 */
class OzonSellerClient
{
	const BASE_URL = 'https://api-seller.ozon.ru';

	private $clientId;
	private $apiKey;
	private $timeout;

	public function __construct($clientId, $apiKey, $timeout = 30)
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
	 * (или из файла .env в корне проекта, если переменные не заданы).
	 */
	public static function fromEnv($envFile = null)
	{
		$envFile = $envFile ?: dirname(__DIR__) . '/.env';
		if (is_readable($envFile)) {
			foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
				if ($line[0] === '#' || strpos($line, '=') === false) {
					continue;
				}
				list($name, $value) = array_map('trim', explode('=', $line, 2));
				if (getenv($name) === false) {
					putenv($name . '=' . trim($value, "\"'"));
				}
			}
		}
		return new self((string) getenv('OZON_CLIENT_ID'), (string) getenv('OZON_API_KEY'));
	}

	/**
	 * Выполняет POST-запрос к методу API и возвращает декодированный JSON.
	 */
	public function request($method, array $body = array())
	{
		$ch = curl_init(self::BASE_URL . $method);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_HTTPHEADER => array(
				'Client-Id: ' . $this->clientId,
				'Api-Key: ' . $this->apiKey,
				'Content-Type: application/json',
			),
			CURLOPT_POSTFIELDS => json_encode($body ?: new stdClass(), JSON_UNESCAPED_UNICODE),
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
			throw new RuntimeException('Ozon API вернул HTTP ' . $status . ': ' . $message, $status);
		}
		return $data;
	}

	/**
	 * Список товаров продавца (v3/product/list).
	 */
	public function getProducts($limit = 100, $lastId = '')
	{
		return $this->request('/v3/product/list', array(
			'filter' => array('visibility' => 'ALL'),
			'last_id' => $lastId,
			'limit' => $limit,
		));
	}
}
