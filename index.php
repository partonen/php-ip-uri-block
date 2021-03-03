<?php
/**
 * Класс проверки и блокировки ip-адреса.
 */
class TBlockIp {
    /**
     * Время блокировки в секундах.
     */
    const blockSeconds = 60;
    /**
     * Интервал времени запросов страниц.
     */
    const intervalSeconds = 60;
    /**
     * Количество запросов страницы в интервал времени.
     */
    const intervalTimes = 20;
    /**
     * Флаг подключения всегда активных пользователей.
     */
    const isAlwaysActive = true;
    /**
     * Флаг подключения всегда заблокированных пользователей.
     */
    const isAlwaysBlock = true;
    /**
     * Путь к директории кэширования активных пользователей.
     */
    const pathActive = 'active';
    /**
     * Путь к директории кэширования заблокированных пользователей.
     */
    const pathBlock = 'block';
    /**
     * Флаг абсолютных путей к директориям.
     */
    const pathIsAbsolute = false;
    /**
     * Список всегда активных пользователей.
     */
    public static $alwaysActive = array(
    '172.16.1.1', 
    );

    /**
     * Список всегда заблокированных пользователей.
     */
    public static $alwaysBlock = array(
    '172.16.1.1', 
    );



    /**
     * Метод проверки ip-адреса на активность и блокировку.
     */
    public static function checkIp() {

        //Получаем URI -
        $client_uri = self::_getUri();
        $hash_uri = hash('md4',$client_uri);

        // Получение ip-адреса
        $ip_address = self::_getIp();

        // Пропускаем всегда активных пользователей
        if (in_array($ip_address, self::$alwaysActive) && self::isAlwaysActive) {
            return;
        }
	// Пропускаем Россию
	require_once 'get_ip_code.php';
	if ($country_code === 'RU') {
  	// код для посетителей из России...
	echo "Ваш ip: ".$ip_address." ".$country_code;
	return;
	}

	// Блокируем всегда заблокированных пользователей
        if (in_array($ip_address, self::$alwaysBlock) && self::isAlwaysBlock) {
            echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
            echo '<html xmlns="http://www.w3.org/1999/xhtml">';
            echo '<head>';
            echo '<title>Вы заблокированы</title>';
            echo '<meta http-equiv="content-type" content="text/html; charset=utf-8" />';
            echo '</head>';
            echo '<body>';
            echo '<p style="background:#ccc;border:solid 1px #aaa;margin:30px au-to;padding:20px;text-align:center;width:700px">';
            echo 'Вы заблокированы администрацией ресурса.<br />';
            echo '</p>';
            echo '</body>';
            echo '</html>';
            exit;
        }

        // Установка путей к директориям
        $path_active = self::pathActive;
        $path_block = self::pathBlock;

        // Приведение путей к директориям к абсолютному виду
        if (!self::pathIsAbsolute) {
            $path_active = str_replace('\\' , '/', dirname(__FILE__) . '/' . $path_active . '/');
            $path_block = str_replace('\\' , '/', dirname(__FILE__) . '/' . $path_block . '/');
        }

        // Проверка возможности записи в директории
        if (!is_writable($path_active)) {
            die('Директория кэширования активных пользователей не создана или закрыта для записи.');
        }
        if (!is_writable($path_block)) {
            die('Директория кэширования заблокированных пользователей не создана или закрыта для записи.');
        }

        // Проверка активных ip-адресов
        $is_active = false;
        if ($dir = opendir($path_active)) {
            while (false !== ($filename = readdir($dir))) {
                // Выбирается ip + время активации этого ip + hash uri
		echo $filename . "<br>";
                if (preg_match('#^(\d{1,3}.\d{1,3}.\d{1,3}.\d{1,3})_(\d{1,10})_(.*?)$#', $filename, $matches)) {
                     echo $matches[3]. "<br>";
			if ($matches[2] >= time() - self::intervalSeconds) {
			if ($matches[1] == $ip_address && $matches[3] == $hash_uri) {
			$times = intval(trim(file_get_contents($path_active . $filename)));
                            if ($times >= self::intervalTimes - 1) {
                                //touch функцию можно заменить на функцию добавления правила в фаервол
								touch($path_block . $filename);
								
                                unlink($path_active . $filename);
                            } else {
                                file_put_contents($path_active . $filename, $times + 1);
                            }
                            $is_active = true;
                        }
                    } else {
                        unlink($path_active . $filename);
                    }
                }
            }
            closedir($dir);
        }

        // Проверка заблокированных ip-адресов
        $is_block = false;
        if ($dir = opendir($path_block)) {
            while (false !== ($filename = readdir($dir))) {
                // Выбирается ip + время блокировки этого ip
                if (preg_match('#^(\d{1,3}.\d{1,3}.\d{1,3}.\d{1,3})_(\d{1,10})_(.*?)$#', $filename, $matches)) {
                    if ($matches[2] >= time() - self::blockSeconds) {
                        if ($matches[1] == $ip_address && $matches[3] == $hash_uri) {
                            $is_block = true;
                            $time_block = $matches[2] - (time() - self::blockSeconds) + 1;
                        }
                    } else {
						//unlink функцию можно заменить на функцию удаления правила из фаервола
                        unlink($path_block . $filename);
                    }
                }
            }
            closedir($dir);
        }

        // ip-адрес заблокирован
        if ($is_block) {
            header('HTTP/1.0 502 Bad Gateway');
            echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
            echo '<html xmlns="http://www.w3.org/1999/xhtml">';
            echo '<head>';
            echo '<title>502 Bad Gateway</title>';
            echo '<meta http-equiv="content-type" content="text/html; charset=utf-8" />';
            echo '</head>';
            echo '<body>';
            echo '<h1 style="text-align:center">502 Bad Gateway</h1>';
            echo '<p style="background:#ccc;border:solid 1px #aaa;margin:30px au-to;padding:20px;text-align:center;width:700px">';
            echo 'К сожалению, Вы временно заблокированы, из-за частого запроса страниц сайта.<br />';
            echo 'Вам придется подождать. Через ' . $time_block . ' секунд(ы) Вы будете автоматически разблокированы.';
            echo '</p>';
            echo '</body>';
            echo '</html>';
            exit;
        }

        // Создание идентификатора активного ip-адреса
        if (!$is_active) {
            touch($path_active . $ip_address . '_' . time() . '_' . $hash_uri);
        }
    }


