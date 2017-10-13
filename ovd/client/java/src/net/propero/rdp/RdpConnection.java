/*
 * Copyright (C) 2009-2012 Ulteo SAS
 * http://www.ulteo.com
 * Author Thomas MOUTON <thomas@ulteo.com> 2009-2012
 * Author Guillaume DUPAS <guillaume@ulteo.com> 2010
 * Author David LECHEVALIER <david@ulteo.com> 2011, 2012
 * Author Arnaud LEGRAND <arnaud@ulteo.com> 2010
 * Author Samuel BOVEE <samuel@ulteo.com> 2010
 * Author Julien LANGLOIS <julien@ulteo.com> 2011
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

package net.propero.rdp;

import java.awt.Dimension;
import java.io.File;
import java.io.IOException;
import java.io.InputStream;
import java.net.InetAddress;
import java.net.SocketException;
import java.net.UnknownHostException;
import java.security.InvalidParameterException;
import java.util.StringTokenizer;
import java.util.concurrent.CopyOnWriteArrayList;
import javax.swing.JFrame;
import javax.swing.SwingUtilities;

import net.propero.rdp.compress.MPPCType;
import net.propero.rdp.keymapping.KeyCode_FileBased;
import net.propero.rdp.keymapping.KeyMapException;
import net.propero.rdp.rdp5.seamless.SeamListener;
import net.propero.rdp.rdp5.seamless.SeamlessChannel;
import net.propero.rdp.rdp5.ukbrdr.UkbrdrChannel;
import net.propero.rdp.rdp5.Rdp5;
import net.propero.rdp.rdp5.VChannel;
import net.propero.rdp.rdp5.VChannels;
import net.propero.rdp.rdp5.cliprdr.ClipChannel;
import net.propero.rdp.rdp5.rdpdr.RdpdrChannel;
import net.propero.rdp.rdp5.rdpsnd.SoundChannel;
import org.apache.log4j.Logger;
import org.ulteo.rdp.rdpdr.OVDRdpdrChannel;

public class RdpConnection implements SeamListener, Runnable{
	public static final int RDP_PORT = 3389;
	public static final int DEFAULT_BPP = 24;
	public static final int DEFAULT_WIDTH = 800;
	public static final int DEFAULT_HEIGHT = 600;
	public static final int DEFAULT_PERSISTENT_CACHE_SIZE = 100;
	public static final String DEFAULT_KEYMAP = "us";

	public static enum State {DISCONNECTED, CONNECTING, CONNECTED, FAILED};

	public final static String KEYMAP_PATH = "/resources/keymaps/";

	private VChannels channels = null;
	protected RdpdrChannel rdpdrChannel = null;
	protected SoundChannel soundChannel = null;
	protected ClipChannel clipChannel = null;
	protected SeamlessChannel seamChannel = null;
	protected UkbrdrChannel ukbrdrChannel = null;
	
	protected Rdp5 RdpLayer = null;
	protected Options opt = null;
	protected Common common = null;
	private RdesktopCanvas_Localised canvas = null;
	private String mapFile = DEFAULT_KEYMAP;
	protected String inputMethod = null;
	private CopyOnWriteArrayList<RdpListener> listener = new CopyOnWriteArrayList<RdpListener>();
	private State state = State.DISCONNECTED;
	private int tryNumber = 0;
	private String failedMsg = null;
	private Thread connectionThread = null;
	private Logger logger = Logger.getLogger(RdpConnection.class);

	private boolean keep_running = false;

	private JFrame backstoreFrame = null;
	private boolean useSeamless;
	
	public RdpConnection(Options opt_, Common common_) {
		this.common = common_;
		this.opt = opt_;

		this.opt.width = DEFAULT_WIDTH;
		this.opt.height = DEFAULT_HEIGHT;
		this.opt.set_bpp(DEFAULT_BPP);

		this.opt.use_rdp5 = true;
		this.opt.rdp5_performanceflags = Rdp5.PERF_DISABLE_ALL;
		
		try {
			InetAddress localhost = InetAddress.getLocalHost();
			String name = localhost.getHostName();
			StringTokenizer tok = new StringTokenizer(name, ".");
			this.opt.clientName = tok.nextToken();
			this.opt.clientName.trim();
		} catch (UnknownHostException e) {
			this.opt.clientName = "127.0.0.1";
		}
		
		this.channels = new VChannels(this.opt);
	}

	public String toString() {
		return this.opt.hostname+":"+this.opt.port;
	}
	
	public State getState() {
		return this.state;
	}

	public int getTryNumber() {
		return this.tryNumber;
	}

	public String getServer() {
		return this.opt.hostname;
	}
	
	public String getUsername() {
		return this.opt.username;
	}

	public Dimension getGraphics() {
		return new Dimension(this.opt.width, this.opt.height);
	}

	public int getBpp() {
		return this.opt.server_bpp;
	}

	/**
	 * Set the host to connect on default port
	 * @param address
	 *	The RDP server address
	 */
	public void setServer(String address) {
		this.setServer(address, RDP_PORT);
	}

	public void setFrame(JFrame frame) {
		this.common.desktopFrame = frame;   
	}
	
	/**
	 * Set the host and the port to connect
	 * @param host
	 *	The RDP server address
	 * @param port
	 *	The port to use
	 */
	public void setServer(String host, int port) {
		this.opt.hostname = host;
		this.opt.port = port;
	}

	/**
	 * Set credentials
	 * @param username
	 * @param password
	 */
	public void setCredentials(String username, String password) {
		this.opt.username = username;
		this.opt.password = password;
	}

	/**
	 * Set informations about display
	 * The default bpp is 24 bits
	 * @param width
	 * @param height
	 */
	public void setGraphic(int width, int height) {
		this.setGraphic(width, height, DEFAULT_BPP);
	}

	/**
	 * Set informations about display
	 * @param width
	 * @param height
	 * @param bpp
	 */
	public void setGraphic(int width, int height, int bpp) {
		this.opt.width = width;
		this.opt.height = height;
		this.opt.set_bpp(bpp);
	}

	public void setGraphicOffset(int x, int y) {
		this.opt.x_offset = x;
		this.opt.y_offset = y;
	}

	public void setWallpaperEnabled(boolean enabled) {
		if (enabled)
			this.opt.rdp5_performanceflags &= ~Rdp5.PERF_DISABLE_WALLPAPER;
		else
			this.opt.rdp5_performanceflags |= Rdp5.PERF_DISABLE_WALLPAPER;
	}

	public void setFullWindowDragEnabled(boolean enabled) {
		if (enabled)
			this.opt.rdp5_performanceflags &= ~Rdp5.PERF_DISABLE_FULLWINDOWDRAG;
		else
			this.opt.rdp5_performanceflags |= Rdp5.PERF_DISABLE_FULLWINDOWDRAG;
	}

	public void setMenuAnimationsEnabled(boolean enabled) {
		if (enabled)
			this.opt.rdp5_performanceflags &= ~Rdp5.PERF_DISABLE_MENUANIMATIONS;
		else
			this.opt.rdp5_performanceflags |= Rdp5.PERF_DISABLE_MENUANIMATIONS;
	}

	public void setThemingEnabled(boolean enabled) {
		if (enabled)
			this.opt.rdp5_performanceflags &= ~Rdp5.PERF_DISABLE_THEMING;
		else
			this.opt.rdp5_performanceflags |= Rdp5.PERF_DISABLE_THEMING;
	}

	public void setCursorShadowEnabled(boolean enabled) {
		if (enabled)
			this.opt.rdp5_performanceflags &= ~Rdp5.PERF_DISABLE_CURSOR_SHADOW;
		else
			this.opt.rdp5_performanceflags |= Rdp5.PERF_DISABLE_CURSOR_SHADOW;
	}

	public void setCursorSettingsEnabled(boolean enabled) {
		if (enabled)
			this.opt.rdp5_performanceflags &= ~Rdp5.PERF_DISABLE_CURSORSETTINGS;
		else
			this.opt.rdp5_performanceflags |= Rdp5.PERF_DISABLE_CURSORSETTINGS;
	}

	public void setFontSmoothingEnabled(boolean enabled) {
		if (enabled)
			this.opt.rdp5_performanceflags &= ~Rdp5.PERF_ENABLE_FONT_SMOOTHING;
		else
			this.opt.rdp5_performanceflags |= Rdp5.PERF_ENABLE_FONT_SMOOTHING;
	}

	public void setDesktopCompositionEnabled(boolean enabled) {
		if (enabled)
			this.opt.rdp5_performanceflags &= ~Rdp5.PERF_ENABLE_DESKTOP_COMPOSITION;
		else
			this.opt.rdp5_performanceflags |= Rdp5.PERF_ENABLE_DESKTOP_COMPOSITION;
	}

	public void setAllDesktopEffectsEnabled(boolean enabled) {
		if (enabled)
			this.opt.rdp5_performanceflags = Rdp5.PERF_ENABLE_ALL;
		else
			this.opt.rdp5_performanceflags = Rdp5.PERF_DISABLE_ALL;
	}

	private void initCanvas() throws RdesktopException {
		if (this.opt.width <= 0 || this.opt.height <= 0)
			throw new RdesktopException("Unable to init canvas: The desktop size is negative or nil");
		this.canvas = new RdesktopCanvas_Localised(this.opt, this.common);
		this.canvas.addFocusListener(new RdesktopFocusListener(this.canvas, this.opt));
		this.canvas.addFocusListener(clipChannel);

		this.logger.info("Desktop size: "+this.opt.width+"x"+this.opt.height);
	}
	
	protected void addChannel(VChannel channel) throws RdesktopException {
		this.channels.register(channel);
	}

	protected void initSoundChannel() throws RdesktopException {
		if (this.soundChannel != null)
			return;

		this.soundChannel = new SoundChannel(this.opt, this.common);
		this.addChannel(this.soundChannel);
	}

	protected void initRdpdrChannel() throws RdesktopException {
		if (this.rdpdrChannel != null)
			return;

		this.rdpdrChannel = new RdpdrChannel(this.opt, this.common);
		this.addChannel(this.rdpdrChannel);
	}

	/**
	 * Add clip channel
	 */
	protected void initClipChannel() throws RdesktopException {
		if (this.clipChannel != null)
			return;

		if (! ClipChannel.isAccessClipboardPermissionSet()) {
			System.out.println("Clipboard access is disabled.");
			this.clipChannel = null;
			return;
		}

		this.clipChannel = new ClipChannel(this.common, this.opt);
		if (! this.clipChannel.isSupported()) {
			this.clipChannel = null;
			return;
		}

		this.addChannel(this.clipChannel);
		if (this.seamChannel != null)
			this.seamChannel.setClip(clipChannel);
	}

	
	protected void initIMEChannel(boolean useSeamless) throws RdesktopException {
		this.ukbrdrChannel = new UkbrdrChannel(this.opt, this.common, useSeamless);
		this.addChannel(this.ukbrdrChannel);
		IMEManager.getInstance().addChannel(this.common, this.ukbrdrChannel);
	}
	
	
	protected void initSeamlessChannel() throws RdesktopException {
		this.opt.seamlessEnabled = true;
		if (this.seamChannel != null)
			return;

		this.seamChannel = new SeamlessChannel(this.opt, this.common);
		this.addChannel(this.seamChannel);
		this.seamChannel.addSeamListener(this);
	}

	public void setShell(String shell) {
		this.opt.command = shell;
	}

	public void setSeamForm(boolean enabled) {
		this.opt.seamformEnabled = enabled;
	}

	/**
	 * Enable/disable MPPC-BULK compression
	 * @param packetCompression
	 */
	public void setPacketCompression(boolean packetCompression) {
		this.opt.packet_compression = packetCompression;
	}

	/**
	 * Set MPPC-BULK compression type
	 * @param packetCompression
	 */
	public void setPacketCompressionType(MPPCType packetCompressionType) {
		this.opt.packet_compression_type = packetCompressionType;
	}

	/**
	 * Enable/disable volatile bitmap caching
	 * @param volatileCaching
	 */
	public void setVolatileCaching(boolean volatileCaching) {
		if ((! volatileCaching) && this.opt.persistent_bitmap_caching)
			this.setPersistentCaching(false);
		this.opt.bitmap_caching = volatileCaching;
	}

	/**
	 * Enable/disable persistent bitmap caching
	 * @param persistentCaching
	 */
	public void setPersistentCaching(boolean persistentCaching) {
		this.opt.persistent_bitmap_caching = persistentCaching;

		if (! persistentCaching)
			return;

		if (! this.opt.bitmap_caching)
			this.setVolatileCaching(true);

		this.setPersistentCachingMaxSize(DEFAULT_PERSISTENT_CACHE_SIZE);
	}

	/**
	 * Not implemented yet
	 * Specify the path where the persistent bitmap cache is
	 * @param persistentCachingPath
	 */
	public void setPersistentCachingPath(String persistentCachingPath) {
		if (persistentCachingPath == null || persistentCachingPath.equals(""))
			return;
		
		String separator = System.getProperty("file.separator");

		if (persistentCachingPath.lastIndexOf(separator) != persistentCachingPath.length()-1)
			persistentCachingPath = persistentCachingPath.concat(separator);

		this.opt.persistent_caching_path = persistentCachingPath;
	}

	/**
	 * Not implemented yet
	 * Specify the maximum size of persistent bitmap cache in MegaByte
	 * @param persistentCachingMaxSize (MB)
	 */
	public void setPersistentCachingMaxSize(int persistentCachingMaxSize) {
		if (persistentCachingMaxSize == 0)
			return;
		
		if(! System.getProperty("os.name").startsWith("Mac OS X")) {
			int maxSize = (int) ((new File(System.getProperty("user.home")).getFreeSpace()) / 1024 /1024); // convert bytes to megabytes
			if (maxSize > (persistentCachingMaxSize * 1.25))
				maxSize = persistentCachingMaxSize;
			else
				persistentCachingMaxSize = (int) (maxSize * 0.8);
		}
		this.opt.persistent_caching_max_cells = (persistentCachingMaxSize * 1024 * 1024) / PstCache.MAX_CELL_SIZE;
	}

	/**
	 * set keyboard layout
	 * @param keymap specific keyboard layout
	 */
	public void setKeymap(String keymap) {
		if (keymap == null)
			throw new InvalidParameterException("'setKeymap' does not accept 'null' keymap parameter");
		
		if (this.opt.supportIME)
			return;
		
		this.mapFile = keymap;
	}
	
	public void setInputMethod(String inputMethod) {
		if (inputMethod.equalsIgnoreCase("scancode"))
			this.opt.supportUnicodeInput = false;
		else if (inputMethod.equalsIgnoreCase("unicode"))
			this.opt.supportUnicodeInput = true;
		else if (inputMethod.equalsIgnoreCase("unicode_local_ime")) {
			this.opt.supportUnicodeInput = true;
			this.opt.supportIME = true;
			this.mapFile = "us";
		}
	}

	/**
	 *  Load a keyboard layout
	 * @throws KeyMapException 
	 */
	private void loadKeymap() throws KeyMapException {
		InputStream istr = RdpConnection.class.getResourceAsStream(KEYMAP_PATH + this.mapFile);
		if (istr == null) {
			int idx = this.mapFile.indexOf('-');
			this.mapFile = (idx == -1) ? DEFAULT_KEYMAP : this.mapFile.substring(idx+1, this.mapFile.length());
			istr = RdpConnection.class.getResourceAsStream(KEYMAP_PATH + this.mapFile);
			if (istr == null) {
				this.mapFile = DEFAULT_KEYMAP;
				istr = RdpConnection.class.getResourceAsStream(KEYMAP_PATH + this.mapFile);
			}
		}

		if (istr == null)
			throw new KeyMapException("Unable to find a keymap file");
		
		KeyCode_FileBased keyMap = new KeyCode_FileBased_Localised(istr, opt);
		System.out.println("Autoselected keyboard map " + this.mapFile);

		try {
			istr.close();
		} catch (IOException ex) {
			System.err.println("Error: Unable to close keymap " + this.mapFile +" : "+ ex);
		}
		this.opt.keylayout = keyMap.getMapCode();
		this.canvas.registerKeyboard(keyMap);
	}
	
	public void run() {
		this.tryNumber++;
		
		try {
			this.initCanvas();
		} catch (RdesktopException ex) {
			this.logger.fatal(ex.getMessage());
			this.failedMsg = ex.getMessage();
			this.fireFailed();
			return;
		}

		try {
			this.loadKeymap();
		} catch (KeyMapException ex) {
			this.logger.fatal(ex.getMessage());
			this.failedMsg = ex.getMessage();
			this.fireFailed();
			return;
		}
		
		if (this.opt.seamlessEnabled) {
			this.backstoreFrame = new JFrame();
			this.backstoreFrame.setVisible(false);
			this.backstoreFrame.add(this.canvas);
			this.backstoreFrame.pack();
		}

		this.RdpLayer = new Rdp5(channels, this.opt, this.common);
		this.common.rdp = this.RdpLayer;
		this.RdpLayer.registerDrawingSurface(this.canvas);
		
		this.opt.loggedon = false;
		this.opt.readytosend = false;
		this.opt.grab_keyboard = false;
		if (this.opt.hostname.equalsIgnoreCase("localhost"))
			this.opt.hostname = "127.0.0.1";
		
		this.keep_running = true;
		int exit = 0;

		this.fireConnecting();
		
		while (this.keep_running) {
			// Attempt to connect to server on port Options.port
			try {
				this.RdpLayer.connect(this.opt.username, this.opt.hostname,
						Rdp.RDP_LOGON_NORMAL | Rdp.RDP_LOGON_AUTO, this.opt.domain,
						this.opt.password, this.opt.command, this.opt.directory);

				if (! this.keep_running)
					break;

				boolean[] deactivated = new boolean[1];
				int[] ext_disc_reason = new int[1];
				this.RdpLayer.mainLoop(deactivated, ext_disc_reason, this);
				if (! deactivated[0]) {
					this.disconnect();
					String reason = Rdesktop.textDisconnectReason(ext_disc_reason[0]);
					System.out.println("Connection terminated: " + reason);
				}
				
				this.keep_running = false; // exited main loop
				
				if (!this.opt.readytosend)
					System.out.println("The terminal server disconnected before licence negotiation completed.\nPossible cause: terminal server could not issue a licence.");
			} catch (ConnectionException e) {
				this.failedMsg = e.getMessage();
				this.keep_running = false;
				exit = 1;
			} catch (UnknownHostException e) {
				this.failedMsg = e.getMessage();
				this.keep_running = false;
				exit = 1;
			} catch (SocketException s) {
				if(this.RdpLayer.isConnected()){
					this.failedMsg = s.getMessage();
					exit = 1;
				}
				this.keep_running = false;
			} catch (IndexOutOfBoundsException i) {
				if (this.RdpLayer.isConnected()) {
					this.failedMsg = i.getMessage();
					exit = 1;
				}
				this.keep_running = false;
			} catch (RdesktopException e) {
				this.failedMsg = e.getMessage();
				
				if (!this.opt.readytosend) {
					// maybe the licence server was having a comms
					// problem, retry?
					System.out.println("The terminal server reset connection before licence negotiation completed.\nPossible cause: terminal server could not connect to licence server.");
					
					if (this.RdpLayer != null && this.RdpLayer.isConnected())
						this.RdpLayer.disconnect();
					this.fireDisconnected();
					System.out.println("Retrying connection...");
					this.keep_running = true; // retry
					continue;
				} else {
					this.keep_running = false;
					exit = 1;
				}
			} catch (Exception e) {
				System.err.println("["+this.getServer()+"] An error occurred: "+e.getClass().getName()+" "+e.getMessage());
				e.printStackTrace();

				this.keep_running = false;

				if (this.state == State.CONNECTING)
					this.fireFailed();
				else
					this.fireDisconnected();
			}
		}

		if (this.backstoreFrame != null) {
			SwingUtilities.invokeLater(new Runnable() {

				public void run() {
					if (backstoreFrame == null)
						return;
					backstoreFrame.setVisible(false);
					backstoreFrame.removeAll();
					backstoreFrame.dispose();
					backstoreFrame = null;
				}
			});
		}

		this.disconnect();
		System.gc();
		if (exit == 0)
			this.fireDisconnected();
		else
			this.fireFailed();
	}

	/**
	 * Launch a RdpConnection thread
	 */
	public void connect() {
		this.connectionThread = new Thread(this, "RDP - " + this.opt.hostname);
		this.connectionThread.start();
	}

	public RdesktopCanvas getCanvas() {
		return this.canvas;
	}
	
	public SeamlessChannel getSeamlessChannel() {
		return this.seamChannel;
	}

	public boolean isConnected() {
		return (this.RdpLayer != null && this.RdpLayer.isConnected());
	}
	
	public void disconnect() {
		if (this.common.rdp != null && this.common.rdp.isConnected()) {
			this.common.rdp.disconnect();
			this.common.rdp = null;
		}
	}

	public synchronized void stop() {
		this.keep_running = false;
		this.disconnect();
	}

	protected void closeVChannels() {
		if (this.opt.seamlessEnabled && this.seamChannel != null) {
			this.seamChannel.closeAllWindows();
			this.seamChannel.clearDatas();
			this.seamChannel = null;
		}

		if (this.soundChannel != null) {
			this.soundChannel.stopPlayThread();
			this.soundChannel = null;
		}
		
		if (this.rdpdrChannel != null) {
			((OVDRdpdrChannel)this.rdpdrChannel).reset();
		}
	}
	
	public void addRdpListener(RdpListener l) {
		this.listener.add(l);
	}
	
	public void removeRdpListener(RdpListener l) {
		this.listener.remove(l);
	}
	
	protected void fireConnected() {
		if (this.state == State.CONNECTED)
			return;
		
		this.state = State.CONNECTED;
		
		for(RdpListener list : this.listener) {
			list.connected(this);
		}
	}
	
	protected void fireConnecting() {
		this.state = State.CONNECTING;

		for(RdpListener list : this.listener) {
			list.connecting(this);
		}
	}
	
	protected void fireFailed() {
		this.stop();
		this.state = State.FAILED;
		
		for(RdpListener list : this.listener) {
			list.failed(this, this.failedMsg);
		}
		
		this.closeVChannels();
	}
	
	protected synchronized void fireDisconnected() {
		this.stop();
		this.state = State.DISCONNECTED;
		
		for(RdpListener list : this.listener) {
			list.disconnected(this);
		}
		
		this.closeVChannels();
	}
	
	protected void fireSeamlessEnabled() {
		for(RdpListener list : this.listener) {
			list.seamlessEnabled(this);
		}
	}

	public void ackHello(SeamlessChannel channel) {
		this.fireSeamlessEnabled();
	}
}
