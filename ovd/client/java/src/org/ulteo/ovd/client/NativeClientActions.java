/*
 * Copyright (C) 2011-2012 Ulteo SAS
 * http://www.ulteo.com
 * Author Samuel BOVEE <samuel@ulteo.com> 2011
 * Author Thomas MOUTON <samuel@ulteo.com> 2012
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

package org.ulteo.ovd.client;

/**
 * Actions in the OVD client session called by the native client
 */
public interface NativeClientActions extends OvdClientPerformer {

	/**
	 * respond if native client have to quit after logout
	 * @return have to quit after logout
	 */
	boolean haveToQuit();
	
	/**
	 * disconnect the current OVD client session
	 */
	void disconnect(boolean f);

	/**
	 * is a user session deconnection
	 * @return if it is a user session deconnection
	 */
	boolean isUserDisconnection();

	/**
	 * is persistent session enabled
	 * @return if the persistent session is enabled
	 */
	boolean isPersistentSessionEnabled();
	
}