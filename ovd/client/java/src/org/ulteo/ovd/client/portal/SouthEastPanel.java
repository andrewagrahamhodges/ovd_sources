/*
 * Copyright (C) 2010-2012 Ulteo SAS
 * http://www.ulteo.com
 * Author Guillaume DUPAS <guillaume@ulteo.com> 2010
 * Author Samuel BOVEE <samuel@ulteo.com> 2011
 * Author Omar AKHAM <oakham@ulteo.com> 2011
 * Author Thomas MOUTON <thomas@ulteo.com> 2012
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

package org.ulteo.ovd.client.portal;

import java.awt.ComponentOrientation;
import java.awt.GridBagConstraints;
import java.awt.GridBagLayout;
import java.awt.Image;
import java.awt.Toolkit;
import java.awt.event.ActionEvent;
import java.awt.event.ActionListener;
import java.net.URL;
import javax.swing.Icon;
import javax.swing.ImageIcon;

import javax.swing.JButton;
import javax.swing.JPanel;
import org.ulteo.Logger;

import java.util.Locale;

import org.ulteo.utils.I18n;
import org.ulteo.ovd.client.NativeClientActions;
import org.ulteo.ovd.client.OvdClient;
import org.ulteo.ovd.client.OvdClientFrame;
import org.ulteo.ovd.client.remoteApps.OvdClientPortal;


public class SouthEastPanel extends JPanel {
	
	private JButton disconnect = null;
	private JButton logoffButton = null;
	private JButton localDesktopIntegrationButton = null;;
	private Icon rotateIcon = null;

	private OvdClientFrame parentFrame = null;
	private NativeClientActions rdpActions = null;
	
	public SouthEastPanel(OvdClientFrame parentFrame_, final NativeClientActions rdpActions) {
		this.setLayout(new GridBagLayout());

		this.parentFrame = parentFrame_;
		this.rdpActions = rdpActions;
		
		this.initRotate();
		this.localDesktopIntegrationButton = new JButton(this.rotateIcon);
		
		if (this.rdpActions.isPersistentSessionEnabled()) {
			this.disconnect = new JButton(I18n._("Disconnect"));
			
			this.disconnect.addActionListener(new ActionListener() {
				@Override
				public void actionPerformed(ActionEvent e) {
					parentFrame.setDisconnectionMode(OvdClient.DisconnectionMode.SUSPEND);
					rdpActions.disconnect(true);
				}
			});
		}
		
		this.logoffButton = new JButton(I18n._("Logoff"));
		this.logoffButton.addActionListener(new ActionListener() {
			@Override
			public void actionPerformed(ActionEvent e) {
				parentFrame.setDisconnectionMode(OvdClient.DisconnectionMode.LOGOFF);
				rdpActions.disconnect(false);
			}
		});
		
		this.localDesktopIntegrationButton.addActionListener(new ActionListener() {
			@Override
			public void actionPerformed(ActionEvent arg0) {
				new Thread(new Runnable() {
					@Override
					public void run() {
						SouthEastPanel.this.localDesktopIntegrationButton.setEnabled(false);
						SouthEastPanel.this.localDesktopIntegrationButton.setIcon(rotateIcon);
						SouthEastPanel.this.localDesktopIntegrationButton.setText(null);

						boolean isPublished = ((OvdClientPortal)rdpActions).togglePublications();

						SouthEastPanel.this.localDesktopIntegrationButton.setIcon(null);
						SouthEastPanel.this.switchLocalDesktopIntegrationButtonText(! isPublished);
						SouthEastPanel.this.localDesktopIntegrationButton.setEnabled(true);
					}
				}).start();
			}
		});
		this.localDesktopIntegrationButton.setEnabled(false);

		GridBagConstraints gbc = new GridBagConstraints();
		
		gbc.anchor = GridBagConstraints.LINE_END;
		gbc.insets.left = 5;
		gbc.gridx = gbc.gridy = 0;
		this.add(this.localDesktopIntegrationButton, gbc);
		
		gbc.gridx = 1;
		
		if (this.rdpActions.isPersistentSessionEnabled()) {
			this.add(disconnect, gbc);
			
			gbc.gridx = 2;
		}
		
		this.add(this.logoffButton, gbc);
		
		this.applyComponentOrientation(ComponentOrientation.getOrientation(Locale.getDefault()));
	}

	private void initRotate() {
		URL url = SouthEastPanel.class.getClassLoader().getResource("pics/rotate.gif");
		if (url == null) {
			Logger.error("Weird. The icon pics/rotate.gif was not found in the jar");
			return;
		}

		Image rotateImg = Toolkit.getDefaultToolkit().getImage(url);
		if (rotateImg == null) {
			Logger.error("Weird. Failed to create Image object from icon pics/rotate.gif");
			return;
		}

		this.rotateIcon = new ImageIcon(rotateImg);
		if (this.rotateIcon == null) {
			Logger.error("Weird. Failed to create Icon object from icon pics/rotate.gif");
			return;
		}
	}

	public void initLocalDesktopIntegrationButton(boolean enabled) {
		this.localDesktopIntegrationButton.setIcon(null);
		this.switchLocalDesktopIntegrationButtonText(! ((OvdClientPortal) this.rdpActions).isAutoPublish());
		this.localDesktopIntegrationButton.setEnabled(enabled);
	}
	
	private void switchLocalDesktopIntegrationButtonText(boolean on) {
		this.localDesktopIntegrationButton.setText(on ? I18n._("Enable desktop integration") : I18n._("Disable desktop integration"));
	}
}
