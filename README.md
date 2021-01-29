# commerce-cdek

Плагин делает рассчет стоимости доставки ТК СДЭК.

1. Установить плагин

2. В шаблон формы оформления заказа &formTpl добавить скрытое поле `<input type="hidden" name="cdek_cityid" value="[+cdek_cityid.value+]">`. 
Оно должно быть ВНЕ обёртки способов доставки `data-commerce-deliveries`

3. В шаблоне формы должно пыть поле города name="city"

4. Расчет производится по тарифам с режимами 1 и 2 (доставка по адресу и в пункт выдачи, с забором груза у отправителя курьером)

5. В шаблоне deliveryRowTpl должен быть плейсхолдер [+markup+] для вывода дополнительных данных. Поэтому выбор способов доставки должен осуществляться блочным элементом (не select).

UPDATE 30.01.2021
Добавлена возможность отправки заявки на доставку в Личный Кабинет СДЭК (тестирование, могут быть баги)
В конфигурации выбрать параметр "Регистрировать заявки в ЛК" = Да

![](https://github.com/autogen-travel/images/raw/main/1605917254409.jpg)
![](https://github.com/autogen-travel/images/raw/main/1605917354617.jpg)
![](https://github.com/autogen-travel/images/raw/main/1605917378237.jpg)
