<?php

/**
 * Минимальный клиент Ozon Seller API (https://docs.ozon.ru/api/seller/).
 *
 * Все методы API — POST с JSON-телом и заголовками Client-Id / Api-Key.
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
			throw new InvalidArgumentException('Не заданы Client-Id и Api-Key');
		}
		$this->clientId = (string)$clientId;
		$this->apiKey = (string)$apiKey;
		$this->timeout = (int)$timeout;
	}

	/**
	 * Создаёт клиент из переменных окружения OZON_CLIENT_ID / OZON_API_KEY,
	 * при необходимости подгружая их из файла .env.
	 */
	public static function fromEnv($envFile = null)
	{
		if ($envFile !== null && is_readable($envFile)) {
			foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
				$line = trim($line);
				if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
					continue;
				}
				list($name, $value) = array_map('trim', explode('=', $line, 2));
				if (getenv($name) === false) {
					putenv($name . '=' . trim($value, "\"'"));
				}
			}
		}
		return new self((string)getenv('OZON_CLIENT_ID'), (string)getenv('OZON_API_KEY'));
	}

	/**
	 * Выполняет запрос к произвольному методу API.
	 *
	 * @param string $path Путь метода, например '/v3/product/list'
	 * @param array  $body Тело запроса
	 * @return array Декодированный JSON-ответ
	 * @throws OzonApiException
	 */
	public function request($path, array $body = array())
	{
		$ch = curl_init(self::BASE_URL . $path);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_HTTPHEADER => array(
				'Client-Id: ' . $this->clientId,
				'Api-Key: ' . $this->apiKey,
				'Content-Type: application/json',
			),
			// Пустой массив должен уходить как {}, а не [].
			CURLOPT_POSTFIELDS => $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : '{}',
		));

		$response = curl_exec($ch);
		if ($response === false) {
			$error = curl_error($ch);
			curl_close($ch);
			throw new OzonApiException('Ошибка соединения: ' . $error, 0);
		}
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$data = json_decode($response, true);
		if ($status >= 400) {
			$message = isset($data['message']) ? $data['message'] : $response;
			throw new OzonApiException("Ozon API $path вернул HTTP $status: $message", $status, $data);
		}
		if (!is_array($data)) {
			throw new OzonApiException("Некорректный JSON в ответе $path", $status);
		}
		return $data;
	}

	/** Список товаров. */
	public function productList(array $filter = array(), $limit = 100, $lastId = '')
	{
		return $this->request('/v3/product/list', array(
			'filter' => $filter ?: new stdClass(),
			'limit' => $limit,
			'last_id' => $lastId,
		));
	}

	/** Подробная информация о товарах по offer_id, product_id или sku. */
	public function productInfoList(array $offerIds = array(), array $productIds = array(), array $skus = array())
	{
		return $this->request('/v3/product/info/list', array_filter(array(
			'offer_id' => $offerIds,
			'product_id' => $productIds,
			'sku' => $skus,
		)));
	}

	/** Остатки товаров. */
	public function productStocks(array $filter = array(), $limit = 100, $cursor = '')
	{
		return $this->request('/v4/product/info/stocks', array(
			'filter' => $filter ?: array('visibility' => 'ALL'),
			'limit' => $limit,
			'cursor' => $cursor,
		));
	}

	/** Цены товаров. */
	public function productPrices(array $filter = array(), $limit = 100, $cursor = '')
	{
		return $this->request('/v5/product/info/prices', array(
			'filter' => $filter ?: array('visibility' => 'ALL'),
			'limit' => $limit,
			'cursor' => $cursor,
		));
	}

	/** Список складов FBS/rFBS. */
	public function warehouseList()
	{
		return $this->request('/v1/warehouse/list');
	}

	/** Список отправлений FBS за период (даты в формате ISO 8601). */
	public function fbsPostingList($since, $to, $limit = 100, $offset = 0, array $filter = array())
	{
		return $this->request('/v3/posting/fbs/list', array(
			'dir' => 'ASC',
			'filter' => array_merge(array('since' => $since, 'to' => $to), $filter),
			'limit' => $limit,
			'offset' => $offset,
		));
	}
}

class OzonApiException extends RuntimeException
{
	private $response;

	public function __construct($message, $code = 0, $response = null)
	{
		parent::__construct($message, $code);
		$this->response = $response;
	}

	/** Тело ответа с ошибкой (code, message, details). */
	public function getResponse()
	{
		return $this->response;
	}
}
