# -*- coding: utf-8 -*-

# Copyright (C) 2009-2014 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2009, 2011, 2012
# Author Samuel BOVEE <samuel@ulteo.com> 2010-2011
# Author David LECHEVALIER <david@ulteo.com> 2012, 2013, 2014
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


import grp
import locale
import os
import platform
import pwd
import time

from ovd.Logger import Logger

from Base.System import System as AbstractSystem

# Remove language support from process to avoid to have translated 
# error message, perl warning, unsupported languages and so on ...
if "LANG" in os.environ:
	del os.environ["LANG"]

class System(AbstractSystem):
	@staticmethod
	def getName():
		return "linux"
	
	
	@staticmethod
	def get_default_config_dir():
		return "/etc/ulteo/ovd"
	
	
	@staticmethod
	def get_default_spool_dir():
		return "/var/spool/ulteo/ovd/slaveserver"
	
	@staticmethod
	def get_default_sys_dir():
		return "/sys"
	
	
	@staticmethod
	def get_default_data_dir():
		return "/var/lib/ulteo/ovd/slaveserver"
	
	
	@staticmethod
	def get_default_log_dir():
		return "/var/log/ulteo/ovd"
	
	
	@staticmethod
	def getVersion():
		try:
			f = file("/etc/issue", "r")
			buf = f.readline()
			buf = buf.replace("\\n", "")
			buf = buf.replace("\\l", "")
			buf = buf.replace("\n", "")
			buf = buf.encode('utf-8')
		
		except Exception:
			Logger.exception("System::getVersion")
			buf = platform.version()
		
		return buf
	
	
	@staticmethod
	def _getCPULoad():
		try:
			fd = file("/proc/stat", "r")
			line = fd.readline()
			fd.close()
		except Exception:
			Logger.exception("System::getCPULoad")
			return (0.0, 0.0)
		
		
		values = line.strip().replace("  ", " ").split(" ")
		
		load = float(values[1]) + float(values[2]) + float(values[3])
		total = load + float(values[4])
		
		return (load, total)
	
	
	@staticmethod
	def getCPULoad():
		(load1, total1) = System._getCPULoad()
		time.sleep(1)
		(load2, total2) = System._getCPULoad()
	
		if total2 - total1 == 0:
			return 0.0
			
		return ((load2 - load1) / (total2 - total1))
	
	
	@staticmethod
	def parseProcFile(filename_):
		try:
			fd = file(filename_, "r")
			lines = fd.readlines()
			fd.close()
		except Exception:
			Logger.exception("System::_getMeminfo")
			return {}
		
		infos = {}
		for line in lines:
			line = line.strip()
			if not ":" in line:
				continue
			
			k,v = line.split(":", 1)
			infos[k.strip()] = v.strip()
		
		return infos
	
	
	@staticmethod
	def getCPUInfos():
		infos = System.parseProcFile("/proc/cpuinfo")
		
		try:
			name = infos["model name"]
			nb = int(infos["processor"]) + 1
			
		except Exception:
			Logger.exception("getCPUInfos")
			return (1, "Unknown")
		
		return (nb, name)
	
	
	@staticmethod
	def _getMeminfo():
		try:
			fd = file("/proc/meminfo", "r")
			lines = fd.readlines()
			fd.close()
		except Exception:
			Logger.exception("System::_getMeminfo")
			return {}
		
		infos = {}
		for line in lines:
			line = line.strip()
			if not ":" in line:
				continue
			
			k,v = line.split(":", 1)
			
			v = v.strip()
			if " " in v:
				v,_ = v.split(" ", 1)
			
			infos[k.strip()] = v.strip()
		
		return infos
	
	
	@staticmethod
	def getRAMUsed():
		infos = System._getMeminfo()
		
		try:
			total = int(infos["MemTotal"])
			free = int(infos["MemFree"])
			cached = int(infos["Cached"])
			buffers = int(infos["Buffers"])
		except Exception:
			Logger.exception("getRAMUsed")
			return 0.0
		
		return total - (free + cached + buffers)
	
	
	@staticmethod
	def getRAMTotal():
		infos = System._getMeminfo()
		
		try:
			total = int(infos["MemTotal"])
		
		except Exception:
			Logger.exception("getRAMTotal")
			return 0.0
		
		return total
	
	
	@staticmethod
	def getADDomain():
		return False
	
	
	@staticmethod
	def logoff(user, domain):
		raise Exception("Not implementer")
	
	
	@staticmethod
	def DeleteDirectory(path):
		System.execute("rm -rf '%s'"%(path))
	
	
	@staticmethod
	def groupCreate(name_):
		cmd = "groupadd %s"%(name_)
		
		p = System.execute(cmd)
		if p.returncode != 0:
			Logger.error("groupCreate return %d (%s)"%(p.returncode, p.stdout.read()))
			return False
		
		return True
	
	
	@staticmethod
	def groupDelete(name_):
		cmd = "groupdel  %s"%(name_)
		p = System.execute(cmd)
		if p.returncode is not 0:
			Logger.error("groupDelete return '%d' (%s)"%(p.returncode, p.stdout.read().decode("UTF-8")))
			return False
		
		return True
	
	
	@staticmethod
	def groupExist(name_):
		try:
			grp.getgrnam(name_)
		except KeyError:
			return False
		
		return True
	
	
	@staticmethod
	def groupMember(name_):
		try:
			group = grp.getgrnam(name_)
		except KeyError:
			Logger.exception("groupMember")
			return None
		
		return group[3]
	
	
	@staticmethod
	def userRemove(name_):
		cmd = "userdel --force  --remove %s"%(name_)
		
		p = System.execute(cmd)
		if p.returncode == 12:
			Logger.debug("mail dir error: '%s' return %d => %s"%(cmd, p.returncode, p.stdout.read()))
		elif p.returncode != 0:
			Logger.error("userRemove return %d (%s)"%(p.returncode, p.stdout.read()))
			return False
		
		return True
	
	
	@staticmethod
	def userAdd(login_, displayName_, password_, groups_):
		cmd = "useradd -m -k /dev/null %s"%(login_)
		p = System.execute(cmd)
		if p.returncode != 0:
			Logger.error("userAdd return %d (%s)"%(p.returncode, p.stdout.read()))
			return False
		
		cmd = 'echo "%s:%s" | chpasswd'%(login_, password_)
		p = System.execute(cmd)
		if p.returncode != 0:
			Logger.error("userAdd return %d (%s)"%(p.returncode, p.stdout.read()))
			return False
		
		for group in groups_:
			cmd = "adduser %s %s"%(login_, group)
			p = System.execute(cmd)
			if p.returncode != 0:
				Logger.error("userAdd return %d (%s)"%(p.returncode, p.stdout.read()))
				return False
		
		return True
	
	
	@staticmethod
	def userExist(name_):
		try:
			pwd.getpwnam(System.local_encode(name_))
		except KeyError:
			return False
		
		return True
	
	
	@classmethod
	def customize_subprocess_args(cls, args):
		args["preexec_fn"] =  os.setpgrp
	
	
	@staticmethod
	def tcp_server_allow_reuse_address():
		return True
	
	
	@staticmethod
	def prepareForSessionActions():
		pass
	
	
	@staticmethod
	def _rchown(path, uid, gid):
		os.chown(path, uid, gid)
		for item in os.listdir(path):
			itempath = os.path.join(path, item)
			os.chown(itempath, uid, gid)
			
			if os.path.isdir(itempath):
				System._rchown(itempath, uid, gid)
	
	
	@staticmethod
	def rchown(path, user):
		p = None
		try:
			p = pwd.getpwnam(System.local_encode(user))
		except:
			return False
		
		try:
			System._rchown(path, p.pw_uid, p.pw_gid)
		except:
			return False
		
		return True
