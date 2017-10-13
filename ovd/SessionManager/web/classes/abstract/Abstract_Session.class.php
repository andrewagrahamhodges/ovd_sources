<?php
/**
 * Copyright (C) 2009-2014 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com> 2010
 * Author Jeremy DESVAGES <jeremy@ulteo.com> 2009
 * Author Jocelyn DELALANDE <j.delalande@ulteo.com> 2012
 * Author Julien LANGLOIS <julien@ulteo.com> 2012
 * Author David PHAM-VAN <d.pham-van@ulteo.com> 2012
 * Author David LECHEVALIER <david@ulteo.com> 2012, 2014
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
require_once(dirname(__FILE__).'/../../includes/core.inc.php');

/**
 * Abstraction layer between the Session instances and the SQL backend.
 */
class Abstract_Session {
	const table = 'sessions';
	
	public static function init($prefs_) {
		Logger::debug('main', 'Starting Abstract_Session::init');

		$sql_conf = $prefs_->get('general', 'sql');
		$SQL = SQL::newInstance($sql_conf);

		$sessions_table_structure = array(
			'id'				=>	'varchar(255)',
			'client_id'			=>	'varchar(255)',
			'need_creation'			=>	'int(1)',
			'server'			=>	'varchar(255)',
			'mode'				=>	'varchar(32)',
			'type'				=>	'varchar(32)',
			'status'			=>	'varchar(32)',
			'settings'			=>	'text',
			'user_login'		=>	'varchar(255)',
			'user_displayname'	=>	'varchar(255)',
			'servers'			=>	'mediumtext',
			'applications'		=>	'text',
			'start_time'		=>	'varchar(255)',
			'timestamp'			=>	'int(10)'
		);

		$ret = $SQL->buildTable(self::table, $sessions_table_structure, array('id'));

		if (! $ret) {
			Logger::error('main', 'Unable to create MySQL table \''.self::table.'\'');
			return false;
		}

		Logger::debug('main', 'MySQL table \''.self::table.'\' created');
		return true;
	}

	public static function exists($id_) {
		Logger::debug('main', 'Starting Abstract_Session::exists for \''.$id_.'\'');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT 1 FROM #1 WHERE @2 = %3 LIMIT 1', self::table, 'id', $id_);
		$total = $SQL->NumRows();

		if ($total == 0)
			return false;

		return true;
	}

	public static function load($id_) {
		Logger::debug('main', 'Starting Abstract_Session::load for \''.$id_.'\'');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT * FROM #1 WHERE @2 = %3 LIMIT 1', self::table, 'id', $id_);
		$total = $SQL->NumRows();

		if ($total == 0) {
			Logger::error('main', "Abstract_Session::load($id_) failed: NumRows == 0");
			return false;
		}

		$row = $SQL->FetchResult();

		$buf = self::generateFromRow($row);

		return $buf;
	}

	public static function save($session_) {
		Logger::debug('main', 'Starting Abstract_Session::save for \''.$session_->id.'\'');
		
		$SQL = SQL::getInstance();

		$id = $session_->id;

		if (! Abstract_Session::exists($id)) {
			Logger::debug('main', "Abstract_Session::save($session_) session does NOT exist, we must create it");

			if (! Abstract_Session::create($session_)) {
				Logger::error('main', "Abstract_Session::save($session_) failed to create session");
				return false;
			}
		}
		
		$data = array();
		$data['applications'] = array_map("Application::toArray", $session_->getPublishedApplications());
		$data['running_applications'] = array_map("Application::toArray", $session_->getRunningApplications());
		$data['closed_applications'] = array_map("Application::toArray", $session_->getClosedApplications());

		$SQL->DoQuery('UPDATE #1 SET @2=%3,@4=%5,@6=%7,@8=%9,@10=%11,@12=%13,@14=%15,@16=%17,@18=%19,@20=%21,@22=%23,@24=%25,@26=%27 WHERE @28 = %29 LIMIT 1', self::table, 'server', $session_->server, 'client_id', $session_->client_id, 'need_creation', $session_->need_creation, 'mode', $session_->mode, 'type', $session_->type, 'status', $session_->status, 'settings', json_serialize($session_->settings), 'user_login', $session_->user_login, 'user_displayname', $session_->user_displayname, 'servers', json_serialize($session_->servers), 'applications', json_serialize($data), 'start_time', $session_->start_time, 'timestamp', time(), 'id', $id);

		return true;
	}

