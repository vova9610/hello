<?php
	require __DIR__ . '/src/OzonPerformanceClient.php';

	// Проверка подключения к Ozon Performance API: выводит рекламные кампании.
	try {
		$performance = OzonPerformanceClient::fromEnv();
		$campaigns = $performance->getCampaigns();
		echo "Подключение к Ozon Performance API установлено. Кампаний: " . count($campaigns['list']) . PHP_EOL;
		foreach ($campaigns['list'] as $campaign) {
			echo "  #" . $campaign['id'] . " " . $campaign['title'] . " — " . $campaign['state'] . PHP_EOL;
		}
	} catch (Exception $e) {
		echo "Ошибка: " . $e->getMessage() . PHP_EOL;
		exit(1);
	}
