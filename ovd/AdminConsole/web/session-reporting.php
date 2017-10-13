<?php
/**
* Copyright (C) 2010-2014 Ulteo SAS
* http://www.ulteo.com
* Author Julien LANGLOIS <julien@ulteo.com> 2010, 2012, 2013
* Author David PHAM-VAN <d.pham-van@ulteo.com> 2012, 2014
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
**/


require_once(dirname(dirname(__FILE__)).'/includes/core.inc.php');
require_once(dirname(dirname(__FILE__)).'/includes/page_template.php');


if (! checkAuthorization('viewStatus'))
	redirect('index.php');


if (isset($_REQUEST['action'])) {
	if ($_REQUEST['action']=='manage') {
		if (isset($_REQUEST['id']))
			show_manage($_REQUEST['id']);
	}
	if ($_REQUEST['action']=='pdf') {
		if (isset($_REQUEST['id']))
			show_pdf($_REQUEST['id']);
	}
}

show_default();


function show_default() {
	$search_by_user = false;
	$search_by_time = false;
	$user = '';
	$t1 = time();
	$t0 = strtotime('-1 Month', $t1);
	
	$search_limit = $_SESSION['configuration']['max_items_per_page'];
	
	if (isset($_REQUEST['search_by']) && is_array($_REQUEST['search_by'])) {
		if (in_array('user', $_REQUEST['search_by'])) {
			$search_by_user = true;
			if (isset($_REQUEST['user']))
				$user = $_REQUEST['user'];
		}
		else
			$search_by_user = false;
		if (in_array('time', $_REQUEST['search_by'])) {
			$search_by_time = true;
			if (isset($_REQUEST['from']))
				$t0 = $_REQUEST['from'];
			if (isset($_REQUEST['to']))
				$t1 = $_REQUEST['to'];
		}
		else
			$search_by_time = false;
	}
	
	$sessions = $_SESSION['service']->sessions_reports_list3(($search_by_time?$t0:null), ($search_by_time?$t1:null), ($search_by_user?$user:null), $search_limit+1);
	if (is_null($sessions)) {
		$sessions = array();
	}
	
	$partial_result = false;
	if (count($sessions) > $search_limit) {
		$partial_result = true;
		array_pop($sessions);
	}

	page_header(array('js_files' => array('media/script/lib/calendarpopup/CalendarPopup.js')));

	echo '<script type="text/javascript" charset="utf-8">';
	echo '  function toggle_login(state) {';
	echo '    if (state == true) {';
	echo '      $(\'by_login_1\').show();';
	echo '      $(\'by_login_2\').show();';
	echo '    } else {';
	echo '      $(\'by_login_1\').hide();';
	echo '      $(\'by_login_2\').hide();';
	echo '    }';
	echo '  };';
	echo '  function toggle_time(state) {';
	echo '    if (state == true) {';
	echo '      $(\'by_time_1\').show();';
	echo '      $(\'by_time_2\').show();';
	echo '    } else {';
	echo '      $(\'by_time_1\').hide();';
	echo '      $(\'by_time_2\').hide();';
	echo '    }';
	echo '  };';
	echo '  Event.observe(window, \'load\', function() {';
	echo '    toggle_login('.($search_by_user?'true':'false').');';
	echo '    toggle_time('.($search_by_time?'true':'false').');';
	echo '  });';
	echo '</script>';



	echo '<h1>'._('Session Reporting').'</h1>';
	
	
	echo '<div style="margin-bottom: 15px;">';
	echo '<form action="" method="GET">';
	echo '<table>';
	echo '<tr><td colspan="5">'._('Search for archived sessions :').'</td></tr>';
	echo '<tr><td style="padding-left: 15px;">&nbsp;</td><td><input type="checkbox" name="search_by[]" value="user" onchange="toggle_login(this.checked);"';
	if ($search_by_user === true)
		echo ' checked="checked"';
	echo '/>'._('By user').'</td></tr>';
	echo '<tr id="by_login_1"><td></td><td></td><td colspan="3"><input type="text" name="user" value="'.$user.'" /></td></tr>';
	echo '<tr id="by_login_2"><td></td><td></td><td colspan="3"><em>'._('user login').'</em></td></tr>';
	
	echo '<tr><td></td><td><input type="checkbox" name="search_by[]" value="time" onchange="toggle_time(this.checked);"';
	if ($search_by_time === true)
		echo ' checked="checked"';
	echo '"/>'._('By time').'</td></tr>';
	echo '<tr id="by_time_1"><td></td><td></td><td><strong>'._('From').'</strong></td>';
	echo '<td>';
	echo '<a href="#" id="anchor_day_from" onclick="calendar_day_from.select($(\'from\'), \'anchor_day_from\'); return false;" >'.date('Y-m-d', $t0).'</a>';
	echo '&nbsp;<select id="hour_from" onchange="calendars_update();">';
	for ($i = 0; $i < 24; $i++)
		echo '<option value="'.$i.'">'.(($i<10)?'0':'').$i.':00</option>';
	echo '</select>';
	echo '<div id="calendar_day_from" style="position: absolute; visibility: hidden; background: white;"></div>';
	echo '</td>';
	echo '</tr>';
	echo '<tr id="by_time_2"><td></td><td></td><td><strong>'._('To').'</strong></td>';
	echo '<td>';
	echo '<a href="#" id="anchor_day_to" onclick="calendar_day_to.select($(\'to\'), \'anchor_day_to\'); return false;" >'.date('Y-m-d', $t1).'</a>';
	echo '&nbsp;<select id="hour_to" onchange="calendars_update();">';
	for ($i = 0; $i < 24; $i++)
		echo '<option value="'.$i.'">'.(($i<10)?'0':'').$i.':00</option>';
	echo '</select>';
	echo '<div id="calendar_day_to" style="position: absolute; visibility: hidden; background: white;"></div>';
	echo '</td>';
	echo '</tr>';
	
	echo '<tr><td></td><td></td><td><input type="submit" value="'._('Search').'" /><td><td>';
	if (count($sessions)>0)
		echo sprintf(ngettext('<strong>%d</strong> result.', '<strong>%d</strong> results.', count($sessions)), count($sessions));
	else
		echo '<span class="error"><strong>'._('No result found!').'</strong></span>';
	
	echo '</td></tr>';
	if ($partial_result === true) {
		echo '<tr><td></td>';
		echo '<td colspan="5">';
		echo '<span class="error">';
		echo sprintf(ngettext("<strong>Partial content:</strong> Only <strong>%d result</strong> displayed but there are more. Please restrict your search field.", "<strong>Partial content:</strong> Only <strong>%d results</strong> displayed but there are more. Please restrict your search field.", $search_limit), $search_limit);
		echo '</span>';
		echo '</td></tr>';
	}
 	
	echo '</table>';
	
	echo '<input id="from" name="from" value="'.$t0.'" type="hidden" />';
	echo '<input id="to" name="to" value="'.$t1.'" type="hidden" />';
	echo '</form>';
	echo '</div>';

	echo '<script type="text/javascript" charset="utf-8">';
	echo '  document.write(getCalendarStyles());';
	echo '  var calendar_day_from = new CalendarPopup("calendar_day_from");';
	echo '  var calendar_day_to   = new CalendarPopup("calendar_day_to");';
	
	echo '  function calendars_init() {';
	echo '    calendar_day_from.setReturnFunction("calendars_callback_from");';
	echo '    calendar_day_to.setReturnFunction("calendars_callback");';
	
	echo '    var from_date = new Date();';
	echo '    from_date.setTime($("from").value*1000);';
	echo '    calendar_day_from.currentDate = from_date;';
	echo '    rewrite_date(from_date, $(\'anchor_day_from\'), $(\'hour_from\'));';
	
	echo '    var to_date = new Date();';
	echo '    to_date.setTime($("to").value*1000);';
	echo '    calendar_day_to.currentDate = to_date;';
 	echo '    rewrite_date(to_date, $(\'anchor_day_to\'), $(\'hour_to\'));';
	echo '  }';
	
	echo '  function calendars_callback_from(y, m, d) {';
	echo '    calendar_day_from.currentDate.setFullYear(y);';
	echo '    calendar_day_from.currentDate.setMonth(m-1);';
	echo '    calendar_day_from.currentDate.setDate(d);';
	echo '    calendars_update();';
	echo '  }';
	
	echo '  function calendars_callback_to(y, m, d) {';
	echo '    calendar_day_to.currentDate.setFullYear(y);';
	echo '    calendar_day_to.currentDate.setMonth(m-1);';
	echo '    calendar_day_to.currentDate.setDate(d);';
	echo '    calendars_update();';
	echo '  }';
	
	echo '  function calendars_update() {';
	echo '    var from_date = calendar_day_from.currentDate;';
	echo '    var from_hour = $(\'hour_from\').options[$(\'hour_from\').selectedIndex].value;';
	echo '    from_date.setHours(from_hour);';
	echo '    rewrite_date(from_date, $(\'anchor_day_from\'), $(\'hour_from\'));';
	echo '    $("from").value = from_date.getTime()/1000;';
	
	echo '    var to_date = calendar_day_to.currentDate;';
	echo '    var to_hour = $(\'hour_to\').options[$(\'hour_to\').selectedIndex].value;';
	echo '    to_date.setHours(to_hour);';
	echo '    rewrite_date(to_date, $(\'anchor_day_to\'), $(\'hour_to\'));';
 	echo '    $("to").value = to_date.getTime()/1000;';
	echo '  }';
	
	echo '  function rewrite_date(date, ymd_node, hour_select_node) {';
	echo '    var buf = date.getFullYear()+"-";';
	echo '    if (date.getMonth()+1 < 10)';
	echo '      buf+= "0";';
	echo '    buf+=date.getMonth()+1+"-";';
	echo '    if (date.getDate() < 10)';
	echo '      buf+= "0";';
	echo '    buf+=date.getDate(); ';
	echo '    ymd_node.innerHTML = buf;';
	echo '';
	echo '    for(var i=0; i<hour_select_node.options.length; i++) {';
	echo '      if (hour_select_node.options[i].value == date.getHours())';
	echo '        hour_select_node.selectedIndex = i;';
	echo '    }';
	echo '  }';

	echo '  Event.observe(window, \'load\', function() {';
	echo '    calendars_init();';
	echo '  });';
	
	echo '</script>';
	
	if (count($sessions) > 0) {
		echo '<table class="main_sub sortable" id="main_table" border="0" cellspacing="1" cellpadding="5">';
		echo '<thead>';
		echo '<tr class="title">';
		if (isAuthorized('manageReporting') && count($sessions)>1)
			echo '<th class="unsortable"></th>';
		echo '<th>'._('Session id').'</th>';
		echo '<th>'._('User').'</th>';
		echo '<th>'._('Date').'</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';
		
		$count = 0;
		foreach($sessions as $session) {
			$content = 'content'.(($count++%2==0)?1:2);
			echo '<tr class="'.$content.'">';
			
			if (isAuthorized('manageReporting') && count($sessions)>1) {
				echo '<td>';
				echo '<input class="input_checkbox" type="checkbox" name="sessions[]" value="'.$session->getId().'" />';
				echo '</td>';
			}
			
			echo '<td><a title="'._('Get more information').'" href="?action=manage&id='.$session->getId().'">'.$session->getId().'</a></td>';
			echo '<td><a href="users.php?action=manage&id='.$session->getUser().'">'.$session->getUser().'</a></td>';
			echo '<td>'.$session->getStartTime().'</td>';
			echo '<td><form><input type="hidden" name="action" value="manage"/><input type="hidden" name="id" value="'.$session->getId().'"/><input type="submit" value="'._('Get more information').'"/></form></td>';
			
			if (isAuthorized('manageReporting')) {
				echo '<td>';
				echo '<form method="post" action="actions.php" onsubmit="return confirm(\''._('Are you sure you want to delete this archived session?').'\');">';
				echo '<input type="hidden" name="name" value="SessionReporting"/>';
				echo '<input type="hidden" name="action" value="delete"/>';
				echo '<input type="hidden" name="session" value="'.$session->getId().'"/>';
				echo '<input type="submit" value="'._('Delete').'"/>';
				echo '</form>';
				echo '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody>';
		
		if (isAuthorized('manageReporting') && count($sessions)>1) {
			$content = 'content'.(($count++%2==0)?1:2);
			echo '<tfoot>';
			echo '<tr class="'.$content.'">';
			echo '<td colspan="5">';
			echo '<a href="javascript:;" onclick="markAllRows(\'main_table\'); return false">'._('Mark all').'</a>';
			echo ' / <a href="javascript:;" onclick="unMarkAllRows(\'main_table\'); return false">'._('Unmark all').'</a>';
			echo '</td>';
			echo '<td>';
			echo '<form action="actions.php" method="post" onsubmit="updateMassActionsForm(this, \'main_table\'); return confirm(\''._('Are you sure you want to delete these archived sessions?').'\');"  ">';
			echo '<input type="hidden" name="name" value="SessionReporting"/>';
			echo '<input type="hidden" name="action" value="delete"/>';
			echo '<input type="submit" value="'._('Delete').'"/>';
			echo '</form>';
			echo '</td>';
			echo '</tr>';
			echo '</tfoot>';
		}
		echo '</table>';
	}
	page_footer();
	die();
}


function show_manage($id_) {
	$session = $_SESSION['service']->session_report_info($id_);
	if (! $session) {
		popup_error(sprintf(_('Unknown session %s'), $id_));
		return false;
	}

	$user = $_SESSION['service']->user_info($session->getUser());
	
	$mode = '<em>'._('unknown').'</em>';
	$servers = array();
	$published_applications = array();
	$applications_instances = array();
	$storages = array();
	
	$dom = new DomDocument('1.0', 'utf-8');
	$ret = @$dom->loadXML($session->getData());
	if ($ret) {
		$root_node = $dom->documentElement;
		if ($root_node->hasAttribute('mode'))
			$mode = $root_node->getAttribute('mode');
		
		foreach ($dom->getElementsByTagName('server') as $node) {
			if (! ($node->hasAttribute('id') &&
				$node->hasAttribute('role')
				)) {
				// Not enough information to continue ...
				continue;
			}
			
			$server = array();
			$server['id'] = $node->getAttribute('id');
			if ($node->hasAttribute('fqdn')) {
				$server['fqdn'] = $node->getAttribute('fqdn');
				$server['name'] = $server['fqdn'];
			}
			else
				$server['name'] = $server['id'];
			$server['role'] = $node->getAttribute('role');
			$server['desktop_server'] = ($node->hasAttribute('desktop_server'));
			if ($node->hasAttribute('type'))
				$server['type'] = $node->getAttribute('type');
			
			$server['dump'] = array();
			foreach($node->childNodes as $child_node) {
				if ($child_node->nodeName != 'dump')
					continue;
				
				if (! $child_node->hasAttribute('name'))
					continue;
				
				$name = $child_node->getAttribute('name');
				$server['dump'][$name] = base64_decode ($child_node->textContent);
			}
			
			$server_obj = $_SESSION['service']->server_info($server['id']);
			if (is_object($server_obj))
				$server['obj'] = $server_obj;
			
			$servers[]= $server; // can be the same server twice with different role ... ToDO: fix that on backend side
		}
		
		foreach ($dom->getElementsByTagName('storage') as $node) {
			$s = nodeattrs2array($node);
			$storages[$s['rid']] = $s;
		}
		
		foreach ($dom->getElementsByTagName('application') as $node) {
			if (! $node->hasAttribute('id')) {
				// Not enough information to continue ...
				continue;
			}
			
			$application = array('id' => $node->getAttribute('id'));
			if ($node->hasAttribute('name'))
				$application['name'] = $node->getAttribute('name');
			
			$app_buf = $_SESSION['service']->application_info($application['id']);
			
			if (is_object($app_buf))
				$application['obj'] = $app_buf;
			
			$published_applications[$application['id']]= $application;
		}
		
		foreach ($dom->getElementsByTagName('instance') as $node) {
			if (! ($node->hasAttribute('id') &&
				$node->hasAttribute('application') &&
				$node->hasAttribute('server') &&
				$node->hasAttribute('start') &&
				$node->hasAttribute('stop')
				)) {
				// Not enough information to continue ...
				continue;
			}
			
			$instance = array('id' => $node->getAttribute('id'));
			$instance['application'] = $node->getAttribute('application');
			$instance['server'] = $node->getAttribute('server');
			$instance['start'] = $node->getAttribute('start');
			$instance['stop'] = $node->getAttribute('stop');
			
			if (! array_key_exists($instance['application'], $published_applications))
				continue;
			
			$applications_instances[]= $instance;
		}
	}
	
	
	page_header();

	echo '<h1><a title="'._('Back to archived session list').'" href="?">'._('Archived Session').'</a> - '.$session->getId();
	echo ' <a href="?action=pdf&amp;id='.$session->getId().'"><img src="media/image/download.png" width="22" height="22" alt="download" onmouseover="showInfoBulle(\''._('Export as PDF file').'\'); return false;" onmouseout="hideInfoBulle(); return false;" /></a>';
	echo '</h1>';

	echo '<ul>';
	echo '<li><strong>'._('User:').'</strong> ';
	if (is_object($user))
		echo '<a href="users.php?action=manage&id='.$user->getAttribute('login').'">'.$user->getAttribute('displayname').'</a>';
	else
		echo $session->getUser().' <span><em>'._('Does not exist').'</em></span>';
	echo '</li>';
	
	echo '<li><strong>'._('Mode:').'</strong> '.$mode.'</li>';
	
	echo '<li><strong>'._('Started:').'</strong> ';
	echo $session->getStartTime();
	echo '</li>';
	echo '<li><strong>'._('Stopped:').'</strong> ';
	echo $session->getStopTime();
	if (! is_null($session->getStopWhy()) && strlen($session->getStopWhy())>0)
		echo '&nbsp<em>('.$session->getStopWhy().')</em>';
	echo '</li>';
	echo '</ul>';
	
	if (isAuthorized('manageReporting')) {
		echo '<form method="post" action="actions.php" onsubmit="return confirm(\''._('Are you sure you want to delete this archived session?').'\');">';
		echo '<input type="hidden" name="name" value="SessionReporting"/>';
		echo '<input type="hidden" name="action" value="delete"/>';
		echo '<input type="hidden" name="session" value="'.$session->getId().'"/>';
		echo '<input type="submit" value="'._('Delete').'"/>';
		echo '</form>';
	}
	
	echo '<div>';
	echo '<h2>'._('Servers').'</h2>';
	if (count($servers) == 0)
		echo _('No information available');
	else {
		echo '<ul>';
		foreach ($servers as $server) {
			echo '<li>';
			if (array_key_exists('obj', $server))
				echo '<a href="servers.php?action=manage&id='.$server['obj']->id.'">'.$server['obj']->getDisplayName().'</a>';
			else {
				echo $server['name'];
				
				
				echo '&nbsp;<span><em>'._('does not exist').'</em></span>';
			}
			
			$infos = array();
			$infos[]= 'role: '.$server['role'];
			
			if ($mode == Session::MODE_DESKTOP && $server['desktop_server'])
				$infos[]= 'desktop server';
			
			if (array_key_exists('type', $server))
				$infos[]= '(OS: '.$server['type'].')';
			
			echo '&nbsp;'.implode(', ', $infos);
			if (count($server['dump']) > 0) {
				echo '<div style="margin-left: 20px;">';
				echo '<ul>';
				foreach ($server['dump'] as $name => $dump) {
					$elem_id = $server['id'].$name;
					$nb_lines = substr_count($dump, "\n");
					if ($nb_lines > 10)
						$nb_lines = 10;
					
					echo '<li>'.$name.'&nbsp;';
					echo '<em>(';
					echo '<a href="" id="'.$elem_id.'_show" title="'._('Show the dump data').'" onclick="$(\''.$elem_id.'_text\').show(); $(\''.$elem_id.'_hide\').show(); this.hide(); return false;">'._('show').'</a>';
					echo '<a href="" id="'.$elem_id.'_hide" title="'._('Hide the dump data').'" onclick="$(\''.$elem_id.'_text\').hide(); $(\''.$elem_id.'_show\').show(); this.hide(); return false;" style="display: none;" >'._('hide').'</a>';
					echo ')</em><br/>';
					echo '<textarea id="'.$elem_id.'_text" style="display: none; margin-left: 20px;" cols="100" rows="'.$nb_lines.'">'.$dump.'</textarea>';
					echo '</li>';
				}
				echo '</ul>';
				echo '</div>';
			}
			
			echo '</li>';
		}
		echo '</ul>';
	}
	echo '</div>';
	
	echo '<div>';
	echo '<h2>'._('Published Applications').'</h2>';
	if (count($published_applications) == 0)
		echo _('No information available');
	else {
		echo '<ul>';
		foreach ($published_applications as $app_id => $application) {
			echo '<li>';
			if (isset($application['obj'])) {
				echo '<img class="icon32" src="media/image/cache.php?id='.$application['obj']->getAttribute('id').'" alt="" title="" /> ';
				echo '<a href="applications.php?action=manage&id='.$application['obj']->getAttribute('id').'">'.$application['obj']->getAttribute('name').'</a>';
			}
			else {
				if (array_key_exists('name', $application))
					echo '<span>'.$application['name'].'</span>';
				else
					echo '<span>'.sprintf(_('Unknown application (id: %s)'), $application['id']).'</span>';
				
				echo '&nbsp;<span><em>'._('does not exist').'</em></span>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
	echo '</div>';
	
	echo '<div>';
	echo '<h2>'._('Used Applications').'</h2>';
	if (count($applications_instances) == 0)
		echo _('No information available');
	else {
		echo '<ul>';
		foreach ($applications_instances as $instance) {
			$application = $published_applications[$instance['application']];
			
			echo '<li>';
			
			if (array_key_exists('obj', $application)) {
				echo '<img class="icon32" src="media/image/cache.php?id='.$application['obj']->getAttribute('id').'" alt="" title="" /> ';
				echo '<a href="applications.php?action=manage&id='.$application['obj']->getAttribute('id').'">'.$application['obj']->getAttribute('name').'</a>';
			}
			else {
				if (array_key_exists('name', $application))
					echo '<span>'.$application['name'].'</span>';
				else
					echo '<span>'.sprintf(_('Unknown application (id: %s)'), $application['id']).'</span>';
				
				echo '&nbsp;<span><em>'._('does not exist').'</em></span>';
			}
			
			echo ' - ';
			$duration = sprintf('%0.2f', (($instance['stop']-$instance['start'])/60));
			echo str_replace(array('%SERVER%', '%DURATION%', '%STARTED_TIME%', '%STOPPED_TIME%'), 
				  array($instance['server'], $duration, strftime('%T', $instance['start']), strftime('%T', $instance['stop'])),
				  _('executed on server %SERVER% during %DURATION%m (started at %STARTED_TIME%, stopped at %STOPPED_TIME%)'));
			
			echo '</li>';
		}
		
		echo '</ul>';
	}
	
	echo '</div>';
	
	echo '<div>';
	echo '<h2>'._('Storage').'</h2>';
	if (count($storages) == 0)
		echo _('No information available');
	else {
		echo '<ul>';
		foreach ($storages as $storage_id => $storage) {
			if ($storage['type'] != 'profile') {
				continue;
			}
				
			echo '<li>'._('User profile').' '._('on server: ').$storage['server_name'].' ('.$storage['server_id'].')'.'</li>';
		}
			
		foreach ($storages as $storage_id => $storage) {
			if ($storage['type'] == 'profile') {
				continue;
			}
			
			echo '<li>'._('Shared folder').' - '.$storage['name'].' <em>('.$storage['mode'].')</em> '._('on server: ').$storage['server_name'].' ('.$storage['server_id'].')</li>';
		}
		
		echo '</ul>';
	}
	
	echo '</div>';
	
	page_footer();
	die();
}


function show_pdf($id_) {
	define('K_PATH_IMAGES', dirname(__FILE__).'/media/image/');
	define('PDF_HEADER_LOGO', 'header.png');
 	define('PDF_HEADER_LOGO_WIDTH', 20);
	require_once(dirname(dirname(__FILE__)).'/includes/tcpdf/tcpdf.php');
	
	$html = get_html($id_);
	
	// create new PDF document
	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

	// set document information
	$pdf->SetCreator(PDF_CREATOR);
	$pdf->SetAuthor('Ulteo OVD Administration Console '.OVD_VERSION);
	$pdf->SetTitle('Archived session - '.$id_);
	$pdf->SetSubject('Archived session - '.$id_);
	$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, 'Archived session - '.$id_, 'Ulteo OVD Administration Console '.OVD_VERSION);

	// set header and footer fonts
	$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
	$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

	// set default monospaced font
	$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

	// set margins
	$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
	$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
	$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

	// set auto page breaks
	$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

	// set image scale factor
	$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

	$pdf->AddPage();
	$pdf->writeHTML($html, true, false, true, false, '');
	$pdf->lastPage();
	$pdf->Output('Ulteo-OVD-Archived-session-'.$id_.'.pdf', 'D');
	die();
}

function get_html($id_) {
	$session = $_SESSION['service']->session_report_info($id_);
	if (! $session) {
		popup_error(sprintf(_('Unknown session %s'), $id_));
		return false;
	}

	$user = $_SESSION['service']->user_info($session->getUser());
	
	$mode = '<em>'._('unknown').'</em>';
	$servers = array();
	$published_applications = array();
	$applications_instances = array();
	$storages = array();
	
	$dom = new DomDocument('1.0', 'utf-8');
	$ret = @$dom->loadXML($session->getData());
	if ($ret) {
		$root_node = $dom->documentElement;
		if ($root_node->hasAttribute('mode'))
			$mode = $root_node->getAttribute('mode');
		
		foreach ($dom->getElementsByTagName('server') as $node) {
			if (! ($node->hasAttribute('id') &&
				$node->hasAttribute('role')
				)) {
				// Not enough information to continue ...
				continue;
			}
			
			$server = array();
			$server['id'] = $node->getAttribute('id');
			if ($node->hasAttribute('fqdn')) {
				$server['fqdn'] = $node->getAttribute('fqdn');
				$server['name'] = $server['fqdn'];
			}
			else
				$server['name'] = $server['id'];
			$server['role'] = $node->getAttribute('role');
			$server['desktop_server'] = ($node->hasAttribute('desktop_server'));
			if ($node->hasAttribute('type'))
				$server['type'] = $node->getAttribute('type');
			
			$server['dump'] = array();
			foreach($node->childNodes as $child_node) {
				if ($child_node->nodeName != 'dump')
					continue;
				
				if (! $child_node->hasAttribute('name'))
					continue;
				
				$name = $child_node->getAttribute('name');
				$server['dump'][$name] = base64_decode ($child_node->textContent);
			}
			
			$server_obj = $_SESSION['service']->server_info($server['id']);
			if (is_object($server_obj))
				$server['obj'] = $server_obj;
			
			$servers[]= $server; // can be the same server twice with different role ... ToDO: fix that on backend side
		}
		
		foreach ($dom->getElementsByTagName('storage') as $node) {
			$s = nodeattrs2array($node);
			$storages[$s['rid']] = $s;
		}
		
		foreach ($dom->getElementsByTagName('application') as $node) {
			if (! $node->hasAttribute('id')) {
				// Not enough information to continue ...
				continue;
			}
			
			$application = array('id' => $node->getAttribute('id'));
			if ($node->hasAttribute('name'))
				$application['name'] = $node->getAttribute('name');
			
			$app_buf = $_SESSION['service']->application_info($application['id']);
			
			if (is_object($app_buf))
				$application['obj'] = $app_buf;
			
			$published_applications[$application['id']]= $application;
		}
		
		foreach ($dom->getElementsByTagName('instance') as $node) {
			if (! ($node->hasAttribute('id') &&
				$node->hasAttribute('application') &&
				$node->hasAttribute('server') &&
				$node->hasAttribute('start') &&
				$node->hasAttribute('stop')
				)) {
				// Not enough information to continue ...
				continue;
			}
			
			$instance = array('id' => $node->getAttribute('id'));
			$instance['application'] = $node->getAttribute('application');
			$instance['server'] = $node->getAttribute('server');
			$instance['start'] = $node->getAttribute('start');
			$instance['stop'] = $node->getAttribute('stop');
			
			if (! array_key_exists($instance['application'], $published_applications))
				continue;
			
			$applications_instances[]= $instance;
		}
	}
	
	
	$ret = '';
	$ret.= '<ul>';
	$ret.= '<li><strong>User:</strong> ';
	if (is_object($user))
		$ret.= ''.$user->getAttribute('displayname').' (login: '.$user->getAttribute('login').')';
	else
		$ret.= $session->getUser().' <span><em>'._('does not exist').'</em></span>';
	$ret.= '</li>';
	
	$ret.= '<li><strong>Mode:</strong> '.$mode.'</li>';
	
	$ret.= '<li><strong>Started:</strong> ';
	$ret.= $session->getStartTime();
	$ret.= '</li>';
	$ret.= '<li><strong>Stopped:</strong> ';
	$ret.= $session->getStopTime();
	if (! is_null($session->getStopWhy()) && strlen($session->getStopWhy())>0)
		$ret.= ' <em>('.$session->getStopWhy().')</em>';
	$ret.= '</li>';
	$ret.= '</ul>';
	
	$ret.= '<div>';
	$ret.= '<h2>Servers</h2>';
	if (count($servers) == 0)
		$ret.= 'No information available';
	else {
		$ret.= '<ul>';
		foreach ($servers as $server) {
			$ret.= '<li>';
			if (array_key_exists('obj', $server))
				$ret.= $server['obj']->getDisplayName().' (id: '.$server['obj']->id.')';
			else {
				$ret.= $server['name'];
				$ret.= ' <span><em>'._('does not exist').'</em></span>';
			}
			
			$infos = array();
			$infos[]= 'role: '.$server['role'];
			
			if ($mode == Session::MODE_DESKTOP && $server['desktop_server'])
				$infos[]= 'desktop server';
			
			if (array_key_exists('type', $server))
				$infos[]= '(OS: '.$server['type'].')';
			
			$ret.= ' '.implode(', ', $infos);
			if (count($server['dump']) > 0) {
				$ret.= '<div style="margin-left: 20px;">';
				$ret.= '<ul>';
				foreach ($server['dump'] as $name => $dump) {
					$ret.= '<li>'.$name.'&nbsp;';
					$ret.= '<pre style="margin-left: 20px; font-size: small;">'.$dump.'</pre>';
					$ret.= '</li>';
				}
				$ret.= '</ul>';
				$ret.= '</div>';
			}
			
			$ret.= '</li>';
		}
		$ret.= '</ul>';
	}
	$ret.= '</div>';
	
	$ret.= '<div>';
	$ret.= '<h2>Published applications</h2>';
	if (count($published_applications) == 0)
		$ret.= _('No information available');
	else {
		$ret.= '<ul>';
		foreach ($published_applications as $app_id => $application) {
			$ret.= '<li>';
			if (isset($application['obj'])) {
// 				$ret.= '<img class="icon32" src="media/image/cache.php?id='.$application['obj']->getAttribute('id').'" alt="" title="" /> ';
				$ret.= $application['obj']->getAttribute('name').' (id: '.$application['obj']->getAttribute('id').')';
			}
			else {
				if (array_key_exists('name', $application))
					$ret.= '<span>'.$application['name'].'</span>';
				else
					$ret.= '<span>'.sprintf('Unknown application (id: %s)', $application['id']).'</span>';
				
				$ret.= '&nbsp;<span><em>'._('does not exist').'</em></span>';
			}
			$ret.= '</li>';
		}
		$ret.= '</ul>';
	}
	$ret.= '</div>';
	
	$ret.= '<div>';
	$ret.= '<h2>Used applications</h2>';
	if (count($applications_instances) == 0)
		$ret.= 'No information available';
	else {
		$ret.= '<ul>';
		foreach ($applications_instances as $instance) {
			$application = $published_applications[$instance['application']];
			
			$ret.= '<li>';
			
			if (array_key_exists('obj', $application)) {
// 				$ret.= '<img class="icon32" src="media/image/cache.php?id='.$application['obj']->getAttribute('id').'" alt="" title="" /> ';
				$ret.= '<a href="applications.php?action=manage&id='.$application['obj']->getAttribute('id').'">'.$application['obj']->getAttribute('name').'</a>';
			}
			else {
				if (array_key_exists('name', $application))
					$ret.= '<span>'.$application['name'].'</span>';
				else
					$ret.= '<span>'.sprintf(_('Unknown application (id: %s)'), $application['id']).'</span>';
				
				$ret.= '&nbsp;<span><em>'._('does not exist').'</em></span>';
			}
			
			$ret.= ' - ';
			$duration = sprintf('%0.2f', (($instance['stop']-$instance['start'])/60));
			$ret.= str_replace(array('%SERVER%', '%DURATION%', '%STARTED_TIME%', '%STOPPED_TIME%'), 
				  array($instance['server'], $duration, strftime('%T', $instance['start']), strftime('%T', $instance['stop'])),
				  _('executed on server %SERVER% during %DURATION%m (started at %STARTED_TIME%, stopped at %STOPPED_TIME%)'));
			
			$ret.= '</li>';
		}
		
		$ret.= '</ul>';
	}
	
	$ret.= '</div>';
	
	$ret.= '<div>';
	$ret.= '<h2>'._('Storage').'</h2>';
	if (count($storages) == 0)
		$ret.= _('No information available');
	else {
		$ret.= '<ul>';
		foreach ($storages as $storage_id => $storage) {
			if ($storage['type'] != 'profile') {
				continue;
			}
				
			$ret.= '<li>User profile on server: '.$storage['server_name'].' ('.$storage['server_id'].')'.'</li>';
		}
			
		foreach ($storages as $storage_id => $storage) {
			if ($storage['type'] == 'profile') {
				continue;
			}
			
			$ret.= '<li>Shared foler - '.$storage['name'].' <em>('.$storage['mode'].')</em> on server: '.$storage['server_name'].' ('.$storage['server_id'].')</li>';
		}
		
		$ret.= '</ul>';
	}
	
	$ret.= '</div>';
	return $ret;
}
