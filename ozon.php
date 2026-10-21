<?php
	require __DIR__ . '/src/OzonSellerClient.php';

	// Проверка подключения к Ozon Seller API: выводит число товаров продавца.
	try {
		$ozon = OzonSellerClient::fromEnv();
		$products = $ozon->getProducts(1);
		echo "Подключение к Ozon Seller API установлено. Товаров: " . $products['result']['total'] . PHP_EOL;
	} catch (Exception $e) {
		echo "Ошибка: " . $e->getMessage() . PHP_EOL;
		exit(1);
	}
