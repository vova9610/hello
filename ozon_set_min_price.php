<?php
	// Установка минимальной цены товаров Ozon без изменения текущей и зачёркнутой цены.
	// По умолчанию только показывает план; изменения отправляются с флагом --apply:
	//   php ozon_set_min_price.php --apply
	require __DIR__ . '/src/OzonSellerClient.php';

	$minPrices = array(
		'1_коричневая' => 1300, // доска 28,5×18,5, безубыточность ≈ 1250 ₽
		'2_коричневый' => 2150, // доска 36×28,5 «Дуб», безубыточность ≈ 2080 ₽
	);
	$apply = in_array('--apply', $argv, true);

	$ozon = OzonSellerClient::fromEnv();
	$current = $ozon->post('/v5/product/info/prices', array(
		'filter' => array('offer_id' => array_keys($minPrices), 'visibility' => 'ALL'),
		'limit' => 100,
	));

	$prices = array();
	foreach ($current['items'] as $item) {
		$p = $item['price'];
		$min = $minPrices[$item['offer_id']];
		if ($min > $p['price']) {
			echo "Пропуск {$item['offer_id']}: минимальная цена {$min} выше текущей {$p['price']}\n";
			continue;
		}
		echo "{$item['offer_id']}: цена {$p['price']}, зачёркнутая {$p['old_price']}, мин. {$p['min_price']} → {$min}\n";
		$prices[] = array(
			'offer_id' => $item['offer_id'],
			'price' => (string) $p['price'],
			'old_price' => (string) $p['old_price'],
			'min_price' => (string) $min,
			'currency_code' => 'RUB',
		);
	}

	if (!$apply) {
		echo "\nПробный запуск. Для применения: php ozon_set_min_price.php --apply\n";
		exit;
	}

	$result = $ozon->post('/v1/product/import/prices', array('prices' => $prices));
	foreach ($result['result'] as $row) {
		$status = $row['updated'] ? 'обновлено' : 'ошибка: ' . json_encode($row['errors'], JSON_UNESCAPED_UNICODE);
		echo "{$row['offer_id']}: {$status}\n";
	}
