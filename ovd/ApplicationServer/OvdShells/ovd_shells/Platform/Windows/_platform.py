# -*- coding: utf-8 -*-

# Copyright (C) 2010-2014 Ulteo SAS
# http://www.ulteo.com
# Author Laurent CLOUET <laurent@ulteo.com> 2010
# Author Julien LANGLOIS <julien@ulteo.com> 2010, 2011
# Author David LECHEVALIER <david@ulteo.com> 2011, 2013, 2014
# Author Thomas MOUTON <thomas@ulteo.com> 2012
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

import locale
import os
import time

import platform
import pythoncom
import win32com
import pywintypes
import win32api
import win32com.client
from win32com.shell import shell, shellcon
import win32con
import win32event
import win32file
import win32process
import win32security
import win32ts

import ProcessMonitoring

# this is a hack: we need to store handle from used by deleteOnClose otherwise the file is close
handles = []

def rdpSessionIsConnected():
	sessionState = win32ts.WTSQuerySessionInformation(None, win32ts.WTS_CURRENT_SESSION, win32ts.WTSConnectState);
		
	return sessionState == win32ts.WTSActive

def findProcessWithEnviron(pattern):
	sid, _,_  = win32security.LookupAccountName(None, win32api.GetUserName())
	pid = os.getpid()
	
	pids = win32process.EnumProcesses()
	for this_pid in pids:
		if not isProcessOwnerSID(this_pid, sid):
			continue
		
		if this_pid == pid:
			continue
		
		block = ProcessMonitoring.getEnvironnmentBlock(this_pid)
		if block is None or pattern not in block:
			continue
		
		return this_pid
	
	return None


def isProcessOwnerSID(pid, sid):
	try:
		phandle = win32api.OpenProcess(win32con.PROCESS_QUERY_INFORMATION | win32con.PROCESS_VM_READ, False,  pid)
	except pywintypes.error, err:
		return False
	
	try:
		hProcessToken = win32security.OpenProcessToken(phandle, win32con.TOKEN_READ)
	except pywintypes.error, err:
		win32api.CloseHandle(phandle)
		return False
	
	p_sid  = win32security.GetTokenInformation(hProcessToken, win32security.TokenOwner)
	ret = (p_sid == sid)
	
	win32api.CloseHandle(hProcessToken)
	win32api.CloseHandle(phandle)
	
	return ret


def existProcess(pid):
	try:
		hProcess = win32api.OpenProcess(win32con.PROCESS_QUERY_INFORMATION, False, pid)
	except pywintypes.error, err:
		return False
	
	if hProcess is None:
		return False
	
	win32file.CloseHandle(hProcess);
	return True

def launch(cmd, wait=False):
	(hProcess, hThread, dwProcessId, dwThreadId) = win32process.CreateProcess(None, cmd, None , None, False, 0 , None, None, win32process.STARTUPINFO())
	
	if wait:
		win32event.WaitForSingleObject(hProcess, win32event.INFINITE)
	
	win32file.CloseHandle(hProcess)
	win32file.CloseHandle(hThread)
	
	return dwProcessId

def kill(pid):
	hProcess = win32api.OpenProcess(win32con.PROCESS_TERMINATE, False, pid)
	if hProcess is None:
		 print "doesn't exist pid"
		 return False
	
	ret = win32process.TerminateProcess(hProcess, 0)
	
	win32file.CloseHandle(hProcess);
	return ret

def getUserSessionDir():
	try:
		encoding = locale.getpreferredencoding()
	except locale.Error:
		encoding = "UTF-8"
	
	d = shell.SHGetFolderPath(0, shellcon.CSIDL_COMMON_APPDATA, 0, 0)
	d = d.encode(encoding)
	user = os.environ["USERNAME"]
	
	return os.path.join(d, "ulteo", "ovd", user)


