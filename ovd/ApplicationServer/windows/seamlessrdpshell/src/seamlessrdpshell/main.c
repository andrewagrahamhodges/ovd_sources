/* -*- c-basic-offset: 8 -*-
   rdesktop: A Remote Desktop Protocol client.
   Seamless windows - Remote server executable

   Based on code copyright (C) 2004-2005 Martin Wickett

   Copyright (C) Peter Åstrand <astrand@cendio.se> 2005-2006
   Copyright (C) Pierre Ossman <ossman@cendio.se> 2006

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
*/
/*
 * Copyright (C) 2012-2013 Ulteo SAS
 * http://www.ulteo.com
 * Author Thomas MOUTON <thomas@ulteo.com> 2012
 * Author David PHAM-VAN <d.pham-van@ulteo.com> 2013
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
 */

#define WINVER 0x0501

#include <Shlwapi.h>
#include <windows.h>
#include <wtsapi32.h>

#include "activeThread.h"
#include <hook/hookdll.h>
#include <common/internalWindow.h>
#include <common/windowUtil.h>
#include "seamlessChannel.h"
#include "seamlessWindow.h"
#include "seamlessWindowHistory.h"

#include "resource.h"

#define APP_NAME "SeamlessRDP Shell"

/* Default values */
#ifndef DEFAULT_MOVE_OFFSCREEN_FORBIDDEN
	#define DEFAULT_MOVE_OFFSCREEN_FORBIDDEN FALSE
#endif

#ifndef DEFAULT_USE_ACTIVE_MONITORING
	#define DEFAULT_USE_ACTIVE_MONITORING TRUE
#endif

/* Global data */
static HINSTANCE g_instance;

static DWORD g_session_id;
static DWORD *g_startup_procs;
static int g_startup_num_procs;

static BOOL g_connected;
static BOOL g_desktop_hidden;

BOOL g_is_move_offscreen_forbidden = DEFAULT_MOVE_OFFSCREEN_FORBIDDEN;

typedef void (*set_hooks_proc_t) ();
typedef void (*remove_hooks_proc_t) ();
typedef int (*get_instance_count_proc_t) ();

