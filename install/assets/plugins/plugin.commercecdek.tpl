//<?php
/**
 * СДЭК
 *
 * Плагин для расчёта доставки СДЭК
 *
 * @category    plugin
 * @version     0.1
 * @author      autogen aka BilboBaggins
 * @internal    @events OnBeforeOrderSaving,OnBeforeOrderProcessing,OnCollectSubtotals,OnManagerBeforeOrderEditRender,OnManagerBeforeOrderRender,OnManagerBeforeOrdersListRender,OnOrderRawDataChanged,OnOrderSaved,OnRegisterDelivery,OnPageNotFound
 * @internal    @properties &sender_city=ID города отправки;number;44;;Узнать ID города можно здесь: https://dadata.ru/suggestions/#address (пункт Идентификаторы служб доставки).; &client_id=СДЭК логин;string; &client_secret=СДЭК пароль;string; &yandexapikey=Ключ АПИ Я.Карт для отображения ПВЗ;string;;;Может не работать без ключа (ограничено количество запросов); &upsale=Наценка;number;0;0; &extradays=Добавлять дней к сроку доставки;number;0;0; &weight_tv=ID TV-параметра веса товара;number;;;Значение параметра должно быть указано как целое число в граммах; &weight=Вес по умолчанию, в граммах;number;0;1000;
 * @internal    @modx_category Commerce
 * @internal    @disabled 1
 * @internal    @installset base
*/


require MODX_BASE_PATH. 'assets/plugins/commerce-cdek/commerce-cdek.php';


