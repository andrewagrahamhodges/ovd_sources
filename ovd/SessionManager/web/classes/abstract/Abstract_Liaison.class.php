<?php
/**
 * Copyright (C) 2009-2013 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com> 2009
 * Author Julien LANGLOIS <julien@ulteo.com> 2012, 2013
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
require_once(dirname(__FILE__).'/../../includes/core.inc.php');

class Abstract_Liaison {
	const table = 'liaison';
	
	public static function load($type_, $element_=NULL, $group_=NULL) {
		if (is_null($element_) && is_null($group_))
			return Abstract_Liaison::loadAll($type_);
		else if (is_null($element_))
			return Abstract_Liaison::loadElements($type_, $group_);
		else if (is_null($group_))
			return Abstract_Liaison::loadGroups($type_, $element_);
		else
			return Abstract_Liaison::loadUnique($type_, $element_, $group_);
	}
	public static function save($type_, $element_, $group_) {
		Logger::debug('main', "Abstract_Liaison::save ($type_,$element_,$group_)");
		$sql2 = SQL::getInstance();
		
		$res = $sql2->DoQuery('SELECT @3,@4 FROM #1 WHERE @2=%5 AND @3=%6 AND @4=%7', self::table, 'type', 'element', 'group',  $type_, $element_, $group_);
		if ($sql2->NumRows() > 0) {
			ErrorManager::report('liaison(type='.$type_.',element='.$element_.',group='.$group_.') already exists');
			return false;
		}
		$res = $sql2->DoQuery('INSERT INTO #1 ( @2,@3,@4 ) VALUES ( %5,%6,%7)', self::table, 'type', 'element', 'group', $type_, $element_, $group_);
		return ($res !== false);
	}
	public static function delete($type_, $element_, $group_) {
		Logger::debug('main', "Abstract_Liaison::delete ($type_,$element_,$group_)");
		$sql2 = SQL::getInstance();
		$prefs = Preferences::getInstance();
		if (! $prefs) {
			Logger::error('main', 'Abstract_Liaison::delete get Preferences failed');
			return false;
		}
		
		$res = false;
		if (is_null($element_) && is_null($group_)) {
			$res = $sql2->DoQuery('DELETE FROM #1 WHERE @2=%3', self::table, 'type', $type_);
		}
		else if (is_null($element_)) {
			$res = $sql2->DoQuery('DELETE FROM #1 WHERE @2=%3 AND @4=%5', self::table, 'type', $type_, 'group', $group_);
		}
		else if (is_null($group_)) {
			$res = $sql2->DoQuery('DELETE FROM #1 WHERE @2=%3 AND @4=%5', self::table, 'type', $type_, 'element', $element_);
		}
		else {
			$res = $sql2->DoQuery('DELETE FROM #1 WHERE @2=%3 AND @4=%5 AND @6=%7', self::table, 'type', $type_, 'element', $element_, 'group', $group_);
		}
		return ($res !== false);
	}
	public static function loadElements($type_, $group_) {
		Logger::debug('main', "Abstract_Liaison::loadElements ($type_,$group_)");
		$result = array();
		$sql2 = SQL::getInstance();
		$prefs = Preferences::getInstance();
		if (! $prefs) {
			Logger::error('main', 'Abstract_Liaison::loadElements get Preferences failed');
			return NULL;
		}
		
		$res = $sql2->DoQuery('SELECT @1 FROM #2 WHERE @3 = %4 AND @5 = %6','element', self::table, 'type', $type_, 'group', $group_);
		if ($res !== false){
			$result = array();
			$rows = $sql2->FetchAllResults($res);
			foreach ($rows as $row){
				$l = new Liaison($row['element'], $group_);
				$result[$l->element]= $l;
			}
			return $result;
		}
		Logger::error('main', "Abstract_Liaison::loadElements($type_, $group_) error end of function");
		return NULL;
	}
	
	public static function loadGroups($type_, $element_) {
		Logger::debug('main', "Abstract_Liaison::loadGroups ($type_,$element_)");
		$result = array();
		$sql2 = SQL::getInstance();
		$prefs = Preferences::getInstance();
		if (! $prefs) {
			Logger::error('main', 'Abstract_Liaison::loadGroups get Preferences failed');
			return NULL;
		}
		
		$res = $sql2->DoQuery('SELECT @1 FROM #2 WHERE @3 = %4 AND @5 = %6', 'group', self::table, 'type', $type_, 'element', $element_);
		if ($res !== false){
			$result = array();
			$rows = $sql2->FetchAllResults($res);
			foreach ($rows as $row){
				$l = new Liaison($element_, $row['group']);
				$result[$l->group]= $l;
			}
			return $result;
		}
		Logger::error('main', "Abstract_Liaison::loadGroups($type_, $element_) error end of function");
		return NULL;
	}
	
	public static function loadAll($type_) {
		Logger::debug('main', "Abstract_Liaison::loadAll ($type_)");
		$result = array();
		$sql2 = SQL::getInstance();
		$prefs = Preferences::getInstance();
		if (! $prefs) {
			Logger::error('main', 'Abstract_Liaison::loadAll get Preferences failed');
			return NULL;
		}
		
		$res = $sql2->DoQuery('SELECT @1,@2 FROM #3 WHERE @4 = %5', 'element', 'group', self::table, 'type', $type_);
		if ($res !== false){
			$result = array();
			$rows = $sql2->FetchAllResults($res);
			foreach ($rows as $row){
				$l = new Liaison($row['element'], $row['group']);
				$result[]= $l;
			}
			return $result;
		}
		Logger::error('main', "Abstract_Liaison::loadAll($type_) error end of function");
		return NULL;
	}
	public static function loadUnique($type_, $element_, $group_) {
		Logger::debug('main', "Abstract_Liaison::loadUnique ($type_,$element_,$group_)");
		$result = array();
		$sql2 = SQL::getInstance();
		$prefs = Preferences::getInstance();
		if (! $prefs) {
			Logger::error('main', 'Abstract_Liaison::loadAll get Preferences failed');
			return NULL;
		}
		
		$res = $sql2->DoQuery('SELECT @3,@4 FROM #1 WHERE @2=%5 AND @3=%6 AND @4=%7', self::table, 'type', 'element', 'group',  $type_, $element_, $group_);
// 		echo 'FetchAllResults ';var_dump2($sql2->FetchAllResults());
		if ($res !== false){
			if ($sql2->NumRows() == 0) {
				return NULL;
			}
			else if ($sql2->NumRows() > 1) {
				Logger::error('main', "Abstract_Liaison::loadUnique($type_, $element_, $group_) error doublon (".$sql2->NumRows().")");
				self::cleanup();
			}
			return new Liaison($element_, $group_);
		}
		else {
			Logger::error('main', "Abstract_Liaison::loadUnique($type_, $element_, $group_) error DoQuery failed");
			return NULL;
		}
	}
	
	public static function init($prefs_) {
		$sql_conf = $prefs_->get('general', 'sql');
		if (!is_array($sql_conf)) {
			Logger::error('main', 'Abstract_Liaison::init sql conf not valid');
			return false;
		}
		// we create the sql table
		$sql2 = SQL::newInstance($sql_conf);
		
		$LIAISON_table_structure = array(
			'type' => 'varchar(200) NOT NULL',
			'element' => 'varchar(200) NOT NULL',
			'group' => 'varchar(200) NOT NULL');
		
		$ret = $sql2->buildTable(self::table, $LIAISON_table_structure, array(), array('I_TYPE'=>array('type'), 'I_ELEMENT'=>array('element'), 'I_GROUP'=>array('group')));
		
		if ( $ret === false) {
			Logger::error('main', 'Abstract_Liaison::init table '.self::table.' fail to created');
			return false;
		}
		else {
			Logger::debug('main', 'Abstract_Liaison::init table '.self::table.' created');
			return true;
		}
	}
	
	protected static function  cleanup() {
		Logger::debug('main', 'Abstract_Liaison::cleanup');
		$sql2 = SQL::getInstance();
		$prefs = Preferences::getInstance();
		if (! $prefs) {
			Logger::error('main', 'Abstract_Liaison::cleanup get Preferences failed');
			return false;
		}
		
		$res = $sql2->DoQuery('SELECT @1,@2,@3 FROM #4', 'type', 'element', 'group', self::table);
		if ($res === false) {
			Logger::error('main', 'Abstract_Liaison::cleanup DoQuery failed');
			return false;
		}
		
		$rows = $sql2->FetchAllResults();
		$rows2 = $rows; // for bug in php of rhel5.2
		foreach ($rows as $key => $row) {
			foreach ($rows2 as $key2 => $row2) {
				if ($row['type'] == $row2['type'] && $row['element'] == $row2['element'] &&  $row['group'] == $row2['group'] && $key != $key2) {
					// it's a duplicate
					self::delete($row['type'], $row['element'],  $row['group']);
					unset($rows2[$key2]); // optimization
				}
			}
			unset($rows2[$key]); // optimization
		}
		return true;
	}
}
