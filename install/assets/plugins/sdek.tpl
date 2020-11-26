//<?php
/**
 * СДЭК
 *
 * <strong>0.3</strong> Плагин для расчёта доставки СДЭК
 *
 * @category    plugin
 * @internal    @events OnPageNotFound,OnCollectSubtotals,OnRegisterDelivery,OnOrderRawDataChanged,OnBeforeOrderProcessing,OnBeforeOrderSaving,OnOrderSaved,OnManagerBeforeOrdersListRender,OnManagerBeforeOrderRender,OnManagerBeforeOrderEditRender
 * @internal    @modx_category Commerce
 * @internal    @properties &sender_city=ID города отправки;number;44 &client_id=СДЭК логин;string; &client_secret=СДЭК пароль;string; &yandexapikey=Ключ АПИ Я.Карт для отображения ПВЗ;string; &upsale=Наценка;text;0 &extradays=Добавлять дней к сроку доставки;number;0 &weight=Вес по умолчанию, в кг;number;1000 &weight_tv=ID TV-параметра веса товара;number;;Вес в кг &length_tv=ID TV-параметра длины упаковки;number;;длина в см &width_tv=ID TV-параметра ширины упаковки;number;;ширина в см &height_tv=ID TV-параметра высоты упаковки;number;;высота в см &group=Объединять размеры в одну посылку;list;Нет==0||Да==1;0
 * @internal    @disabled 0
 * @internal    @installset base
 */
require MODX_BASE_PATH. 'assets/plugins/commerce-cdek/commerce-cdek.php';