	private static function create($session_) {
		Logger::debug('main', 'Starting Abstract_Session::create for \''.$session_->id.'\'');

		if (Abstract_Session::exists($session_->id)) {
			Logger::error('main', 'Abstract_Session::create(\''.$session_->id.'\') session already exists');
			return false;
		}

		$SQL = SQL::getInstance();
		$SQL->DoQuery('INSERT INTO #1 (@2) VALUES (%3)', self::table, 'id', $session_->id);

		foreach ($session_->servers[Server::SERVER_ROLE_APS] as $server_id => $data)
			Abstract_Liaison::save('ServerSession', $server_id, $session_->id);

		return true;
	}

	public static function delete($id_) {
		Logger::debug('main', 'Starting Abstract_Session::delete for \''.$id_.'\'');

		$SQL = SQL::getInstance();

		$id = $id_;

		$SQL->DoQuery('SELECT 1 FROM #1 WHERE @2 = %3 LIMIT 1', self::table, 'id', $id);
		$total = $SQL->NumRows();

		if ($total == 0) {
			Logger::error('main', "Abstract_Session::delete($id_) session does not exist (NumRows == 0)");
			return false;
		}

		$SQL->DoQuery('DELETE FROM #1 WHERE @2 = %3 LIMIT 1', self::table, 'id', $id);

		Abstract_Liaison::delete('ServerSession', NULL, $id_);

		$tokens = Abstract_Token::load_by_session($id_);
		foreach ($tokens as $token)
			Abstract_Token::delete($token->id);

		return true;
	}

	private static function generateFromRow($row_) {
		foreach ($row_ as $k => $v)
			$$k = $v;

		$buf = new Session((string)$id);
		$buf->client_id = (string)$client_id;
		$buf->need_creation = (bool)$need_creation;
		$buf->server = (string)$server;
		$buf->mode = (string)$mode;
		$buf->type = (string)$type;
		$buf->status = (string)$status;
		$buf->settings = json_unserialize($settings);
		$buf->user_login = (string)$user_login;
		$buf->user_displayname = (string)$user_displayname;
		$buf->servers = json_unserialize($servers);
		
		$data = json_unserialize($applications);
		if (array_key_exists('applications', $data))
			$buf->setPublishedApplications(array_map("Application::fromArray", $data['applications']));
		if (array_key_exists('running_applications', $data))
			$buf->setRunningApplications(array_map("Application::fromArray", $data['running_applications']));
		if (array_key_exists('closed_applications', $data))
			$buf->setClosedApplications(array_map("Application::fromArray", $data['closed_applications']));
		
		$buf->start_time = (string)$start_time;
		$buf->timestamp = (int)$timestamp;

		return $buf;
	}

	public static function load_all() {
		Logger::debug('main', 'Starting Abstract_Session::load_all');
		
		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT * FROM #1', self::table);
		$rows = $SQL->FetchAllResults();

		$sessions = array();
		foreach ($rows as $row) {
			$session = self::generateFromRow($row);
			if (! is_object($session))
				continue;

			$sessions[] = $session;
		}

		return $sessions;
	}

	public static function load_partial($offset_=NULL, $start_=NULL) {
		Logger::debug('main', 'Starting Abstract_Session::load_partial('.$offset_.', '.$start_.')');

		$SQL = SQL::getInstance();

		if (! is_null($offset_))
			$SQL->DoQuery('SELECT * FROM #1 ORDER BY @2 DESC LIMIT '.((! is_null($start_))?$start_.',':'').$offset_, self::table, 'timestamp');
		else
			$SQL->DoQuery('SELECT * FROM #1 ORDER BY @2 DESC', self::table, 'timestamp');
		$rows = $SQL->FetchAllResults();

		$sessions = array();
		foreach ($rows as $row) {
			$session = self::generateFromRow($row);
			if (! is_object($session))
				continue;

			$sessions[] = $session;
		}

		return $sessions;
	}

