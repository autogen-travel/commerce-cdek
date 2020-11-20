<?php

class commerceCDEK {
	public function __construct($config) {
		$this->evo = evolutionCMS();
		$this->config = $config;
		$this->token = $this->getToken();
		$this->session = $this->getSession();
		//1 - дверь-дверь
		//2 - дверь - ПВЗ
		//6 - дверь - постамат (список постаматов вовращает не для всех городов, какой-то глюк у СДЭКа)
		$this->delivery_modes = [1,2];
	}

	private function getSession($key='') {
		if(!isset($_SESSION['cdek'])) $_SESSION['cdek'] = [];

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
		if(isset($this->config['weight_tv']) && !empty($this->config['weight_tv'])) {
			//Суммируем вес всех товаров
			$weight = 0;
			$cart = ci()->carts->getCart('products');
			$items = $cart->getItems();
			
			$docs = [];
			foreach(array_values($items) as $item) {
				$docs[$item['id']] = $item['count'];
			}

			$q = $this->evo->db->select('contentid, value', '[+prefix+]site_tmplvar_contentvalues', 'contentid IN ('.implode(',',array_keys($docs)).') AND tmplvarid='.$this->config['weight_tv']);

			while($row = $this->evo->db->getRow($q)) {
				$weight = $weight+($row['value'] * $docs[$row['contentid']]);
			}	
		} else {
			$weight = (isset($this->config['weight']) && is_numeric($this->config['weight']) && $this->config['weight'] > 0) ? intVal($this->config['weight']) : 500;
		}

		$packages = [
			'weight'=>$weight
		];
		return $packages;
	}


	private function getCalcHash() {
		$packages = $this->getPackages();
		$hash_str = $this->session['cityid'].'_'.json_encode($packages).$this->config['extradays'].$this->config['upsale'].$this->config['sender_city'];
		return md5($hash_str);
	}

	private function getToken() {
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

	public function calc() {
		$hash = $this->getCalcHash();
		if(isset($this->session['cdek_tariff_'.$hash])) {
			return $this->session['cdek_tariff_'.$hash];
		} else {
			$post_data = [
				'type'=>1, //Тип заказа: 1 - Интернет-магазин, 2 - доставка
				'from_location'=>[
					'code'=>$this->config['sender_city']
				],
				'to_location'=>[
					'code'=>$this->session['cityid']
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
			$tariff['delivery_sum'] = $tariff['delivery_sum']+$this->config['upsale'];
			$tariff['period_min'] = $tariff['period_min']+$this->config['extradays'];
			$tariff['period_max'] = $tariff['period_max']+$this->config['extradays'];
			switch($tariff['delivery_mode']) {
				case 1:
					if(!isset($cleanResult['cdek_courier']) || $cleanResult['cdek_courier']['delivery_sum'] > $tariff['delivery_sum']) {
						$tariff['name'] = 'Доставка до двери';
						$cleanResult['cdek_courier'] = $tariff;
					}
				break;

				case 2:
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
		return $this->evo->parseText($this->tpl('markup'), $tariff, '[+', '+]');
	}

	private function getPvzList($type='ALL') {
		$pvzFile = MODX_BASE_PATH . 'assets/plugins/commerce-cdek/pvz/'.$this->session['cityid'].'.json';
		if(file_exists($pvzFile) && filectime($pvzFile) > time()-864000) {
			$json = file_get_contents($pvzFile);
			return json_decode($json, true);
		}

		$url = 'https://api.cdek.ru/v2/deliverypoints';
		$post_data = ['city_code'=>$this->session['cityid'], 'type'=>$type];
		$result = $this->request($url, $post_data, 'get');
		file_put_contents($pvzFile, json_encode($result));
		return $result;
	}

	public function renderActiveMethod($method) {
		//cdek_courier
		$markup = $this->renderMarkup($method);
		$markup .= $this->evo->parseText($this->tpl($method), $this->session, '[+', '+]');
		
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