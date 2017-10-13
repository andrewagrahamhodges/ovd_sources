/* Base class */

uovd.provider.http.Base = function() { }

uovd.provider.http.Base.prototype.initialize = function() {
	this.session_management = null;
}

uovd.provider.http.Base.prototype.sessionStart = function() {
	var self = this; /* closure */
	this.sessionStart_implementation( function(xml) {
		var error = self.session_management.session.update(xml);
		if(error) {
			self.session_management.fireEvent("ovd.httpProvider.sessionStart", self, {"state":uovd.ERROR, "code":error});
		} else {
			self.session_management.fireEvent("ovd.httpProvider.sessionStart", self, {"state":uovd.SUCCESS});
		}
	});
}

uovd.provider.http.Base.prototype.sessionStatus = function() {
	var self = this; /* closure */
	this.sessionStatus_implementation( function(xml) {
		var error = self.session_management.session.update(xml);
		if(error) {
			self.session_management.fireEvent("ovd.httpProvider.sessionStatus", self, {"state":uovd.ERROR, "code":error});
		} else {
			self.session_management.fireEvent("ovd.httpProvider.sessionStatus", self, {"state":uovd.SUCCESS});
		}
	});
}

uovd.provider.http.Base.prototype.sessionEnd = function() {
	var self = this; /* closure */
	this.sessionEnd_implementation( function(xml) {
		var error = self.session_management.session.update(xml);
		if(error) {
			self.session_management.fireEvent("ovd.httpProvider.sessionEnd", self, {"state":uovd.ERROR, "code":error});
		} else {
			self.session_management.fireEvent("ovd.httpProvider.sessionEnd", self, {"state":uovd.SUCCESS});
		}
	});
}

uovd.provider.http.Base.prototype.sessionSuspend = function() {
	var self = this; /* closure */
	this.sessionSuspend_implementation( function(xml) {
		var error = self.session_management.session.update(xml);
		if(error) {
			self.session_management.fireEvent("ovd.httpProvider.sessionSuspend", self, {"state":uovd.ERROR, "code":error});
		} else {
			self.session_management.fireEvent("ovd.httpProvider.sessionSuspend", self, {"state":uovd.SUCCESS});
		}
	});
}

uovd.provider.http.Base.prototype.build_sessionStart = function(parameters, type) {
	var doc = null;
	try      { doc = document.implementation.createDocument("", "", null); }
	catch(e) { doc = new ActiveXObject("Microsoft.XMLDOM"); }

	/* Session */
	var session_node = doc.createElement("session");
	session_node.setAttribute("mode", parameters["mode"]);
	session_node.setAttribute("language", parameters["language"]);
	session_node.setAttribute("timezone", parameters["timezone"]);
	if("no_desktop" in parameters) {
		session_node.setAttribute("no_desktop", parameters["no_desktop"]);
	}

	/* User */
	var user_node = doc.createElement("user");
	if("token" in parameters) {
		user_node.setAttribute("token", parameters["token"]);
	} else {
		user_node.setAttribute("login", parameters["login"]);
		user_node.setAttribute("password", parameters["password"]);
	}
	
	if (this.session_management.parameters.saml_response != undefined) {
		var saml_node = doc.createElement("saml_ticket");
		saml_node.appendChild(doc.createTextNode(this.session_management.parameters.saml_response));
		user_node.appendChild(saml_node);
	}

	/* Application */
	if("application" in parameters) {
		var start_node = doc.createElement("start");
		var application_node = doc.createElement("application");
		application_node.setAttribute("id", parameters["application"]["id"]);

		if("file_path"     in parameters["application"] &&
		   "file_location" in parameters["application"] &&
		   "file_type"     in parameters["application"] ) {
			application_node.setAttribute("file_path",     parameters["application"]["file_path"]);
			application_node.setAttribute("file_location", parameters["application"]["file_location"]);
			application_node.setAttribute("file_type",     parameters["application"]["file_type"]);
		}

		start_node.appendChild(application_node);
		user_node.appendChild(start_node);
	}

	session_node.appendChild(user_node);
	doc.appendChild(session_node);

	/* Returning */
	switch(type) {
		case "txt" :
			try      { return ((new XMLSerializer()).serializeToString(doc)); }
			catch(e) { return doc.xml; }
		case "xml" : return doc;
		default    : return doc;
	}
}

uovd.provider.http.Base.prototype.build_sessionEnd = function(parameters, type) {
	var doc = null;
	try      { doc = document.implementation.createDocument("", "", null); }
	catch(e) { doc = new ActiveXObject("Microsoft.XMLDOM"); }

	/* Logout */
	var logout_node = doc.createElement("logout");
	logout_node.setAttribute("mode", "logout");

	doc.appendChild(logout_node);

	/* Returning */
	switch(type) {
		case "txt" :
			try      { return ((new XMLSerializer()).serializeToString(doc)); }
			catch(e) { return doc.xml; }
		case "xml" : return doc;
		default    : return doc;
	}
}

uovd.provider.http.Base.prototype.build_sessionSuspend = function(parameters, type) {
	var doc = null;
	try      { doc = document.implementation.createDocument("", "", null); }
	catch(e) { doc = new ActiveXObject("Microsoft.XMLDOM"); }

	/* Logout */
	var logout_node = doc.createElement("logout");
	logout_node.setAttribute("mode", "suspend");

	doc.appendChild(logout_node);

	/* Returning */
	switch(type) {
		case "txt" :
			try      { return ((new XMLSerializer()).serializeToString(doc)); }
			catch(e) { return doc.xml; }
		case "xml" : return doc;
		default    : return doc;
	}
}
