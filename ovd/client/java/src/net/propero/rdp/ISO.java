/* ISO.java
 * Component: ProperJavaRDP
 * 
 * Revision: $Revision: 1.1.1.1 $
 * Author: $Author: suvarov $
 * Date: $Date: 2007/03/08 00:26:21 $
 *
 * Copyright (c) 2005 Propero Limited
 * Copyright (C) 2011-2012 Ulteo SAS
 * http://www.ulteo.com
 * Author David LECHEVALIER <david@ulteo.com> 2011, 2012
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
 *
 * Purpose: ISO layer of communication
 */
package net.propero.rdp;

import java.io.*;
import java.net.*;
import net.propero.rdp.crypto.CryptoException;
import org.apache.log4j.Logger;

public abstract class ISO {
	static Logger logger = Logger.getLogger(ISO.class);
	@SuppressWarnings("unused")
	private HexDump dump = null;
	protected Socket rdpsock = null;
	private DataInputStream in = null;
	private DataOutputStream out = null;
	protected Options opt = null;
	@SuppressWarnings("unused")
	private Common common = null;
	private TLSLayer tlsLayer;
	private int totalReceived = 0;
	private int baseRTT = 0;
	private int bandwidth = 0;
	private int averageRTT = 0;
	private boolean RTTEnable = false;
	
	/* this for the ISO Layer */
	private static final int CONNECTION_REQUEST = 0xE0;
	private static final int CONNECTION_CONFIRM = 0xD0;
	private static final int DISCONNECT_REQUEST = 0x80;
	private static final int DATA_TRANSFER = 0xF0;
	@SuppressWarnings("unused")
	private static final int ERROR = 0x70;
	private static final int PROTOCOL_VERSION = 0x03;
	private static final int EOT = 0x80;
	
	private static final int TYPE_RDP_NEG_REQ = 0x00000001;
	private static final int TYPE_RDP_NEG_RSP = 0x00000002;
	
	public static final int PROTOCOL_RDP      = 0x00000000;  // RC4 encryption method
	public static final int PROTOCOL_SSL      = 0x00000001;  // TLSv1 encryption method
	public static final int PROTOCOL_HYBRID   = 0x00000002;  // TLSv1 encryption method with NLA
	
	private static final int EXTENDED_CLIENT_DATA_SUPPORTED  = 0x01;
	private static final int DYNVC_GFX_PROTOCOL_SUPPORTED    = 0x02;
	private static final int RDP_NEGRSP_RESERVED             = 0x04;

	/**
	 * Construct ISO object, initialises hex dump
	 */
	public ISO(Options opt_, Common common_) {
		dump = new HexDump();
		this.opt = opt_;
		this.common = common_;
		this.totalReceived = 0;
	}

	/**
	 * Initialise an ISO PDU
	 * @param length Desired length of PDU
	 * @return Packet configured as ISO PDU, ready to write at higher level
	 */
	public RdpPacket_Localised init(int length) {
		RdpPacket_Localised data = new RdpPacket_Localised(length+7);//getMemory(length+7);
		data.incrementPosition(7);
		data.setStart(data.getPosition());
		return data;
	}
	
	/*
	protected Socket negotiateSSL(Socket sock) throws Exception{
		return sock;
	}*/

	/**
	 * Create a socket from SocketFactoryInterface
	 * @param host Address of server
	 * @param port Port on which to connect socket
	 * @throws IOException
	 */

	protected void doSocketConnect(String host, int port) throws IOException, RdesktopException {
		if (this.opt.socketFactory == null) {
			this.opt.socketFactory = new TCPSocketFactory(host, port);
		}
		
		try {
			this.rdpsock = (Socket) this.opt.socketFactory.createSocket();
		} catch (Exception e) {
			throw new RdesktopException("Creating socket failed: " + e.getMessage());
		}
	}

