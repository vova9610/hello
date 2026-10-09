<?php

/**
 * Минимальный клиент Ozon Performance API (рекламный кабинет,
 * https://docs.ozon.ru/api/performance/).
 * Авторизация — OAuth2 client_credentials: Client ID и Client Secret
 * из кабинета Performance → Настройки → API-ключи. Токен живёт 30 минут.
 */
class OzonPerformanceClient
{
	const BASE_URL = 'https://api-performance.ozon.ru';

	private $clientId;
	private $clientSecret;
	private $token;
	private $tokenExpiresAt = 0;

	public function __construct($clientId, $clientSecret)
	{
		if ($clientId === '' || $clientSecret === '') {
			throw new InvalidArgumentException('Не заданы OZON_PERFORMANCE_CLIENT_ID и OZON_PERFORMANCE_CLIENT_SECRET');
		}
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
	}

	public static function fromEnv()
	{
		OzonSellerClient::loadEnvFile(__DIR__ . '/../.env');
		return new self(
			(string) getenv('OZON_PERFORMANCE_CLIENT_ID'),
			(string) getenv('OZON_PERFORMANCE_CLIENT_SECRET')
		);
	}

	public function get($method, array $query = array())
	{
		$url = $method . ($query ? '?' . http_build_query($query) : '');
		return $this->request('GET', $url, null, true);
	}

	public function post($method, array $body = array())
	{
		return $this->request('POST', $method, $body, true);
	}

	public function getCampaigns()
	{
		return $this->get('/api/client/campaign');
	}

	private function getToken()
	{
		if ($this->token === null || time() >= $this->tokenExpiresAt) {
			$data = $this->request('POST', '/api/client/token', array(
				'client_id' => $this->clientId,
				'client_secret' => $this->clientSecret,
				'grant_type' => 'client_credentials',
			), false);
			$this->token = $data['access_token'];
			// Обновляем токен с запасом в минуту до истечения
			$this->tokenExpiresAt = time() + (int) $data['expires_in'] - 60;
		}
		return $this->token;
	}

	private function request($httpMethod, $method, $body, $auth)
	{
		$headers = array('Content-Type: application/json', 'Accept: application/json');
		if ($auth) {
			$headers[] = 'Authorization: Bearer ' . $this->getToken();
		}

		$ch = curl_init(self::BASE_URL . $method);
		curl_setopt_array($ch, array(
			CURLOPT_CUSTOMREQUEST => $httpMethod,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTPHEADER => $headers,
		));
		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode((object) $body));
		}
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
			$message = isset($data['message']) ? $data['message']
				: (isset($data['error']) ? $data['error'] : $response);
			throw new RuntimeException("Ozon Performance API {$method} вернул HTTP {$status}: {$message}");
		}
		return $data;
	}
}
