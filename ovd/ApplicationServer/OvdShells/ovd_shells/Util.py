# -*- coding: utf-8 -*-

# Copyright (C) 2010-2012 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2010, 2011, 2012
# Author David LECHEVALIER <david@ulteo.com> 2011
# Author Thomas MOUTON <thomas@ulteo.com> 2012
# Author David PHAM-VAN <d.pham-van@ulteo.com> 2012
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

import base64
import glob
import os
import sys
import subprocess

from ovd_shells.Platform import _platform as Platform
from ovd_shells.Platform.Novell import Novell

def redirect_to_dump(purge = True):
	path = os.path.join(Platform.getUserSessionDir(), "dump.txt")
	try:
		dirname = os.path.dirname(path)
		if not os.path.exists(dirname):
			os.makedirs(dirname)
		
		if purge is True:
			mode = "w"
		else:
			mode = "a"
		
		buf = file(path, mode, 0)
	except IOError, err:
		return
	
	sys.stderr = buf
	sys.stdout = buf
	print "#################################################"


def loadUserEnv(d):
	path = os.path.join(d, "env")
	try:
		f = file(path, "r")
	except:
		return
	
	lines = f.readlines()
	f.close()
	
	for line in lines:
		line = line.strip()
		try:
			key,value = line.split("=", 1)
		except:
			continue
		
		os.environ[key] = value

def manageAutoStartApplication(config, im):
	for application in config.application_to_start:
		if application.has_key("file") or application.has_key("arg"):
			if application.has_key("file"):
				f_type = application["file"]["type"]
				path = application["file"]["path"]
				share = application["file"]["location"]
				
				if f_type == "native":
					dir_type = im.DIR_TYPE_NATIVE
				elif f_type == "shared_folder":
					dir_type = im.DIR_TYPE_SHARED_FOLDER
				elif f_type == "rdp_drive":
					dir_type = im.DIR_TYPE_RDP_DRIVE
				elif f_type == "known_drives":
					dir_type = im.DIR_TYPE_KNOWN_DRIVES
				elif f_type == "http":
					dir_type = im.DIR_TYPE_HTTP_URL
				else:
					print "manageAutoStartApplication: unknown dir type %X"%(f_type)
					return
			
			else:
				dir_type = im.DIR_TYPE_NATIVE
				share = None
				path  = application["arg"].strip()
			
			im.start_app_with_arg(None, application["id"], dir_type, share, path)
		
		else:
			im.start_app_empty(None, application["id"])

def manageAutoStartScripts(config, d):
	scripts = []
	
	for script in config.scripts_to_start:
		scripts.append(script["name"])
	
	scripts.sort()
	
	for s in scripts:
		print "Start registered scripts", s
		f = os.path.join(d, "scripts", s)
		if not os.access(f, os.R_OK):
			print f, "scripts is not readable"
			continue
		
		if f.endswith('.sh'):
			cmd = '/bin/sh "%s"' %f
		elif f.endswith(".py"):
			cmd = 'python "%s"' %f
		elif f.endswith(".bat"):
			cmd = f
		elif f.endswith(".vbs"):
			cmd = 'wscript "%s"' %f
		elif f.endswith(".ps1"):
			cmd = 'powershell -executionpolicy bypass -file "%s" < CON' %f
			
		p = execute(cmd)
        	if p.returncode != 0:
			print "Command result (%d) :" %(p.returncode)
			print p.stdout.read()
			print "==================="


def startModules():
	novell = Novell()
	novell.perform()

def execute(args, wait = True):
	if type(args) is type([]):
		shell = False
	elif type(args) in [type(""), type(u"")]:
		shell = True
		
	subprocess_args = {}
	subprocess_args["stdin"] = subprocess.PIPE
	subprocess_args["stdout"] = subprocess.PIPE
	subprocess_args["stderr"] = subprocess.STDOUT
	subprocess_args["shell"] = shell
	if "linux" in sys.platform:
		subprocess_args["preexec_fn"] =  os.setpgrp
	
	p = subprocess.Popen(args, **subprocess_args)
	
	if wait:
		p.wait()
		
	return p
