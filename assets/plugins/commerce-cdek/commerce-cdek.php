<?php
$e = $modx->Event;

if (!class_exists('commerceCDEK')) {
	require_once(MODX_BASE_PATH . 'assets/plugins/commerce-cdek/cdek.class.php');
}

$cdek = commerceCDEK::getInstance($params);
if(!$cdek->token) {
	$modx->regClientScript('<script>alert(\'Проверьте учетные данные СДЭК или отключите плагин\');</script>');
}



switch ($e->name) {
	case 'OnOrderRawDataChanged': {

		if(isset($data['cdek_cityid'])) {
			//Если сменился город, то очищаем поля адреса и pvz
			if($cdek->getSession('cityid')!=intVal($data['cdek_cityid'])) {
				$cdek->removeSession('address', '');
				$cdek->removeSession('pvz_code', '');	
				$cdek->setSession('cityid', intVal($data['cdek_cityid']));
			} else {
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
			if(isset($data['city_sdek'])) {
				$cdek->setSession('cityname', $data['city_sdek']);
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
		/*
		'order_id'  => $order_id,
        'values'    => &$values,
        'items'     => &$items,
        'fields'    => &$fields,
        'subtotals' => &$subtotals,
		*/


		//$modx->logEvent(123, 1, '<pre>'.print_r($items, true).'</pre>', 'СДЭК items');


        $cdek_tariffs = $cdek->calc();
		$fields = json_decode(stripslashes($fields), true);
		$result_array = array_merge($cdek_tariffs, $fields, $values);
		$delivery_method = $result_array['delivery_method'];
		$packages_order = $result_array[$delivery_method]['packages'];

		/* Если способ доставки НЕ СДЭК - выходим */
		if(!in_array($delivery_method, ['cdek_pvz', 'cdek_courier', 'cdek_postamat']) || $cdek->config['register']!=1) {
			$cdek->destroy();
			break;
		}
	
		$packages = [];
		/* Если не объединяются посылки, то для каждого товара отдельная упаковка */
		if($cdek->config['group']!=1) {
			$k=0;
			foreach($items as $hash=>$item) {
				for($i=0;$i<$item['count'];$i++) {
					$packages[] = [
						'number'=>'bar-'.$k,
						'items'=>[
							[
								'ware_key'=>$item['id'],
								'payment'=>[
									'value'=>$result_array['payment_method']=='on_delivery' ? $item['price'] : 0
								],
								'name'=>$item['name'],
								'cost'=>$item['price'],
								'amount'=>1,
								'weight'=>$packages_order[$k]['weight'],
								'url'=>$modx->makeUrl($item['id'], '', '', 'full')
							]
						],
						'height'=>$packages_order[$k]['height'],
						'length'=>$packages_order[$k]['length'],
						'width'=>$packages_order[$k]['width'],
						'weight'=>$packages_order[$k]['weight']
					];
					$k++;
				}
				
			}
		} else {
			/* Все товары одной посылкой */
			$package_items = [];
			foreach($items as $hash=>$item) {
				if(isset($cdek->config['weight_tv']) && !empty($cdek->config['weight_tv'])) {
					$weight_query = $modx->db->select('value', '[+prefix+]site_tmplvar_contentvalues', 'tmplvarid='.$cdek->config['weight_tv'].' AND contentid='.$item['id'], '', 1);
					if($modx->db->getRecordCount($weight_query)==0) {
						$weight = (isset($cdek->config['weight']) && is_numeric($cdek->config['weight']) && $cdek->config['weight'] > 0) ? intVal($cdek->config['weight']) : 0.5;
					} else {
						$weight = $modx->db->getValue($weight_query);
					}
				}
				$package_items[] = [
					'ware_key'=>$item['id'],
					'payment'=>[
						'value'=>$result_array['payment_method']=='on_delivery' ? $item['price'] : 0
					],
					'name'=>$item['name'],
					'cost'=>$item['price'],
					'amount'=>$item['count'],
					'weight'=>$weight_query,
					'url'=>$modx->makeUrl($item['id'], '', '', 'full')
				];
			}
			$packages[] = [
				'number'=>'bar-0',
				'items'=>$package_items,
				'height'=>$packages_order[$k]['height'],
				'length'=>$packages_order[$k]['length'],
				'width'=>$packages_order[$k]['width'],
				'weight'=>$packages_order[$k]['weight']
			];
		}

		$cdek_register = [
			'type'=>1,
			'number'=>$order_id,
			'tariff_code'=>$result_array[$delivery_method]['tariff_code'],
			'delivery_point'=>$result_array['cdek_pvz_code'],
			'recipient'=>[
				'name'=>$result_array['name'],
				'phones'=>[
					'number'=>'+'.preg_replace("/\D/", "", $result_array['phone'])
				]
			],
			'sender'=>[
				'name'=>$modx->getConfig('site_name')
			],
			'packages'=>$packages,
			'from_location'=>[
				'code'=>$result_array[$delivery_method]['from_cityid']
			],
			'to_location'=>[
				'code'=>$result_array[$delivery_method]['to_cityid'],
				'address'=>$result_array['address']
			],
		];
		
		//$modx->logEvent(123, 1, '<pre>'.print_r($result_array, true).'</pre>', 'СДЭК result_array');
		//$modx->logEvent(123, 1, '<pre>'.print_r($cdek_register, true).'</pre>', 'СДЭК register');

		$result = $cdek->registerOrder($cdek_register);
		$modx->logEvent(123, 1, '<pre>'.print_r($result, true).'</pre>', 'СДЭК register');

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
		
		$modx->setPlaceholder('city_sdek.value', $cdek->getSession('cityname'));	
		$modx->setPlaceholder('cdek_cityid.value', $cdek->getSession('cityid'));
		$modx->setPlaceholder('cdek_pvz_code.value', $cdek->getSession('pvz_code'));

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


		if(in_array($cdek->session['cityid'], $cdek->exclude_cities) || !isset($cdek->session['cityid']) || empty($cdek->session['cityid'])) break;

		$processor = $modx->commerce->loadProcessor();
	
		$method = $processor->getCurrentDelivery();
		$method = $method ? $method : 0;
	

		$tariffs = $cdek->calc();

		if(count($tariffs)>0) {
			if(!isset($cdek->session['pvz_code']) && $method=='cdek_pvz') {
				$modx->setPlaceholder('address.value', 'не выбран');
			} else {
				$address = $cdek->getSession('address');
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
					'markup' => ($code === $method) ? $cdek->renderActiveMethod($code) : $cdek->renderMarkup($code)
				];
			}	
		} 

		if(count($params['rows'])==0) {
			$params['rows']['individual'] = [
				'title' => 'Индивидуальные условия доставки',
				'price' => 0,
				'markup' => 'После оформления заказа, менеджер свяжется с Вами для уточнения вариантов доставки.'
			];
		}


		break;
	}

	case 'OnManagerBeforeOrdersListRender': {
        // добавляем столбец в таблицу заказов
        $params['columns']['city_sdek'] = [
            'title' => 'Город',
            'content' => function($data, $DL, $eDL) {
                return !empty($data['fields']['city_sdek']) ? $data['fields']['city_sdek'] : '';
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
        $params['groups']['payment_delivery']['fields']['city_sdek'] = [
            'title' => 'Город',
            'content' => function($data) {
                return !empty($data['fields']['city_sdek']) ? $data['fields']['city_sdek'] : '';
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
        $params['fields']['city_sdek'] = [
            'title' => 'Город',
            'content' => function($data) {
                $value = !empty($data['fields']['city_sdek']) ? $data['fields']['city_sdek'] : '';
                return '<input type="text" class="form-control" name="order[fields][city_sdek]" value="' . htmlentities($value) . '">';
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