def setupIME():
	# Manage local ime
	path = r"Software\Microsoft\CTF\Assemblies\0x00000409\{34745C63-B2F0-4784-8B67-5E12C8701A31}"
	try:
		CreateKeyR(win32con.HKEY_CURRENT_USER, path)
		key = win32api.RegOpenKey(win32con.HKEY_CURRENT_USER, path, 0, win32con.KEY_SET_VALUE)
	except:
		key = None
	
	if key is None:
		print "Unable to open key '%s'"%(path)
	else:
		win32api.RegSetValueEx(key, "Default", 0, win32con.REG_SZ, "{E7EA138E-69F8-11D7-A6EA-00065B84435C}")
		win32api.RegSetValueEx(key, "Profile", 0, win32con.REG_SZ, "{E7EA138E-69F8-11D7-A6EA-00065B84435C}")
		win32api.RegSetValueEx(key, "KeyboardLayout", 0, win32con.REG_DWORD, 0x04090409)
		win32api.RegCloseKey(key)
	
	path = r"Keyboard Layout\Preload"
	try:
		CreateKeyR(win32con.HKEY_CURRENT_USER, path)
		key = win32api.RegOpenKey(win32con.HKEY_CURRENT_USER, path, 0, win32con.KEY_SET_VALUE)
	except:
		key = None
	if key is None:
		print "Unable to open key '%s'"%(path)
	else:
		win32api.RegSetValueEx(key, "1", 0, win32con.REG_SZ, "00000409")
		win32api.RegCloseKey(key)
	
	path = r"Software\Microsoft\CTF\LangBar"
	try:
		CreateKeyR(win32con.HKEY_CURRENT_USER, path)
		key = win32api.RegOpenKey(win32con.HKEY_CURRENT_USER, path, 0, win32con.KEY_SET_VALUE)
	except:
		key = None
	
	if key is None:
		print "Unable to open key '%s'"%(path)
	else:
		win32api.RegSetValueEx(key, "ShowStatus", 0, win32con.REG_DWORD, 3)
		win32api.RegSetValueEx(key, "Transparency", 0, win32con.REG_DWORD, 0xff)
		win32api.RegSetValueEx(key, "Label", 0, win32con.REG_DWORD, 1)
		win32api.RegSetValueEx(key, "ExtraIconsOnMinimized", 0, win32con.REG_DWORD, 1)
		win32api.RegCloseKey(key)

	
	launch("ukbrdr.exe")


def startDesktop():
	explorer_path = r"%s\explorer.exe"%(os.environ["windir"])
	launch(explorer_path, True)

def startWM():
	pass

def startSeamless():
	launch("seamlessrdpshell")
	
def transformCommand(cmd_, args_):
		args = args_
		if len(args)>0:
			if "%1" in cmd_:
				cmd_ = cmd_.replace("%1", args.pop(0))
			if "%*" in cmd_:
				cmd_ = cmd_.replace("%*", " ".join(['"'+a+'"' for a in args]))
				args = []
		
		if len(args)>0:
			cmd_+= " "+" ".join(['"'+a+'"' for a in args])
		
		return cmd_

def getSubProcess(ppid):
	pythoncom.CoInitialize()
	WMI = win32com.client.GetObject('winmgmts:')
	processes = WMI.InstancesOf('Win32_Process')
	
	pids = []
	
	for process in processes:
		pid = process.Properties_('ProcessID').Value
		parent = process.Properties_('ParentProcessId').Value
		
		if parent == ppid:
			pids.append(pid)
	
	return pids


def lock(t):
	pushLock()
	
	t0 = time.time()
	
	while isLocked():
		if time.time() - t0 > t:
			return False
		
		time.sleep(0.5)
	
	return True


def isLocked():
	appdata = os.getenv("APPDATA")
	lockFile = os.path.join(appdata, "ulteo", "ulock")
	
	return os.path.exists(lockFile)


def pushLock():
	appdata = os.getenv("APPDATA")
	lockFile = os.path.join(appdata, "ulteo", "ulock")
	
	try:
		handle = open(lockFile, 'w')
		handle.close()
	except Exception, e:
		print "Unable to create lock: "+str(e)


def CreateKeyR(hkey, path):
	if path.endswith("\\"):
		path = path[:-2]
	
	if "\\" in path:
		(parents, name) = path.rsplit("\\", 1)
		
		try:
			hkey2 = win32api.RegOpenKey(hkey, parents, 0, win32con.KEY_SET_VALUE)
		except Exception, err:
			CreateKeyR(hkey, parents)
			hkey2 = win32api.RegOpenKey(hkey, parents, 0, win32con.KEY_SET_VALUE)
	else:
		name = path
		hkey2 = hkey
	
	win32api.RegCreateKey(hkey2, name)
	win32api.RegCloseKey(hkey2)


def deleteOnclose(path):
	try:
		handle = win32file.CreateFile(path, win32file.GENERIC_READ, win32file.FILE_SHARE_READ, None, win32file.OPEN_EXISTING, win32file.FILE_FLAG_DELETE_ON_CLOSE, None)
		handles.append(handle)
	except Exception, e:
		print "Failed to mark file '%s'as to delete: %s"%(path, str(e))

def toUnicode(str):
	try:
		encoding = locale.getpreferredencoding()
	except locale.Error:
		encoding = "UTF-8"
	
	return unicode(str, encoding)
	

def getLongVersion():
	pythoncom.CoInitialize()
	wmi = win32com.client.Dispatch("WbemScripting.SWbemLocator")
	wmi_serv = wmi.ConnectServer(".")
	windows_server = wmi_serv.ExecQuery("Select Caption from Win32_OperatingSystem")

	return windows_server[0].Caption


def getVersion():
	varray = platform.version().split(".")
	
	if len(varray) == 1:
		return int(varray(0))
       
	res = float(varray[0]+"."+varray[1])
	
	# We can not make difference between Windows 2012
	# GetVersion is deprecated in Windows Server 2012R2
	# http://msdn.microsoft.com/en-us/library/windows/desktop/ms724439(v=vs.85).aspx
	if res == 6.2 and "R2" in getLongVersion():
		return 6.3
