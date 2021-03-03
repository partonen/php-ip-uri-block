<?php
  
// получим ip клиента
$ip = $_SERVER['REMOTE_ADDR']; 
// подключим файл SxGeo.php
require_once 'SxGeo.php';
// создадим объект SxGeo (1 аргумент – имя файла базы данных, 2 аргумент – режим работы: SXGEO_FILE (по умолчанию), SXGEO_BATCH  (пакетная обработка, увеличивает скорость при обработке множества IP за раз), SXGEO_MEMORY (кэширование БД в памяти, еще увеличивает скорость пакетной обработки, но требует больше памяти, для загрузки всей базы в память).
$SxGeo = new SxGeo('SxGeo.dat', SXGEO_BATCH | SXGEO_MEMORY);
// получаем двухзначный ISO-код страны (RU, UA и др.)
$country_code = $SxGeo->getCountry($ip);

//echo $country_code;

?>
