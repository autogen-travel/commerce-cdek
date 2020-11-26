<?php
$e = $modx->Event;

require_once(MODX_BASE_PATH . 'assets/plugins/commerce-cdek/cdek.class.php');
$modx->cdek = new commerceCDEK($params);
if(!$modx->cdek->token) {
	$modx->regClientScript('<script>alert(\'Проверьте учетные данные СДЭК или отключите плагин\');</script>');
}

switch ($e->name) {
	case 'OnOrderRawDataChanged': {

		if(isset($data['cdek_cityid'])) {
			//Если сменился город, то очищаем поля адреса и pvz
			if(isset($modx->cdek->getSession['cityid']) && $modx->cdek->getSession['cityid']!=intVal($data['cdek_cityid'])) {
				$modx->cdek->removeSession('address', '');
				$modx->cdek->removeSession('pvz_code', '');	
				$modx->cdek->setSession('cityid', intVal($data['cdek_cityid']));
			} else {
				if(isset($data['city'])) {
					$modx->cdek->setSession('cityname', $data['city']);
				}
				if(isset($data['address'])) {
					$modx->cdek->setSession('address', $data['address']);
					if(isset($data['cdek_pvz_code'])) {
						$data['address'] = $data['address'] .' ('. $data['cdek_pvz_code'].')';
					}
				}
				if(isset($data['cdek_cityid'])) {
					$modx->cdek->setSession('cityid', intVal($data['cdek_cityid']));
				}
				if(isset($data['cdek_pvz_code'])) {
					$modx->cdek->setSession('pvz_code', $data['cdek_pvz_code']);
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
		$modx->cdek->destroy();
		break;
	}

	case 'OnPageNotFound': {
        if (!empty($_GET['q']) && is_scalar($_GET['q']) && strpos($_GET['q'], 'cdek-cities') === 0) {
        	$query = trim($_GET['query']);
        	if(!preg_match("/[а-яё]/iu", $query)) {
        		$query = $modx->cdek->switcher($query);
        	}
            $result = $modx->cdek->findCity($query);
            echo $result;
            die();
        }
        break;
    }

	case 'OnCollectSubtotals': {
		$processor = $modx->commerce->loadProcessor();
		$method = $processor->getCurrentDelivery();
		$deliveryMethods = $modx->commerce->getDeliveries();
		
		if(!$method) break;

		if (!$modx->isBackend() && $processor->isOrderStarted()) {

			//Если выбран способ доставки которого нет в выбранном городе (при смене города)
			if(!isset($deliveryMethods[$method])) {
				//Назначаем первый из возможных
				$t_keys = array_keys($deliveryMethods);
				$method = array_shift($t_keys);
			}

			if(isset($deliveryMethods[$method])) {
				$params['total'] += $deliveryMethods[$method]['price']; 
				$params['rows'] = [];
				$params['rows'][$method] = [
					'title' => $deliveryMethods[$method]['title'],
					'price' => $deliveryMethods[$method]['price']
				];
			}
		}
		break;
	}

	case 'OnRegisterDelivery': {	
		$processor = $modx->commerce->loadProcessor();

		if($modx->isBackend()) {
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
			break;
		}

		
			$src = "<script type='text/javascript' src='assets/plugins/commerce-cdek/js/script.js'></script>";
			$src .= "<link type='text/css' href='assets/plugins/commerce-cdek/css/styles.css' rel='stylesheet'>";
			$modx->regClientScript($src);
		

			$method = $processor->getCurrentDelivery();
			$method = $method ? $method : 0;

			$deliveryMethods = $modx->commerce->getDeliveries();
			if(count($deliveryMethods)>0) break;

			$tariffs = $modx->cdek->calc();

			if(count($tariffs)>0) {
				if(!isset($modx->cdek->session['pvz_code']) && $method=='cdek_pvz') {
					$modx->setPlaceholder('address.value', 'не выбран');
				} else {
					$address = $modx->cdek->getSession('address');
					$modx->setPlaceholder('address.value', $address=='не выбран' ? '' : $address);
				}

				if(!isset($tariffs[$method])) {
					$t_keys = array_keys($tariffs);
					$method = array_shift($t_keys);
				}	

				foreach($tariffs as $code=>$tariff) {
					$params['rows'][$code] = [
						'title' => $tariff['name'],
						'price' => $tariff['delivery_sum'],
						'markup' => ($code === $method) ? $modx->cdek->renderActiveMethod($code) : $modx->cdek->renderMarkup($code)
					];
				}	
			} else {
				$params['rows']['individual'] = [
					'title' => 'Индивидуальные условия доставки',
					'price' => 0,
					'markup' => 'После оформления заказа, менеджер свяжется с Вами для уточнения вариантов доставки.'
				];
			}


		$modx->setPlaceholder('city.value', $modx->cdek->getSession('cityname'));	
		$modx->setPlaceholder('cdek_cityid.value', $modx->cdek->getSession('cityid'));
		$modx->setPlaceholder('cdek_pvz_code.value', $modx->cdek->getSession('pvz_code'));
		
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