	public static function uptodate($session_) {
		Logger::debug('main', 'Starting Abstract_Session::uptodate for \''.$session_->id.'\'');
		
		$SQL = SQL::getInstance();
		$SQL->DoQuery('SELECT @1 FROM #2 WHERE @3 = %4 LIMIT 1', 'timestamp', self::table, 'id', $session_->id);
		$total = $SQL->NumRows();

		if ($total == 0) {
			Logger::error('main', "Abstract_Session::uptodate($session_) session does not exist (NumRows == 0)");
			return false;
		}

		$row = $SQL->FetchResult();

		if ((int)$row['timestamp'] < time()-DEFAULT_CACHE_DURATION)
			return false;

		return true;
	}

	public static function countByStatus($status_=NULL) {
		Logger::debug('main', "Starting Abstract_Session::countByStatus($status_)");

		$SQL = SQL::getInstance();

		if (! is_null($status_))
			$SQL->DoQuery('SELECT 1 FROM #1 WHERE @2 = %3', self::table, 'status', $status_);
		else
			$SQL->DoQuery('SELECT 1 FROM #1', self::table);

		return $SQL->NumRows();
	}

	public static function countByServer($server_id_) {
		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT 1 FROM #1 WHERE @2 = %3', self::table, 'server', $server_id_);

		return $SQL->NumRows();
	}

	public static function getByServer($server_id_, $offset_=NULL, $start_=NULL) {
		$SQL = SQL::getInstance();

		if (! is_null($offset_))
			$SQL->DoQuery('SELECT * FROM #1 WHERE @2 LIKE %3 ORDER BY @4 DESC LIMIT '.((! is_null($start_))?$start_.',':'').$offset_, self::table, 'servers', '%'.$server_id_.'%', 'timestamp');
		else
			$SQL->DoQuery('SELECT * FROM #1 WHERE @2 LIKE %3 ORDER BY @4 DESC', self::table, 'servers', '%'.$server_id_.'%', 'timestamp');
		$rows = $SQL->FetchAllResults();

		$sessions = array();
		foreach ($rows as $row) {
			$session = self::generateFromRow($row);
			if (! is_object($session))
				continue;

			$sessions[] = $session;
		}

		return $sessions;
	}

	public static function getByUser($user_login_) {
		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT * FROM #1 WHERE @2 = %3', self::table, 'user_login', $user_login_);
		$rows = $SQL->FetchAllResults();

		$sessions = array();
		foreach ($rows as $row) {
			$session = self::generateFromRow($row);
			if (! is_object($session))
				continue;

			$sessions[] = $session;
		}

		return $sessions;
	}

	public static function getByFSUser($fs_user_login_) {
		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT * FROM #1 WHERE @2 LIKE %3', self::table, 'settings', '%fs_access_login%'.$fs_user_login_.'%');
		$rows = $SQL->FetchAllResults();

		$sessions = array();
		foreach ($rows as $row) {
			$session = self::generateFromRow($row);
			if (! is_object($session))
				continue;

			$sessions[] = $session;
		}

		return $sessions;
	}
	
	public static function getByNetworkFolder($network_folder_id_) {
		$SQL = SQL::getInstance();
		
		$SQL->DoQuery('SELECT * FROM #1 WHERE @2 LIKE %3', self::table, 'servers', '%dir%'.$network_folder_id_.'%');
		$rows = $SQL->FetchAllResults();
		
		$sessions = array();
		foreach ($rows as $row) {
			$session = self::generateFromRow($row);
			if (! is_object($session))
				continue;
			
			$sessions[] = $session;
		}
		
		return $sessions;
	}
}
