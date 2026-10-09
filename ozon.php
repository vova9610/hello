<?php
	// Проверка подключения к Ozon Seller API: php ozon.php
	require __DIR__ . '/src/OzonSellerClient.php';

	try {
		$ozon = OzonSellerClient::fromEnv(__DIR__ . '/.env');

		$products = $ozon->productList(array(), 10);
		echo "Подключение успешно. Товаров всего: " . $products['result']['total'] . "\n";
		foreach ($products['result']['items'] as $item) {
			echo $item['product_id'] . "\t" . $item['offer_id'] . "\n";
		}
	} catch (Exception $e) {
		echo "Ошибка: " . $e->getMessage() . "\n";
		exit(1);
	}
