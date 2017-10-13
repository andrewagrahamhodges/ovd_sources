/*
 * Copyright (C) 2010 Ulteo SAS
 * http://www.ulteo.com
 * Author Guillaume DUPAS <guillaume@ulteo.com> 2010
 * Author Samuel BOVEE <samuel@ulteo.com> 2011
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
import java.awt.Dimension;
import java.awt.Image;
import java.awt.Window;

import javax.swing.JDialog;
import javax.swing.JProgressBar;

import java.util.Locale;

import org.ulteo.utils.I18n;

public class DisconnectionDialog extends JDialog {
	
	public DisconnectionDialog(Window w) {
		super(w, ModalityType.APPLICATION_MODAL);
		
		Image logo = getToolkit().getImage(getClass().getClassLoader().getResource("pics/ulteo.png"));
		this.setIconImage(logo);
		this.setTitle(I18n._("Disconnecting!"));
		this.applyComponentOrientation(ComponentOrientation.getOrientation(Locale.getDefault()));
		this.setDefaultCloseOperation(JDialog.DO_NOTHING_ON_CLOSE);
		this.setSize(300, 50);
		this.setPreferredSize(new Dimension(300,50));
		this.setResizable(false);

		JProgressBar aJProgressBar = new JProgressBar(JProgressBar.HORIZONTAL);
		aJProgressBar.setIndeterminate(true);
		aJProgressBar.setPreferredSize(new Dimension(280, 20));
		
		this.add(aJProgressBar);
		this.setLocationRelativeTo(null);
		this.pack();
	}
	
}