	/**
	 * Connect to a server
	 * @param host Address of server
	 * @param port Port to connect to on server
	 * @throws IOException
	 * @throws RdesktopException
	 * @throws OrderException
	 * @throws CryptoException
	 */
	public void connect(String host, int port) throws IOException, RdesktopException, OrderException, CryptoException {
		int[] code = new int[1];
		doSocketConnect(host, port);
		this.rdpsock.setTcpNoDelay(this.opt.low_latency);
		
		this.in = new DataInputStream(new BufferedInputStream(rdpsock.getInputStream()));
		this.out= new DataOutputStream(new BufferedOutputStream(rdpsock.getOutputStream()));
		send_connection_request();
		RdpPacket_Localised s = receiveMessage(code, false);
		
		if (code[0] != CONNECTION_CONFIRM) {
			throw new RdesktopException("Expected CC got:" + Integer.toHexString(code[0]).toUpperCase());
		}
				
		if (s.getPosition() == s.getEnd()) {
			logger.debug("TLS Layer not supported");
			this.opt.useTLS = false;
			return;
		}
		
		int request_type = s.get8();
		int flags = s.get8();                 // Request flags
		if ((flags & EXTENDED_CLIENT_DATA_SUPPORTED) != 0) {
			this.opt.extendedClientDataBlocksSupported = true;
		}
		
		s.getLittleEndian16();    // Request length
		int selected_proto = s.getLittleEndian32();
		if (request_type != TYPE_RDP_NEG_RSP)
			throw new RdesktopException("Failed to negociate security layer");
		
		if ((selected_proto & PROTOCOL_SSL) > 0) {
			logger.debug("TLS Layer activated");
			this.tlsLayer = new TLSLayer();
			Socket sslSocket = this.tlsLayer.initTransportLayer(this.rdpsock, host, port);
			if (s != null) {
				this.rdpsock = sslSocket;
				sslSocket.setTcpNoDelay(this.opt.low_latency);
				sslSocket.setSoTimeout(this.opt.socketTimeout);
				sslSocket.setReceiveBufferSize(1024 * 16);
				
				this.in = new DataInputStream(new BufferedInputStream(sslSocket.getInputStream()));
				this.out= new DataOutputStream(new BufferedOutputStream(sslSocket.getOutputStream()));
				this.opt.encryption = false;
			}
			else
				throw new RdesktopException("Unable to establish TLS connection");
		}
		
		if (this.opt.useBandwithLimitation || this.opt.useKeepAlive) {
			this.rdpsock.setSoTimeout(this.opt.socketTimeout);
		}
		
		/*if(Options.use_ssl){
			try {
				rdpsock = this.negotiateSSL(rdpsock);
				this.in = new DataInputStream(rdpsock.getInputStream());
				this.out= new DataOutputStream(rdpsock.getOutputStream());
			} catch (Exception e) {
				e.printStackTrace();
				throw new RdesktopException("SSL negotiation failed: " + e.getMessage());
			}
		}*/
	
	}

	/**
	 * Send a self contained iso-pdu
	 *
	 * @param type one of the following CONNECT_RESPONSE, DISCONNECT_REQUEST
	 * @exception IOException when an I/O Error occurs
	 */
	private void sendMessage(int type) throws IOException {
		RdpPacket_Localised buffer = new RdpPacket_Localised(11);//getMemory(11);
		byte[] packet=new byte[11];
		buffer.set8(PROTOCOL_VERSION); // send Version Info
		buffer.set8(0); // reserved byte
		buffer.setBigEndian16(11); // Length
		buffer.set8(6); // Length of Header
		buffer.set8(type); //where code = CR or DR
		buffer.setBigEndian16(0); // Destination reference ( 0 at CC and DR)
		buffer.setBigEndian16(0); // source reference should be a reasonable address we use 0
		buffer.set8(0); //service class
		buffer.copyToByteArray(packet, 0, 0, packet.length);
		out.write(packet);
		out.flush();
	}

	/**
	 * Send a packet to the server, wrapped in ISO PDU
	 * @param buffer Packet containing data to send to server
	 * @throws RdesktopException
	 * @throws IOException
	 */
	public void send(RdpPacket_Localised buffer) throws RdesktopException, IOException {
		if(rdpsock == null || out==null) return;
		
		if (buffer.getEnd() < 0) {
			throw new RdesktopException("No End Mark!");
		} else {
			int length = buffer.getEnd();
			byte[] packet = new byte[length];
			//RdpPacket data = this.getMemory(length+7);
			buffer.setPosition(0);
			buffer.set8(PROTOCOL_VERSION); // Version
			buffer.set8(0); // reserved
			buffer.setBigEndian16(length); //length of packet
			buffer.set8(2); //length of header
			buffer.set8(DATA_TRANSFER);
			buffer.set8(EOT);
			buffer.copyToByteArray(packet, 0, 0, buffer.getEnd());
			if(this.opt.debug_hexdump) HexDump.encode(packet, "SEND"/*System.out*/);
			out.write(packet);
			out.flush();
		}
	}

