<?php
/**
 * Copyright (C) 2011 Ulteo SAS
 * http://www.ulteo.com
 * Author Julien LANGLOIS <julien@ulteo.com>
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
 
require_once('config.inc.php');
require_once('functions.inc.php');

session_start();

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
	if (isset($_REQUEST['disconnect']) && isset($_SESSION['login']))
		unset($_SESSION['login']);
	
	else if (isset($_REQUEST['login']))
		$_SESSION['login'] = $_REQUEST['login'];

	header('Location: '.$_SERVER['HTTP_REFERER']);
	die();
}

if (isset($_SESSION['login']))
	$user = $_SESSION['login'];
else if (defined('ULTEO_OVD_DEFAULT_LOGIN'))
	$user = ULTEO_OVD_DEFAULT_LOGIN;
else
	$user = null;


$apps = getApplications($user);
$files = getFiles();

foreach ($files as $name => $f) {
	$files[$name]['applications'] = array();
	
	if (is_array($apps)) {
		foreach($apps as $application) {
			if (in_array($f['mimetype'], $application['mimetypes']))
				$files[$name]['applications'] []= $application;
		}
	}
}

$base_url_file = 'http://'.$_SERVER['HTTP_HOST'].(($_SERVER['SERVER_PORT']==80)?'':$_SERVER['SERVER_PORT']);
if (str_endswith($_SERVER['REQUEST_URI'], basename(__FILE__)))
	$base_url_file.= dirname($_SERVER['REQUEST_URI']);
else
	$base_url_file.= $_SERVER['REQUEST_URI'];

if (str_endswith($base_url_file, '/'))
	$base_url_file.= '/';

$base_url_file.= 'file.php';
?>
<html>
<head>
<title>My portal</title>

<script type="text/javascript" src="<?php echo ULTEO_OVD_WEBCLIENT_URL; ?>/media/script/external.js" charset="utf-8"></script>

<script type="text/javascript">

function getBaseOVDObj(mode_) {
	var ovd = new UlteoOVD_session('<?php echo ULTEO_OVD_WEBCLIENT_URL; ?>', mode_);
	
<?php if (ULTEO_OVD_AUTH_TYPE == 'token') {?>
	ovd.setAuthToken('<?php echo base64_encode($user); ?>');
<?php } else {?>
	ovd.setAuthPassword('<?php echo $user; ?>', '<?php echo $user; ?>');
<?php } ?>
	
	return ovd;
}

function startEmptySession() {
	var ovd = getBaseOVDObj(ULTEO_OVD_SESSION_MODE_APPLICATIONS);
	ovd.start();
}

function startApplication(mode_, app_id_) {
	var ovd = getBaseOVDObj(mode_);
	ovd.setApplication(app_id_);
	ovd.start();
}

function startApplicationWithPath(mode_, app_id_, path_, url_) {
	var ovd = getBaseOVDObj(mode_);
	ovd.setApplication(app_id_);
	ovd.setPathHTTP(url_, path_, null);
	ovd.start();
}
</script>
</head>
<body>
<h1>Welcome to My Portal</h1>

<?php if ($apps !== false) {?>
	<div>
		<form onsubmit="startEmptySession(); return false;">
			<input type="submit" value="Preload Ulteo seamless session" />
		</form>
	</div>

	<div>
	<h2>Available applications</h2>
	<table>
	<?php
		foreach($apps as $app) {
	?>
		<tr>
			<td><img src="icon.php?id=<?php echo $app['id']; ?>"/></td>
			<td><?php echo $app['name']; ?></td>
			<td><form onsubmit="startApplication(ULTEO_OVD_SESSION_MODE_APPLICATIONS, '<?php echo $app['id']; ?>'); return false;">
				<input type="submit" value="Start instance (seamless)" />
			</form></td>
			<td><form onsubmit="startApplication(ULTEO_OVD_SESSION_MODE_DESKTOP, '<?php echo $app['id']; ?>'); return false;">
				<input type="submit" value="Start instance (browser)" />
			</form></td>
		</tr>
	<?php
		}
	?>
	</table>
	</div>

	<div>
	<h2>Files</h2>
	<table>
<?php
		foreach($files as $f) {
?>
		<tr>
		<td><?php echo $f['name']; ?></td>
		<td><?php echo $f['mimetype']; ?></td>
		</tr>
<?php
			foreach($f['applications'] as $application) {
?>
		<tr>
		<td></td><td></td>
		<td><img src="icon.php?id=<?php echo $application['id']; ?>"/></td>
		<td><?php echo $application['name']; ?></td>
		<td>
			<form onsubmit="startApplicationWithPath(ULTEO_OVD_SESSION_MODE_APPLICATIONS, '<?php echo $application['id']; ?>', '<?php echo $f['name']; ?>', '<?php echo urlencode(base64_encode($base_url_file.'?path='.$f['name'])); ?>'); return false;">
				<input type="submit" value="Open (seamless)" />
			</form>
		</td>
		<td>
			<form onsubmit="startApplicationWithPath(ULTEO_OVD_SESSION_MODE_DESKTOP, '<?php echo $application['id']; ?>', '<?php echo $f['name']; ?>', '<?php echo urlencode(base64_encode($base_url_file.'?path='.$f['name'])); ?>'); return false;">
				<input type="submit" value="Open (browser)" />
			</form>
		</td>
		</tr>
<?php
			}
		}
?>
	</table>	
	</div>
	
<?php } ?>

<div>
<h2>Ulteo OVD Authentication</h2>
<form action="" method="POST">
<table>
<?php if (! isset($_SESSION['login']) or defined('ULTEO_OVD_DEFAULT_LOGIN')) { ?>
	<tr>
		<td>Login: </td>
		<td><input name="login" value="<?php echo $user; ?>" /></td>
		<td><input type="submit" value="Go" /></td>
	</tr>
<?php } else { ?>
	<tr>
		<td>Logged as <?php echo $_SESSION['login']; ?></td>
		<td><input type="submit" name="disconnect" value="Disconnect" /></td>
	</tr>
<?php } ?>
</table>
</form>

<?php if (isset($_SESSION['error'])) { ?>
<pre><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></pre>
<?php } ?>
</div>

</body>
</html>
