<?php
/**
 * Copyright (C) 2008-2014 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com> 2008-2011
 * Author Jeremy DESVAGES <jeremy@ulteo.com> 2008-2010
 * Author Antoine WALTER <anw@ulteo.com> 2008
 * Author Julien LANGLOIS <julien@ulteo.com> 2011, 2013
 * Author David LECHEVALIER <david@ulteo.com> 2014
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
if (!function_exists('ldap_connect'))
	die_error('Please install LDAP support for PHP',__FILE__,__LINE__);

if (! defined('LDAP_INVALID_CREDENTIALS'))
	define('LDAP_INVALID_CREDENTIALS', 0x0031);

class LDAP {
	private $link=NULL;
	private $buf_errno;
	private $buf_error;

	private $hosts = array();
	private $port;
	private $use_ssl = false;
	private $login;
	private $password;
	private $suffix;
	private $options = array();
	private $attribs = array();

	public function __construct($config_){
		Logger::debug('main', 'LDAP - construct');
		if (isset($config_['hosts']))
			$this->hosts = $config_['hosts'];
		if (isset($config_['port']))
			$this->port = $config_['port'];
		if (isset($config_['use_ssl']))
			$this->use_ssl = ($config_['use_ssl'] === 1);
		if (isset($config_['login']))
			$this->login = $config_['login'];
		if (isset($config_['password']))
			$this->password = $config_['password'];
		if (isset($config_['suffix']))
			$this->suffix = $config_['suffix'];
		if (isset($config_['options']))
			$this->options = $config_['options'];

	}
	public function __sleep() {
		$this->disconnect();
	}

	public function __wakeup() {
		$this->connect();
	}

	private function check_link() {
		if (is_null($this->link)){
			$this->connect();
		}
	}

	private function connect_on_one_host($host, &$log=array()) {
		Logger::debug('main', 'LDAP - connect_on_one_host(\''.$host.'\', \''.$this->port.'\')');
		$buf = false;
		$buf = @ldap_connect($this->get_ldap_uri($host));
		if (!$buf) {
			Logger::error('main', 'Link to LDAP server failed. Please try again later.');
			$log['LDAP connect'] = false;
			return false;
		}
		$log['LDAP connect'] = true;

		$this->link = $buf;
		foreach ($this->options as $an_option => $an_value) {
			if (! defined($an_option)) {
				continue;
			}
			
			@ldap_set_option($this->link, constant($an_option), $an_value);
		}

		if ($this->login == '') {
			$buf_bind = $this->bind();
			if ($buf_bind === false) {
				Logger::error('main', 'LDAP::connect bind anonymous failed');
				$log['LDAP anonymous bind'] = false;
			}
			else
				$log['LDAP anonymous bind'] = true;
		}
		else {
			$buf_bind = $this->bind($this->login, $this->password);
			if ($buf_bind === false) {
				Logger::error('main', 'LDAP::connect bind failed');
				$log['LDAP bind'] = false;
			}
			else
				$log['LDAP bind'] = true;
		}
		return $buf_bind;
	}

	public function connect(&$log=array()) {
		Logger::debug('main', 'LDAP - connect(\''.serialize($this->hosts).'\', \''.$this->port.'\')');
		$buf = false;
		foreach ($this->hosts as $host) {
			if ($host === '')
				continue;
			$buf = $this->connect_on_one_host($host, $log);
			if ($buf !== false) {
				break;
			}
		}
		return $buf;
	}

	public function disconnect() {
		Logger::debug('main', 'LDAP - disconnect()');

		@ldap_close($this->link);
	}

	private function bind($dn_=NULL, $pwd_=NULL){
		Logger::debug('main', "LDAP - bind('".$dn_."')");
		$buf = @ldap_bind($this->link, $dn_, $pwd_);

		if (!$buf) {
			Logger::error('main', "LDAP::bind bind with user '$dn_' failed : (error:".$this->errno().')');
			$auth = '';
			
			if (! empty($dn_))
				$auth = '-W -D "'.$dn_.'"';

			$protocol_version = '';
			if (array_key_exists('LDAP_OPT_PROTOCOL_VERSION', $this->options))
				$protocol_version = '-P '.$this->options['LDAP_OPT_PROTOCOL_VERSION'];
			$ldapsearch = 'ldapsearch -x -H "'.$this->get_ldap_uri($this->hosts[0]).'" '.$protocol_version.' '.$auth.' -LLL -b "'.$this->suffix.'"';
			Logger::error('main', 'LDAP - failed to validate the configuration please try this bash command : '.$ldapsearch);
			return false;
		}

		return $buf;
	}

	public function errno() {
		Logger::debug('main', 'LDAP - errno()');

		$this->check_link();

		if ($this->buf_errno)
			return $this->buf_errno;

		return @ldap_errno($this->link);
	}

	public function error() {
		Logger::debug('main', 'LDAP - error()');

		$this->check_link();

		if ($this->buf_error)
			return $this->buf_error;

		return @ldap_error($this->link);
	}

	public function error_string() {
		Logger::debug('main', 'LDAP - error_string()');
		
		$this->check_link();
		
		@ldap_get_option($this->link, LDAP_OPT_ERROR_STRING, $error);
		return $error;
	}
	
	public function search($filter_, $attribs_=NULL, $limit_=0) {
		$this->check_link();
		if (! is_null($attribs_)) {
			$attribs_ = array_unique($attribs_);
		}
		Logger::debug('main', 'LDAP - search(\''.$filter_.'\','.self::log_attribs($attribs_).',\''.$this->suffix.'\','.$limit_.')');
		if (array_key_exists('debug', $this->options)) {
			Logger::info('main', 'LDAP - search(\''.$filter_.'\','.self::log_attribs($attribs_).',\''.$this->suffix.'\','.$limit_.')');
		}

		if (is_null($attribs_))
			$attribs_ = $this->attribs;

		$ret = @ldap_search($this->link, $this->suffix, $filter_, $attribs_, 0, $limit_);

		if (is_resource($ret))
			return $ret;

		return false;
	}

	public function searchDN($filter_, $attribs_=NULL) {
		$this->check_link();
		if (! is_null($attribs_)) {
			$attribs_ = array_unique($attribs_);
		}
		
		Logger::debug('main', 'LDAP - searchDN(\''.$filter_.'\','.self::log_attribs($attribs_).',\''.$this->suffix.'\')');

		if (is_null($attribs_))
			$attribs_ = $this->attribs;

		$buf = explode_with_escape(',', $filter_, 2);

		$ret = @ldap_search($this->link, $buf[1], $buf[0], $attribs_, 0, 1);

		if (is_resource($ret))
			return $ret;

		return false;
	}

	public function get_entries($search_) {
		Logger::debug('main', 'LDAP - get_entries()');

		$this->check_link();

		if (!is_resource($search_)) {
			Logger::error('main', 'LDAP::get_entries: search_ is not a resource (type: '.gettype($search_).')');
			return false;
		}

		$ret = array();
		for ($entryID=ldap_first_entry($this->link, $search_); $entryID != false; $entryID = ldap_next_entry($this->link, $entryID)) {
			$info = ldap_get_attributes($this->link, $entryID);
			$dn = ldap_get_dn($this->link, $entryID);
			if ( $dn !== false)
				$ret[$dn] = $info;
		}
		return $ret;
	}

	public function count_entries($result_) {
		Logger::debug('main', 'LDAP - count_entries()');

		$this->check_link();

		if (!is_resource($result_))
			return false;

		$ret = @ldap_count_entries($this->link, $result_);

		if (is_numeric($ret))
			return $ret;

		return false;
	}
	
	public function branch_exists($branch) {
		$this->check_link();
		if ($branch == '') {
			$dn = $this->suffix;
		}
		else {
			$dn = $branch.','.$this->suffix;
		}
		$ret = @ldap_read($this->link, $dn, "(objectclass=*)");
		if (is_resource($ret))
			return true;
		return false;
	}
	
	public static function join_filters($filters, $rule) {
		// rule can be & or |
		$res = array();
		foreach($filters as $filter) {
			if (is_null($filter)) {
				continue;
			}
			
			$filter = trim($filter);
			if (strlen($filter) == 0) {
				continue;
			}
			
			if (! (str_startswith($filter, '(') and str_endswith($filter, ')'))) {
				$filter = '('.$filter.')';
			}
			
			array_push($res, $filter);
		}
		
		switch(count($res)) {
			case 0:
				return null;
			case 1:
				return $res[0];
			default:
				return '('.$rule.implode('', $res).')';
		}
	}
	
	protected static function log_attribs($attribs_) {
		if (is_null($attribs_)) {
			return 'null';
		}
		elseif (is_array($attribs_)) {
			return '['.implode(', ', $attribs_).']';
		}
		
		return $attribs_;
	}
	
	private function get_ldap_uri($host_) {
		$ldap_uri = '';
		if ($this->use_ssl)
			$ldap_uri.= 'ldaps://';
		else
			$ldap_uri.= 'ldap://';
		
		$ldap_uri.= $host_;
		
		if (($this->use_ssl === false && $this->port != 389 ) || ($this->use_ssl === true && $this->port != 636 ))
			$ldap_uri.= ":".$this->port;
		
		return $ldap_uri;
	}
}