	/**
	 * Receive a data transfer message from the server
	 * @return Packet containing message (as ISO PDU)
	 * @throws IOException
	 * @throws RdesktopException
	 * @throws OrderException
	 * @throws CryptoException
	 */
	public RdpPacket_Localised receive(boolean save_version) throws IOException, RdesktopException, OrderException, CryptoException {
		int[] type = new int[1];
		RdpPacket_Localised buffer = receiveMessage(type, true);
		
		if(buffer == null) return null;

		if (this.RTTEnable);
			this.totalReceived += buffer.size();
		
		if (save_version && this.opt.server_rdp_version != 3)
			return buffer;
		
		if (type[0] != DATA_TRANSFER) {
			throw new RdesktopException("Expected DT got:" + type[0]);
		}
		
		return buffer;
	}

	/**
	 * Receive a specified number of bytes from the server, and store in a packet
	 * @param p Packet to append data to, null results in a new packet being created
	 * @param length Length of data to read
	 * @return Packet containing read data, appended to original data if provided
	 * @throws IOException
	 */
	private RdpPacket_Localised tcp_recv(RdpPacket_Localised p, int length) throws IOException {
		logger.debug("ISO.tcp_recv");
		RdpPacket_Localised buffer = null;
		
		byte [] packet = new byte[length];
		
		int dataRead = 0;
		
		while (dataRead != length) {
			try{
				dataRead += in.read(packet, dataRead, length - dataRead);
			}
			catch (SocketTimeoutException e) {
				if (this.opt.readytosend && dataRead == 0)
					throw e;
			}
		}
		
		if(this.opt.debug_hexdump) HexDump.encode(packet, "RECEIVE" /*System.out*/);
			
		if(p == null) {
			buffer = new RdpPacket_Localised(length);
			buffer.copyFromByteArray(packet, 0, 0, packet.length);
			buffer.markEnd(length);
			buffer.setStart(buffer.getPosition());
		} else {
			buffer = new RdpPacket_Localised((p.getEnd() - p.getStart()) + length);
			buffer.copyFromPacket(p,p.getStart(),0,p.getEnd());
			buffer.copyFromByteArray(packet, 0, p.getEnd(), packet.length);
			buffer.markEnd(p.size() + packet.length);
			buffer.setPosition(p.getPosition());
			buffer.setStart(0);
		}
		
		return buffer;
	}
	
	/**
	 * Receive a message from the server
	 * @param type Array containing message type, stored in type[0]
	 * @return Packet object containing data of message
	 * @throws IOException
	 * @throws RdesktopException
	 * @throws OrderException
	 * @throws CryptoException
	 */
	private RdpPacket_Localised receiveMessage(int[] type, boolean save_version) throws IOException, RdesktopException, OrderException, CryptoException {
		logger.debug("ISO.receiveMessage");
		RdpPacket_Localised s = null;
		int length, version;
		s = tcp_recv(null,4);
		
		if(s == null) {
			System.err.println("Packet lost!");
			return null;
		}
		
		version = s.get8();
		
		if (save_version) {
			this.opt.server_rdp_version = version;
		}
		
		if(version == 3) {
			s.incrementPosition(1); // pad
			length = s.getBigEndian16();
		}else{
			length = s.get8();
			if((length & 0x80) != 0){
				length &= ~0x80;
				length = (length << 8) + s.get8();
			}
		}
		
		if (length < 4) {
			logger.error("Bad packet header");
			return null;
		}
		
		s = tcp_recv(s, length - 4);
		
		if(s == null) return null;
		
		if (version != 3)
			return s;
		
		s.incrementPosition(1);
		type[0] = s.get8();
		
		if(type[0] == DATA_TRANSFER){
			logger.debug("Data Transfer Packet");
			s.incrementPosition(1); // eot
			return s;
		}
		
		s.incrementPosition(5); // dst_ref, src_ref, class
		return s;
	}

