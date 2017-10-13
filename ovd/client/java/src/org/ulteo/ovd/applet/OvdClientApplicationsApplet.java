/*
 * Copyright (C) 2010-2012 Ulteo SAS
 * http://www.ulteo.com
 * Author Thomas MOUTON <thomas@ulteo.com> 2010, 2012
 * Author Samuel BOVEE <samuel@ulteo.com> 2011
 * Author David LECHEVALIER <david@ulteo.com> 2012
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

package org.ulteo.ovd.applet;

import java.util.concurrent.ConcurrentHashMap;

import net.propero.rdp.RdpConnection;

import org.ulteo.Logger;
import org.ulteo.ovd.ApplicationInstance;
import org.ulteo.ovd.client.OvdClientRemoteApps;
import org.ulteo.ovd.sm.Properties;
import org.ulteo.ovd.sm.ServerAccess;
import org.ulteo.ovd.sm.SessionManagerCommunication;
import org.ulteo.rdp.OvdAppChannel;
import org.ulteo.rdp.RdpConnectionOvd;

public class OvdClientApplicationsApplet extends OvdClientRemoteApps {

	/**
	 * matching variable associate the RDP connection list index with the JS RDP connection id
	 */
	private ConcurrentHashMap<Integer, RdpConnectionOvd> matching = null;

	private WebClient applet = null;
	
	/**
	 * enable desktop effect in RDP connection
	 */
	private boolean enableDesktopEffect;

	public OvdClientApplicationsApplet(SessionManagerCommunication smComm, Properties properties, WebClient applet_) {
		super(smComm, properties.isPersistent());

		this.applet = applet_;
		this.matching = new ConcurrentHashMap<Integer, RdpConnectionOvd>();
		this.showDesktopIcons = true;

		this.enableDesktopEffect = properties.isDesktopEffectsEnabled();
		this.setPerformDesktopIntegration(properties.isDesktopIcons());
		this.configureRDP(properties);
	}

	@Override
	protected void customizeConnection(RdpConnectionOvd co) {
		super.customizeConnection(co);
		co.setAllDesktopEffectsEnabled(this.enableDesktopEffect);
	}

	@Override
	protected void hide(RdpConnectionOvd co) {}

	/**
	 * create a {@link RdpConnectionOvd} and add it to the connections list
	 * @param JSId
	 * 		ID used for referencing the server beside to the WebClient
	 * @param server
	 * 		information object needed to create the {@link RdpConnectionOvd}
	 * @return
	 * 		<code>true</code> if the function succeed, <code>false</code> instead
	 */
	public boolean addServer(int JSId, ServerAccess server) {
		RdpConnectionOvd co = createRDPConnection(server);
		if (co == null)
			return false;

		this.processIconCache(co);
		this.customizeConnection(co);
		this.matching.put(JSId, co);
		
		co.addRdpListener(this);
		co.connect();
		return true;
	}
	
	public void startApplication(int token, int app_id, int server_id) {
		this.startApplication(token, app_id, server_id, -1, null, null);
	}

	public void startApplication(int token, int app_id, int server_id, int shareType, String path, String sharename) {
		RdpConnectionOvd co = this.matching.get(server_id);
		if (co == null) {
			Logger.error("Cannot launch application "+app_id+"("+token+"): Bad server id");
			return;
		}

		OvdAppChannel chan = co.getOvdAppChannel();
		if (chan == null) {
			Logger.error("Cannot launch application "+app_id+"("+token+"): Weird. The server "+server_id+" has any OVD Applications channel");
			return;
		}

		if (! chan.isReady()) {
			Logger.warn("Cannot launch application "+app_id+"("+token+"): The OVD Applications channel (server "+server_id+") is not ready");
			return;
		}

		if (shareType < 0 || sharename == null || path == null) {
			chan.sendStartApp(token, app_id);
		}
		else {
			chan.sendStartApp(token, app_id, shareType, sharename, path);
		}

	}

	@Override
	public void ovdInited(OvdAppChannel channel) {
		super.ovdInited(channel);
		
		for (Integer JSId : this.matching.keySet()) {
			RdpConnectionOvd rc = this.matching.get(JSId);
			if (rc == null)
				continue;

			if (rc.getOvdAppChannel() == channel) {
				this.applet.forwardServerStatusToJS(JSId, WebClient.JS_API_O_SERVER_READY);
				return;
			}
		}
		Logger.error("Received an OVD inited message but no connection is corresponding");
	}

	@Override
	public void ovdInstanceError(ApplicationInstance appInstance) {
		this.applet.forwardApplicationStatusToJS(0, new Integer(appInstance.getToken()), WebClient.JS_API_O_INSTANCE_ERROR);
	}

	@Override
	public void ovdInstanceStarted(OvdAppChannel channel_, ApplicationInstance appInstance) {
		this.applet.forwardApplicationStatusToJS(appInstance.getApplication().getId(), new Integer(appInstance.getToken()), WebClient.JS_API_O_INSTANCE_STARTED);
	}

	@Override
	public void ovdInstanceStopped(ApplicationInstance appInstance) {
		this.applet.forwardApplicationStatusToJS(0, new Integer(appInstance.getToken()), WebClient.JS_API_O_INSTANCE_STOPPED);
	}

	@Override
	public void connected(RdpConnection co) {
		super.connected(co);

		for (Integer JSId: this.matching.keySet()) {
			RdpConnectionOvd rc = this.matching.get(JSId);
			if (rc == co) {
				this.applet.forwardServerStatusToJS(JSId, WebClient.JS_API_O_SERVER_CONNECTED);
				return;
			}
		}
	}

	@Override
	public void disconnected(RdpConnection co) {
		for (Integer JSId: this.matching.keySet()) {
			RdpConnectionOvd rc = this.matching.get(JSId);
			if (rc == co) {
				this.matching.remove(JSId);
				this.applet.forwardServerStatusToJS(JSId, WebClient.JS_API_O_SERVER_DISCONNECTED);
				return;
			}
		}
		super.disconnected(co);
	}

	@Override
	public void failed(RdpConnection co, String msg) {
		RdpConnection.State state = co.getState();
		if (state == RdpConnection.State.CONNECTED) {
			return;
		}
		if (state != RdpConnection.State.FAILED) {
			Logger.debug("checkRDPConnections "+co.getServer()+" -- Bad connection state("+state+"). Will continue normal process.");
			return;
		}

		int tryNumber = co.getTryNumber();
		if (tryNumber < 1) {
			Logger.debug("checkRDPConnections "+co.getServer()+" -- Bad try number("+tryNumber+"). Will continue normal process.");
			return;
		}

		if (tryNumber > 1) {
			Logger.error("checkRDPConnections "+co.getServer()+" -- Several try to connect failed.");
			for (Integer JSId : this.matching.keySet()) {
				RdpConnectionOvd rc = this.matching.get(JSId);
				if (rc == co) {
					this.matching.remove(JSId);
					this.applet.forwardServerStatusToJS(JSId, WebClient.JS_API_O_SERVER_FAILED);
					return;
				}
			}
			Logger.error("checkRDPConnections "+co.getServer()+" -- Failed to retrieve connection.");
			return;
		}

		Logger.warn("checkRDPConnections "+co.getServer()+" -- Connection failed. Will try to reconnect.");
		co.connect();

		super.failed(co, msg);
	}
}
