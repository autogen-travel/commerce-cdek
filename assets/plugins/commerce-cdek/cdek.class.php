<?php

class commerceCDEK {

	private static $instances = [];
	protected function __clone() { }
	public function __wakeup() {
        throw new \Exception("Cannot unserialize a singleton.");
    }

	protected function __construct($config) {
		$this->evo = evolutionCMS();
		$this->config = $config;
		$this->token = $this->getToken();
		$this->session = $this->getSession();
		//1 - дверь-дверь
		//2 - дверь - ПВЗ
		//6 - дверь - постамат (список постаматов вовращает не для всех городов, какой-то глюк у СДЭКа)
		$this->delivery_modes = [1,2,3,4];
		$this->exclude_cities = [];
	}

	public static function getInstance($config): commerceCDEK {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static($config);
        }

        return self::$instances[$cls];
    }


	public function getSession($key='') {
		if(!isset($_SESSION['cdek'])) {
			$_SESSION['cdek'] = [
				'cityid'=>0
			];
		}

		if(empty($key)) {
			return $_SESSION['cdek'];
		} else {
			return isset($_SESSION['cdek'][$key]) ? $_SESSION['cdek'][$key] : '';
		}	
	}

	public function setSession($key, $val) {
		$_SESSION['cdek'][$key] = $val;
		$this->session = $_SESSION['cdek'];
		return true;
	}

	public function removeSession($key){
		$_SESSION['cdek'][$key] = '';
		unset($_SESSION['cdek'][$key]);
		$this->session = $_SESSION['cdek'];
		return true;
	}

	public function destroy() {
		$_SESSION['cdek'] = [];
		unset($_SESSION['cdek']);
		$this->session = false;
		return true;
	}

	public function switcher($value) {
		$converter = [
			'f' => 'а',	',' => 'б',	'd' => 'в',	'u' => 'г',	'l' => 'д',	't' => 'е',	'`' => 'ё',
			';' => 'ж',	'p' => 'з',	'b' => 'и',	'q' => 'й',	'r' => 'к',	'k' => 'л',	'v' => 'м',
			'y' => 'н',	'j' => 'о',	'g' => 'п',	'h' => 'р',	'c' => 'с',	'n' => 'т',	'e' => 'у',
			'a' => 'ф',	'[' => 'х',	'w' => 'ц',	'x' => 'ч',	'i' => 'ш',	'o' => 'щ',	'm' => 'ь',
			's' => 'ы',	']' => 'ъ',	"'" => "э",	'.' => 'ю',	'z' => 'я',					
	 
			'F' => 'А',	'<' => 'Б',	'D' => 'В',	'U' => 'Г',	'L' => 'Д',	'T' => 'Е',	'~' => 'Ё',
			':' => 'Ж',	'P' => 'З',	'B' => 'И',	'Q' => 'Й',	'R' => 'К',	'K' => 'Л',	'V' => 'М',
			'Y' => 'Н',	'J' => 'О',	'G' => 'П',	'H' => 'Р',	'C' => 'С',	'N' => 'Т',	'E' => 'У',
			'A' => 'Ф',	'{' => 'Х',	'W' => 'Ц',	'X' => 'Ч',	'I' => 'Ш',	'O' => 'Щ',	'M' => 'Ь',
			'S' => 'Ы',	'}' => 'Ъ',	'"' => 'Э',	'>' => 'Ю',	'Z' => 'Я',					
	 
			'@' => '"',	'#' => '№',	'$' => ';',	'^' => ':',	'&' => '?',	'/' => '.',	'?' => ',',
		];
		$value = strtr($value, $converter);
		return $value;
	}

	public function findCity($query) {
		$ch = curl_init();
		$url = 'http://api.cdek.ru/city/getListByTerm/jsonp.php' . '?' . http_build_query(['q'=>$query]);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		curl_close($ch);

		//$result = json_decode($result, true);
		return $result;
	}

	private function tpl($filename='') {
		if(!file_exists(MODX_BASE_PATH . 'assets/plugins/commerce-cdek/tpl/'.$filename.'.tpl') || empty($filename)) {
			return '';
		}
		return $this->evo->getTpl('@FILE:assets/plugins/commerce-cdek/tpl/'.$filename.'.tpl');
	}

	private function getPackages() {
		//Дефолтные значения
		$weight_default = (isset($this->config['weight']) && is_numeric($this->config['weight']) && $this->config['weight'] > 0) ? intVal($this->config['weight']) : 0.5;
		$length_default = (isset($this->config['length']) && is_numeric($this->config['length']) && $this->config['length'] > 0) ? intVal($this->config['length']) : 25;
		$width_default = (isset($this->config['width']) && is_numeric($this->config['width']) && $this->config['width'] > 0) ? intVal($this->config['width']) : 25;
		$height_default = (isset($this->config['height']) && is_numeric($this->config['height']) && $this->config['height'] > 0) ? intVal($this->config['height']) : 25;

		$packages = [];

		//Суммируем параметры всех товаров
		$weight = 0;
		$cart = ci()->carts->getCart('products');
		$items = $cart->getItems();
		
		$docs = [];
		foreach($items as $k=>$item) {
			$docs[$k] = ['id'=>$item['id'], 'count'=>$item['count']];	
		}

		//$this->evo->logEvent(123, 1, '<pre>'.print_r($docs,true).'</pre>', 'СДЭК getPackages');

		$tvids = [];
		if(isset($this->config['weight_tv']) && !empty($this->config['weight_tv'])) {
			$tvids[] = $this->config['weight_tv'];
		}
		if(isset($this->config['length_tv']) && !empty($this->config['length_tv'])) {
			$tvids[] = $this->config['length_tv'];
		}
		if(isset($this->config['width_tv']) && !empty($this->config['width_tv'])) {
			$tvids[] = $this->config['width_tv'];
		}
		if(isset($this->config['height_tv']) && !empty($this->config['height_tv'])) {
			$tvids[] = $this->config['height_tv'];
		}

		if(count($tvids) > 0) {
			foreach($docs as $k=>$item) {
				$id = $item['id'];
				$count = $item['count'];
				$q = $this->evo->db->select('contentid, tmplvarid, value', '[+prefix+]site_tmplvar_contentvalues', 'contentid = '.$id.' AND tmplvarid IN ('.implode(',', $tvids).')');
				while($row = $this->evo->db->getRow($q)) {
					for($i=0;$i<$count;$i++) {
						switch($row['tmplvarid']) {
							case $this->config['weight_tv']:
								$value = $row['value'] ? $row['value'] : $weight_default;
								$packages[$k.'_'.$i]['weight'] = $value*1000;
							break;
							case $this->config['length_tv']:
								$value = $row['value'] ? $row['value'] : $length_default;
								$packages[$k.'_'.$i]['length'] = $value;
							break;
							case $this->config['width_tv']:
								$value = $row['value'] ? $row['value'] : $width_default;
								$packages[$k.'_'.$i]['width'] = $value;
							break;
							case $this->config['height_tv']:
								$value = $row['value'] ? $row['value'] : $height_default;
								$packages[$k.'_'.$i]['height'] = $value;
							break;
						}
					}
				}
			}
		} else {
			foreach($docs as $k=>$item) {
				$id = $item['id'];
				$count = $item['count'];
				for($i=0;$i<$count;$i++) {
					$packages[$k.'_'.$i]['weight'] = $weight_default*1000;
					$packages[$k.'_'.$i]['length'] = $length_default;
					$packages[$k.'_'.$i]['width'] = $width_default;
					$packages[$k.'_'.$i]['height'] = $height_default;					
				}
				
			}
		}

		if($this->config['group']!=1) {
			return array_values($packages);
		}

		

		$volume = 0;
		$weight = 0;
		foreach($packages as $pack) {
			$weight = $weight+$pack['weight'];
			$pack_volume = $pack['width'] * $pack['length'] * $pack['height'];
			$volume = $volume + $pack_volume; 
		}

		$edge = ceil(pow($volume, 1/3));
		$packages_group = [
			[
				'weight' => $weight,
				'width' => $edge,
				'length' => $edge,
				'height' => $edge
			]
		];


		return $packages_group ;
	}


	private function getCalcHash() {
		$packages = $this->getPackages();
		$hash_str = $this->getSession('cityid').'_'.json_encode($packages).$this->config['extradays'].$this->config['upsale'].$this->config['sender_city'].'secretkey';
		return md5($hash_str);
	}

	private function getToken() {
		if(!isset($this->config['sender_city']) || empty($this->config['sender_city']) || !isset($this->config['client_id']) || empty($this->config['client_id']) || !isset($this->config['client_secret']) || empty($this->config['client_secret'])) {
			$this->evo->logEvent(123, 1, 'Проверьте учетные данные плагина СДЭК', 'Проверьте учетные данные плагина СДЭК');
			die('Проверьте учетные данные плагина СДЭК');
		}
		if(isset($_SESSION['cdek_token']) && isset($_SESSION['cdek_tokenExpired']) && $_SESSION['cdek_tokenExpired'] > time()) {
			return $_SESSION['cdek_token'];
		}

		$url = "https://api.cdek.ru/v2/oauth/token?parameters";
		$post_data = [
			'grant_type' => 'client_credentials',
			'client_id' => $this->config['client_id'],
			'client_secret' => $this->config['client_secret']
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		$result = curl_exec($ch);
		curl_close($ch);

		$result = json_decode($result, true);
		if(!isset($result['access_token'])) {
			$this->evo->logEvent(123, 1, '<pre>'.print_r($result, true).'</pre>', 'Проверьте учетные данные плагина СДЭК');
			return false;
		}

		$_SESSION['cdek_token'] = $result['access_token'];
		$_SESSION['cdek_tokenExpired'] = time() + $result['expires_in'];
		return $_SESSION['cdek_token'];
	}

	private function request($url='', $post_data=array(), $method='get') {
		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer '.$this->token
		];

		$ch = curl_init();

		if($method=='post') {
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
		} else {
			$url = $url . '?' . http_build_query($post_data);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		}

		$result = curl_exec($ch);
		curl_close($ch);

		$result = json_decode($result, true);
		return $result;
	}

	public function registerOrder($fields=array()){
		$result = $this->request('https://api.cdek.ru/v2/orders', $fields, 'post');
		return $result;
	}

	public function calc() {
		$hash = $this->getCalcHash();
		if(isset($this->session['cdek_tariff_'.$hash])) {
			return $this->getSession('cdek_tariff_'.$hash);
		} else {
			$post_data = [
				'type'=>1, //Тип заказа: 1 - Интернет-магазин, 2 - доставка
				'from_location'=>[
					'code'=>$this->config['sender_city']
				],
				'to_location'=>[
					'code'=>$this->getSession('cityid')
				],
				'packages'=>$this->getPackages()
			];
			$url = 'https://api.cdek.ru/v2/calculator/tarifflist';
			$calcResult = $this->request($url, $post_data, 'post');
		}

		$calcResult = isset($calcResult['tariff_codes']) ? $calcResult['tariff_codes'] : [];

		$filterResult = array_filter($calcResult, function($tariff){
			return in_array($tariff['delivery_mode'], $this->delivery_modes);
		});

		$cleanResult = [];
		foreach($filterResult as $tariff) {
			$tariff['from_cityid'] = $this->config['sender_city'];
			$tariff['to_cityid'] = $this->getSession('cityid');
			$tariff['packages'] = $this->getPackages();

			$delivery_sum = $tariff['delivery_sum']+$this->config['upsale'];
			$tariff['delivery_sum'] = $delivery_sum > 0 ? $delivery_sum : 0;
			$tariff['period_min'] = $tariff['period_min']+$this->config['extradays'];
			$tariff['period_max'] = $tariff['period_max']+$this->config['extradays'];
			switch($tariff['delivery_mode']) {
				case 1:
				case 3:
					if(!isset($cleanResult['cdek_courier']) || $cleanResult['cdek_courier']['delivery_sum'] > $tariff['delivery_sum']) {
						$tariff['name'] = 'Доставка курьером до двери';
						$cleanResult['cdek_courier'] = $tariff;
					}
				break;

				case 2:
				case 4:
					if(!isset($cleanResult['cdek_pvz']) || $cleanResult['cdek_pvz']['delivery_sum'] > $tariff['delivery_sum']) {
						$tariff['name'] = 'Самовывоз из пункта выдачи';
						$cleanResult['cdek_pvz'] = $tariff;
					}
				break;

				case 6:
					if(!isset($cleanResult['cdek_postamat']) || $cleanResult['cdek_postamat']['delivery_sum'] > $tariff['delivery_sum']) {
						$tariff['name'] = 'Постамат';
						$cleanResult['cdek_postamat'] = $tariff;
					}
				break;
			}
		}

		$this->setSession('cdek_tariff_'.$hash, $cleanResult);
		return $cleanResult;
	}

	private function plural_form($n, $forms) {
	  return $n%10==1&&$n%100!=11?$forms[0]:($n%10>=2&&$n%10<=4&&($n%100<10||$n%100>=20)?$forms[1]:$forms[2]);
	}

	public function renderMarkup($method) {
		$tariffs = $this->calc();
		$tariff = $tariffs[$method];
		$tariff['plural_days'] = $this->plural_form($tariff['period_max'], ['дня', 'дней', 'дней']);
		$packages = $this->getPackages();
		$pack_cnt = count($packages);
		$pack_weight = 0;
		foreach($packages as $pack) {
			$pack_weight += floatVal($pack['weight']/1000);
		}
		$tariff['package'] = '<span class="cdek-packages-info">'.$pack_cnt.' '.$this->plural_form($pack_cnt, ['место', 'места', 'мест']);
		$tariff['package'] .= ' общим весом '.$pack_weight.' кг.</span>';
		//$tariff['package']  = json_encode($packages);
		return $this->evo->parseText($this->tpl('markup'), $tariff, '[+', '+]');
	}

	private function getPvzList($type='ALL') {
		$pvzFile = MODX_BASE_PATH . 'assets/plugins/commerce-cdek/pvz/'.$this->getSession('cityid').'.json';
		if(file_exists($pvzFile) && filectime($pvzFile) > time()-864000) {
			$json = file_get_contents($pvzFile);
			return json_decode($json, true);
		}

		$url = 'https://api.cdek.ru/v2/deliverypoints';
		$post_data = ['city_code'=>$this->getSession('cityid'), 'type'=>$type];
		$result = $this->request($url, $post_data, 'get');
		file_put_contents($pvzFile, json_encode($result));
		return $result;
	}

	public function renderActiveMethod($method) {
		//cdek_courier
		$markup = $this->renderMarkup($method);
		$markup .= $this->evo->parseText($this->tpl($method), $this->getSession(), '[+', '+]');
		
		if($method == 'cdek_pvz') {
			$pvzList_array = $this->getPvzList('PVZ');
			$pvzRowTpl = $this->tpl('pvz_row');

			$pvzList = '';
			foreach($pvzList_array as $pvzItem) {
				$pvzItem['work_time'] = str_replace(", ", '<br/>', $pvzItem['work_time']);
				$pvzItem['coordX'] = str_replace(',', '.', $pvzItem['location']['longitude']);
				$pvzItem['coordY'] = str_replace(',', '.', $pvzItem['location']['latitude']);
				$pvzItem['address'] = $pvzItem['location']['address'];
				$pvzList .= $this->evo->parseText($pvzRowTpl, $pvzItem, '[+', '+]');
			}

			$markup .= $this->evo->parseText($this->tpl('pvz_map'), ['apikey'=>!empty($this->config['yandexapikey']) ? '?apikey='.$this->config['yandexapikey'] : '', 'pvzList'=>$pvzList], '[+', '+]');
		}

		return $markup;
	}
}

?>