    /**
     * Метод получения текущего ip-адреса из переменных сервера.
     */
    private static function _getIp() {

        // ip-адрес по умолчанию
        $ip_address = '127.0.0.1';

        // Массив возможных ip-адресов
        $addrs = array();

        // Сбор данных возможных ip-адресов
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Проверяется массив ip-клиента установленных прозрачными прокси-серверами
            foreach (array_reverse(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) as $value) {
                $value = trim($value);
                // Собирается ip-клиента
                if (preg_match('#^\d{1,3}.\d{1,3}.\d{1,3}.\d{1,3}$#', $value)) {
                    $addrs[] = $value;
                }
            }
        }
        // Собирается ip-клиента
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $addrs[] = $_SERVER['HTTP_CLIENT_IP'];
        }
        // Собирается ip-клиента
        if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            $addrs[] = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        }
        // Собирается ip-клиента
        if (isset($_SERVER['HTTP_PROXY_USER'])) {
            $addrs[] = $_SERVER['HTTP_PROXY_USER'];
        }
        // Собирается ip-клиента
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $addrs[] = $_SERVER['REMOTE_ADDR'];
        }

        // Фильтрация возможных ip-адресов, для выявление нужного
        foreach ($addrs as $value) {
            // Выбирается ip-клиента
            if (preg_match('#^(\d{1,3}).(\d{1,3}).(\d{1,3}).(\d{1,3})$#', $value, $matches)) {
                $value = $matches[1] . '.' . $matches[2] . '.' . $matches[3] . '.' . $matches[4];
                if ('...' != $value) {
                    $ip_address = $value;
                    break;
                }
            }
        }

        // Возврат полученного ip-адреса
        return $ip_address;
    }

    private static function _getUri() {

        $client_uri = $_SERVER['REQUEST_URI'];
      	return $client_uri;
    }

}

// Проверка текущего ip-адреса
TBlockIp::checkIp();

?>
