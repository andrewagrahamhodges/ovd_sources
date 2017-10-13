# -*- coding: UTF-8 -*-

# Copyright (C) 2009-2014 Ulteo SAS
# http://www.ulteo.com
# Author Laurent CLOUET <laurent@ulteo.com> 2010-2011
# Author Julien LANGLOIS <julien@ulteo.com> 2009, 2010, 2011, 2012, 2014
# Author Thomas MOUTON <thomas@ulteo.com> 2010
# Author David LECHEVALIER <david@ulteo.com> 2012
# Author David PHAM-VAN <d.pham-van@ulteo.com> 2012, 2014
#
# This program is free software; you can redistribute it and/or 
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; version 2
# of the License
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

import os
import pwd
import shutil

from ovd.Config import Config
from ovd.Logger import Logger
from ovd.Role.ApplicationServer.Session import Session as AbstractSession
from ovd.Platform.System import System

class Session(AbstractSession):
	SPOOL_USER = "/var/spool/ulteo/ovd/"
	
	def init(self):
		pass
	
	
	def install_client(self):
		name = System.local_encode(self.user.name)
		
		self.clean_tmp_dir()
		
		d = os.path.join(self.SPOOL_USER, self.user.name)
		self.init_user_session_dir(d)
		
		os.chown(self.instanceDirectory, pwd.getpwnam(name)[2], -1)
		os.chown(self.user_session_dir, pwd.getpwnam(name)[2], -1)
		
		xdg_dir = os.path.join(d, "xdg")
		xdg_app_d = os.path.join(xdg_dir, "applications")
		if not os.path.isdir(xdg_app_d):
			os.makedirs(xdg_app_d)
			os.chown(xdg_app_d, pwd.getpwnam(name)[2], -1)
		
		self.install_desktop_shortcuts()
		
		System.execute('update-desktop-database "%s"'%(System.local_encode(xdg_app_d)))
		
		if self.parameters.has_key("desktop_icons") and self.parameters["desktop_icons"] == "1":
			path = os.path.join(xdg_app_d, ".show_on_desktop")
			f = file(path, "w")
			f.close()
		
		for f in os.listdir(xdg_app_d):
			os.chown(os.path.join(xdg_app_d, f), pwd.getpwnam(name)[2], -1)
		
		env_file_lines = []
		# Set the language
		if self.parameters.has_key("locale"):
			env_file_lines.append("LANG=%s.UTF-8\n"%(self.parameters["locale"]))
			env_file_lines.append("LC_ALL=%s.UTF-8\n"%(self.parameters["locale"]))
			env_file_lines.append("LANGUAGE=%s.UTF-8\n"%(self.parameters["locale"]))
		
		if self.parameters.has_key("timezone"):
			tz_file = "/usr/share/zoneinfo/" + self.parameters["timezone"]
			if not os.path.exists(tz_file):
				Logger.warn("Unsupported timezone '%s'"%(self.parameters["timezone"]))
				Logger.debug("Unsupported timezone '%s'. File '%s' does not exists"%(self.parameters["timezone"], tz_file))
			else:
				env_file_lines.append("TZ=%s\n"%(tz_file))
		
		f = file(os.path.join(d, "env"), "w")
		f.writelines(env_file_lines)
		f.close()
		
		if self.profile is not None:
			profile_cache_dir = os.path.join(self.SPOOL_USER, "profiles", self.user.name)
			if not os.path.isdir(profile_cache_dir):
				os.makedirs(profile_cache_dir)
			os.chown(profile_cache_dir, pwd.getpwnam(name)[2], -1)
			
			if self.profile.mount() == False:
				if self.parameters.has_key("need_valid_profile") and self.parameters["need_valid_profile"] == "1":
					return False
			
			shares_dir = os.path.join(d, "shares")
			if not os.path.isdir(shares_dir):
				os.makedirs(shares_dir)
			
			self.profile.register_shares(shares_dir)
		
		return True
	
	
	def uninstall_client(self):
		self.archive_shell_dump()
		
		if self.profile is not None:
			self.profile.umount()
		
		d = os.path.join(self.SPOOL_USER, self.user.name)
		if os.path.exists(d):
			shutil.rmtree(d)
		
		self.clean_tmp_dir()
	
	
	def get_target_file(self, application):
		return "%s.desktop"%(str(application["id"]))
	
	
	
	def clone_shortcut(self, src, dst, command, args):
		try:
			f = file(src, "r")
		except:
			return False
		
		lines = f.readlines()
		f.close()
		
		for i in xrange(len(lines)):
			if lines[i].startswith("Exec="):
				lines[i] = "Exec=%s %s\n"%(command, " ".join(args))  
		
		try:
			f = file(dst, "w")
		except:
			return False
		
		f.writelines(lines)
		f.close()
		return True
	
	
	def install_shortcut(self, shortcut):
		xdg_app_d = os.path.join(self.user_session_dir, "xdg", "applications")
		
		dstFile = os.path.join(xdg_app_d, os.path.basename(shortcut))
		if os.path.exists(dstFile):
			os.remove(dstFile)
		
		shutil.copyfile(shortcut, dstFile)
	
	
	def clean_tmp_dir(self):
		# Purge TMP directory
		uid = pwd.getpwnam(System.local_encode(self.user.name))[2]
		for f in os.listdir("/tmp"):
			filename = os.path.join("/tmp", f)
			s = os.lstat(filename)
			if s.st_uid != uid:
				continue
			
			try:
				if os.path.isdir(filename):
					shutil.rmtree(filename)
				else:
					os.remove(filename)
			except:
				Logger.exception("Unable to remove %s"%(filename))
