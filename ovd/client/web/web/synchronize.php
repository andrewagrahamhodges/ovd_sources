<?php
/**
 * Copyright (C) 2010-2013 Ulteo SAS
 * http://www.ulteo.com
 * Author Jeremy DESVAGES <jeremy@ulteo.com> 2010
 * Author Julien LANGLOIS <julien@ulteo.com> 2011, 2013
 * Author David LECHEVALIER <david@ulteo.com> 2013
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **/

require_once(dirname(__FILE__).'/includes/core.inc.php');

$client_headers = getallheaders();
foreach ($client_headers as $k => $v) {
	if (in_array($k, array('Forward-Cookie', 'forward-cookie'))) {
		$ret = $k.': '.$v;
		preg_match('@Forward-Cookie: (.*)=(.*);@i', $ret, $matches);
		if (count($matches) == 3) {
			$_SESSION['ovd-client']['sessionmanager'] = array();
			$_SESSION['ovd-client']['sessionmanager']['session_var'] = $matches[1];
			$_SESSION['ovd-client']['sessionmanager']['session_id'] = $matches[2];
		}
	}
}

if(array_key_exists('sessionmanager_host',   $_POST)) setcookie('ovd-client[sessionmanager_host]',   $_POST['sessionmanager_host'],   (time()+(60*60*24*7)));
if(array_key_exists('login',                 $_POST)) setcookie('ovd-client[user_login]',            $_POST['login'],                 (time()+(60*60*24*7)));
if(array_key_exists('use_local_credentials', $_POST)) setcookie('ovd-client[use_local_credentials]', $_POST['use_local_credentials'], (time()+(60*60*24*7)));
if(array_key_exists('mode',                  $_POST)) setcookie('ovd-client[session_mode]',          $_POST['mode'],                  (time()+(60*60*24*7)));
if(array_key_exists('type',                  $_POST)) setcookie('ovd-client[session_type]',          $_POST['type'],                  (time()+(60*60*24*7)));
if(array_key_exists('language',              $_POST)) setcookie('ovd-client[session_language]',      $_POST['language'],              (time()+(60*60*24*7)));
if(array_key_exists('keymap',                $_POST)) setcookie('ovd-client[session_keymap]',        $_POST['keymap'],                (time()+(60*60*24*7)));
if(array_key_exists('input_method',          $_POST)) setcookie('ovd-client[session_input_method]',  $_POST['input_method'],          (time()+(60*60*24*7)));
if(array_key_exists('desktop_fullscreen',    $_POST)) setcookie('ovd-client[desktop_fullscreen]',    $_POST['desktop_fullscreen'],    (time()+(60*60*24*7)));
if(array_key_exists('debug',                 $_POST)) setcookie('ovd-client[debug]',                 $_POST['debug'],                 (time()+(60*60*24*7)));
