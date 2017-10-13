<?php
/**
 * Copyright (C) 2008-2014 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com> 2009
 * Author Jeremy DESVAGES <jeremy@ulteo.com> 2008
 * Author Samuel BOVEE <samuel@ulteo.com> 2010
 * Author Julien LANGLOIS <julien@ulteo.com> 2011, 2012, 2013
 * Author David LECHEVALIER <david@ulteo.com> 2014
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

if (! checkAuthorization('viewPublications'))
	redirect('index.php');


show_default();

function show_default() {
  $publications = array();

  $groups_apps = $_SESSION['service']->applications_groups_list();
  if (is_null($groups_apps))
      $groups_apps = array();
  foreach($groups_apps as $i => $group_apps) {
    if (! $group_apps->published)
      unset($groups_apps[$i]);
      
	$group_apps2 = $_SESSION['service']->applications_group_info($group_apps->id);
	if (is_null($group_apps2)) {
		unset($groups_apps[$i]);
	}
	
	$groups_apps[$i] = $group_apps2;
  }

	$usersgroupsList = new UsersGroupsList($_REQUEST);
	$groups_users = $usersgroupsList->search();
	if (! is_array($groups_users)) {
		$groups_users = array();
		popup_error(_("Failed to get User Groups list"));
	}
	uasort($groups_users, "usergroup_cmp");
	$searchDiv = $usersgroupsList->getForm();

  foreach($groups_users as $i => $group_users) {
    if (! $group_users->published)
      unset($groups_users[$i]);
  }


	// Starts from the applications groups instead of users groups because 
	// it's possible to not be able to have the complete users groups list (LDAP)
	foreach($groups_apps as $group_apps) {
		if (! $group_apps->hasAttribute('usersgroups')) {
			continue;
		}
		
		foreach($group_apps->getAttribute('usersgroups') as $group_users_id => $group_users_name) {
			$group_users = $_SESSION['service']->users_group_info($group_users_id);
			if (is_null($group_users)) {
				continue;
			}
			
			if (! $group_users->published)
				continue;
			
			$publications[]= array('user' => $group_users, 'app' => $group_apps);
		}
	}

  $has_publish = count($publications);

  $can_add_publish = true;
  if (count($groups_users) == 0)
    $can_add_publish = false;
  elseif (count($groups_apps) == 0)
    $can_add_publish = false;
  elseif (count($groups_users) * count($groups_apps) <= count($publications))
    $can_add_publish = false;

	$servers_groups = $_SESSION['service']->servers_groups_list();
	$publication_servers = array();
	foreach($servers_groups as $servers_group_id => $servers_group) {
		$servers_group = $_SESSION['service']->servers_group_info($servers_group_id);
		if (! is_object($servers_group)) {
			continue;
		}
		
		if (! $servers_group->published) {
			continue;
		}
		
		if (! $servers_group->hasAttribute('usersgroups')) {
			continue;
		}
		
		foreach($servers_group->getAttribute('usersgroups') as $users_group_id => $users_group_name) {
			$users_group = $_SESSION['service']->users_group_info($users_group_id);
			if (is_null($users_group)) {
				continue;
			}
			
			if (! $users_group->published)
				continue;
			
			$publication_servers[]= array('user' => $users_group, 'server' => $servers_group);
		}
	}


  $count = 0;

	$can_manage_publications = isAuthorized('managePublications');

  page_header(array('js_files' => array('media/script/publication.js')));


  echo '<div>';
  echo '<h1>'._('Publications').'</h1>';

  echo '<table class="main_sub sortable" id="publications_list_table" border="0" cellspacing="1" cellpadding="5">';
  echo '<thead>';
  echo '<tr class="title">';
  echo '<th>'._('User Group').'</th>';
  echo '<th>'._('Application Group').'</th>';
  echo '</tr>';
  echo '</thead>';

  echo '<tbody>';
  if (! $has_publish) {
    $content = 'content'.(($count++%2==0)?1:2);
    echo '<tr class="'.$content.'"><td colspan="3">'._('No publications').'</td></tr>';
  } else {
    foreach($publications as $publication){
      $content = 'content'.(($count++%2==0)?1:2);
      $group_u = $publication['user'];
      $group_a = $publication['app'];

      echo '<tr class="'.$content.'">';
      echo '<td><a href="usersgroup.php?action=manage&amp;id='.$group_u->id.'">'.$group_u->name.'</a></td>';
      echo '<td><a href="appsgroup.php?action=manage&amp;id='.$group_a->id.'">'.$group_a->name.'</a></td>';

			if ($can_manage_publications) {
				echo '<td><form action="actions.php" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this publication?').'\');"><div>';
				echo '<input type="hidden" name="action" value="del" />';
				echo '<input type="hidden" name="name" value="Publication" />';
				echo '<input type="hidden" name="group_a" value="'.$group_a->id.'" />';
				echo '<input type="hidden" name="group_u" value="'.$group_u->id.'" />';
				echo '<input type="submit" value="'._('Delete').'"/>';
				echo '</div></form></td>';
			}
      echo '</tr>';
    }
  }
  echo '</tbody>';

  $nb_groups_apps  = count($groups_apps);
  $nb_groups_users = count($groups_users);

  if ($can_add_publish and $can_manage_publications) {
    $content = 'content'.(($count++%2==0)?1:2);

    echo '<tfoot>';
    echo '<tr class="'.$content.'">';
    echo '<td>';
    echo '<select id="select_group_u" name="group_u" onchange="ovdsm_publication_hook_select(this)" style="width: 100%;">';
    echo '<option value="">*</option>';
    foreach($groups_users as $group_users)
      if (! $group_users->hasAttribute('applicationsgroups') || count($group_users->getAttribute('applicationsgroups')) < $nb_groups_apps)
        echo '<option value="'.$group_users->id.'" >'.$group_users->name.'</option>';
    echo '</select>';
    echo '</td>';

    echo '<td>';
    echo '<select id="select_group_a" name="group_a" onchange="ovdsm_publication_hook_select(this)" style="width: 100%;">';
    echo '<option value="" >*</option>';
    foreach($groups_apps as $group_apps)
      if (! $group_apps->hasAttribute('usersgroups') || count($group_apps->getAttribute('usersgroups')) < $nb_groups_users)
        echo '<option value="'.$group_apps->id.'" >'.$group_apps->name.'</option>';
    echo '</select>';
    echo '</td><td>';
    echo '<form action="actions.php" method="post" ><div>';
    echo '<input type="hidden" name="action" value="add" />';
    echo '<input type="hidden" name="name" value="Publication" />';
    echo '<input type="hidden" name="group_u" value="" id="input_group_u" />';
    echo '<input type="hidden" name="group_a" value="" id="input_group_a" />';
    echo '<input type="button" value="'._('Add').'" onclick="if($(\'input_group_u\').value == \'\') {alert(\''.addslashes(_('Please select a User Group')).'\'); return;} if($(\'input_group_a\').value == \'\') {alert(\''.addslashes(_('Please select an Application Group')).'\'); return;} this.form.submit();" />';
    echo '</div></form>';
    echo '</td>';
    echo '</tr>';
    echo '</tfoot>';
  }

  echo '</table>';
  echo '<br /><br /><br />';
  echo '</div>';

	// Servers groups publication
	$count = 0;
	echo '<div>';
	echo '<h2>'._('Server Group publications').'</h2>';
	
	echo '<table class="main_sub sortable" border="0" cellspacing="1" cellpadding="5">';
	echo '<thead>';
	echo '<tr class="title">';
	echo '<th>'._('User Group').'</th>';
	echo '<th>'._('Server Group').'</th>';
	echo '</tr>';
	echo '</thead>';
	echo '<tbody>';
	if (count($publication_servers) == 0) {
		$content = 'content'.(($count++%2==0)?1:2);
		echo '<tr class="'.$content.'"><td colspan="3">'._('No publication').'</td></tr>';
	}
	else {
		foreach($publication_servers as $publication) {
			$content = 'content'.(($count++%2==0)?1:2);
			$group_u = $publication['user'];
			$group_s = $publication['server'];
			
			echo '<tr class="'.$content.'">';
			echo '<td><a href="usersgroup.php?action=manage&amp;id='.$group_u->id.'">'.$group_u->name.'</a></td>';
			echo '<td><a href="serversgroup.php?action=manage&amp;id='.$group_s->id.'">'.$group_s->name.'</a></td>';
			
			if ($can_manage_publications) {
				echo '<td><form action="actions.php" method="post" onsubmit="return confirm(\''._('Are you sure you want to delete this publication?').'\');"><div>';
				echo '<input type="hidden" name="action" value="del" />';
				echo '<input type="hidden" name="name" value="UsersGroupServersGroup" />';
				echo '<input type="hidden" name="servers_group" value="'.$group_s->id.'" />';
				echo '<input type="hidden" name="users_group" value="'.$group_u->id.'" />';
				echo '<input type="submit" value="'._('Delete').'"/>';
				echo '</div></form></td>';
			}
			
			echo '</tr>';
		}
	}
	
	echo '</tbody>';

	if ($can_manage_publications) {
		$content = 'content'.(($count++%2==0)?1:2);

		echo '<tfoot>';
		echo '<form action="actions.php" method="post" >';
		echo '<input type="hidden" name="action" value="add" />';
		echo '<input type="hidden" name="name" value="UsersGroupServersGroup" />';
		echo '<tr class="'.$content.'">';
		echo '<td>';
		echo '<select name="users_group" style="width: 100%;">';
		echo '<option value="">*</option>';
		foreach($groups_users as $group_users) {
			echo '<option value="'.$group_users->id.'" >'.$group_users->name.'</option>';
		}
		
		echo '</select>';
		echo '</td>';

		echo '<td>';
		echo '<select name="servers_group" style="width: 100%;">';
		echo '<option value="" >*</option>';
		foreach($servers_groups as $servers_group) {
		    echo '<option value="'.$servers_group->id.'" >'.$servers_group->name.'</option>';
		}
		echo '</select>';
		echo '</td><td>';
		echo '<input type="submit" value="'._('Add').'" />';
		echo '</td>';
		echo '</tr>';
		echo '</form>';
		echo '</tfoot>';
	}

	echo '</table>';
	echo '</div>';

  echo '<br /><br /><br />';
  echo $searchDiv;

  echo '</div>';
  page_footer();
}


function get_users_group($id_, $list_) {
	foreach($list_ as $i => $group) {
		if ($group->id == $id_) {
			return $group;
		}
	}
	
	return null;
}
