/*
 * Copyright (C) 2009-2013 Ulteo SAS
 * http://www.ulteo.com
 * Author Vincent ROULLIER <v.roullier@ulteo.com> 2013
 * Author Thomas MOUTON <thomas@ulteo.com> 2010-2011
 * Author Jeremy DESVAGES <jeremy@ulteo.com> 2010
 * Author Julien LANGLOIS <julien@ulteo.com> 2010, 2011, 2012
 * Author David LECHEVALIER <david@ulteo.com> 2010 , 2012
 * Author Arnaud LEGRAND <arnaud@ulteo.com> 2010
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

package org.ulteo.ovd.sm;

import java.io.BufferedInputStream;
import java.io.ByteArrayInputStream;
import java.io.InputStream;
import java.io.IOException;
import java.io.OutputStream;
import java.io.OutputStreamWriter;
import java.io.StringWriter;
import java.io.UnsupportedEncodingException;
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.URL;
import java.security.cert.CertificateException;
import java.security.cert.X509Certificate;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.concurrent.CopyOnWriteArrayList;
import javax.imageio.ImageIO;
import javax.net.ssl.HostnameVerifier;
import javax.net.ssl.HttpsURLConnection;
import javax.net.ssl.SSLContext;
import javax.net.ssl.SSLSession;
import javax.net.ssl.SSLSocketFactory;
import javax.net.ssl.TrustManager;
import javax.net.ssl.X509TrustManager;
import javax.swing.ImageIcon;

import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import javax.xml.parsers.ParserConfigurationException;
import javax.xml.transform.Transformer;
import javax.xml.transform.TransformerConfigurationException;
import javax.xml.transform.TransformerException;
import javax.xml.transform.TransformerFactory;
import javax.xml.transform.dom.DOMSource;
import javax.xml.transform.stream.StreamResult;
import net.propero.rdp.RdpConnection;
import org.ulteo.Logger;
import org.w3c.dom.Document;
import org.w3c.dom.Element;
import org.w3c.dom.NamedNodeMap;
import org.w3c.dom.Node;
import org.w3c.dom.NodeList;
import org.xml.sax.InputSource;



public class SessionManagerCommunication implements HostnameVerifier, X509TrustManager {
	public static final String SESSION_MODE_REMOTEAPPS = "applications";
	public static final String SESSION_MODE_DESKTOP = "desktop";

	private static final String WEBSERVICE_ICON = "icon";
	private static final String WEBSERVICE_MIMETYPE_ICON = "mimetype-icon";
	private static final String WEBSERVICE_START_SESSION = "start";
	private static final String WEBSERVICE_EXTERNAL_APPS = "remote_apps";
	private static final String WEBSERVICE_SESSION_STATUS = "session_status";
	private static final String WEBSERVICE_NEWS = "news";
	private static final String WEBSERVICE_LOGOUT = "logout";

	public static final String FIELD_LOGIN = "login";
	public static final String FIELD_PASSWORD = "password";
	public static final String FIELD_TOKEN = "token";
	public static final String FIELD_SESSION_MODE = "session_mode";
	public static final String FIELD_ICON_ID = "id";

	public static final String NODE_SETTINGS = "settings";
	public static final String NODE_SETTING = "setting";

	public static final String FIELD_NAME = "name";
	public static final String FIELD_VALUE = "value";

	public static final String VALUE_HIDDEN_PASSWORD = "****";

	public static final String CONTENT_TYPE_FORM = "application/x-www-form-urlencoded";
	private static final String CONTENT_TYPE_XML = "text/xml";
	private static final String CONTENT_TYPE_PNG = "image/png";

	public static final String REQUEST_METHOD_POST = "POST";
	public static final String REQUEST_METHOD_GET = "GET";

	public static final String SESSION_STATUS_UNKNOWN = "unknown";
	public static final String SESSION_STATUS_ERROR = "error";
	public static final String SESSION_STATUS_INIT = "init";
	public static final String SESSION_STATUS_INITED = "ready";
	public static final String SESSION_STATUS_ACTIVE = "logged";
	public static final String SESSION_STATUS_INACTIVE = "disconnected";
	public static final String SESSION_STATUS_WAIT_DESTROY = "wait_destroy";
	public static final String SESSION_STATUS_DESTROYED = "destroyed";

	private static final int TIMEOUT = 2000;
	private static final int MAX_REDIRECTION_TRY = 5;
	public static final int DEFAULT_PORT = 443;
	public static final int DEFAULT_RDP_PORT = RdpConnection.RDP_PORT;

	private String host = null;
	private int port;
	private boolean use_https = false;

	private String base_url = null;

	private Properties requestProperties = null;
	private Properties responseProperties = null;
	private List<ServerAccess> servers = null;

	private CopyOnWriteArrayList<Callback> callbacks = null;

	private List<String> cookies = null;

	public SessionManagerCommunication(String host_, int port_, boolean use_https_) {
		this.servers = new  ArrayList<ServerAccess>();
		this.callbacks = new CopyOnWriteArrayList<Callback>();

		this.cookies = new ArrayList<String>();
		this.host = host_;
		this.port = port_;
		this.use_https = use_https_;

		this.base_url = this.makeUrl("");

		SessionExpiration.getInstance().reset();
	}

	public String getHost() {
		return this.host;
	}

	private String makeUrl(String service) {
		return (this.use_https ? "https" : "http") + "://" + this.host + (this.port==SessionManagerCommunication.DEFAULT_PORT ? "" : ":"+this.port) + "/ovd/client/" + service;
	}

	private static String makeStringForPost(List<String> listParameter) {
		String listConcat = "";
		if(listParameter.size() > 0) {
			listConcat += listParameter.get(0);
			for(int i = 1 ; i < listParameter.size() ; i++) {
				listConcat += "&";
				listConcat += listParameter.get(i);
			}
		}
		return listConcat;
	}

	private static String concatParams(HashMap<String,String> params) {
		List<String> listParameter = new ArrayList<String>();
		for (String name : params.keySet()) {
			listParameter.add(name+"="+params.get(name));
		}

		return makeStringForPost(listParameter);
	}
	
	public static Document getNewDocument() {
		DocumentBuilderFactory factory = DocumentBuilderFactory.newInstance();
		DocumentBuilder builder;
		try {
			builder = factory.newDocumentBuilder();
		} catch (ParserConfigurationException e) {
			e.printStackTrace();
			return null;
		}
		
		return builder.newDocument();
	}
	
	public static String Document2String(Document document) {
		DOMSource domSource = new DOMSource(document);
		StringWriter writer = new StringWriter();
		StreamResult result = new StreamResult(writer);
		TransformerFactory tf = TransformerFactory.newInstance();
		Transformer transformer;
		try {
			transformer = tf.newTransformer();
			transformer.transform(domSource, result);
		} catch (TransformerConfigurationException e) {
			e.printStackTrace();
			return null;
		} catch (TransformerException e) {
			e.printStackTrace();
			return null;
		}
		
		return writer.toString(); 
	}
	

	public boolean askForSession(String login, String password, Properties request) throws SessionManagerException {
		if (login == null || password == null || request == null || this.requestProperties != null)
 			return false;
		
		String encodedLogin = null;
		String encodedPassword = null;
		
		try {
			encodedLogin = new String(login.getBytes("UTF-8"));
			encodedPassword = new String(password.getBytes("UTF-8"));
		} catch (UnsupportedEncodingException e) {
			Logger.warn("Failed to encode login and password in unicode");
			encodedLogin = login;
			encodedPassword = password;
		}
		
		this.requestProperties = request;
		
		Document doc = getNewDocument();
		if (doc == null)
			return false;
		
		Element session = doc.createElement("session");
		doc.appendChild(session);
		
		if (request.getMode() == Properties.MODE_DESKTOP)
			session.setAttribute("mode", SESSION_MODE_DESKTOP);
		else if (request.getMode() == Properties.MODE_REMOTEAPPS)
			session.setAttribute("mode", SESSION_MODE_REMOTEAPPS);

		Element user = doc.createElementNS(null, "user");
		user.setAttribute("login", encodedLogin);
		user.setAttribute("password", encodedPassword);

		session.appendChild(user);
		
		session.setAttribute("language", request.getLang());
		session.setAttribute("timezone", request.getTimeZone());
		
		String data = Document2String(doc);
		if (data == null)
			return false;
		
		Object obj = this.askWebservice(WEBSERVICE_START_SESSION, CONTENT_TYPE_XML, REQUEST_METHOD_POST, data, true);
		if (! (obj instanceof Document))
			return false;
 		
 		return this.parseStartSessionResponse((Document) obj);
	}

	/**
	 * If using Apache auth module (NTLM, Kerberos, ...), Java use directly Windows credentials
	 */
	public boolean askForSession(Properties request) throws SessionManagerException {
		if (request == null || this.requestProperties != null)
			return false;

		this.requestProperties = request;
		
		Document doc = getNewDocument();
		if (doc == null)
			return false;
		
		Element session = doc.createElement("session");
		doc.appendChild(session);
		
		if (request.getMode() == Properties.MODE_DESKTOP)
			session.setAttribute("mode", SESSION_MODE_DESKTOP);
		else if (request.getMode() == Properties.MODE_REMOTEAPPS)
			session.setAttribute("mode", SESSION_MODE_REMOTEAPPS);
		
		String data = Document2String(doc);
		if (data == null)
			return false;
			
		Object obj = this.askWebservice(WEBSERVICE_START_SESSION, CONTENT_TYPE_XML, REQUEST_METHOD_POST, data, true);
		if (! (obj instanceof Document))
			return false;

 		return this.parseStartSessionResponse((Document) obj);
	}
	
	public boolean askForExternalAppsSession(String token, Properties request) throws SessionManagerException {
		if (token == null || request == null || this.requestProperties != null)
			return false;

		this.requestProperties = request;

		HashMap<String,String> params = new HashMap<String,String>();
		if (request.getMode() == Properties.MODE_DESKTOP)
			params.put(FIELD_SESSION_MODE, SESSION_MODE_DESKTOP);
		else if (request.getMode() == Properties.MODE_REMOTEAPPS)
			params.put(FIELD_SESSION_MODE, SESSION_MODE_REMOTEAPPS);

		params.put(FIELD_TOKEN, token);

		Object obj = this.askWebservice(WEBSERVICE_EXTERNAL_APPS, CONTENT_TYPE_FORM, REQUEST_METHOD_POST, concatParams(params), true);
		if (! (obj instanceof Document))
			return false;

 		return this.parseStartSessionResponse((Document) obj);
	}

	public boolean askForLogout(boolean persistent) throws SessionManagerException {
		SessionExpiration.getInstance().reset();
		Document doc = getNewDocument();
		if (doc == null)
			return false;
		
		Element logout = doc.createElement("logout");

		if (persistent)
			logout.setAttribute("mode", "suspend");
		else
			logout.setAttribute("mode", "logout");
		doc.appendChild(logout);
		
		String data = Document2String(doc);
		if (data == null)
			return false;
		
		Object obj = this.askWebservice(WEBSERVICE_LOGOUT, CONTENT_TYPE_XML, REQUEST_METHOD_POST, data, true);
		return obj instanceof Document;
	}

	public String askForSessionStatus() {
		try {
			Document doc = getNewDocument();
			if (doc == null)
				throw new SessionManagerException();
			
			Object obj = this.askWebservice(WEBSERVICE_SESSION_STATUS, CONTENT_TYPE_FORM, REQUEST_METHOD_POST, null, false);
			if (! (obj instanceof Document))
				throw new SessionManagerException();
			
	 		return this.parseSessionStatusResponse((Document) obj);
		} catch (SessionManagerException e) {
			Logger.warn("Session status could not be received: " + e.getMessage());
			return SESSION_STATUS_UNKNOWN;
		}
	}

	public List<News> askForNews() throws SessionManagerException {
		Object obj = this.askWebservice(WEBSERVICE_NEWS, CONTENT_TYPE_FORM, REQUEST_METHOD_POST, null, false);
		if (! (obj instanceof Document))
			return null;
		
		return this.parseNewsResponse((Document) obj);
	}
	
	
	/**
	 * get an application's icon stored in the Session Manager
	 * @param appItem
	 * 		Session Manager application item
	 * @return
	 * 		an icon, null if not found or if an error occurred
	 */
	public ImageIcon askForIcon(Application appItem) {
		HashMap<String, String> params = new HashMap<String, String>();
		params.put(FIELD_ICON_ID, Integer.toString(appItem.getId()));

		Object obj = null;
		try {
			obj = this.askWebservice(WEBSERVICE_ICON+"?"+concatParams(params), CONTENT_TYPE_FORM, REQUEST_METHOD_GET, null, true);
		} catch (SessionManagerException e) {
			Logger.warn("Cannot get the \"" + appItem.getName() + "\" icon from Session Manager: " + e.getMessage());
		}
		
		if (! (obj instanceof ImageIcon))
			return null;

		ImageIcon icon = (ImageIcon) obj;
		if (icon.getIconHeight() <= 0 || icon.getIconWidth() <= 0)
			return null;
		
		return icon;
	}

	/**
	 * get an mime-type's icon stored in the Session Manager
	 * @param mimeType
	 * 		specified mime-type
	 * @return
	 * 		an icon, null if not found or if an error occurred
	 */
	public ImageIcon askForMimeTypeIcon(String mimeType) {
		HashMap<String, String> params = new HashMap<String, String>();
		params.put(FIELD_ICON_ID, mimeType);

		Object obj = null;
		try {
			obj = this.askWebservice(WEBSERVICE_MIMETYPE_ICON+"?"+concatParams(params), CONTENT_TYPE_FORM, REQUEST_METHOD_GET, null, true);
		} catch (SessionManagerException e) {
			Logger.error("Cannot get the mime type \"" + mimeType + "\" icon from Session Manager: " + e.getMessage());
		}
		
		if (! (obj instanceof ImageIcon))
			return null;

		ImageIcon icon = (ImageIcon) obj;
		if (icon.getIconHeight() <= 0 || icon.getIconWidth() <= 0)
			return null;

		return icon;
	}
	
	
	/**
	 * send a customized request to the Session Manager 
	 * @param webservice
	 * 		the service path to join on the SM
	 * @param content_type
	 * 		the content type expected to receive
	 * @param method
	 * 		specify GET or POST for the HTTP request
	 * @param data
	 * 		some optional data to send during the request
	 * @param showLog
	 * 		verbosity of this function
	 * @return
	 * 		generic {@link Object} result sent by the Session Manager
	 * @throws SessionManagerException
	 * 		generic exception for all failure during the Session manager communication
	 */
	public Object askWebservice(String webservice, String content_type, String method, String data, boolean showLog) throws SessionManagerException {
		try {
			URL url = new URL(this.base_url + webservice);
			return askWebservice(url, content_type, method, data, showLog, MAX_REDIRECTION_TRY);
		} catch (MalformedURLException e) {
			throw new SessionManagerException(e.getMessage());
		}
	}
	
	
	/**
	 * send a customized request to the Session Manager 
	 * @param url
	 * 		the complete {@link URL} to join on the SM
	 * @param content_type
	 * 		the content type expected to receive
	 * @param method
	 * 		specify GET or POST for the HTTP request
	 * @param data
	 * 		some optional data to send during the request
	 * @param showLog
	 * 		verbosity of this function
	 * @param retry
	 * 		indicate the maximum of redirected request to make 
	 * @return
	 * 		generic {@link Object} result sent by the Session Manager
	 * @throws SessionManagerException
	 * 		generic exception for all failure during the Session manager communication
	 */
	private Object askWebservice(URL url, String content_type, String method, String data, boolean showLog, int retry) throws SessionManagerException {
		if (showLog)
			Logger.debug("Connecting URL: " + url);
		
		if (retry == 0)
			throw new SessionManagerException(MAX_REDIRECTION_TRY + " redirections has been done without success");
		
		Object obj = null;
		HttpURLConnection connexion = null;

		try {
			connexion = (HttpURLConnection) url.openConnection();
			connexion.setAllowUserInteraction(true);
			connexion.setConnectTimeout(TIMEOUT);
			connexion.setDoInput(true);
			connexion.setDoOutput(true);
			connexion.setInstanceFollowRedirects(false);
			connexion.setRequestMethod(method);
			connexion.setRequestProperty("Content-type", content_type);
			for (String cookie : this.cookies) {
				connexion.setRequestProperty("Cookie", cookie);
			}
			if (this.use_https) {		
				SSLContext sc = SSLContext.getInstance("SSL");
				sc.init(null, new TrustManager[] { this }, null);
				SSLSocketFactory factory = sc.getSocketFactory();
				((HttpsURLConnection)connexion).setSSLSocketFactory(factory);
				((HttpsURLConnection)connexion).setHostnameVerifier(this);
			}
			connexion.connect();

			if (data != null) {
				OutputStreamWriter out = new OutputStreamWriter(connexion.getOutputStream());
				out.write(data);
				out.flush();
				out.close();

				try {
					DocumentBuilder domBuilder = DocumentBuilderFactory.newInstance().newDocumentBuilder();
					Document xmlOut = domBuilder.parse(new ByteArrayInputStream(data.getBytes()));
					Logger.debug("Sending XML data: ");
					dumpXML(xmlOut);
				} catch (Exception ex) {
					Logger.debug("Send: "+data);
				}
			}
			
			int r = connexion.getResponseCode();
			String res = connexion.getResponseMessage();
			String contentType = connexion.getContentType();

			if (showLog)
				Logger.debug("Response "+r+ " ==> "+res+ " type: "+contentType);

			String http_infos = "\tResponse code: "+ r +"\n\tResponse message: "+ res +"\n\tContent type: "+ contentType;

			if (r == HttpURLConnection.HTTP_OK) {
				InputStream in = connexion.getInputStream();

				if (contentType.startsWith(CONTENT_TYPE_XML)) {
					DocumentBuilder domBuilder = DocumentBuilderFactory.newInstance().newDocumentBuilder();
					Document doc = domBuilder.parse(new InputSource(in));
					
					Element rootNode = doc.getDocumentElement();
					if (rootNode.getNodeName().equalsIgnoreCase("error")) {
						for (Callback each : this.callbacks) {
							each.reportError(Integer.parseInt(rootNode.getAttribute("id")), rootNode.getAttribute("message"));
						}
					}
					else {
						obj = doc;
					}

					if (showLog) {
						Logger.debug("Receiving XML:");
						this.dumpXML((Document) doc);
					}
				}
				else if (contentType.startsWith(CONTENT_TYPE_PNG)) {
					try {
						obj = new ImageIcon(ImageIO.read(in));
					}
					catch (IOException err) {
						Logger.error("askWebservice: unable to icon image");
						Logger.debug("askWebservice: unable to icon image "+err);
					}
				}
				else {
					BufferedInputStream d = new BufferedInputStream(in);
					String buffer = "";
					for( int c = d.read(); c !=-1; c = d.read())
						buffer+=(char)c;
					
					Logger.warn("Unknown content-type: "+contentType+"buffer: \n"+buffer+"==\n");
				}
				in.close();

				String headerName=null;
				for (int i=1; (headerName = connexion.getHeaderFieldKey(i))!=null; i++) {
					if (headerName.equals("Set-Cookie")) {
						String cookie = connexion.getHeaderField(i);

						boolean cookieIsPresent = false;
						for (String value : this.cookies) {
							if (value.equalsIgnoreCase(cookie))
								cookieIsPresent = true;
						}
						if (! cookieIsPresent)
							this.cookies.add(cookie);
					}
				}
			}
			else if (r == HttpURLConnection.HTTP_MOVED_TEMP) {
				URL location = new URL(connexion.getHeaderField("Location"));
				Logger.debug("Redirection: " + location);
				return askWebservice(location, content_type, method, data, showLog, retry-1);
			}
			else if (r == HttpURLConnection.HTTP_UNAUTHORIZED) {
				for (Callback c : this.callbacks)
					c.reportUnauthorizedHTTPResponse(http_infos);
			}
			else if (r == HttpURLConnection.HTTP_NOT_FOUND) {
				for (Callback c : this.callbacks)
					c.reportNotFoundHTTPResponse(http_infos);
			}
			else if (r == HttpURLConnection.HTTP_SERVER_ERROR) {
				Logger.warn("Http Error " + r);
			}
			else {
				for (Callback c : this.callbacks)
					c.reportError(r, res);
			}
		}
		catch (Exception e) {
			throw new SessionManagerException(e.getMessage());
		}
		finally {
			connexion.disconnect();
		}

		return obj;
	}

	private String parseSessionStatusResponse(Document in) throws SessionManagerException {
		Element rootNode = in.getDocumentElement();

		if (! rootNode.getNodeName().equals("session")) {
			for (Callback c : this.callbacks)
				c.reportBadXml("");

			throw new SessionManagerException("bad xml");
 		}

		String status = null;
		try {
			status = rootNode.getAttribute("status");
 		}
		catch (Exception err) {
			for (Callback c : this.callbacks)
				c.reportBadXml("");

			throw new SessionManagerException("bad xml");
		}
		
		int timeout = -1;
		try {
			// time restriction is in minute
			String time_restriction = rootNode.getAttribute("time_restriction");
			timeout = Integer.parseInt(time_restriction);
		}
		catch (Exception err) {
			timeout = -1;
		}
		
		SessionExpiration.getInstance().setExpiration(timeout);

		return status;
	}

	private List<News> parseNewsResponse(Document in) throws SessionManagerException {
		List<News> newsList = new ArrayList<News>();
		
		Element rootNode = in.getDocumentElement();
		
		if (! rootNode.getNodeName().equals("news")) {
			for (Callback c : this.callbacks)
				c.reportBadXml("");
			
			throw new SessionManagerException("bad xml");
		}
		
		NodeList newNodes = rootNode.getElementsByTagName("new");
		for (int j = 0; j < newNodes.getLength(); j++) {
			Element newNode = (Element) newNodes.item(j);
			
			News n = new News(Integer.parseInt(newNode.getAttribute("id")), newNode.getAttribute("title"), newNode.getFirstChild().getTextContent(), Integer.parseInt(newNode.getAttribute("timestamp")));
			
			newsList.add(n);
		}
		
		return newsList;
	}

	private boolean parseStartSessionResponse(Document document) {
		Element rootNode = document.getDocumentElement();

		if (! rootNode.getNodeName().equals("session")) {
			if (rootNode.getNodeName().equals("response")) {
				try {
					String code = rootNode.getAttribute("code");

					for (Callback c : this.callbacks)
						c.reportErrorStartSession(code);

					return false;
				}
				catch(Exception err) {
					Logger.error("Error: bad XML #1");
				}

				for (Callback c : this.callbacks)
					c.reportBadXml("");

				return false;
 			}
		}

		try {
			int mode = Properties.MODE_ANY;
			boolean mode_gateway = false;

			if (rootNode.getAttribute("mode").equals(SESSION_MODE_DESKTOP))
				mode = Properties.MODE_DESKTOP;
			else if (rootNode.getAttribute("mode").equals(SESSION_MODE_REMOTEAPPS))
				mode = Properties.MODE_REMOTEAPPS;
			if (mode == Properties.MODE_ANY)
				throw new Exception("bad xml: no valid session mode");

			Properties response = new Properties(mode);

			if (rootNode.hasAttribute("mode_gateway")) {
				if (rootNode.getAttribute("mode_gateway").equals("on"))
					mode_gateway = true;
			}

			if (rootNode.hasAttribute("duration"))
				response.setDuration(Integer.parseInt(rootNode.getAttribute("duration")));

			NodeList settingsNodeList = rootNode.getElementsByTagName(NODE_SETTINGS);
			if (settingsNodeList.getLength() == 1) {
				Element settingsNode = (Element) settingsNodeList.item(0);

				settingsNodeList = settingsNode.getElementsByTagName(NODE_SETTING);
				for (int i = 0; i < settingsNodeList.getLength(); i++) {
					Element setting = (Element) settingsNodeList.item(i);
					
					try {
						String name = setting.getAttribute(FIELD_NAME);
						String value = setting.getAttribute(FIELD_VALUE);
						
						Protocol.parseSessionSettings(response, name, value);
					}
					catch(org.w3c.dom.DOMException err) {}
				}
			}
			
			NodeList usernameNodeList = rootNode.getElementsByTagName("user");
			if (usernameNodeList.getLength() == 1) {
				response.setUsername(((Element) usernameNodeList.item(0)).getAttribute("displayName"));
			}

			this.responseProperties = response;
			this.parseServers(rootNode, mode_gateway);
			this.parseWebappServers(rootNode, mode_gateway);
 		}
		catch(Exception err) {
			for (Callback c : this.callbacks)
				c.reportBadXml(err.toString());
			return false;
		}

		return true;
	}
	
	private void parseServers(Element rootNode, boolean mode_gateway) {
		NodeList serverNodes = rootNode.getElementsByTagName("server");
		
		// We no longer throw an exception here if list is empty.
		// A server might only be providing web applications and we are fine with that.

		for (int i = 0; i < serverNodes.getLength(); i++) {
			Element serverNode = (Element) serverNodes.item(i);
			
			String server_host;
			if (mode_gateway)
				server_host = this.host;
			else
				server_host = serverNode.getAttribute("fqdn");
			
			int server_port = SessionManagerCommunication.DEFAULT_RDP_PORT;
			if (mode_gateway)
				server_port = this.port;
			else if (serverNode.hasAttribute("port"))
				try {
					server_port = Integer.parseInt(serverNode.getAttribute("port"));
				}
				catch (NumberFormatException ex) {
					Logger.warn("Invalid protocol: server port attribute is not a digit ("+serverNode.getAttribute("port")+")");
				}
			
			ServerAccess server = new ServerAccess(server_host, server_port,
						serverNode.getAttribute("login"), serverNode.getAttribute("password"));
			
			if (mode_gateway)
				server.setGatewayToken(serverNode.getAttribute("token"));
			
			server.applications = parseApplications(serverNode);
			this.servers.add(server);
		}
	}
	
	private void parseWebappServers(final Element rootNode, final boolean mode_gateway) throws MalformedURLException {
		NodeList webappServerNodes = rootNode.getElementsByTagName("webapp-server");
		for (int i = 0; i < webappServerNodes.getLength(); i++) {
			final Element serverNode = (Element) webappServerNodes.item(i);
			final String baseUrl = serverNode.getAttribute("base-url");
			final String login = serverNode.getAttribute("login");
			final String password = serverNode.getAttribute("password");
			//final String type = serverNode.getAttribute("type");
			final String url = serverNode.getAttribute("webapps-url");
			final List<Application> applications = parseApplications(serverNode);
			final URL parsedUrl = new URL(baseUrl);
			final String host = parsedUrl.getHost();
			final int port = parsedUrl.getPort();
			final WebAppsServerAccess server = new WebAppsServerAccess(host, port, login, password, url);
			server.setApplications(applications);
			this.servers.add(server);
		}
	}
	
	/**
	 * parse a DOM {@link Element} list of applications to a standard java {@link ArrayList}
	 * @param serverNode
	 * 		DOM {@link Element} to parse
	 * @return
	 * 		iterable {@link ArrayList} of {@link Application}
	 */
	public static ArrayList<Application> parseApplications(Element serverNode) {
		ArrayList<Application> apps = new ArrayList<Application>();
		
		NodeList applicationsNodes = serverNode.getElementsByTagName("application");
		for (int j = 0; j < applicationsNodes.getLength(); j++) {
			Element applicationNode = (Element) applicationsNodes.item(j);

			Application application = new Application(Integer.parseInt(applicationNode.getAttribute("id")),
					applicationNode.getAttribute("name"));
			
			NodeList mimeNodes = applicationNode.getElementsByTagName("mime");
			for (int k = 0; k < mimeNodes.getLength(); k++) {
				Element mimeNode = (Element) mimeNodes.item(k);
				application.addMime(mimeNode.getAttribute("type"));
			}
			apps.add(application);
		}
		return apps;
	}

	/**
	 * display all XML data in the standard logger output
	 * @param doc
	 * 		XML {@link Document} to display
	 */
	private void dumpXML(Document doc) {
		if (doc == null)
			throw new NullPointerException("Document parameter must not be null");
		
		OutputStream out = Logger.getOutputStream();
		try {
			if (out == null)
				throw new NullPointerException("no output stream is available from Logger");
			
			doc = this.cloneDomDocument(doc);
			this.hidePassword(doc);
			
			Transformer transformer = TransformerFactory.newInstance().newTransformer();
			transformer.transform(new DOMSource(doc), new StreamResult(out));

			out.flush();
		} catch (Exception ex) {
			Logger.error("Failed to dump XML data: "+ex.getMessage());
		}
	}

	private Document cloneDomDocument(Document document) throws ParserConfigurationException {
		DocumentBuilder domBuilder = DocumentBuilderFactory.newInstance().newDocumentBuilder();
		Document copiedDocument = domBuilder.newDocument();
		Node copiedRoot = copiedDocument.importNode(document.getDocumentElement(), true);
		copiedDocument.appendChild(copiedRoot);
		return copiedDocument;
	}

	private void hidePassword(Document document) {
		Element rootNode = document.getDocumentElement();

		if (rootNode.getNodeName().equals("session")) {
			String[] nodesWithPasswordAttribute = {"user", "profile", "server"};
			NodeList nodes = null;

			for (String each : nodesWithPasswordAttribute) {
				nodes = rootNode.getElementsByTagName(each);
				if (nodes.getLength() == 1) {
					Element element = (Element) nodes.item(0);
					if (element.hasAttribute(FIELD_PASSWORD)) {
						NamedNodeMap attributes = element.getAttributes();
						Node passwordAttribute = attributes.getNamedItem(FIELD_PASSWORD);
						passwordAttribute.setTextContent(VALUE_HIDDEN_PASSWORD);
					}
				}
			}

			nodes = rootNode.getElementsByTagName(NODE_SETTINGS);
			if (nodes.getLength() == 1) {
				Element settingsNode = (Element) nodes.item(0);
				NodeList settingNodeList = settingsNode.getElementsByTagName(NODE_SETTING);
				for (int i = 0; i < settingNodeList.getLength(); i++) {
					Element setting = (Element) settingNodeList.item(i);

					if (! setting.hasAttribute(FIELD_NAME) || ! setting.hasAttribute(FIELD_VALUE))
						continue;
					NamedNodeMap settingAttributes = setting.getAttributes();

					String settingName = settingAttributes.getNamedItem(FIELD_NAME).getTextContent();
					if (settingName.equalsIgnoreCase("aps_access_password")
						|| settingName.equalsIgnoreCase("fs_access_password")) {
						Node passwordAttribute = settingAttributes.getNamedItem(FIELD_VALUE);
						passwordAttribute.setTextContent(VALUE_HIDDEN_PASSWORD);
					}
				}
			}
 		}
	}

	public void addCallbackListener(Callback c) {
		this.callbacks.add(c);
	}

	public void removeCallbackListener(Callback c) {
		this.callbacks.remove(c);
	}

	public Properties getResponseProperties() {
		return this.responseProperties;
	}

	public List<ServerAccess> getServers() {
		return this.servers;
	}

	@Override
	public boolean verify(String hostname, SSLSession session) {
		return true;
	}

	@Override
	public void checkClientTrusted(X509Certificate[] arg0, String arg1)
			throws CertificateException {
		return;
	}

	@Override
	public void checkServerTrusted(X509Certificate[] arg0, String arg1)
			throws CertificateException {
		return;		
	}

	@Override
	public X509Certificate[] getAcceptedIssuers() {
		return null;
	}
}
