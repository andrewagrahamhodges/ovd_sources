# -*- coding: utf-8 -*-

# Copyright (C) 2010-2014 Ulteo SAS
# http://www.ulteo.com
# Author Laurent CLOUET <laurent@ulteo.com> 2011
# Author Julien LANGLOIS <julien@ulteo.com> 2010, 2011
# Author David PHAM-VAN <d.pham-van@ulteo.com> 2014
#
# This program is free software; you can redistribute it and/or 
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; version 2
# of the License
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

import httplib
import socket
import urllib2
from xml.dom import minidom
from xml.dom.minidom import Document

from ovd.Config import Config
from ovd.Logger import Logger


class SMRequestManager():
	STATUS_PENDING = "pending"
	STATUS_READY = "ready"
	STATUS_DOWN = "down"
	STATUS_BROKEN = "broken"
	
	def __init__(self):
		self.url = None
		self.host = Config.session_manager
		self.port = Config.SM_SERVER_PORT
		self.name = None
	
	
	def initialize(self):
		self.perform_dns_request()
		
		node = self.send_server_name()
		if node is None:
			raise Exception("invalid response")
		
		if not node.hasAttribute("name"):
			raise Exception("invalid response")
		
		self.name = node.getAttribute("name")
	
	
	def perform_dns_request(self):
		try:
			buf = socket.getaddrinfo(self.host, self.port, socket.AF_INET, 0, socket.SOL_TCP)
		except socket.gaierror, err:
			raise Exception("Unable to resolv %s in IPv4 address"%(self.host))
		if len(buf)==0:
			raise Exception("Unable to resolv %s in IPv4 address"%(self.host))
		
		(addr,port) = buf[0][4]
		self.url = "http://%s:%d"%(addr, port)
	
	
	def switch_status(self, status):
		if self.name is None:
			return False
		
		return self.send_server_status(status)
	
	
	@staticmethod
	def get_response_xml(stream):
		if not stream.headers.has_key("Content-Type"):
			return None
		
		contentType = stream.headers["Content-Type"].split(";")[0]
		if not contentType == "text/xml":
			Logger.error("content type: %s"%(contentType))
			print stream.read()
			return None
		
		try:
			document = minidom.parseString(stream.read())
		except:
			Logger.warn("No response XML")
			return None
		
		return document
	
	
	def send_server_name(self):
		response = self.send_packet("/server/name")
		if response is False:
			Logger.warn("SMRequest::send_server_status Unable to send packet")
			return None
		
		document = self.get_response_xml(response)
		if document is None:
			Logger.warn("SMRequest:send_server_name not XML response")
			return None
		
		rootNode = document.documentElement
		
		if rootNode.nodeName != "server":
			return None
		
		return rootNode
	
	
	def send_packet(self, path, document = None):
		if self.url is None:
			self.perform_dns_request()
		
		url = "%s%s"%(self.url, path)
		Logger.debug("SMRequest::send_packet url %s"%(url))
		
		req = urllib2.Request(url)
		req.add_header("Host", "%s:%s"%(self.host, self.port))
		
		if document is not None:
			rootNode = document.documentElement
			rootNode.setAttribute("name", str(self.name))
			req.add_header("Content-type", "text/xml; charset=UTF-8")
			req.add_data(document.toxml("UTF-8"))
		
		try:
			stream = urllib2.urlopen(req)
		except IOError:
			Logger.exception("SMRequest::send_packet path: "+path)
			return False
		except httplib.BadStatusLine:
			Logger.exception("SMRequest::send_packet path: "+path+" not receive HTTP response")
			return False
		
		return stream
	
	
	def send_server_status(self, status):
		doc = Document()
		rootNode = doc.createElement('server')
		rootNode.setAttribute("status", status)
		doc.appendChild(rootNode)
		response = self.send_packet("/server/status", doc)
		if response is False:
			Logger.warn("SMRequest::send_server_status Unable to send packet")
			return False
		
		document = self.get_response_xml(response)
		if document is None:
			Logger.warn("SMRequest::send_server_status response not XML")
			return False
		
		rootNode = document.documentElement
		
		if rootNode.nodeName != "server":
			Logger.error("SMRequest::send_server_status response not valid %s"%(rootNode.toxml()))
			return False
		
		if not rootNode.hasAttribute("name") or rootNode.getAttribute("name") != self.name:
			Logger.error("SMRequest::send_server_status response invalid name")
			return False
		
		if not rootNode.hasAttribute("status") or rootNode.getAttribute("status") != status:
			Logger.error("SMRequest::send_server_status response invalid status")
			return False
		
		return True
	
	
	def send_server_monitoring(self, doc):
		response = self.send_packet("/server/monitoring", doc)
		if response is False:
			return False
		
		document = self.get_response_xml(response)
		if document is None:
			Logger.warn("SMRequest::send_server_monitoring response not XML")
			return False
		
		rootNode = document.documentElement
		if rootNode.nodeName != "server":
			Logger.error("SMRequest::send_server_monitoring response not valid %s"%(rootNode.toxml()))
			return False
		
		if not rootNode.hasAttribute("name") or rootNode.getAttribute("name") != self.name:
			Logger.error("SMRequest::send_server_monitoring response invalid name")
			return False
		
		return True
