/*
 * Copyright (C) 2010-2013 Ulteo SAS
 * http://www.ulteo.com
 * Author Guillaume DUPAS <guillaume@ulteo.com> 2010
 * Author Omar AKHAM <oakham@ulteo.com> 2011
 * Author David PHAM-VAN <d.pham-van@ulteo.com> 2012
 * Author Thomas MOUTON <thomas@ulteo.com> 2013
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

import java.awt.BorderLayout;
import java.awt.Color;
import java.awt.Component;
import java.awt.ComponentOrientation;
import java.awt.Dimension;
import java.awt.GridBagConstraints;
import java.awt.GridBagLayout;
import java.util.ArrayList;

import javax.swing.BorderFactory;
import javax.swing.JLabel;
import javax.swing.JPanel;
import javax.swing.JScrollPane;

import java.util.Locale;

import org.apache.log4j.Logger;
import org.ulteo.ovd.ApplicationInstance;
import org.ulteo.rdp.OvdAppChannel;
import org.ulteo.rdp.OvdAppListener;

public class RunningApplicationPanel extends JPanel implements OvdAppListener {

	private Logger logger = Logger.getLogger(RunningApplicationPanel.class);
	
	//private static final ImageIcon KILL_ICON = new ImageIcon(Toolkit.getDefaultToolkit().getImage(RunningApplicationPanel.class.getClassLoader().getResource("pics/button_cancel.png")));
	private ArrayList<Component> components = null;
	private JScrollPane listScroller = null;
	private JPanel listPanel = new JPanel();
	private int y = 0;
	private GridBagConstraints gbc = null;
	
	public RunningApplicationPanel() {
		this.listPanel.setBackground(Color.white);
		this.components = new ArrayList<Component>();
		
		this.setLayout(new BorderLayout());
		this.setPreferredSize(new Dimension(300, 194));
		this.setBorder(BorderFactory.createLineBorder(Color.BLACK));
		
		this.listPanel.setLayout(new GridBagLayout());
		this.gbc = new GridBagConstraints();
		
		this.listScroller = new JScrollPane(listPanel);
		this.add(listScroller, BorderLayout.CENTER);
		
		this.applyComponentOrientation(ComponentOrientation.getOrientation(Locale.getDefault()));
		this.revalidate();
	}
	
	private void add(ApplicationInstance new_ai) {
		String appId = new_ai.getApplication().getName()+new_ai.getToken();
		JLabel appIcon = new JLabel();
		JLabel appName = new JLabel(new_ai.getApplication().getName());
		appIcon.setIcon(new_ai.getApplication().getSmallIcon());
		appIcon.setName(appId);
		appName.setName(appId);
		
		/*JButton kill = new JButton();
		kill.setIcon(KILL_ICON);
		kill.setName(appId);*/
		
		components.add(appIcon);
		components.add(appName);
		//components.add(kill);
		
			this.gbc.gridx = 0;
			this.gbc.gridy = this.y;
			this.gbc.anchor = GridBagConstraints.LINE_START;
			this.gbc.insets.right = 5;
			
			this.listPanel.add(appIcon, gbc);
			
			this.gbc.gridx = 1;
			this.gbc.fill = GridBagConstraints.HORIZONTAL;
			this.listPanel.add(appName, gbc);
			
			/*this.gbc.gridx = 2;
			this.gbc.fill = GridBagConstraints.NONE;
			this.gbc.anchor = GridBagConstraints.LINE_END;
			this.listPanel.add(kill, gbc);*/
			this.y++;
			
			this.gbc.anchor = GridBagConstraints.CENTER;
			this.listPanel.revalidate();
	}
	
	private void remove(ApplicationInstance old_ai) {
		for (Component cmp : components) {
			if(cmp.getName().equals(old_ai.getApplication().getName()+old_ai.getToken()))
				this.listPanel.remove(cmp);
		}
		this.listPanel.revalidate();
		this.listScroller.revalidate();
		this.revalidate();
		this.repaint();
	}
	
	public void ovdInited(OvdAppChannel o) {}

	public void ovdInstanceError(ApplicationInstance appInst_) {}

	public void ovdInstanceStarted(OvdAppChannel channel_, ApplicationInstance appInst_) {
		this.add(appInst_);
	}

	public void ovdInstanceStopped(ApplicationInstance appInst_) {
		this.remove(appInst_);
	}
}
