<?php
	require __DIR__ . '/src/OzonSellerClient.php';
	require __DIR__ . '/src/OzonPerformanceClient.php';

	header('Content-Type: text/plain; charset=utf-8');

	try {
		$performance = OzonPerformanceClient::fromEnv();
		$campaigns = $performance->getCampaigns();

		echo "Подключение к Ozon Performance API успешно\n\n";
		echo "Рекламные кампании:\n";
		print_r($campaigns);
	} catch (Exception $e) {
		http_response_code(500);
		echo 'Ошибка: ' . $e->getMessage() . "\n";
	}
