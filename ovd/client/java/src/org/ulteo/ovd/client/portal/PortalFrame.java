/*
 * Copyright (C) 2010-2013 Ulteo SAS
 * http://www.ulteo.com
 * Author Guillaume DUPAS <guillaume@ulteo.com> 2010
 * Author Julien LANGLOIS <julien@ulteo.com> 2010
 * Author Thomas MOUTON <thomas@ulteo.com> 2010-2013
 * Author Samuel BOVEE <samuel@ulteo.com> 2011
 * Author Omar AKHAM <oakham@ulteo.com> 2011
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

import java.awt.Color;
import java.awt.Component;
import java.awt.ComponentOrientation;
import java.awt.Font;
import java.awt.GridBagConstraints;
import java.awt.GridBagLayout;
import java.awt.Image;
import java.awt.Insets;
import java.awt.event.WindowEvent;
import java.util.ArrayList;
import java.util.List;

import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.JPanel;

import java.util.Locale;

import org.ulteo.utils.I18n;
import org.ulteo.gui.GUIActions;
import org.ulteo.gui.SwingTools;
import org.ulteo.ovd.client.NativeClientActions;
import org.ulteo.ovd.client.OvdClientFrame;
import org.ulteo.ovd.client.bugreport.gui.BugReportButton;
import org.ulteo.ovd.integrated.OSTools;

public class PortalFrame extends OvdClientFrame {

	private String username = null;
	
	private MyApplicationPanel appsPanel = null;
	private RunningApplicationPanel runningAppsPanel = null;
	private NewsPanel newsPanel = null;
	private SouthEastPanel sep = null;	

	private boolean newPanelAdded = false;
	private boolean showBugReporter = false;
	
	public PortalFrame(NativeClientActions actions, String username, Image logo, boolean showBugReporter_) {
		super(actions);
		if (username == null)
			username = "";
		String displayName = I18n._("Welcome {user}");
		displayName = displayName.replaceAll("\\{user\\}", username);
		this.username = displayName;
		this.showBugReporter = showBugReporter_;

		this.setIconImage(logo);
		
		this.init();
		this.newsPanel = new NewsPanel();

		this.applyComponentOrientation(ComponentOrientation.getOrientation(Locale.getDefault()));
	}
	
	
	public void init() {
		this.setTitle("OVD Remote Applications");
		this.setSize(700, 400);
		this.setResizable(false);
		this.setLocationRelativeTo(null);
		this.setDefaultCloseOperation(JFrame.DO_NOTHING_ON_CLOSE);

		this.setLayout(new GridBagLayout());
		GridBagConstraints gbc = new GridBagConstraints();

		JPanel headerPanel = new JPanel();

		JLabel user = new JLabel(this.username);
		user.setFont(new Font("Dialog", 1, 18));
		user.setForeground(new Color(97, 99, 102));
		headerPanel.add(user);

		if (this.showBugReporter)
			headerPanel.add(new BugReportButton());

		gbc.gridx = 0;
		gbc.gridy = 0;
		gbc.gridwidth = 2;
		gbc.insets.bottom = 25;
		gbc.anchor = GridBagConstraints.EAST;
		this.add(headerPanel, gbc);
		
		JLabel application = new JLabel(I18n._("My applications"));
		application.setBackground(Color.red);
		application.setFont(new Font("Dialog", 1, 12));
		gbc.insets = new Insets(0, 0, 5, 20);
		gbc.gridy = 1;
		gbc.gridwidth = 1;
		gbc.anchor = GridBagConstraints.LINE_START;
		this.add(application, gbc);

		this.runningAppsPanel = new RunningApplicationPanel();
		this.appsPanel = new MyApplicationPanel();
		gbc.gridy = 2;
		gbc.anchor = GridBagConstraints.CENTER;
		gbc.insets.bottom = 20;
		this.add(appsPanel, gbc);

		JLabel runningApps = new JLabel(I18n._("Running applications"));
		runningApps.setFont(new Font("Dialog", 1, 12));
		gbc.gridx = 1;
		gbc.gridy = 1;
		gbc.insets.bottom = 5;
		gbc.anchor = GridBagConstraints.LINE_START;
		this.add(runningApps, gbc);
		
		gbc.gridy = 2;
		gbc.anchor = GridBagConstraints.CENTER;
		gbc.insets.bottom = 20;
		gbc.insets.right = 5;
		this.add(runningAppsPanel, gbc);
		
		this.validate();
	}
	
	public void initButtonPan() {
		this.sep = new SouthEastPanel(this, this.actions);
		
		GridBagConstraints gbc = new GridBagConstraints();
		gbc.gridy = 4;
		gbc.gridx = 0;
		gbc.gridwidth = 2;
		gbc.insets.bottom = 10;
		gbc.anchor = GridBagConstraints.LINE_END;
		this.add(sep, gbc);
		this.validate();
	}

	public void initLocalDesktopIntegrationButton(boolean enabled) {
		if (this.sep == null)
			return;

		this.sep.initLocalDesktopIntegrationButton(enabled);
	}
	
	public MyApplicationPanel getApplicationPanel() {
		return this.appsPanel;
	}
	
	public RunningApplicationPanel getRunningApplicationPanel() {
		return runningAppsPanel;
	}
	
	public NewsPanel getNewsPanel() {
		return this.newsPanel;
	}
	
	public boolean containsNewsPanel() {
		return this.newPanelAdded;
	}
	
	public void addNewsPanel() {
		GridBagConstraints gbc = new GridBagConstraints();
		
		gbc.anchor = GridBagConstraints.LINE_START;
		gbc.gridheight = 1;
		gbc.gridy = 4;
		gbc.gridx = 0;
		gbc.fill = GridBagConstraints.NONE;
		gbc.insets = new Insets(0, 0, 5, 20);
		gbc.insets.bottom = 5;
		gbc.insets.right = 5;
		
		List<Component> list1 = new ArrayList<Component>();
		List<GridBagConstraints> list2 = new ArrayList<GridBagConstraints>();
		
		list1.add(this.newsPanel);
		list2.add(gbc);
		
		SwingTools.invokeLater(GUIActions.addComponents(this, list1, list2));
		
		this.newPanelAdded = true;
	}
	
	public void removeNewsPanel() {
		List<Component> list1 = new ArrayList<Component>();
		list1.add(this.newsPanel);
		
		SwingTools.invokeLater(GUIActions.removeComponents(this, list1));
		SwingTools.invokeLater(GUIActions.validate(this));
		
		this.newPanelAdded = false;
	}
	
	@Override
	public void windowIconified(WindowEvent arg0) {
		if (OSTools.isWindows())
			this.setVisible(false);
		/* Bug on linux, when frame is iconified,
		 * it will never be deiconified by clicking on systray icon */
	}

}