	/**
	 * Disconnect from an RDP session, closing all sockets
	 */
	public void disconnect() {
		if (rdpsock == null) return;

		try {
			sendMessage(DISCONNECT_REQUEST);
			if(in != null) in.close();
			if(out != null) out.close();
			if(rdpsock != null) rdpsock.close();
		} catch(IOException e) {
			in = null;
			out = null;
			rdpsock = null;
			return;
		}
		
		in = null;
		out = null;
		rdpsock = null;
	}

	/**
	* Send the server a connection request, detailing client protocol version
	* @throws IOException
	*/
	void send_connection_request() throws IOException {
		String uname = this.opt.username;
		String cookietail = this.opt.rdpCookie.getFormatedCookie();
		int proto = PROTOCOL_RDP;
		
		if (uname.length() == 0)
			uname = "user"; // Default username
		
		if(uname.length() > 9) uname = uname.substring(0,9);
		byte[] temp = null;
		try {
			temp = uname.getBytes("CP1252");
		}
		catch (UnsupportedEncodingException e) {
			logger.warn("Unsupported encoding");
			temp = "user".getBytes();
		}

		int length = 21 + "Cookie: mstshash=".length() + temp.length + cookietail.length();
		if (this.opt.useTLS)
			length += 8;
		
		RdpPacket_Localised buffer = new RdpPacket_Localised(length);
		byte[] packet=new byte[length];
		buffer.set8(PROTOCOL_VERSION); // send Version Info
		buffer.set8(0); // reserved byte
		buffer.setBigEndian16(length); // Length
		buffer.set8(length-5); // Length of Header
		buffer.set8(CONNECTION_REQUEST);
		buffer.setBigEndian16(0); // Destination reference ( 0 at CC and DR)
		buffer.setBigEndian16(0); // source reference should be a reasonable address we use 0
		buffer.set8(0); //service class
		logger.debug("Including username");
		buffer.out_uint8p("Cookie: mstshash=", "Cookie: mstshash=".length());
		buffer.copyFromByteArray(temp, 0, buffer.getPosition(), temp.length);
		buffer.incrementPosition(temp.length);
		buffer.out_uint8p(cookietail, cookietail.length());
		buffer.set8(0x0d); // unknown
		buffer.set8(0x0a); // unknown

		/*
		// Authentication request?
		buffer.setLittleEndian16(0x01);
		buffer.setLittleEndian16(0x08);
		// Do we try to use SSL?
		buffer.set8(Options.use_ssl? 0x01 : 0x00);
		buffer.incrementPosition(3);
		*/
		if (this.opt.useTLS) {
			proto = PROTOCOL_SSL;
		}
		
		// This packet is needed in order to receive a packet TYPE_RDP_NEG_RSP which inform about:
		//  * TLS transport layer initialization
		//  * support of network autodetection
		if (this.opt.useTLS || this.opt.networkConnectionType == Rdp.CONNECTION_TYPE_AUTODETECT) {
			buffer.set8(TYPE_RDP_NEG_REQ);  // Type
			buffer.set8(0);                 // Flags (must be set to 0)
			buffer.setLittleEndian16(8);    // Length (must be set to 8)
			buffer.setLittleEndian32(proto); // Protocol
		}
		
		buffer.copyToByteArray(packet, 0, 0, packet.length);
		out.write(packet);
		out.flush();
	}
		
	public void resetCounter() {
		this.RTTEnable = true;
		this.totalReceived = 0;
	}
	
	public int getTotalReceived() {
		return this.totalReceived;
	}
	
	public void updateRTT(int baseRTT, int bandwidth, int averageRTT) {
		this.baseRTT = baseRTT;
		this.bandwidth = bandwidth;
		this.averageRTT = averageRTT;
		
		logger.info("Bandwidth autodetection result:");
		logger.info("  base RTT = "+this.baseRTT+" ms");
		logger.info("  bandwidth = "+this.bandwidth+" kbs");
		logger.info("  average RTT = "+this.averageRTT+" ms");
	}
	
	public int getBaseRTT() {
		return this.baseRTT;
	}
	
	public int getBandwidth() {
		return this.bandwidth;
	}
	
	public int getAverageRTT() {
		return this.averageRTT;
	}
}
