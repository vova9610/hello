<?php

// Проверка подключения к Ozon Seller API: php ozon_check.php
require __DIR__ . '/src/OzonSellerClient.php';

try {
	$ozon = OzonSellerClient::fromConfig();
	$result = $ozon->productList(5);
	$total = $result['result']['total'] ?? 0;
	echo "Подключение к Ozon Seller API успешно. Товаров в магазине: $total\n";
	foreach ($result['result']['items'] ?? [] as $item) {
		echo " - {$item['offer_id']} (product_id {$item['product_id']})\n";
	}
} catch (Throwable $e) {
	fwrite(STDERR, $e->getMessage() . "\n");
	exit(1);
}
