<?php
	require __DIR__ . '/src/OzonSellerClient.php';

	header('Content-Type: text/plain; charset=utf-8');

	try {
		$ozon = OzonSellerClient::fromEnv();
		$seller = $ozon->getSellerInfo();
		$products = $ozon->getProductList(10);

		echo "Подключение к Ozon Seller API успешно\n\n";
		echo "Продавец:\n";
		print_r($seller);
		echo "\nТовары (первые 10):\n";
		print_r($products);
	} catch (Exception $e) {
		http_response_code(500);
		echo 'Ошибка: ' . $e->getMessage() . "\n";
	}