void CALLBACK InternalWindow_processCopyData(PCOPYDATASTRUCT data) {
	SeamlessWindow *sw = NULL;

	switch(data->dwData) {
		case HOOK_MSG_STATE:
			{
				HookMsg_State *msg = (HookMsg_State *)(data->lpData);
				HWND hwnd = (HWND) msg->wnd;
				SeamlessOrder_State *lastOrder = NULL;

				sw = getWindowFromHistory(hwnd);
				if (sw == NULL)
					break;
				
				if (sw->state == msg->state)
					break;
				
				sw->state = msg->state;

				lastOrder = (SeamlessOrder_State *) SeamlessChannel_getLastOrder(SEAMLESSORDER_STATE);

				if (lastOrder && (lastOrder->wnd == hwnd) && (lastOrder->state == msg->state)) {
					SeamlessChannel_sendAck(lastOrder->serial);
				}
				else {
					SeamlessChannel_sendState(hwnd, msg->state, 0);
				}
			}
			break;
		case HOOK_MSG_FOCUS:
			{
				HookMsg_Focus *msg = (HookMsg_Focus *)(data->lpData);
				HWND hwnd = (HWND) msg->wnd;

				sw = getWindowFromHistory(hwnd);
				if (sw == NULL) {
					sw = addHWDNToHistory(hwnd);
					if (sw == NULL)
						break;

					sw->focus = TRUE;
					break;
				}

				if (hwnd == SeamlessChannel_getLastFocusedWindow())
					break;

				SeamlessChannel_setFocusedWindow(hwnd);

				SeamlessChannel_sendFocus(hwnd);
			}
			break;
		case HOOK_MSG_ICON:
			{
				HookMsg_Icon *msg = (HookMsg_Icon *)(data->lpData);
				HWND hwnd = (HWND) msg->wnd;
				int size;

				sw = getWindowFromHistory(hwnd);
				if (sw == NULL)
					break;

				size = (msg->large) ? 32 : 16;

				if (msg->haveToGetIcon) {
					/*
					 * Somehow, we never get WM_SETICON for the small icon.
					 * So trigger a read of it every time the large one is
					 * changed.
					 */
					msg->icon = WindowUtil_getIcon(hwnd, 0);
					if (msg->icon)
					{
						SeamlessWindow_updateIcon(sw, msg->icon, 0);
						DeleteObject(msg->icon);
					}

					break;
				}

				if (msg->icon == NULL) {
					SeamlessChannel_sendDelIcon(hwnd, size, size);
					break;
				}
				
				SeamlessWindow_updateIcon(sw, msg->icon, msg->large);
			}
			break;
		case HOOK_MSG_TITLE:
			{
				HookMsg_Title *msg = (HookMsg_Title *)(data->lpData);
				HWND hwnd = (HWND) msg->wnd;

				sw = getWindowFromHistory(hwnd);

				if (sw == NULL){
					SeamlessWindow_create(hwnd);
				}
				else{
					SeamlessWindow_updateTitle(sw);
				}
			}
			break;
		case HOOK_MSG_DESTROY:
			{
				HookMsg_Destroy *msg = (HookMsg_Destroy *)(data->lpData);
				HWND hwnd = (HWND) msg->wnd;

				sw = getWindowFromHistory(hwnd);
				if (! sw)
					break;

				SeamlessWindow_destroy(sw);
			}
			break;
		case HOOK_MSG_POSITION:
			{
				HookMsg_Position *msg = (HookMsg_Position *)(data->lpData);
				HWND hwnd = (HWND) msg->wnd;

				sw = getWindowFromHistory(hwnd);
				if (! sw)
					break;

				SeamlessWindow_updatePosition(sw);
			}
			break;
		case HOOK_MSG_SHOW:
			{
				HookMsg_Show *msg = (HookMsg_Show *)(data->lpData);
				HWND hwnd = (HWND) msg->wnd;

				SeamlessWindow_create(hwnd);
			}
			break;
		case HOOK_MSG_DESTROYGRP:
			{
				HookMsg_DestroyGrp *msg = (HookMsg_DestroyGrp *)(data->lpData);

				SeamlessChannel_sendDestroyGrp(msg->pid, 0);
			}
			break;
		case HOOK_MSG_ZCHANGE:
			{
				HookMsg_ZChange *msg = (HookMsg_ZChange *)(data->lpData);
				HWND hwnd = (HWND) msg->wnd;

				sw = getWindowFromHistory(hwnd);
				if (! sw)
					break;

				SeamlessWindow_updateZOrder(sw);
			}
			break;
	}
}

static void
message(const char *text)
{
	MessageBox(GetDesktopWindow(), text, "SeamlessRDP Shell", MB_OK);
}

static BOOL
build_startup_procs(void)
{
	PWTS_PROCESS_INFO pinfo;
	DWORD i, j, count;

	if (!WTSEnumerateProcesses(WTS_CURRENT_SERVER_HANDLE, 0, 1, &pinfo, &count))
		return FALSE;

	g_startup_num_procs = 0;

	for (i = 0; i < count; i++)
	{
		if (pinfo[i].SessionId != g_session_id)
			continue;

		g_startup_num_procs++;
	}

	g_startup_procs = malloc(sizeof(DWORD) * g_startup_num_procs);

	j = 0;
	for (i = 0; i < count; i++)
	{
		if (pinfo[i].SessionId != g_session_id)
			continue;

		g_startup_procs[j] = pinfo[i].ProcessId;
		j++;
	}

	WTSFreeMemory(pinfo);

	return TRUE;
}

static void
free_startup_procs(void)
{
	free(g_startup_procs);

	g_startup_procs = NULL;
	g_startup_num_procs = 0;
}

static BOOL
should_terminate(void)
{
	PWTS_PROCESS_INFO pinfo;
	DWORD i, j, count;

	if (!WTSEnumerateProcesses(WTS_CURRENT_SERVER_HANDLE, 0, 1, &pinfo, &count))
		return TRUE;

	for (i = 0; i < count; i++)
	{
		if (pinfo[i].SessionId != g_session_id)
			continue;

		for (j = 0; j < g_startup_num_procs; j++)
		{
			if (pinfo[i].ProcessId == g_startup_procs[j])
				break;
		}

		if (j == g_startup_num_procs)
		{
			WTSFreeMemory(pinfo);
			return FALSE;
		}
	}

	WTSFreeMemory(pinfo);

	return TRUE;
}

