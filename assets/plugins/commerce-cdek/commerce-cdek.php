<?php
$e = $modx->Event;

require_once(MODX_BASE_PATH . 'assets/plugins/commerce-cdek/cdek.class.php');
$cdek = new commerceCDEK($params);
if(!$cdek->token) {
	$modx->regClientScript('<script>alert(\'Проверьте учетные данные СДЭК или отключите плагин\');</script>');
}

switch ($e->name) {
	case 'OnOrderRawDataChanged': {
		if(isset($data['cdek_cityid'])) {
			//Если сменился город, то очищаем поля адреса и pvz
			if(isset($cdek->session['cityid']) && $cdek->session['cityid']!=intVal($data['cdek_cityid'])) {
				$cdek->removeSession('address', '');
				$cdek->removeSession('pvz_code', '');	
				$cdek->setSession('cityid', intVal($data['cdek_cityid']));
			} else {
				if(isset($data['city'])) {
					$cdek->setSession('cityname', $data['city']);
				}
				if(isset($data['address'])) {
					$cdek->setSession('address', $data['address']);
					if(isset($data['cdek_pvz_code'])) {
						$data['address'] = $data['address'] .' ('. $data['cdek_pvz_code'].')';
					}
				}
				if(isset($data['cdek_cityid'])) {
					$cdek->setSession('cityid', intVal($data['cdek_cityid']));
				}
				if(isset($data['cdek_pvz_code'])) {
					$cdek->setSession('pvz_code', $data['cdek_pvz_code']);
				}
			}	
		}
		break;
	}

	case 'OnBeforeOrderProcessing': {
		$processor = $modx->commerce->loadProcessor();
		$method = $processor->getCurrentDelivery();
		$fl = $params['FL'];
		$rules = $fl->getValidationRules();
		$fields = $fl->getFormData('fields');

		switch($method) {
			case 'cdek_courier':
				if(!isset($fields['address']) || empty(trim($fields['address']))) {
					$fl->addError('address', '_required', 'Укажите адрес доставки');
					$params['prevent'] = true;
				}
			break;

			case 'cdek_pvz':
				if(!isset($fields['cdek_pvz_code']) || empty(trim($fields['cdek_pvz_code']))) {
					$fl->addError('address', '_required', 'Не выбран пункт самовывоза');
					$params['prevent'] = true;
				}
			break;
		}

		break;
	}

	case 'OnOrderSaved': {
		$cdek->destroy();
		break;
	}

	case 'OnPageNotFound': {
        if (!empty($_GET['q']) && is_scalar($_GET['q']) && strpos($_GET['q'], 'cdek-cities') === 0) {
        	$query = trim($_GET['query']);
        	if(!preg_match("/[а-яё]/iu", $query)) {
        		$query = $cdek->switcher($query);
        	}
            $result = $cdek->findCity($query);
            echo $result;
            die();
        }
        break;
    }

	case 'OnCollectSubtotals': {
		$processor = $modx->commerce->loadProcessor();
		$method = $processor->getCurrentDelivery();
		if(!$method) break;
		//Если выбран способ доставки, которого нет в других плагинах (значит CDEK)
		$otherMethods = $modx->commerce->getDeliveries();

		if ($processor->isOrderStarted()) {
			$tariffs = $cdek->calc();

			//Если выбран способ доставки которого нет в выбранном городе (при смене города)
			if(!isset($tariffs[$method]) && count($otherMethods)==0) {
				//Назначаем первый из возможных
				$t_keys = array_keys($tariffs);
				$method = array_shift($t_keys);
			}	

			$params['rows'][$method] = [
				'title' => $tariffs[$method]['name'],
				'price' => $tariffs[$method]['delivery_sum']
			];
			$params['total'] += $tariffs[$method]['delivery_sum']; 

		}
		break;
	}

	case 'OnRegisterDelivery': {	
		$processor = $modx->commerce->loadProcessor();

		if($modx->documentIdentifier == $modx->commerce->getSetting('order_page_id') || $processor->isOrderStarted()) {
			$src = "<script type='text/javascript' src='assets/plugins/commerce-cdek/js/script.js'></script>";
			$src .= "<link type='text/css' href='assets/plugins/commerce-cdek/css/styles.css' rel='stylesheet'>";
			$modx->regClientScript($src);

			$method = $processor->getCurrentDelivery();
			$method = $method ? $method : 0;

			$tariffs = $cdek->calc();

			$modx->setPlaceholder('city.value', $cdek->getSession('cityname'));	
			$modx->setPlaceholder('cdek_cityid.value', $cdek->getSession('cityid'));
			$modx->setPlaceholder('cdek_pvz_code.value', $cdek->getSession('pvz_code'));

			if(!isset($cdek->session['pvz_code']) && $method=='cdek_pvz') {
				$modx->setPlaceholder('address.value', 'не выбран');
			} else {
				$modx->setPlaceholder('address.value', $cdek->getSession('address'));
			}


			$otherMethods = $modx->commerce->getDeliveries();

			if(count($tariffs)>0) {
				//die(var_dump($method));
				if(!isset($tariffs[$method]) && count($otherMethods)==0) {
					$t_keys = array_keys($tariffs);
					$method = array_shift($t_keys);
				}	
				foreach($tariffs as $code=>$tariff) {
					$params['rows'][$code] = [
						'title' => $tariff['name'],
						'price' => $tariff['delivery_sum'],
						'markup' => ($code === $method) ? $cdek->renderActiveMethod($code) : $cdek->renderMarkup($code)
					];
				}
			}
		} else {	
			//Для админки
			$params['rows']['cdek_courier'] = [
				'title' => 'Доставка до двери',
				'price' => 0
			];
			$params['rows']['cdek_pvz'] = [
				'title' => 'Самовывоз из пункта выдачи',
				'price' => 0
			];
			$params['rows']['cdek_postamat'] = [
				'title' => 'Постамат',
				'price' => 0
			];
		}
		break;
	}

	case 'OnManagerBeforeOrdersListRender': {
        // добавляем столбец в таблицу заказов
        $params['columns']['city'] = [
            'title' => 'Город',
            'content' => function($data, $DL, $eDL) {
                return !empty($data['fields']['city']) ? $data['fields']['city'] : '';
            },
            'sort' => 50
        ];

    	$params['columns']['address'] = [
            'title' => 'Адрес',
            'content' => function($data, $DL, $eDL) {
            	return !empty($data['fields']['address']) ? $data['fields']['address'] : '';
            },
            'sort' => 51,
        ];
        
        break;
    }
        
    case 'OnManagerBeforeOrderRender': {
        // добавляем поле на страницу просмотра заказа
        $params['groups']['payment_delivery']['fields']['city'] = [
            'title' => 'Город',
            'content' => function($data) {
                return !empty($data['fields']['city']) ? $data['fields']['city'] : '';
            },
            'sort' => 20
        ];

        $params['groups']['payment_delivery']['fields']['address'] = [
            'title' => 'Адрес',
            'content' => function($data) {
            	if(!empty($data['fields']['address'])) {
            		return $data['fields']['address'];
            	}
            },
            'sort' => 20,
        ];
        
        break;
    }

    case 'OnManagerBeforeOrderEditRender': {
        $params['fields']['city'] = [
            'title' => 'Город',
            'content' => function($data) {
                $value = !empty($data['fields']['city']) ? $data['fields']['city'] : '';
                return '<input type="text" class="form-control" name="order[fields][city]" value="' . htmlentities($value) . '">';
            },
            'sort' => 40
        ];
        $params['fields']['address'] = [
            'title' => 'Адрес',
            'content' => function($data) {
                $value = !empty($data['fields']['address']) ? $data['fields']['address'] : '';
                return '<input type="text" class="form-control" name="order[fields][address]" value="' . htmlentities($value) . '">';
            },
            'sort' => 40
        ];
        break;
    }
}
