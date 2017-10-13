/*
 * Copyright (C) 2010-2011 Ulteo SAS
 * http://www.ulteo.com
 * Author Guillaume DUPAS <guillaume@ulteo.com> 2010
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

package org.ulteo.ovd.client.authInterface;

import java.awt.ComponentOrientation;
import java.awt.BorderLayout;
import java.awt.Dimension;
import java.awt.Image;
import java.awt.event.ActionEvent;
import java.awt.event.ActionListener;
import java.awt.event.WindowAdapter;
import java.awt.event.WindowEvent;

import javax.swing.JButton;
import javax.swing.JFrame;
import javax.swing.JLabel;
import javax.swing.JProgressBar;

import java.util.Locale;

import org.ulteo.ovd.client.Language.JDialog;
import org.ulteo.utils.I18n;

public class LoadingFrame extends JDialog implements ActionListener {

	private JButton cancel = null;
	private JProgressBar aJProgressBar = null;
	private JLabel jlabel = null;
	
	private LoadingStatus loadingStatus = LoadingStatus.LOADING_START;
	private boolean showProgressBar;

	public LoadingFrame(boolean showProgressBar) {
		this.showProgressBar = showProgressBar;

		Image logo = getToolkit().getImage(getClass().getClassLoader().getResource("pics/ulteo.png"));
		this.setIconImage(logo);
		this.setTitle(I18n._("Now loading"));
		this.setDefaultCloseOperation(JFrame.DO_NOTHING_ON_CLOSE);
		this.setSize(400, 100);
		this.setPreferredSize(new Dimension(400,100));
		this.setResizable(false);
		this.setModal(true);
		
		this.cancel = new JButton(I18n._("Cancel"));
		this.cancel.setPreferredSize(new Dimension(120, 10));
		this.cancel.setSize(new Dimension(120, 10));
		this.cancel.addActionListener(this);

		this.applyComponentOrientation(ComponentOrientation.getOrientation(Locale.getDefault()));
		
		this.addWindowListener(new WindowAdapter() {
			@Override
			public void windowActivated(WindowEvent e) {
				cancel.setEnabled(true);
			}
			@Override
			public void windowClosing(WindowEvent e) {
				cancel.setEnabled(false);
		    }
		});
		
		this.aJProgressBar = new JProgressBar(JProgressBar.HORIZONTAL, 100);
		this.aJProgressBar.setIndeterminate(false);
		this.aJProgressBar.setValue(0);
		this.aJProgressBar.setStringPainted(true);
		this.aJProgressBar.setPreferredSize(new Dimension(280, 20));
		this.aJProgressBar.setLocation(10,45);
		
		this.jlabel = new JLabel(LoadingStatus.getMsg(LoadingStatus.SM_START));
		
		this.add(BorderLayout.NORTH, this.aJProgressBar);
		this.add(BorderLayout.LINE_END, this.cancel);
		this.add(BorderLayout.SOUTH, this.jlabel);
		this.pack();
	}
	
	
	public void addActionListener(final ActionListener listener) {
		final LoadingFrame me = this;
		this.cancel.addActionListener(new ActionListener() {
			@Override
			public void actionPerformed(ActionEvent e) {
				listener.actionPerformed(new ActionEvent(me, e.getID(), "cancel"));
			}
		});
		this.addWindowListener(new WindowAdapter() {
			@Override
			public void windowClosing(WindowEvent e) {
				listener.actionPerformed(new ActionEvent(me, e.getID(), "cancel"));
		    }
		});
	}
	
	/**
	 * say if the user has cancelled the loading
	 * @return
	 */
	public boolean cancelled() {
		return ! this.cancel.isEnabled();
	}
	
	public void updateProgression(LoadingStatus status, int subStatus) {
		if (! showProgressBar)
			return;
		
		this.loadingStatus = status;
		this.aJProgressBar.setValue(LoadingStatus.getIncrement(status, subStatus));
		this.jlabel.setText(LoadingStatus.getMsg(status));
	}
	
	@Override
	public void changeLanguage() {
		this.jlabel.setText(LoadingStatus.getMsg(this.loadingStatus));
		this.cancel.setText(I18n._("Cancel"));
		this.setTitle(I18n._("Now loading"));
	}
	
	@Override
	public void actionPerformed(ActionEvent e) {
		cancel.setEnabled(false);
	}
	
}
