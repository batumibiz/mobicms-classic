<?php
/**
 * mobiCMS (https://mobicms.org/)
 * This file is part of mobiCMS Content Management System.
 *
 * @license     https://opensource.org/licenses/GPL-3.0 GPL-3.0 (see the LICENSE.md file)
 * @link        http://mobicms.org mobiCMS Project
 * @copyright   Copyright (C) mobiCMS Community
 */

defined('MOBICMS') or die('Error: restricted access');

/** @var Psr\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var League\Plates\Engine $view */
$view = $container->get(League\Plates\Engine::class);

$id = isset($_REQUEST['id']) ? abs(intval($_REQUEST['id'])) : 0;
$search_post = isset($_POST['search']) ? trim($_POST['search']) : false;
$search_get = isset($_GET['search']) ? rawurldecode(trim($_GET['search'])) : '';
$search = $search_post ? $search_post : $search_get;

ob_start();
require dirname(__DIR__) . '/classes/download.php';

// Форма для поиска
echo '<div class="phdr"><a href="?"><b>' . _t('Downloads') . '</b></a> | ' . _t('Search') . '</div>' .
    '<form action="?act=search" method="post"><div class="gmenu"><p>' .
    _t('File Name') . ':<br><input type="text" name="search" value="' . htmlspecialchars($search) . '" /><br>' .
    '<input name="id" type="checkbox" value="1" ' . ($id ? 'checked="checked"' : '') . '/> ' . _t('Search in description') . '<br>' .
    '<input type="submit" value="' . _t('Search') . '" name="submit" /><br>' .
    '</p></div></form>';

// Проверяем на коректность ввода
$error = false;

if (!empty($search) && mb_strlen($search) < 2 || mb_strlen($search) > 64) {
    $error = _t('Invalid file name length. Allowed a minimum of 3 and a maximum of 64 characters.');
}

// Выводим результаты поиска
if ($search && !$error) {
    /** @var PDO $db */
    $db = $container->get(PDO::class);

    /** @var Mobicms\Checkpoint\UserConfig $userConfig */
    $userConfig = $container->get(Mobicms\Api\UserInterface::class)->getConfig();

    /** @var Mobicms\Api\ToolsInterface $tools */
    $tools = $container->get(Mobicms\Api\ToolsInterface::class);

    // Подготавливаем данные для запроса
    $search = preg_replace("/[^\w\x7F-\xFF\s]/", " ", $search);
    $search_db = strtr($search, ['_' => '\\_', '%' => '\\%', '*' => '%']);
    $search_db = '%' . $search_db . '%';
    $search_db = $db->quote($search_db);
    $sql = ($id ? '`about`' : '`rus_name`') . ' LIKE ' . $search_db;

    // Результаты поиска
    echo '<div class="phdr"><b>' . _t('Search results') . '</b></div>';
    $total = $db->query("SELECT COUNT(*) FROM `download__files` WHERE `type` = '2'  AND $sql")->fetchColumn();

    if ($total > $userConfig->kmess) {
        $check_search = htmlspecialchars(rawurlencode($search));
        echo '<div class="topmenu">' . $tools->displayPagination('?act=search&amp;search=' . $check_search . '&amp;id=' . $id . '&amp;', $total) . '</div>';
    }

    if ($total) {
        $req_down = $db->query("SELECT * FROM `download__files` WHERE `type` = '2'  AND $sql ORDER BY `rus_name` ASC" . $tools->getPgStart(true));
        $i = 0;

        while ($res_down = $req_down->fetch()) {
            echo (($i++ % 2) ? '<div class="list2">' : '<div class="list1">') . Download::displayFile($res_down) . '</div>';
        }
    } else {
        echo '<div class="rmenu"><p>' . _t('No items found') . '</p></div>';
    }

    echo '<div class="phdr">' . _t('Total') . ':  ' . $total . '</div>';

    // Навигация
    if ($total > $userConfig->kmess) {
        echo '<div class="topmenu">' . $tools->displayPagination('?act=search&amp;search=' . $check_search . '&amp;id=' . $id . '&amp;', $total) . '</div>' .
            '<p><form action="?" method="get">' .
            '<input type="hidden" value="' . $check_search . '" name="search" />' .
            '<input type="hidden" value="search" name="act" />' .
            '<input type="hidden" value="' . $id . '" name="id" />' .
            '<input type="text" name="page" size="2"/><input type="submit" value="' . _t('To Page') . ' &gt;&gt;"/></form></p>';
    }
    echo '<p><a href="?act=search">' . _t('New Search') . '</a></p>';
} else {
    // FAQ по поиску и вывод ошибки
    if ($error) {
        echo $error;
    }

    echo '<div class="phdr"><small>' . _t('Search by file Name and is case insensitive.<br>The length of the request: 2mins. 64макс.') . '</small></div>';
}

echo '<p><a href="?">' . _t('Downloads') . '</a></p>';

echo $view->render('system::app/legacy', [
    'title'   => _t('Downloads'),
    'content' => ob_get_clean(),
]);
