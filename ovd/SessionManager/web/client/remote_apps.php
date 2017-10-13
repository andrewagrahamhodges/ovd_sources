<?php
/**
 * Copyright (C) 2010-2012 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com> 2010
 * Author Jeremy DESVAGES <jeremy@ulteo.com> 2010
 * Author Julien LANGLOIS <julien@ulteo.com> 2012
 * Author David PHAM-VAN <d.pham-van@ulteo.com> 2012
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
require_once(dirname(__FILE__).'/../includes/core-minimal.inc.php');

function return_error($errno_, $errstr_) {
	header('Content-Type: text/xml; charset=utf-8');
	$dom = new DomDocument('1.0', 'utf-8');
	$node = $dom->createElement('error');
	$node->setAttribute('id', $errno_);
	$node->setAttribute('message', $errstr_);
	$dom->appendChild($node);
	Logger::error('main', "(client/remote_apps) return_error($errno_, $errstr_)");
	return $dom->saveXML();
}

if (! array_key_exists('token', $_REQUEST)) {
	echo return_error(1, 'Usage: missing "token" $_REQUEST parameter');
	die();
}

$token = Abstract_Token::load($_REQUEST['token']);
if (! $token) {
	echo return_error(2, 'No such token: '.$_REQUEST['token']);
	die();
}

if ($token->type != 'external_apps') {
	echo return_error(3, 'Token "'.$_REQUEST['token'].'" is invalid');
	die();
}

$session = Abstract_Session::load($token->link_to);
if (! $session) {
	echo return_error(4, 'No such session: '.$token->link_to);
	die();
}

$userDB = UserDB::getInstance();
$user = $userDB->import($session->user_login);
if (! is_object($user)) {
	echo return_error(5, 'No such user: '.$session->user_login);
	die();
}

if (! array_key_exists('session_id', $_SESSION))
	$_SESSION['session_id'] = $session->id;

header('Content-Type: text/xml; charset=utf-8');
$dom = new DomDocument('1.0', 'utf-8');

$session_node = $dom->createElement('session');
$session_node->setAttribute('id', $session->id);
$session_node->setAttribute('mode', Session::MODE_APPLICATIONS);
$session_node->setAttribute('multimedia', $session->settings['multimedia']);
$session_node->setAttribute('redirect_client_drives', $session->settings['redirect_client_drives']);
$session_node->setAttribute('redirect_client_printers', $session->settings['redirect_client_printers']);
$session_node->setAttribute('redirect_smartcards_readers', $session->settings['redirect_smartcards_readers']);
$settings_node = $dom->createElement('settings');
foreach ($session->settings as $setting_k => $setting_v) {
	$setting_node = $dom->createElement('setting');
	$setting_node->setAttribute('name', $setting_k);
	$setting_node->setAttribute('value', $setting_v);
	$settings_node->appendChild($setting_node);
}
$session_node->appendChild($settings_node);
foreach ($session->servers[Server::SERVER_ROLE_APS] as $server_id => $data) {
	$server = Abstract_Server::load($server_id);
	if (! $server)
		continue;

	if (array_key_exists(Server::SERVER_ROLE_APS, $server->getRoles())) {
		if ($server->id == $session->server)
			continue;

		$server_applications = $server->getApplications();
		if (! is_array($server_applications))
			$server_applications = array();

		$available_applications = array();
		foreach ($server_applications as $server_application)
			$available_applications[] = $server_application->getAttribute('id');

		$server_node = $dom->createElement('server');
		$server_node->setAttribute('fqdn', $server->getExternalName());
		$server_node->setAttribute('login', $session->settings['aps_access_login']);
		$server_node->setAttribute('password', $session->settings['aps_access_password']);
		foreach ($session->getPublishedApplications() as $application) {
			if ($application->getAttribute('type') != $server->getAttribute('type'))
				continue;

			if (! in_array($application->getAttribute('id'), $available_applications))
				continue;

			$application_node = $dom->createElement('application');
			$application_node->setAttribute('id', $application->getAttribute('id'));
			$application_node->setAttribute('name', $application->getAttribute('name'));
			foreach ($application->getMimeTypes() as $mimetype) {
				$mimetype_node = $dom->createElement('mime');
				$mimetype_node->setAttribute('type', $mimetype);
				$application_node->appendChild($mimetype_node);
			}
			$server_node->appendChild($application_node);
		}
		$session_node->appendChild($server_node);
	}
}
		
if (array_key_exists(Server::SERVER_ROLE_WEBAPPS, $session->servers)) {
	foreach ($session->servers[Server::SERVER_ROLE_WEBAPPS] as $server_id => $data) {
		$server = Abstract_Server::load($server_id);
		if (! $server)
			throw_response(INTERNAL_ERROR);
	
		if (! array_key_exists(Server::SERVER_ROLE_WEBAPPS, $server->getRoles()))
			throw_response(INTERNAL_ERROR);
	
		$server_node = $dom->createElement('webapp-server');
		$server_node->setAttribute('type', 'webapps');
		$server_node->setAttribute('base-url', $server->getBaseURL());
		
		$server_node->setAttribute('webapps-url', $session->settings['webapps-url']);
		$server_node->setAttribute('login', $session->settings['webapps_access_login']);
		$server_node->setAttribute('password', $session->settings['webapps_access_password']);
	
		foreach ($session->getPublishedApplications() as $application) {
			if ($application->getAttribute('type') != 'webapp')
				continue;
			$application_node = $dom->createElement('application');
			$application_node->setAttribute('id', $application->getAttribute('id'));
			$application_node->setAttribute('type', 'webapp');
			$application_node->setAttribute('name', $application->getAttribute('name'));
			$server_node->appendChild($application_node);
		}
		$session_node->appendChild($server_node);
	}
}
$dom->appendChild($session_node);

echo $dom->saveXML();
exit(0);
