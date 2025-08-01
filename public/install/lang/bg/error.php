<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Automatically generated strings for Moodle installer
 *
 * Do not edit this file manually! It contains just a subset of strings
 * needed during the very first steps of installation. This file was
 * generated automatically by export-installer.php (which is part of AMOS
 * {@link https://moodledev.io/general/projects/api/amos}) using the
 * list of strings defined in public/install/stringnames.txt file.
 *
 * @package   installer
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cannotcreatedboninstall'] = '<p>Не може да се създаде базата данни.</p>
<p>Посочената база данни не съществува и даденият потребител няма разрешение да създаде базата данни.</p>
<p>Администраторът на сайта трябва да провери конфигурацията на базата данни.</p>';
$string['cannotcreatelangdir'] = 'Не може да се създаде езикова директория';
$string['cannotcreatetempdir'] = 'Не може да създаде временна директория';
$string['cannotdownloadcomponents'] = 'Не могат да се изтеглят компоненти';
$string['cannotdownloadzipfile'] = 'Не може да се изтегли ZIP файл';
$string['cannotfindcomponent'] = 'Не можа да намери компонент';
$string['cannotunzipfile'] = 'Файлът не може да се разархивира';
$string['componentisuptodate'] = 'Компонентът е актуален';
$string['remotedownloaderror'] = '<p>Изтеглянето на компонента към вашия сървър пропадна, проверете настройките на proxy; препоръчително е PHP разширението cURL.</p><p>Вие трябва ръчно да изтеглите файла <a href="{$a->url}">{$a->url}</a>, да го копирате в директория "{$a->dest}" на вашия сървър и да го разархивирате там.</p>';
$string['wrongdestpath'] = 'Грешен път към целта';
$string['wrongsourcebase'] = 'Грешен изходен адрес';
$string['wrongzipfilename'] = 'Грешно име на ZIP файл-а';
