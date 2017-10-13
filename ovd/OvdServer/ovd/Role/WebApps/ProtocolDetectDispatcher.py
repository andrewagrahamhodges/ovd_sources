# -*- coding: utf-8 -*-

# Copyright (C) 2010-2014 Ulteo SAS
# http://www.ulteo.com
# Author Arnaud Legrand <arnaud@ulteo.com> 2010
# Author Samuel BOVEE <samuel@ulteo.com> 2010-2011
# Author Julien LANGLOIS <julien@ulteo.com> 2011
# Author David LECHEVALIER <david@ulteo.com> 2012
# Author Ania WSZEBOROWSKA <anna.wszeborowska@stxnext.pl> 2013
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

import re

from Communicator import HttpClientCommunicator, HttpsClientCommunicator, SSLCommunicator, Communicator
from Config import Config
from ovd.Logger import Logger

from OpenSSL import SSL
import time



class ProtocolException(Exception):
	pass


class HttpProtocolDetectDispatcher(Communicator):
	http_ptn = re.compile('((?:HEAD)|(?:GET)|(?:POST)) (.*) HTTP/(.\..)')
	
	def __init__(self, conn, f_ctrl):
		Communicator.__init__(self, conn)
		self.f_ctrl = f_ctrl
		self.lastPacketTime = time.time()
	
	
	def writable(self):
		# This class doesn't have to write anything,
		# It's just use to detect the protocol
		return False
	
	def readable(self):
		if time.time() - self.lastPacketTime > Config.connection_timeout:
			Logger.error("HttpProtocolDetectDispatcher::connection timeout")
			self.handle_close()
			return False
		
		return True
	
	def handle_read(self):
		try:
			if Communicator.handle_read(self) is -1:
				return
		except Exception, e:
			raise
			# TODO: check exceptions that could be raised and handle them

			# empty connection opened (chrome for example)
			#if e.args[0][0][1] in ['SSL23_READ', 'SSL3_READ_BYTES']:
			#	self.handle_close()
			#	return
			#else:
			#	raise
		
		self.lastPacketTime = time.time()
		request = self._buffer.split('\n', 1)[0]
		request = request.rstrip('\n\r').decode("utf-8", "replace")
		
		# find protocol
		http = self.http_ptn.match(request)
		try:
			if http:
				client = HttpClientCommunicator(self.socket)
				client._buffer = self._buffer
				if client.make_http_message() is not None:
					client._buffer = client.process()
			
			# protocol error
			else:
				# Check if the packet size is larger than a common HTTP first line
				if len(self._buffer) > Config.http_max_header_size:
					raise ProtocolException('bad first request line: ' + request)
				return
		
		except ProtocolException, err:
			Logger.error("HttpProtocolDetectDispatcher::handle_read: %s" % repr(err))
			self.handle_close()


class ProtocolDetectDispatcher(SSLCommunicator):
	http_ptn = re.compile('((?:HEAD)|(?:GET)|(?:POST)) (.*) HTTP/(.\..)')
	
	def __init__(self, conn, f_ctrl, ssl_ctx):
		SSLCommunicator.__init__(self, conn)
		self.ssl_ctx = ssl_ctx
		self.f_ctrl = f_ctrl
		self.lastPacketTime = time.time()
	
	
	def writable(self):
		# This class doesn't have to write anything,
		# It's just use to detect the protocol
		return False
	
	def readable(self):
		if time.time() - self.lastPacketTime > Config.connection_timeout:
			Logger.error("ProtocolDetectDispatcher::connection timeout")
			self.handle_close()
			return False
		
		return True
	
	def handle_read(self):
		try:
			if SSLCommunicator.handle_read(self) is -1:
				return
		except SSL.Error, e:
			# empty connection opened (chrome for example)
			if e.args[0][0][1] in ['SSL23_READ', 'SSL3_READ_BYTES']:
				self.handle_close()
				return
			else:
				raise
		
		self.lastPacketTime = time.time()
		request = self._buffer.split('\n', 1)[0]
		request = request.rstrip('\n\r').decode("utf-8", "replace")
		
		# find protocol
		http = ProtocolDetectDispatcher.http_ptn.match(request)
		try:
			if http:
				client = HttpsClientCommunicator(self.socket, self.f_ctrl, self.ssl_ctx)
				client._buffer = self._buffer
				if client.make_http_message() is not None:
					client._buffer = client.process()
			
			# protocol error
			else:
				# Check if the packet size is larger than a common HTTP first line
				if len(self._buffer) > Config.http_max_header_size:
					raise ProtocolException('bad first request line: ' + request)
				return
		
		except ProtocolException:
			Logger.exception("ProtocolDetectDispatcher::handle_read")
			self.handle_close()
