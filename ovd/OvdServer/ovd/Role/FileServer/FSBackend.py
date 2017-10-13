# -*- coding: utf-8 -*-

# Copyright (C) 2013-2014 Ulteo SAS
# http://www.ulteo.com
# Author David LECHEVALIER <david@ulteo.com> 2013
# Author David PHAM-VAN <d.pham-van@ulteo.com> 2014
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
import signal
import time
import errno

from ovd.Logger import Logger
from ovd.Platform.System import System

from Config import Config


class FSBackend:
	def __init__(self):
		self.path = {}
		self.pid = None
		self.sharesFile = "/etc/ulteo/rufs/shares.conf"
		self.pidFile = "/tmp/FSBackend.pid"
	
	
	def update_shares(self, share, quota, toAdd):
		if self.pid is None:
			try:
				f = open(self.pidFile, "r")
				pidStr = f.readline()
				if len(pidStr) == 0:
					Logger.error("Invalid FSBackend pid: %s"%(pidStr))
					return False
				
				self.pid = int(pidStr)
			except Exception:
				Logger.exception("Failed to get FSBackend pid")
				return False
		
		try:
			# TODO use a file lock
			f = open(self.sharesFile, "rw")
			lines = f.readlines()
			out = ""
			found = False
			f.close()
			
			for line in lines:
				compo = line.split(',')
				if len(compo) != 2:
					# Check comment
					strippedLine = line.strip()
					if strippedLine.startswith("#") or strippedLine.startswith(";") or len(strippedLine) == 0:
						out += line
						continue

					Logger.error("The following line '%s' is not properly formated, removing it"%(line))
					continue
				
				if share == compo[0].strip():
					if toAdd:
						# updating entry
						out += "%s, %s\n"%(share, quota)
						found = True
					continue
				
				# we restore the entry
				out += line
			
			if not found and toAdd:
				# we append a new entry
				out += "%s, %s\n"%(share, quota)
			
			f = open(self.sharesFile, "w+")
			f.write(out)
			f.close()
			
			# force share data relaod
			os.kill(self.pid, signal.SIGHUP)
			return True
			
			
		except Exception:
			Logger.exception("Failed to add entry for the share '%s'"%share)
			return False
		
	
	
	def start(self):
		self.path["spool"] = Config.spool
		self.path["spool.real"] = Config.spool+".real"
		if os.path.ismount(self.path["spool"]):
			Logger.warn("Failed to start FS backend, %s is already mounted"%(self.path["spool"]))
			return False
		
		for p in self.path:
			path = self.path[p]
			try:
				os.makedirs(path)
			except OSError, err:
				if err[0] is not errno.EEXIST:
					Logger.exception("Failed to create spool directory: %s"%path)
					return False
			
			try:
				os.lchown(path, Config.uid, Config.gid)
			except OSError:
				Logger.exception("Unable to change file owner for '%s'"%path)
				return False

			if not os.path.exists(path):
				Logger.error("Spool directory %s do not exist"%(path))
				return False
		
		cmd = "RegularUnionFS \"%s\" \"%s\" -o user=%s -o fsconfig=%s"%(self.path["spool.real"], self.path["spool"], Config.user, Config.FSBackendConf)
		Logger.debug("Backend init command '%s'"%(cmd))
		p = System.execute(cmd)
		if p.returncode != 0:
			Logger.error("Failed to initialize spool directory (status: %d) %s"%(p.returncode, p.stdout.read()))
			return False
		
		return True
	
	
	def stop(self):
		if (len(self.path) == 0) or not os.path.ismount(self.path["spool"]):
			Logger.warn("FSBackend is already stopped")
			return True
		
		cmd = "umount \"%s\""%(self.path["spool"])
		Logger.debug("FSBackend release command '%s'"%(cmd))
		if not os.path.ismount(self.path["spool"]):
			return True
			
		p = System.execute(cmd)
		if p.returncode != 0:
			Logger.debug("FSBackend is busy")
			return False
		
		return True
	
	
	def get_lsof(self, path):
		cmd = "lsof -t \"%s\""%(path)
		
		p = System.execute(cmd)
		if p.returncode != 0:
			Logger.warn("Failed to get the list of processus blocked on a share")
			return None
		
		res = p.stdout.read().decode("UTF-8")
		return res.split()
		
		
	def force_stop(self):
		if (len(self.path) == 0) or not os.path.ismount(self.path["spool"]):
			Logger.warn("FSBackend is already stopped")
			return True
		
		cmd = "umount \"%s\""%(self.path["spool"])
		if not os.path.ismount(self.path["spool"]):
			return True
			
		p = System.execute(cmd)
		if p.returncode == 0:
			return True
		
		pids = self.get_lsof(self.path["spool"])
		
		for pid in pids:
			try:
				pid = int(pid)
				os.kill(pid, signal.SIGTERM)
				time.sleep(0.5)
				os.kill(pid, signal.SIGKILL)
			except Exception, e:
				if e.errno != errno.ESRCH:
					Logger.exception("Failed to kill processus %s"%pid)
		
		if not os.path.ismount(self.path["spool"]):
			return True
		
		Logger.error("FSBackend force the unmount of %s"%(self.path["spool"]))
		cmd = "umount -l \"%s\""%(self.path["spool"])
		p = System.execute(cmd)
		if p.returncode == 0:
			return True
		
		Logger.error("Unable to stop FSBackend %s"%(p.stdout.read().decode("UTF-8")))
		return False
