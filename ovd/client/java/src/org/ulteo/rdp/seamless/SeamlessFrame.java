/*
 * Copyright (C) 2009-2014 Ulteo SAS
 * http://www.ulteo.com
 * Author Julien LANGLOIS <julien@ulteo.com> 2009
 * Author David LECHEVALIER <david@ulteo.com> 2014
 * Author Thomas MOUTON <thomas@ulteo.com> 2009-2010
 * Alexandre CONFIANT-LATOUR <a.confiant@ulteo.com> 2013
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

package org.ulteo.rdp.seamless;

import java.awt.Dimension;
import java.awt.Frame;
import java.awt.Rectangle;
import java.awt.event.FocusEvent;
import java.awt.event.FocusListener;
import java.awt.event.InputMethodEvent;
import java.awt.event.InputMethodListener;
import java.awt.event.KeyEvent;
import java.awt.event.KeyListener;
import java.awt.event.MouseEvent;
import java.awt.font.TextHitInfo;
import java.awt.im.InputMethodRequests;
import java.text.AttributedCharacterIterator;

import org.ulteo.ovd.integrated.OSTools;
import org.ulteo.utils.AbstractFocusManager;

import net.propero.rdp.IMEManager;
import net.propero.rdp.ImeStateListener;
import net.propero.rdp.ImeStateSetter;
import net.propero.rdp.Common;
import net.propero.rdp.Input;
import net.propero.rdp.rdp5.seamless.SeamFrame;
import org.ulteo.gui.GUIActions;


public class SeamlessFrame extends SeamFrame implements SeamlessMovingResizing, FocusListener, ImeStateListener, InputMethodListener, InputMethodRequests, KeyListener {
	public static AbstractFocusManager focusManager = null;
	
	protected boolean lockMouseEvents = false;
	protected RectWindow rw = null;
	
	private Input input = null;

	public SeamlessFrame(int id_, int group_, Rectangle maxBounds_, int flags, Common common_) {
		super(id_, group_, maxBounds_, common_);
		
		this.parseFlags(flags);
		
		Dimension dim = new Dimension(this.backstore.getWidth(), this.backstore.getHeight());
		this.rw = new RectWindow(this, dim, this.maxBounds);
		this.addFocusListener(this);
		input = this.common.canvas.getInput();

		GUIActions.setIconImage(this, GUIActions.DEFAULT_APP_ICON).run();
        this.addKeyListener(this);
        this.addInputMethodListener(this);
	}

	
    public InputMethodRequests getInputMethodRequests() {
        if(! this.common.canvas.isUseLocalIME())
            return null;
        
        return this;
    }

    /* InputMethodListener interface */
    public void caretPositionChanged(InputMethodEvent e) {
    }

    public void inputMethodTextChanged(InputMethodEvent e) {
    	IMEManager.getInstance().inputMethodTextChanged(e, this.common);
    }

    /* InputMethodRequests interface */
    public AttributedCharacterIterator cancelLatestCommittedText(AttributedCharacterIterator.Attribute[] attributes) {
    	return null;
    }
	
    public AttributedCharacterIterator getCommittedText(int beginIndex, int endIndex, AttributedCharacterIterator.Attribute[] attributes) {
    	return null;
    }
	
    public AttributedCharacterIterator getSelectedText(AttributedCharacterIterator.Attribute[] attributes) {
    	return null;
    }
	
    public int getCommittedTextLength() {
    	return 0;
    }
	
    public int getInsertPositionOffset() {
    	return 0;
    }
	
    public TextHitInfo getLocationOffset(int x, int y) {
    	return null;
    }
	
    public Rectangle getTextLocation(TextHitInfo offset) {
    	return new java.awt.Rectangle(100, 200, 0, 10);
    }

    /* KeyListener interface */
    public void keyPressed(KeyEvent e) { }
    public void keyReleased(KeyEvent e) { }
    public void keyTyped(KeyEvent e) { }

	
	private void parseFlags(int flags) {
		if ((flags & SeamlessChannel.WINDOW_CREATE_FIXEDSIZE) != 0)
			this.setResizable(false);
	}

	public boolean isMouseEventsLocked() {
		return this.lockMouseEvents;
	}

	public void lockMouseEvents() {
		this.lockMouseEvents = true;
		this.rw.setVisible(true);
	}

	public void unlockMouseEvents() {
		this.lockMouseEvents = false;
		this.rw.setVisible(false);
	}

	public RectWindow getRectWindow() {
		return this.rw;
	}

	public boolean _isResizable() {
		return this.isResizable();
	}

	@Override
	public void sw_setMyPosition(int x, int y, int width, int height) {
		super.sw_setMyPosition(x, y, width, height);

		if (this.isMouseEventsLocked())
			this.unlockMouseEvents();
	}

	@Override
	public void sw_setExtendedState(int state) {
		super.sw_setExtendedState(state);

		if (state == Frame.ICONIFIED && this.rw.isVisible())
			this.rw.setVisible(false);
	}

	@Override
	public void sw_destroy() {
		super.sw_destroy();

		if (this.rw.isVisible())
			this.rw.setVisible(false);
	}

	public void setImeState(Input input, boolean state) {
		if (input != this.input) {
			return;
		}

		ImeStateSetter imeStS = new ImeStateSetter(this, this, state);
	}

	public void processMouseEvent(MouseEvent e, int type) {
		switch (type) {
			case MOUSE_PRESSED:
				this.mouseAdapter.mousePressed(e);
				break;
			case MOUSE_RELEASED:
				this.mouseAdapter.mouseReleased(e);
				break;
			case MOUSE_MOVED:
				this.mouseMotionAdapter.mouseMoved(e);
				break;
			case MOUSE_DRAGGED:
				this.mouseMotionAdapter.mouseDragged(e);
				break;
			default:
				break;
		}
	}
	
	@Override
	public void focusGained(FocusEvent e) {
		this.input.updateKeyboardFocus(this);
		
		if (SeamlessFrame.focusManager != null)
		{
			SeamlessFrame.focusManager.performedFocusLost(this);
		}
		
		if (this.input.getImeActive() != this.getInputContext().isCompositionEnabled()) {
			this.input.setImeActive(this.input.getImeActive());
		}
	}

	@Override
	public void focusLost(FocusEvent e) {
		input.lostFocus();
		if (SeamlessFrame.focusManager != null)
		{
			SeamlessFrame.focusManager.performedFocusLost(this);
		}
	}
}
