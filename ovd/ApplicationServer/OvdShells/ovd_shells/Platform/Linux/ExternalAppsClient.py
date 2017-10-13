# -*- coding: utf-8 -*-

# Copyright (C) 2011 Ulteo SAS
# http://www.ulteo.com
# Author Julien LANGLOIS <julien@ulteo.com> 2011
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

from ovd_shells.ExternalAppsClient import ExternalAppsClient as AbstractExternalAppsClient
import os

class ExternalAppsClient(AbstractExternalAppsClient):
	@classmethod
	def get_base_command(cls):
		return "OVDExternalAppsClient"
	
	
	@classmethod
	def need_specific_working_directory(cls):
		return False
	
	
	@classmethod
	def get_env(cls):
		my_env = os.environ.copy()
		my_env["LANG"] = "en_US.UTF-8"
		my_env["LC_ALL"] = "en_US.UTF-8"
		my_env["LANGUAGE"] = "en_US.UTF-8"
		return my_env
	
	
	@classmethod
	def get_working_directory(cls):
		return None
