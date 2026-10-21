<?php

/**
 * Минимальный клиент Ozon Performance API (рекламный кабинет,
 * https://docs.ozon.ru/api/performance/). Авторизация — OAuth2 client_credentials:
 * по Client ID и Client Secret выдаётся Bearer-токен на 30 минут.
 */
class OzonPerformanceClient
{
	const BASE_URL = 'https://api-performance.ozon.ru';

	private $clientId;
	private $clientSecret;
	private $timeout;
	private $token;
	private $tokenExpiresAt = 0;

	public function __construct($clientId, $clientSecret, $timeout = 30)
	{
		if ($clientId === '' || $clientSecret === '') {
			throw new InvalidArgumentException('Не заданы Client ID и/или Client Secret для Ozon Performance API');
		}
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->timeout = $timeout;
	}

	/**
	 * Создаёт клиент из переменных окружения OZON_PERFORMANCE_CLIENT_ID /
	 * OZON_PERFORMANCE_CLIENT_SECRET (или из файла .env в корне проекта).
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
		return new self((string) getenv('OZON_PERFORMANCE_CLIENT_ID'), (string) getenv('OZON_PERFORMANCE_CLIENT_SECRET'));
	}

	/**
	 * Возвращает действующий токен доступа, при необходимости запрашивая новый.
	 */
	public function getToken()
	{
		if ($this->token === null || time() >= $this->tokenExpiresAt) {
			$data = $this->send('POST', '/api/client/token', array(
				'client_id' => $this->clientId,
				'client_secret' => $this->clientSecret,
				'grant_type' => 'client_credentials',
			), false);
			$this->token = $data['access_token'];
			// Обновляем токен за минуту до истечения.
			$this->tokenExpiresAt = time() + $data['expires_in'] - 60;
		}
		return $this->token;
	}

	/**
	 * Выполняет запрос к методу API и возвращает декодированный JSON.
	 */
	public function request($httpMethod, $method, array $params = array())
	{
		return $this->send($httpMethod, $method, $params, true);
	}

	/**
	 * Список рекламных кампаний (GET /api/client/campaign).
	 */
	public function getCampaigns(array $params = array())
	{
		return $this->request('GET', '/api/client/campaign', $params);
	}

	private function send($httpMethod, $method, array $params, $auth)
	{
		$url = self::BASE_URL . $method;
		$headers = array('Content-Type: application/json');
		if ($auth) {
			$headers[] = 'Authorization: Bearer ' . $this->getToken();
		}

		$ch = curl_init();
		$options = array(
			CURLOPT_CUSTOMREQUEST => $httpMethod,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_HTTPHEADER => $headers,
		);
		if ($httpMethod === 'GET') {
			if ($params) {
				$url .= '?' . http_build_query($params);
			}
		} else {
			$options[CURLOPT_POSTFIELDS] = json_encode($params ?: new stdClass(), JSON_UNESCAPED_UNICODE);
		}
		$options[CURLOPT_URL] = $url;
		curl_setopt_array($ch, $options);

		$response = curl_exec($ch);
		if ($response === false) {
			$error = curl_error($ch);
			curl_close($ch);
			throw new RuntimeException('Ошибка соединения с Ozon Performance: ' . $error);
		}
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$data = json_decode($response, true);
		if ($status >= 400) {
			$message = isset($data['message']) ? $data['message'] : (isset($data['error']) ? $data['error'] : $response);
			throw new RuntimeException('Ozon Performance API вернул HTTP ' . $status . ': ' . $message, $status);
		}
		return $data;
	}
}