static BOOL
is_connected(void)
{
	BOOL res;
	INT *state;
	DWORD size;

	res = WTSQuerySessionInformation(WTS_CURRENT_SERVER_HANDLE,
					 WTS_CURRENT_SESSION, WTSConnectState, (LPTSTR *) & state,
					 &size);
	if (!res)
		return TRUE;

	res = *state == WTSActive;

	WTSFreeMemory(state);

	return res;
}

static BOOL
is_desktop_hidden(void)
{
	HDESK desk;

	/* We cannot get current desktop. But we can try to open the current
	   desktop, which will most likely be a secure desktop (if it isn't
	   ours), and will thus fail. */
	desk = OpenInputDesktop(0, FALSE, GENERIC_READ);
	if (desk)
		CloseDesktop(desk);

	return desk == NULL;
}

static void load_configuration(BOOL *use_active_monitoring, BOOL *is_move_offscreen_forbidden) {
	HKEY rkey;
	LONG status;
	DWORD buffer_size = 6;
	TCHAR buffer[6];
	DWORD type;

	status = RegOpenKeyEx(HKEY_LOCAL_MACHINE, "Software\\Ulteo\\OVD\\seamlessrdpshell", 0, KEY_READ, &rkey);
	if (status != ERROR_SUCCESS)
		return;

	status = RegQueryValueEx(rkey, "use_active_monitoring", NULL, &type, (BYTE*)buffer, &buffer_size);
	*use_active_monitoring = (status == ERROR_SUCCESS && type == REG_SZ && StrCmpI(buffer, "true") == 0);

	status = RegQueryValueEx(rkey, "move_offscreen_forbidden", NULL, &type, (BYTE*)buffer, &buffer_size);
	*is_move_offscreen_forbidden = (status == ERROR_SUCCESS && type == REG_SZ && StrCmpI(buffer, "true") == 0);

	RegCloseKey (rkey);
}

// Refer to http://msdn.microsoft.com/en-us/library/windows/desktop/ms684139%28v=vs.85%29.aspx
typedef BOOL (WINAPI *LPFN_ISWOW64PROCESS) (HANDLE, PBOOL);
BOOL IsWow64() {
	LPFN_ISWOW64PROCESS fnIsWow64Process;
	BOOL bIsWow64 = FALSE;

	// IsWow64Process is not available on all supported versions of Windows.
	// Use GetModuleHandle to get a handle to the DLL that contains the function
	// and GetProcAddress to get a pointer to the function if available.

	fnIsWow64Process = (LPFN_ISWOW64PROCESS) GetProcAddress(
	GetModuleHandle(TEXT("kernel32")),"IsWow64Process");

	if(fnIsWow64Process == NULL)
		return FALSE;

	if (! fnIsWow64Process(GetCurrentProcess(), &bIsWow64))
		return FALSE;

	return bIsWow64;
}

int WINAPI
WinMain(HINSTANCE instance, HINSTANCE prev_instance, LPSTR cmdline, int cmdshow)
{
	BOOL use_active_monitoring = DEFAULT_USE_ACTIVE_MONITORING;
	HMODULE hookdll;
	MSG msg;
	int check_counter;

	set_hooks_proc_t set_hooks_fn;
	remove_hooks_proc_t remove_hooks_fn;
	get_instance_count_proc_t instance_count_fn;

	g_instance = instance;

	load_configuration(&use_active_monitoring, &g_is_move_offscreen_forbidden);

	if (! use_active_monitoring) {
		hookdll = LoadLibrary("seamlessrdp.dll");
		if (!hookdll)
		{
			message("Could not load hook DLL. Unable to continue.");
			return -1;
		}

		if (IsWow64())
		{
			STARTUPINFO si;
			PROCESS_INFORMATION pi;

			ZeroMemory( &si, sizeof(si) );
			si.cb = sizeof(si);
			ZeroMemory( &pi, sizeof(pi) );

			// Start the child process.
			if (! CreateProcess(NULL, "hook_launcher_x64.exe", NULL, NULL, FALSE, 0, NULL, NULL, &si, &pi)) {
				message("CreateProcess failed.\n");
				return -1;
			}
			CloseHandle(pi.hProcess);
			CloseHandle(pi.hThread);
		}

		set_hooks_fn = (set_hooks_proc_t) GetProcAddress(hookdll, "SetHooks");
		remove_hooks_fn = (remove_hooks_proc_t) GetProcAddress(hookdll, "RemoveHooks");
		instance_count_fn = (get_instance_count_proc_t) GetProcAddress(hookdll, "GetInstanceCount");

		if (!set_hooks_fn || !remove_hooks_fn || !instance_count_fn)
		{
			FreeLibrary(hookdll);
			message("Hook DLL doesn't contain the correct functions. Unable to continue.");
			return -1;
		}

		/* Check if the DLL is already loaded */
		if (instance_count_fn() != 1)
		{
			FreeLibrary(hookdll);
			message("Another running instance of Seamless RDP detected.");
			return -1;
		}
	}

	ProcessIdToSessionId(GetCurrentProcessId(), &g_session_id);

	build_startup_procs();

	if (! SeamlessChannel_init())
		return -1;

	g_connected = is_connected();
	g_desktop_hidden = is_desktop_hidden();

	if (InternalWindow_create(instance, use_active_monitoring ? NULL : InternalWindow_processCopyData) == FALSE)
		SeamlessChannel_sendDebug("Failed to create seamless internal window");

	SeamlessChannel_sendHello(g_desktop_hidden ? SEAMLESS_HELLO_HIDDEN : 0);

	if (use_active_monitoring)
		start_windows_monitoring();
	else
		set_hooks_fn();

	/* Since we don't see the entire desktop we must resize windows
	   immediatly. */
	SystemParametersInfo(SPI_SETDRAGFULLWINDOWS, TRUE, NULL, 0);

	/* Disable screen saver since we cannot catch its windows. */
	SystemParametersInfo(SPI_SETSCREENSAVEACTIVE, FALSE, NULL, 0);

	/* We don't want windows denying requests to activate windows. */
	SystemParametersInfo(SPI_SETFOREGROUNDLOCKTIMEOUT, 0, 0, 0);


	check_counter = 5;
	while (1)
	{
		BOOL connected;
		char* line = NULL;
		int size = 0;

		connected = is_connected();
		if (connected && !g_connected)
		{
			int flags;

			// We have to force reopening channel
			SeamlessChannel_uninit();
			SeamlessChannel_init();

			/* These get reset on each reconnect */
			SystemParametersInfo(SPI_SETDRAGFULLWINDOWS, TRUE, NULL, 0);
			SystemParametersInfo(SPI_SETSCREENSAVEACTIVE, FALSE, NULL,
					     0);
			SystemParametersInfo(SPI_SETFOREGROUNDLOCKTIMEOUT, 0, 0, 0);

			flags = SEAMLESS_HELLO_RECONNECT;
			if (g_desktop_hidden)
				flags |= SEAMLESS_HELLO_HIDDEN;
			SeamlessChannel_sendHello(flags);
		}

		g_connected = connected;

		if (check_counter < 0)
		{
			BOOL hidden;

			hidden = is_desktop_hidden();
			if (hidden && !g_desktop_hidden)
				SeamlessChannel_sendHide(0);
			else if (!hidden && g_desktop_hidden)
				SeamlessChannel_sendUnhide(0);

			g_desktop_hidden = hidden;

			check_counter = 5;
		}

		while (PeekMessage(&msg, NULL, 0, 0, PM_REMOVE))
		{
			TranslateMessage(&msg);
			DispatchMessage(&msg);
		}

		while (SeamlessChannel_recv(&line) >= 0) {
			SeamlessChannel_process(line);
		}
		
		Sleep(100);
	}

	if (use_active_monitoring)
		stop_windows_monitoring();
	else {
		remove_hooks_fn();

		FreeLibrary(hookdll);
	}

	SeamlessChannel_uninit();

	free_startup_procs();

	return 1;
}
