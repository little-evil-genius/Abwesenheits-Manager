<?php
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// HOOKS
$plugins->add_hook("admin_config_settings_change", "away_manager_settings_change");
$plugins->add_hook("admin_settings_print_peekers", "away_manager_settings_peek");
$plugins->add_hook('admin_rpgstuff_update_stylesheet', 'away_manager_admin_update_stylesheet');
$plugins->add_hook('admin_rpgstuff_update_plugin', 'away_manager_admin_update_plugin');
$plugins->add_hook('global_intermediate', 'away_manager_index');
$plugins->add_hook("misc_start", "away_manager_misc");
$plugins->add_hook('usercp_profile_start', 'away_manager_startdate');
$plugins->add_hook("usercp_do_profile_start", "away_manager_do_old_data");
$plugins->add_hook('usercp_do_profile_end', 'away_manager_do_startdate');
$plugins->add_hook('showteam_end', 'away_manager_showteam');
$plugins->add_hook("fetch_wol_activity_end", "away_manager_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "away_manager_online_location");
 
// Die Informationen, die im Pluginmanager angezeigt werden
function away_manager_info(){
	return array(
		"name"		=> "Abwesenheits-Manager",
		"description"	=> "Dieses Plugin zeigt abwesende Mitglieder:innen auf dem Index an, erstellt eine Abwesenheitsliste und listet abwesende Teammitglieder:innen auf der Teamseite auf.",
		"website"	=> "https://github.com/little-evil-genius/Abwesenheits-Manager",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.0",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function away_manager_install() {
    
    global $db;

    // Accountswitcher muss vorhanden sein
    if (!function_exists('accountswitcher_is_installed')) {
		flash_message("Das Plugin <a href=\"http://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2\" target=\"_blank\">\"Enhanced Account Switcher\"</a> muss installiert sein!", 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // RPG Stuff Modul muss vorhanden sein
    if (!file_exists(MYBB_ADMIN_DIR."/modules/rpgstuff/module_meta.php")) {
		flash_message("Das ACP Modul <a href=\"https://github.com/little-evil-genius/rpgstuff_modul\" target=\"_blank\">\"RPG Stuff\"</a> muss vorhanden sein!", 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // EINSTELLUNGEN HINZUFÜGEN
    $maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
    $setting_group = array(
        'name'          => 'away_manager',
        'title'         => 'Abwesenheits-Manager',
        'description'   => 'Einstellungen für das Plugin "Abwesenheits-Manager"',
        'disporder'     => $maxdisporder+1,
        'isdefault'     => 0
    );
    $db->insert_query("settinggroups", $setting_group);

    // Einstellungen
    away_manager_settings();
    rebuild_settings();

    // TEMPLATES ERSTELLEN
	// Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "awaymanager",
        "title" => $db->escape_string("Abwesenheits-Manager"),
    );
    $db->insert_query("templategroups", $templategroup);
    // Templates 
    away_manager_templates();
    
    // STYLESHEET HINZUFÜGEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    // Funktion
    $stylesheet = away_manager_stylesheet();
    $db->insert_query('themestylesheets', $stylesheet);
    cache_stylesheet(1, "away_manager.css", $stylesheet['stylesheet']);
    update_theme_stylesheet_list("1");

}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function away_manager_is_installed() {

	global $mybb;

	if (isset($mybb->settings['away_manager_awaystart'])) {
		return true;
	}
	return false;
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function away_manager_uninstall() {
    
	global $db, $cache;

    // TEMPLATGRUPPE LÖSCHEN
    $db->delete_query("templategroups", "prefix = 'awaymanager'");

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE 'awaymanager%'");
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'away_manager%'");
    $db->delete_query('settinggroups', "name = 'away_manager'");
    rebuild_settings();

    // STYLESHEET ENTFERNEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	$db->delete_query("themestylesheets", "name = 'away_manager.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']); 
	}
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function away_manager_activate() {
    
    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN EINFÜGEN
    find_replace_templatesets('index_boardstats', '#'.preg_quote('{$bbclosedwarning}').'#', '{$birthdays}{$away_manager_index}');
    find_replace_templatesets('usercp_profile_away', '#'.preg_quote('<tr><td colspan="3"><span class="smalltext">{$lang->return_date}</span></td>').'#', '{$awaystartdate} <tr><td colspan="3"><span class="smalltext">{$lang->return_date}</span></td>');
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function away_manager_deactivate() {
    
    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN ENTFERNEN
	find_replace_templatesets("index_boardstats", "#".preg_quote('{$away_manager_index}')."#i", '', 0);
	find_replace_templatesets("usercp_profile_away", "#".preg_quote('{$awaystartdate}')."#i", '', 0);

}

######################
### HOOK FUNCTIONS ###
######################

// EINSTELLUNGEN VERSTECKEN
function away_manager_settings_change(){
    
    global $db, $mybb, $away_manager_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='away_manager'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $away_manager_settings_peeker = ($mybb->get_input('gid') == $group['gid']) && ($mybb->request_method != 'post');
}
function away_manager_settings_peek(&$peekers){

    global $away_manager_settings_peeker;

    if ($away_manager_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_away_manager_index"), $("#row_setting_away_manager_index_split, #row_setting_away_manager_span"),/1/,true)';
        $peekers[] = 'new Peeker($(".setting_away_manager_list"), $("#row_setting_away_manager_list_allowgroups, #row_setting_away_manager_list_nav, #row_setting_away_manager_list_menu, #row_setting_away_manager_list_type"),/1/,true)';
        $peekers[] = 'new Peeker($("#setting_away_manager_list_type"), $("#row_setting_away_manager_list_menu"), /^0/, false)';
        $peekers[] = 'new Peeker($(".setting_away_manager_post"), $("#row_setting_away_manager_post_thread"), /1/, true);';
    }
}

// ADMIN BEREICH - RPG STUFF //
// Stylesheet zum Master Style hinzufügen
function away_manager_admin_update_stylesheet(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_stylesheet_updates');

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // HINZUFÜGEN
    if ($mybb->input['action'] == 'add_master' AND $mybb->get_input('plugin') == "away_manager") {

        $css = away_manager_stylesheet();
        
        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "away_manager.css"), "sid = '".$sid."'", 1);
    
        $tids = $db->simple_select("themes", "tid");
        while($theme = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($theme['tid']);
        } 

        flash_message($lang->stylesheets_flash, "success");
        admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Abwesenheits-Manager")."</b>", array('width' => '70%'));

    // Ob im Master Style vorhanden
    $master_check = $db->fetch_field($db->query("SELECT tid FROM ".TABLE_PREFIX."themestylesheets 
    WHERE name = 'away_manager.css' 
    AND tid = 1
    "), "tid");
    
    if (!empty($master_check)) {
        $masterstyle = true;
    } else {
        $masterstyle = false;
    }

    if (!empty($masterstyle)) {
        $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=away_manager\">".$lang->stylesheets_add."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// Plugin Update
function away_manager_admin_update_plugin(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_plugin_updates');

    // UPDATE
    if ($mybb->input['action'] == 'add_update' AND $mybb->get_input('plugin') == "away_manager") {

        // Einstellungen überprüfen => Type = update
        away_manager_settings('update');
        rebuild_settings();

        // Templates 
        away_manager_templates('update');

        // Stylesheet
        $update_data = away_manager_stylesheet_update();
        $update_stylesheet = $update_data['stylesheet'];
        $update_string = $update_data['update_string'];
        if (!empty($update_string)) {

            // Ob im Master Style die Überprüfung vorhanden ist
            $masterstylesheet = $db->fetch_field($db->query("SELECT stylesheet FROM ".TABLE_PREFIX."themestylesheets WHERE tid = 1 AND name = 'away_manager.css'"), "stylesheet");
            $masterstylesheet = (string)($masterstylesheet ?? '');
            $update_string = (string)($update_string ?? '');
            $pos = strpos($masterstylesheet, $update_string);
            if ($pos === false) { // nicht vorhanden 
            
                $theme_query = $db->simple_select('themes', 'tid, name');
                while ($theme = $db->fetch_array($theme_query)) {
        
                    $stylesheet_query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string('away_manager.css')."' AND tid = ".$theme['tid']);
                    $stylesheet = $db->fetch_array($stylesheet_query);
        
                    if ($stylesheet) {

                        require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
        
                        $sid = $stylesheet['sid'];
            
                        $updated_stylesheet = array(
                            "cachefile" => $db->escape_string($stylesheet['name']),
                            "stylesheet" => $db->escape_string($stylesheet['stylesheet']."\n\n".$update_stylesheet),
                            "lastmodified" => TIME_NOW
                        );
            
                        $db->update_query("themestylesheets", $updated_stylesheet, "sid='".$sid."'");
            
                        if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $updated_stylesheet['stylesheet'])) {
                            $db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet=".$sid), "sid='".$sid."'", 1);
                        }
            
                        update_theme_stylesheet_list($theme['tid']);
                    }
                }
            } 
        }

        flash_message($lang->plugins_flash, "success");
        admin_redirect("index.php?module=rpgstuff-plugin_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Abwesenheits-Manager")."</b>", array('width' => '70%'));

    // Überprüfen, ob Update erledigt
    $update_check = away_manager_is_updated();

    if (!empty($update_check)) {
        $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=away_manager\">".$lang->plugins_update."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// INDEX ANZEIGE
function away_manager_index() {

    global $db, $mybb, $lang, $templates, $away_manager_index, $teambit, $userbit;

    if ($mybb->settings['away_manager_index'] == 0) {
        $away_manager_index = "";
        return;
    }

    // SPRACHDATEI
    $lang->load('away_manager');

    // EINSTELLUNGEN
    $index_split = $mybb->settings['away_manager_index_split'];
    $usergroups_list = $mybb->settings['away_manager_usergroups'];
    $userArray = explode(',', $usergroups_list);
    $teamgroups_list = $mybb->settings['away_manager_teamgroups'];
    $teamArray = explode(',', $teamgroups_list);
    $display_setting = $mybb->settings['away_manager_display'];
    $display_name = $mybb->settings['away_manager_display_name'];
    $span_setting = $mybb->settings['away_manager_span'];

    // User & Team getrennt
    if ($index_split == 1) {

        // Teamgruppen aus Usergruppen entfernen - sicher ist sicher
        $cleanUsergroups = array_diff($userArray, $teamArray);
        $cleanUsergroupsList = implode(',', $cleanUsergroups);

        $awaylist_query = $db->query("SELECT uid, as_uid FROM ".TABLE_PREFIX."users
        WHERE away = 1 
        ORDER BY returndate DESC
        ");

        $awayteamIDs = array();
        $awayuserIDs = array();
        while ($away = $db->fetch_array($awaylist_query)) {

            // leer laufen lassen
            $uid = "";
            $as_uid = "";

            // Mit Infos füllen
            $uid = $away['uid'];
            $as_uid = $away['as_uid'];

            // Accounts einzeln
            if ($display_setting == 1) {
                if (is_member($teamgroups_list, $uid)) {
                    $awayteamIDs[] = $uid;
                } else if (is_member($cleanUsergroupsList, $uid)) {
                    $awayuserIDs[] = $uid;
                }
            } 
            // Zusammengefasst
            else {
        
                if ($as_uid != 0) {
                    $mainUid = $as_uid;
                } else {
                    $mainUid = $uid;
                }

                if (in_array($mainUid, $awayteamIDs) || in_array($mainUid, $awayuserIDs)) {
                    continue;
                }

                if (is_member($teamgroups_list, $mainUid)) {
                    $awayteamIDs[] = $mainUid;
                } else if (is_member($cleanUsergroupsList, $mainUid)) {
                    $awayuserIDs[] = $mainUid;
                }
            }
        }

        $userbit = "";
        foreach ($awayuserIDs as $userID) {

            // kein Zeitraum anzeigen
            if ($span_setting == 0) {
                $awayspan = "";
            }
            // Start- & Rückkehrdatum
            else if ($span_setting == 1) {
                $returndate = DateTime::createFromFormat('d-n-Y', get_user($userID)['returndate']);
                $startdate = date('d.m.Y', get_user($userID)['awaydate']);
                $awayspan = $lang->sprintf($lang->away_manager_index_awayspan_startdate, $startdate, $returndate->format('d.m.Y'));
            } 
            // nur Rückkehrdatum
            else {
                $returndate = DateTime::createFromFormat('d-n-Y', get_user($userID)['returndate']);
                $awayspan = $lang->sprintf($lang->away_manager_index_awayspan_enddate, $returndate->format('d.m.Y'));
            }
         
            if (!empty(get_user($userID)['awayreason'])) {
                $awayreason = get_user($userID)['awayreason'];
            } else {
                $awayreason = $lang->away_manager_awayreason_none;
            }

            // Accountname
            if ($display_name == 0) {
                $name = build_profile_link(get_user($userID)['username'], $userID);
            } 
            // Spielername
            else if ($display_name == 1) {
                $playername = away_manager_playername($userID);
                $name = build_profile_link($playername, $userID);
            }
            // beides
            else if ($display_name == 2) {
                $playername = away_manager_playername($userID);
                if ($display_setting == 1) {
                    $characterlist = get_user($userID)['username'];
                } else {
                
                    $accountArray = away_manager_get_allchars($userID);  
                    $characterlistArray = array();                  
                    foreach ($accountArray as $charactername) {
                        if ($playername == $charactername) {
                            continue;
                        }
                        $characterlistArray[] = $charactername;                
                    }

                    $characterlist = implode(', ', $characterlistArray);
                }
                $playername = build_profile_link($playername, $userID);
                $name = $lang->sprintf($lang->away_manager_index_characterlist, $playername, $characterlist);
            }

            eval("\$userbit .= \"".$templates->get("awaymanager_index_bit")."\";");
        }

        if (empty($awayuserIDs)) {
            $userbit = $lang->away_manager_index_bit_none;
        }

        $userlist = "";
        foreach ($awayteamIDs as $teamID) {

            // kein Zeitraum anzeigen
            if ($span_setting == 0) {
                $awayspan = "";
            }
            // Start- & Rückkehrdatum
            else if ($span_setting == 1) {
                $returndate = DateTime::createFromFormat('d-n-Y', get_user($teamID)['returndate']);
                $startdate = date('d.m.Y', get_user($teamID)['awaydate']);
                $awayspan = $lang->sprintf($lang->away_manager_index_awayspan_startdate, $startdate, $returndate->format('d.m.Y'));
            } 
            // nur Rückkehrdatum
            else {
                $returndate = DateTime::createFromFormat('d-n-Y', get_user($teamID)['returndate']);
                $lang->sprintf($lang->away_manager_index_awayspan_enddate, $returndate->format('d.m.Y'));
            }
         
            if (!empty(get_user($teamID)['awayreason'])) {
                $awayreason = get_user($teamID)['awayreason'];
            } else {
                $awayreason = $lang->away_manager_index_awayreason_none;
            }

            // Accountname
            if ($display_name == 0) {
                $name = build_profile_link(get_user($teamID)['username'], $teamID);
            } 
            // Spielername
            else if ($display_name == 1) {
                $playername = away_manager_playername($teamID);
                $name = build_profile_link($playername, $teamID);
            }
            // beides
            else if ($display_name == 2) {
                $playername = away_manager_playername($teamID);
                if ($display_setting == 1) {
                    $characterlist = get_user($teamID)['username'];
                } else {
                
                    $accountArray = away_manager_get_allchars($teamID);  
                    $characterlistArray = array();                  
                    foreach ($accountArray as $charactername) {
                        if ($playername == $charactername) {
                            continue;
                        }
                        $characterlistArray[] = $charactername;                
                    }

                    $characterlist = implode(', ', $characterlistArray);
                }
                $playername = build_profile_link($playername, $teamID);
                $name = $lang->sprintf($lang->away_manager_index_characterlist, $playername, $characterlist);
            }

            eval("\$userlist .= \"".$templates->get("awaymanager_index_bit")."\";");
        }

        if (empty($awayteamIDs)) {
            $userlist = $lang->away_manager_index_bit_none; 
        }

        eval("\$teambit = \"".$templates->get("awaymanager_index_team")."\";");

    } else {

        $awaylist_query = $db->query("SELECT uid, as_uid FROM ".TABLE_PREFIX."users
        WHERE away = 1 
        ORDER BY returndate DESC
        ");

        $awayuserIDs = array();
        while ($away = $db->fetch_array($awaylist_query)) {

            // leer laufen lassen
            $uid = "";
            $as_uid = "";

            // Mit Infos füllen
            $uid = $away['uid'];
            $as_uid = $away['as_uid'];

            // Accounts einzeln
            if ($display_setting == 1) {
                if (is_member($usergroups_list, $uid)) {
                    $awayuserIDs[] = $uid;
                }
            } 
            // Zusammengefasst
            else {
        
                if ($as_uid != 0) {
                    $mainUid = $as_uid;
                } else {
                    $mainUid = $uid;
                }

                if (in_array($mainUid, $awayuserIDs)) {
                    continue;
                }

                if (is_member($usergroups_list, $mainUid)) {
                    $awayuserIDs[] = $mainUid;
                }
            }
        }

        $userbit = "";
        foreach ($awayuserIDs as $userID) {

            // kein Zeitraum anzeigen
            if ($span_setting == 0) {
                $awayspan = "";
            }
            // Start- & Rückkehrdatum
            else if ($span_setting == 1) {
                $returndate = DateTime::createFromFormat('d-n-Y', get_user($userID)['returndate']);
                $startdate = date('d.m.Y', get_user($userID)['awaydate']);
                $awayspan = $lang->sprintf($lang->away_manager_index_awayspan_startdate, $startdate, $returndate->format('d.m.Y'));
            } 
            // nur Rückkehrdatum
            else {
                $returndate = DateTime::createFromFormat('d-n-Y', get_user($userID)['returndate']);
                $lang->sprintf($lang->away_manager_index_awayspan_enddate, $returndate->format('d.m.Y'));
            }
         
            if (!empty(get_user($userID)['awayreason'])) {
                $awayreason = get_user($userID)['awayreason'];
            } else {
                $awayreason = $lang->away_manager_index_awayreason_none;
            }

            // Accountname
            if ($display_name == 0) {
                $name = build_profile_link(get_user($userID)['username'], $userID);
            } 
            // Spielername
            else if ($display_name == 1) {
                $playername = away_manager_playername($userID);
                $name = build_profile_link($playername, $userID);
            }
            // beides
            else if ($display_name == 2) {
                $playername = away_manager_playername($userID);
                if ($display_setting == 1) {
                    $characterlist = get_user($userID)['username'];
                } else {
                
                    $accountArray = away_manager_get_allchars($userID);  
                    $characterlistArray = array();                  
                    foreach ($accountArray as $charactername) {
                        if ($playername == $charactername) {
                            continue;
                        }
                        $characterlistArray[] = $charactername;                
                    }

                    $characterlist = implode(', ', $characterlistArray);
                }
                $playername = build_profile_link($playername, $userID);
                $name = $lang->sprintf($lang->away_manager_index_characterlist, $playername, $characterlist);
            }

            eval("\$userbit .= \"".$templates->get("awaymanager_index_bit")."\";");
        }

        if (empty($awayuserIDs)) {
            $userbit = $lang->away_manager_index_bit_none;
        }
    }

    if ($mybb->settings['away_manager_list'] == 1) {
        $awaylist = $lang->away_manager_index_list;
    } else {
        $awaylist = "";
    }

    eval("\$away_manager_index = \"".$templates->get("awaymanager_index")."\";");
}

// USER-CP ERWEITERUNG (STARTDATE & POST)
// Alte Daten global setzen
function away_manager_do_old_data() {

    global $mybb, $db;

    if ($mybb->get_input('away', MyBB::INPUT_INT) == 1 && $mybb->settings['away_manager_post'] == 1) {
        $queryOld = $db->simple_select("users", "away, returndate", "uid = ".$mybb->user['uid']);
        $old_data = $db->fetch_array($queryOld);
        $GLOBALS['away_manager_old_data'] = $old_data;
    }
}
// Speichern & Post abschicken
function away_manager_do_startdate() {

    global $mybb, $cache, $awaydate, $returndate, $plugins, $db, $lang, $thread;

    if ($mybb->settings['allowaway'] == 0) return;
    
    $lang->load("away_manager");

    if ($mybb->get_input('away', MyBB::INPUT_INT) == 1) {
        
		$awaydate = TIME_NOW;
		if(!empty($mybb->input['awayday'])) {
			// If the user has indicated that they will return on a specific day, but not month or year, assume it is current month and year
			if(!$mybb->get_input('awaymonth', MyBB::INPUT_INT))
			{
				$mybb->input['awaymonth'] = my_date('n', $awaydate);
			}
			if(!$mybb->get_input('awayyear', MyBB::INPUT_INT))
			{
				$mybb->input['awayyear'] = my_date('Y', $awaydate);
			}

			$return_month = (int)substr($mybb->get_input('awaymonth'), 0, 2);
			$return_day = (int)substr($mybb->get_input('awayday'), 0, 2);
			$return_year = min((int)$mybb->get_input('awayyear'), 9999);
			$returndate = "{$return_day}-{$return_month}-{$return_year}";

            if ($mybb->settings['away_manager_awaystart'] == 1) {
                if(!empty($mybb->input['awaystartday']) && !empty($mybb->input['awaystartmonth']) && !empty($mybb->input['awaystartyear'])) {
			
                    $start_month = (int)substr($mybb->get_input('awaystartmonth'), 0, 2);
                    $start_day = (int)substr($mybb->get_input('awaystartday'), 0, 2);			             
                    $start_year = min((int)$mybb->get_input('awaystartyear'), 9999);

                    if (checkdate($start_month,$start_day,$start_year) == true) {
                        $awaydate = gmmktime(0, 0, 0, $start_month, $start_day, $start_year);
                    } else {
                        $awaydate = TIME_NOW;
                    }
                }
            } else {
                $awaydate = TIME_NOW;    
            }
		} else {
			$returndate = "";
		}
		$away = array(
			"away" => 1,
			"date" => $awaydate,
			"returndate" => $returndate,
			"awayreason" => $mybb->get_input('awayreason')
		);
   
        $post_needed = false;
        if ($mybb->settings['away_manager_post'] == 1) {

            $old = $GLOBALS['away_manager_old_data'];

            if ((int)$old['away'] == 0) {

                $playername = away_manager_playername($mybb->user['uid']);

                $accountArray = away_manager_get_allchars($mybb->user['uid']);  
                $characterlistArray = array();                  
                foreach ($accountArray as $charactername) {
                    if ($playername == $charactername) {
                        continue;
                    }
                    $characterlistArray[] = $charactername;
                }
                $characterlist = implode(', ', $characterlistArray);

                $returndateM = DateTime::createFromFormat('d-n-Y', $returndate);
                $startdateM = date('d.m.Y', $awaydate);
                $awayspan = $lang->sprintf($lang->away_manager_awaypost_span, $startdateM, $returndateM->format('d.m.Y'));

                if (!empty($mybb->get_input('awayreason'))) {
                    $awayreason = $mybb->get_input('awayreason');
                } else {
                    $awayreason = $lang->away_manager_awaypost_reason_none;
                }

                $message = $lang->sprintf($lang->away_manager_awaypost_first, $playername, $characterlist, $awayspan, $awayreason);
                $post_needed = true;
            } else {
                $old_date = DateTime::createFromFormat('j-n-Y', $old['returndate'] ?? '');
                $new_date = DateTime::createFromFormat('j-n-Y', $returndate);
                if (!$old_date || $old_date->format('Y-m-d') !== $new_date->format('Y-m-d')) {
                    $message = $lang->sprintf($lang->away_manager_awaypost_extend, $new_date->format('d.m.Y'));
                    $post_needed = true;
                }
            }
        }

        // Set up user handler.
        require_once MYBB_ROOT."inc/datahandlers/user.php";
        $userhandler = new UserDataHandler("update");

        $user = array(
            "uid" => $mybb->user['uid'],
            "away" => $away,
        );

        $userhandler->set_data($user);
        if ($userhandler->validate_user()) {

            // ABWESENHEITSPOST POSTEN
            if ($post_needed) {
                $lang->load("usercp");

                // Set up posthandler.
                require_once "./global.php";
                require_once MYBB_ROOT."inc/datahandlers/post.php";

                $posthandler = new PostDataHandler("insert");     
                $posthandler->action = "post";
            
                // Deaktiviere die MyAlerts-Funktionalität
                if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                    require_once MYBB_ROOT."inc/class_plugins.php";
                    $plugins->remove_hook('datahandler_post_insert_post', 'myalertsrow_subscribed');
                }
    
                // Create session for this user
                require_once MYBB_ROOT.'inc/class_session.php';
                $session = new session;
                $session->init();        
                $mybb->session = &$session;
    
                $tid = $mybb->settings['away_manager_post_thread'];
                $thread = get_thread($tid);    
                $fid = $thread['fid'];
        
                // Verify incoming POST request        
                verify_post_check($mybb->get_input('my_post_key'));
     
                $posthash = md5($mybb->user['uid'].random_str());

                // Set the post data that came from the input to the $post array.
                $post = array(
                    "tid" => $tid,
                    "replyto" => 0,
                    "fid" => "{$fid}",
                    "subject" => "RE: ".$thread['subject'],
                    "icon" => -1,
                    "uid" => $mybb->user['uid'],
                    "username" => $mybb->user['username'],
                    "message" => $message,
                    "ipaddress" => $session->packedip,
                    "posthash" => $posthash        
                );
    
                if(isset($mybb->input['pid'])){
                    $post['pid'] = $mybb->get_input('pid', MyBB::INPUT_INT);        
                }
                
                // Are we saving a draft post?        
                $post['savedraft'] = 0;
    
                // Set up the post options from the input.
                $post['options'] = array(
                    "signature" => 1,
                    "subscriptionmethod" => "",
                    "disablesmilies" => 0	        
                );
    
                // Apply moderation options if we have them
                $post['modoptions'] = $mybb->get_input('modoptions', MyBB::INPUT_ARRAY);	
                $posthandler->set_data($post);
                
                // Now let the post handler do all the hard work.	        
                $valid_post = $posthandler->validate_post();
                
                $post_errors = array();
                // Fetch friendly error messages if this is an invalid post
                if(!$valid_post){
                    $post_errors = $posthandler->get_friendly_errors();
                }        
                // $post_errors = inline_error($post_errors);
    
                // Mark thread as read            
                require_once MYBB_ROOT."inc/functions_indicators.php";
                mark_thread_read($tid, $fid);

                $json_data = '';

                // Check captcha image
                $post_captcha = null;
                if($mybb->settings['captchaimage'] && !$mybb->user['uid'])
                {
                    require_once MYBB_ROOT.'inc/class_captcha.php';    
                    $post_captcha = new captcha(false, "post_captcha");
        
                    if($post_captcha->validate_captcha() == false)
                    {
                        // CAPTCHA validation failed
                        foreach($post_captcha->get_errors() as $error)
                        {
                            $post_errors[] = $error;
                        }
                    }
                    else
                    {
                        $hide_captcha = true;    
                    }
        
                    if($mybb->get_input('ajax', MyBB::INPUT_INT) && $post_captcha->type == 1)
                    {
                        $randomstr = random_str(5);    
                        $imagehash = md5(random_str(12));
                        $imagearray = array(
                            "imagehash" => $imagehash,
                            "imagestring" => $randomstr,
                            "dateline" => TIME_NOW    
                        );
            
                        $db->insert_query("captcha", $imagearray);
        
                        //header("Content-type: text/html; charset={$lang->settings['charset']}");
                        $data = '';    
                        $data .= "<captcha>$imagehash";
        
                        if($hide_captcha)
                        {
                            $data .= "|$randomstr";    
                        }
        
                        $data .= "</captcha>";
                        //header("Content-type: application/json; charset={$lang->settings['charset']}");
                        $json_data = array("data" => $data);
                    }        
                }
    
                // One or more errors returned, fetch error list and throw to newreply page
                if(count($post_errors) > 0)
                {
                    $reply_errors = inline_error($post_errors, '', $json_data);
                    $mybb->input['action'] = "newreply";
                    // echo '<pre>';
                    // print_r($post_errors);
                    // echo '</pre>';
                    // exit;
                }
                else
                {
                    $postinfo = $posthandler->insert_post();
                    $pid = $postinfo['pid'];
                    $visible = $postinfo['visible'];
                        
                    if(isset($postinfo['closed']))
                    {
                        $closed = $postinfo['closed'];
                    }
                    else
                    {
                        $closed = '';
                    }
        
                    // Invalidate solved captcha
                    if (is_object($post_captcha))
                    {
                        $post_captcha->invalidate_captcha();
                    }
        
                    // Visible post
                    redirect("usercp.php?action=profile", $lang->redirect_profileupdated);
                    exit;
                }
            }
           
            $userhandler->update_user();

            // ACCOUNTSWITCHER
            $plugins_cache = $cache->read('plugins');
            if (is_array($plugins_cache) && is_array($plugins_cache['active']) && $plugins_cache['active']['accountswitcher']) {
                require_once MYBB_ROOT."inc/plugins/accountswitcher/as_usercp.php";
                accountswitcher_set_away();
            }
        }
    }
}
// Anzeigen
function away_manager_startdate() {

    global $mybb, $lang, $templates, $errors, $awaystartdate;

    $awaystartdate = "";
    if ($mybb->settings['allowaway'] == 0) return;
    if ($mybb->settings['away_manager_awaystart'] == 0) return;

    $lang->load("usercp");
    $lang->load('away_manager');

    if ($errors) {
        $startdate = array();
        $startdate[0] = $mybb->get_input('awaystartday', MyBB::INPUT_INT);
        $startdate[1] = $mybb->get_input('awaystartmonth', MyBB::INPUT_INT);
        $startdate[2] = $mybb->get_input('awaystartyear', MyBB::INPUT_INT);	
    } else {
        if (!empty($mybb->user['awaydate'])) {
            $starttimestamp = date('j-n-Y', $mybb->user['awaydate']);
            $startdate = explode("-", $starttimestamp);
        } else {
            $startdate = array('', '', '');
        }
    }

    $startdatesel = '';
    for($day = 1; $day <= 31; ++$day) {
        if($startdate[0] == $day) {
            $selected = "selected=\"selected\"";
        }
        else {
            $selected = '';
        }

        eval("\$startdatesel .= \"".$templates->get("usercp_profile_day")."\";");
    }

    $startdatemonthsel = array();
    foreach(range(1, 12) as $month) {
        $startdatemonthsel[$month] = '';
    }
    $startdatemonthsel[$startdate[1]] = "selected";

    eval("\$awaystartdate = \"".$templates->get("awaymanager_usercp_startdate")."\";");
}

// ABWESENHEITSLISTE
function away_manager_misc() {

    global $db, $cache, $mybb, $lang, $templates, $theme, $header, $headerinclude, $footer, $page, $lists_menu, $userbit;

    // return if the action key isn't part of the input
    if ($mybb->get_input('action', MYBB::INPUT_STRING) !== 'away_manager') {
        return;
    }

    // SPRACHDATEI
    $lang->load('away_manager');

    if ($mybb->settings['away_manager_list'] == 0) {
        error($lang->away_manager_list_disabled);
        return;
    }

    // EINSTELLUNGEN
    $list_allowgroups = $mybb->settings['away_manager_list_allowgroups'];
    $list_nav = $mybb->settings['away_manager_list_nav'];
    $list_type = $mybb->settings['away_manager_list_type'];
    $list_menu = $mybb->settings['away_manager_list_menu'];
    $display_setting = $mybb->settings['away_manager_display'];
    $display_name = $mybb->settings['away_manager_display_name'];
    $awaystartdate = $mybb->settings['away_manager_awaystart'];

    if (!is_member($list_allowgroups)) {
        error_no_permission();
        return;
    }

    $mybb->input['action'] = $mybb->get_input('action');

    // Liste
    if($mybb->input['action'] == "away_manager") {

        // Listenmenü
		if($list_type != 2){
            // Jules Plugin
            if ($list_type == 1) {
                $lang->load("lists");
                $query_lists = $db->simple_select("lists", "*");
                $menu_bit = "";
                while($list = $db->fetch_array($query_lists)) {
                    eval("\$menu_bit .= \"".$templates->get("lists_menu_bit")."\";");
                }
                eval("\$lists_menu = \"".$templates->get("lists_menu")."\";");
            } else {
                eval("\$lists_menu = \"".$templates->get($list_menu)."\";");
            }
        } else {
            $lists_menu = "";
        }

        // NAVIGATION
		if(!empty($list_nav)){
            add_breadcrumb($lang->away_manager_list_lists, $list_nav);
            add_breadcrumb($lang->away_manager_list, "misc.php?action=away_manager");
		} else{
            add_breadcrumb($lang->away_manager_list, "misc.php?action=away_manager");
		}

        $awaylist_query = $db->query("SELECT uid, as_uid FROM ".TABLE_PREFIX."users
        WHERE away = 1 
        ORDER BY returndate DESC
        ");

        $awayuserIDs  = array();
        while ($away = $db->fetch_array($awaylist_query)) {

            // leer laufen lassen
            $uid = "";
            $as_uid = "";

            // Mit Infos füllen
            $uid = $away['uid'];
            $as_uid = $away['as_uid'];

            // Accounts einzeln
            if ($display_setting == 1) {
                $awayuserIDs[] = $uid;
            } 
            // Zusammengefasst
            else {
        
                if ($as_uid != 0) {
                    $mainUid = $as_uid;
                } else {
                    $mainUid = $uid;
                }

                if (in_array($mainUid, $awayuserIDs)) {
                    continue;
                }

                $awayuserIDs[] = $mainUid;
            }
        }

        $userbit = "";
        foreach ($awayuserIDs as $userID) {

            $returndate = DateTime::createFromFormat('d-n-Y', get_user($userID)['returndate']);
            if ($awaystartdate == 1) {
                $startdate = date('d.m.Y', get_user($userID)['awaydate']);
                $awayspan = $lang->sprintf($lang->away_manager_list_awayspan_startdate, $startdate, $returndate->format('d.m.Y'));
            } else {
                $lang->sprintf($lang->away_manager_list_awayspan_enddate, $returndate->format('d.m.Y'));
            }

            if (!empty(get_user($userID)['awayreason'])) {
                $awayreason = get_user($userID)['awayreason'];
            } else {
                $awayreason = $lang->away_manager_list_awayreason_none;
            }

            // Accountname
            if ($display_name == 0) {
                $name = build_profile_link(get_user($userID)['username'], $userID);
            } 
            // Spielername
            else if ($display_name == 1) {
                $playername = away_manager_playername($userID);
                $name = build_profile_link($playername, $userID);
            }
            // beides
            else if ($display_name == 2) {
                $username = build_profile_link(get_user($userID)['username'], $userID);
                $playername = away_manager_playername($userID);

                if (get_user($userID)['username'] === $playername) {
                    $name = build_profile_link($playername, $userID);
                } else {
                    $name = $lang->sprintf($lang->away_manager_list_both, $playername, $username);
                }
            }

            if ($display_setting == 0) {
            
                $charactes = array(
                    'nameFormatted' => [],
                    'nameLink' => [],
                    'nameFormattedLink' => []
                );

                $accountArray = away_manager_get_allchars($userID);          
                foreach ($accountArray as $characterUID => $charactername) {

                    // Accountname
                    if ($display_name == 0) {
                        if ($characterUID == $userID) {
                            continue;
                        }
                    } else if ($display_name == 2) {
                        if ($charactername === get_user($userID)['username']) {
                            continue;
                        }
                    }
                    else {
                        if ($charactername === $playername) {
                            continue;
                        }
                    }


            
                    // Nur Gruppenfarbe
                    $characternameFormatted = format_name($charactername, get_user($characterUID)['usergroup'], get_user($characterUID)['displaygroup']);	
                    // Nur Link
                    $characternameLink = build_profile_link($charactername, $characterUID);
                    // mit Gruppenfarbe + Link
                    $characternameFormattedLink = build_profile_link(format_name($charactername, get_user($characterUID)['usergroup'], get_user($characterUID)['displaygroup']), $characterUID);
                    
                    $charactes['nameFormatted'][] = $characternameFormatted;
                    $charactes['nameLink'][] = $characternameLink;
                    $charactes['nameFormattedLink'][] = $characternameFormattedLink;
                }
        
                $charactes['nameFormatted'] = implode(", ", $charactes['nameFormatted']);
                $charactes['nameLink'] = implode(", ", $charactes['nameLink']);
                $charactes['nameFormattedLink'] = implode(", ", $charactes['nameFormattedLink']);
            } else {
                $charactes['nameFormatted'] = "";
                $charactes['nameLink'] = "";
                $charactes['nameFormattedLink'] = "";
            }


            eval("\$userbit .= \"".$templates->get("awaymanager_list_user")."\";");
        }

        // TEMPLATE FÜR DIE SEITE
        eval("\$page = \"".$templates->get("awaymanager_list")."\";");
        output_page($page);
        die();
    }
}

// SHOWTEAM
function away_manager_showteam() {

    global $db, $mybb, $lang, $templates, $theme, $header, $headerinclude, $footer, $awaymanager_team, $userbit;

    $awaymanager_team = "";
    if ($mybb->settings['away_manager_showteam'] == 0) return;

    $lang->load('away_manager');

    // EINSTELLUNGEN
    $display_name = $mybb->settings['away_manager_display_name'];
    $awaystartdate = $mybb->settings['away_manager_awaystart'];
    $teamgroups = explode(',', $mybb->settings['away_manager_teamgroups']);

    if (!empty($teamgroups)) {
        // additionalgroups
        $additional = [];
        foreach ($teamgroups as $group) {
            $additional[] = "NOT (concat(',',additionalgroups,',') LIKE '%,".$group.",%')";
        }
        // usergroup
        $usergroup = implode(',', array_map('intval', $teamgroups));
        $where_groups = "AND (usergroup IN (".$usergroup.") OR ".implode(' OR ', $additional).")";
    } else {
        $where_groups = "";
    }

    $awayteam_query = $db->query("SELECT uid, as_uid FROM ".TABLE_PREFIX."users
    WHERE away = 1
    ".$where_groups."
    ORDER BY returndate DESC
    ");

    $awayuserIDs  = array();
    while ($away = $db->fetch_array($awayteam_query)) {
        // leer laufen lassen
        $uid = "";
        $as_uid = "";
        
        // Mit Infos füllen
        $uid = $away['uid'];
        $as_uid = $away['as_uid'];

        if ($as_uid != 0) {
            $mainUid = $as_uid;
        } else {
            $mainUid = $uid;            
        }
        
        if (in_array($mainUid, $awayuserIDs)) {
            continue;    
        }    
        
        $awayuserIDs[] = $mainUid;  
    }
    
    $userbit = "";
    foreach ($awayuserIDs as $userID) {

        $returndate = DateTime::createFromFormat('d-n-Y', get_user($userID)['returndate']);
        if ($awaystartdate == 1) {
            $startdate = date('d.m.Y', get_user($userID)['awaydate']);
            $awayspan = $lang->sprintf($lang->away_manager_showteam_awayspan_startdate, $startdate, $returndate->format('d.m.Y'));
        } else {
            $awayspan = $lang->sprintf($lang->away_manager_showteam_awayspan_enddate, $returndate->format('d.m.Y'));
        }
         
        if (!empty(get_user($userID)['awayreason'])) {
            $awayreason = get_user($userID)['awayreason'];
        } else {
            $awayreason = $lang->away_manager_showteam_awayreason_none;
        }

        // Accountname
        if ($display_name == 0) {
            $name = get_user($userID)['username'];
        }
        // Spielername
        else {
            $name = away_manager_playername($userID);
        }
            
        $charactes = array(
            'nameFormatted' => [],
            'nameLink' => [],
            'nameFormattedLink' => []        
        );

        $accountArray = away_manager_get_allchars($userID);                  
        foreach ($accountArray as $characterUID => $charactername) {

            if ($display_name == 0) {
                if ($characterUID == $userID) {
                    continue;
                }
            }
            
            // Nur Gruppenfarbe
            $characternameFormatted = format_name($charactername, get_user($characterUID)['usergroup'], get_user($characterUID)['displaygroup']);
            // Nur Link
            $characternameLink = build_profile_link($charactername, $characterUID);
            // mit Gruppenfarbe + Link
            $characternameFormattedLink = build_profile_link(format_name($charactername, get_user($characterUID)['usergroup'], get_user($characterUID)['displaygroup']), $characterUID);
                
            $charactes['nameFormatted'][] = $characternameFormatted;
            $charactes['nameLink'][] = $characternameLink;
            $charactes['nameFormattedLink'][] = $characternameFormattedLink;    
        }
            
        $charactes['nameFormatted'] = implode(", ", $charactes['nameFormatted']); 
        $charactes['nameLink'] = implode(", ", $charactes['nameLink']);  
        $charactes['nameFormattedLink'] = implode(", ", $charactes['nameFormattedLink']); 

        eval("\$userbit .= \"".$templates->get("awaymanager_showteam_user")."\";");    
    }

    if (empty($userbit)) {
        eval("\$userbit = \"".$templates->get("awaymanager_showteam_user_none")."\";");  
    }

    eval("\$awaymanager_team = \"".$templates->get("awaymanager_showteam")."\";");
}

// ONLINE-LOCATION
function away_manager_online_activity($user_activity) {

	global $parameters, $user;

	$split_loc = explode(".php", $user_activity['location']);
	if(isset($user['location']) && $split_loc[0] == $user['location']) { 
		$filename = '';
	} else {
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}

	switch ($filename) {
		case 'misc':
			if ($parameters['action'] == "away_manager") {
				$user_activity['activity'] = "away_manager";
			}
            break;
	}

	return $user_activity;
}
function away_manager_online_location($plugin_array) {

	global $lang, $db, $mybb;
    
    // SPRACHDATEI LADEN
    $lang->load("away_manager");

	if ($plugin_array['user_activity']['activity'] == "away_manager") {
		$plugin_array['location_name'] = $lang->away_manager_online_location;
	}

	return $plugin_array;
}

#########################
### PRIVATE FUNCTIONS ###
#########################

// ACCOUNTSWITCHER HILFSFUNKTION => Danke, Katja <3
function away_manager_get_allchars($user_id) {

	global $db;

	//für den fall nicht mit hauptaccount online
	if (isset(get_user($user_id)['as_uid'])) {
        $as_uid = intval(get_user($user_id)['as_uid']);
    } else {
        $as_uid = 0;
    }

	$charas = array();
	if ($as_uid == 0) {
	  // as_uid = 0 wenn hauptaccount oder keiner angehangen
	  $get_all_users = $db->query("SELECT uid, username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$user_id.") OR (uid = ".$user_id.") ORDER BY uid");
	} else if ($as_uid != 0) {
	  //id des users holen wo alle an gehangen sind 
	  $get_all_users = $db->query("SELECT uid, username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$as_uid.") OR (uid = ".$user_id.") OR (uid = ".$as_uid.") ORDER BY uid");
	}
	while ($users = $db->fetch_array($get_all_users)) {
        $charas[$users['uid']] = $users['username'];
	}
	return $charas;  
}

// SPITZNAME
function away_manager_playername($uid){
    
    global $db, $mybb;

    $playername_setting = $mybb->settings['away_manager_playername'];

    if (!empty($playername_setting)) {
        if (is_numeric($playername_setting)) {
            $playername_fid = "fid".$playername_setting;
            $playername = $db->fetch_field($db->simple_select("userfields", $playername_fid ,"ufid = '".$uid."'"), $playername_fid);
        } else {
            $playername_fid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$playername_setting."'"), "id");
            $playername = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = '".$uid."' AND fieldid = '".$playername_fid."'"), "value");
        }
    } else {
        $playername = "";
    }

    if (!empty($playername)) {
        $playername = $playername;
    } else {
        $playername = get_user($uid)['username'];
    }

    return $playername;
}

#######################################
### DATABASE | SETTINGS | TEMPLATES ###
#######################################

// EINSTELLUNGEN
function away_manager_settings($type = 'install') {

    global $db; 

    $setting_array = array(
		'away_manager_awaystart' => array(
			'title' => 'Startdatum',
            'description' => 'Soll es die Möglichkeit geben ein Startdatum für die Abwesenheit anzugeben?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 1
		),
        'away_manager_index' => array(
			'title' => 'Abwesene Mitglieder auf dem Index',
            'description' => 'Sollen abwesende User:innen auf dem Index angezeigt werden?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 2
		),
        'away_manager_index_split' => array(
			'title' => 'Unterscheidung Team und Mitglieder',
            'description' => 'Sollen Teammitglieder:innen und User:innen getrennt voneinander aufgelistet werden auf dem Index?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 3
		),
        'away_manager_showteam' => array(
			'title' => 'Teamseite',
            'description' => 'Sollen abwesende Teammitglieder:innen auf der Teamseite (showteam.php) angezeigt werden?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 4
		),
        'away_manager_usergroups' => array(
			'title' => 'Gruppe',
            'description' => 'Von welchen Gruppen sollen die Abwesenheiten erfasst werden?',
            'optionscode' => 'groupselect',
            'value' => '2', // Default
            'disporder' => 5
		),
        'away_manager_teamgroups' => array(
			'title' => 'Teamgruppen',
            'description' => 'In welcher Gruppe werden Teamies eingeordnet? Ob primär oder sekundäre spielt dabei keine Rolle.',
            'optionscode' => 'groupselect',
            'value' => '4', // Default
            'disporder' => 6
		),
        'away_manager_display' => array(
			'title' => 'Abwesenheiten zusammenfassen',
            'description' => 'Sollen abwesende User:innen mit allen Accounts einzeln aufgelistet werden? Sonst werden sie zusammengefasst, dass sie nur einmal aufgelistet werden.',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 7
		),
        'away_manager_display_name' => array(
			'title' => 'Anzeige Name',
            'description' => 'Wie sollen die abwesenden Accounts angezeigt werden?',
            'optionscode' => 'select\n0=Accountname\n1=Spitzname\n2=Beides',
            'value' => '1', // Default
            'disporder' => 8
		),
        'away_manager_playername' => array(
			'title' => 'Spitzname',
			'description' => 'Wie lautet die FID / der Identifikator von dem Profilfeld/Steckbrieffeld für den Spitznamen?<br><b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
			'optionscode' => 'text',
			'value' => '4', // Default
			'disporder' => 9
		),
        'away_manager_span' => array(
			'title' => 'Zeitraum auf dem Index',
			'description' => 'Wie soll der Abwesenheitszeitraum auf dem Index dargestellt werden',
			'optionscode' => 'select\n0=gar kein Zeitraum\n1=Start- und Rückkehrdatum\n2=nur Rückkehrdatum',
			'value' => '0', // Default
			'disporder' => 10
		),
        'away_manager_list' => array(
			'title' => 'Liste aller Abwesenheiten',
            'description' => 'Soll es eine Liste geben mit allen aktuellen Abwesenheiten?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 11
		),
        'away_manager_list_allowgroups' => array(
            'title' => 'Erlaubte Gruppen',
			'description' => 'Welche Gruppen dürfen diese Liste sehen?',
			'optionscode' => 'groupselect',
			'value' => '4', // Default
			'disporder' => 12
        ),
		'away_manager_list_nav' => array(
			'title' => "Listen PHP",
			'description' => "Wie heißt die Hauptseite der Listen-Seite? Dies dient zur Ergänzung der Navigation. Falls nicht gewünscht einfach leer lassen.",
			'optionscode' => 'text',
			'value' => 'lists.php', // Default
			'disporder' => 13
		),
		'away_manager_list_type' => array(
			'title' => 'Listen Menü',
			'description' => 'Soll über die Variable {$lists_menu} das Menü der Listen aufgerufen werden?<br>Wenn ja, muss noch angegeben werden, ob eine eigene PHP-Datei oder das Automatische Listen-Plugin von sparks fly genutzt?',
			'optionscode' => 'select\n0=eigene Listen/PHP-Datei\n1=Automatische Listen-Plugin\n2=keine Menü-Anzeige',
			'value' => '0', // Default
			'disporder' => 14
		),
        'away_manager_list_menu' => array(
            'title' => 'Listen Menü Template',
            'description' => 'Damit das Listen Menü richtig angezeigt werden kann, muss hier einmal der Name von dem Tpl von dem Listen-Menü angegeben werden.',
            'optionscode' => 'text',
            'value' => 'lists_nav', // Default
            'disporder' => 15
        ),
        'away_manager_post' => array(
			'title' => 'Automatischer Abwesenheitspost',
            'description' => 'Soll automatisch ein Post in einem bestimmten Thema gepostet werden, wenn man sich im User-CP abwesend meldet?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 16
		),
        'away_manager_post_thread' => array(
			'title' => 'Thread',
            'description' => 'Wie lautet die Thread-ID (tid) für das Thema für Abwesenheitsdeldungen?',
            'optionscode' => 'numeric',
            'value' => '1', // Default
            'disporder' => 17
		),
    );

    $gid = $db->fetch_field($db->write_query("SELECT gid FROM ".TABLE_PREFIX."settinggroups WHERE name = 'away_manager' LIMIT 1;"), "gid");

    if ($type == 'install') {
        foreach ($setting_array as $name => $setting) {
          $setting['name'] = $name;
          $setting['gid'] = $gid;
          $db->insert_query('settings', $setting);
        }  
    }

    if ($type == 'update') {

        // Einzeln durchgehen 
        foreach ($setting_array as $name => $setting) {
            $setting['name'] = $name;
            $check = $db->write_query("SELECT name FROM ".TABLE_PREFIX."settings WHERE name = '".$name."'"); // Überprüfen, ob sie vorhanden ist
            $check = $db->num_rows($check);
            $setting['gid'] = $gid;
            if ($check == 0) { // nicht vorhanden, hinzufügen
              $db->insert_query('settings', $setting);
            } else { // vorhanden, auf Änderungen überprüfen
                
                $current_setting = $db->fetch_array($db->write_query("SELECT title, description, optionscode, disporder FROM ".TABLE_PREFIX."settings 
                WHERE name = '".$db->escape_string($name)."'
                "));
            
                $update_needed = false;
                $update_data = array();
            
                if ($current_setting['title'] != $setting['title']) {
                    $update_data['title'] = $setting['title'];
                    $update_needed = true;
                }
                if ($current_setting['description'] != $setting['description']) {
                    $update_data['description'] = $setting['description'];
                    $update_needed = true;
                }
                if ($current_setting['optionscode'] != $setting['optionscode']) {
                    $update_data['optionscode'] = $setting['optionscode'];
                    $update_needed = true;
                }
                if ($current_setting['disporder'] != $setting['disporder']) {
                    $update_data['disporder'] = $setting['disporder'];
                    $update_needed = true;
                }
            
                if ($update_needed) {
                    $db->update_query('settings', $update_data, "name = '".$db->escape_string($name)."'");
                }
            }
        }
    }

    rebuild_settings();
}

// TEMPLATES
function away_manager_templates($mode = '') {

    global $db;

    $templates[] = array(
        'title'		=> 'awaymanager_index',
        'template'	=> $db->escape_string('<tr>
        <td class="tcat"><span class="smalltext"><strong>{$lang->away_manager_index}</strong> {$awaylist}</span></td>
        </tr>
        <tr>
        <td class="trow1">
        <span class="smalltext">
			{$lang->away_manager_index_hint}<br>
			{$userbit}
			{$teambit}
		</span>
        </td>
        </tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'awaymanager_index_bit',
        'template'	=> $db->escape_string('» {$name} {$awayspan}'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'awaymanager_index_team',
        'template'	=> $db->escape_string('<hr><strong>{$lang->away_manager_index_team}</strong><br>{$userlist}'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'awaymanager_list',
        'template'	=> $db->escape_string('<html>
        <head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->away_manager_list}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead"><strong>{$lang->away_manager_list}</strong></td>
			</tr>
			<tr>
				<td class="trow1" align="center">
					<div class="awaymanager_list">
						<div class="awaymanager_list-legend">
							<div class="awaymanager_list-legend-item">{$lang->away_manager_list_legend_user}</div>
							<div class="awaymanager_list-legend-item">{$lang->away_manager_list_legend_span}</div>
							<div class="awaymanager_list-legend-item">{$lang->away_manager_list_legend_reason}</div>
						</div>
						{$userbit}
					</div>
				</td>
			</tr>
		</table>
		{$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'awaymanager_list_user',
        'template'	=> $db->escape_string('<div class="awaymanager_list-user">
        <div class="awaymanager_list-user-item">{$name}<br><span class="smalltext">{$charactes[\'nameLink\']}</span></div>
        <div class="awaymanager_list-user-item">{$awayspan}</div>
        <div class="awaymanager_list-user-item">{$awayreason}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'awaymanager_showteam',
        'template'	=> $db->escape_string('<div class="away_manager_showteam">
        <div class="away_manager_showteam-header">{$lang->away_manager_showteam}</div>
        {$userbit}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'awaymanager_showteam_user',
        'template'	=> $db->escape_string('<div class="away_manager_showteam-user">
        <div class="away_manager_showteam-name">
        {$name}<br><span class="smalltext">{$charactes[\'nameLink\']}</span>
        </div>
        <div class="away_manager_showteam-time">{$awayspan}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'awaymanager_showteam_user_none',
        'template'	=> $db->escape_string('<div class="away_manager_showteam-user">{$lang->away_manager_showteam_none}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'awaymanager_usercp_startdate',
        'template'	=> $db->escape_string('<tr>
        <td colspan="3"><span class="smalltext">{$lang->away_manager_usercp_startdate}</span></td>
        </tr>
        <tr>
        <td>
		<select name="awaystartday">
			<option value="">&nbsp;</option>
			{$startdatesel}
		</select>
        </td>
        <td>
		<select name="awaystartmonth">
			<option value="">&nbsp;</option>
			<option value="1" {$startdatemonthsel[\'1\']}>{$lang->month_1}</option>
			<option value="2" {$startdatemonthsel[\'2\']}>{$lang->month_2}</option>
			<option value="3" {$startdatemonthsel[\'3\']}>{$lang->month_3}</option>
			<option value="4" {$startdatemonthsel[\'4\']}>{$lang->month_4}</option>
			<option value="5" {$startdatemonthsel[\'5\']}>{$lang->month_5}</option>
			<option value="6" {$startdatemonthsel[\'6\']}>{$lang->month_6}</option>
			<option value="7" {$startdatemonthsel[\'7\']}>{$lang->month_7}</option>
			<option value="8" {$startdatemonthsel[\'8\']}>{$lang->month_8}</option>
			<option value="9" {$startdatemonthsel[\'9\']}>{$lang->month_9}</option>
			<option value="10" {$startdatemonthsel[\'10\']}>{$lang->month_10}</option>
			<option value="11" {$startdatemonthsel[\'11\']}>{$lang->month_11}</option>
			<option value="12" {$startdatemonthsel[\'12\']}>{$lang->month_12}</option>
		</select>
        </td>
        <td>
        <input type="text" class="textbox" size="4" maxlength="4" name="awaystartyear" value="{$startdate[\'2\']}" pattern="\d{4}" />
        </td>
        </tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    if ($mode == "update") {

        foreach ($templates as $template) {
            $query = $db->simple_select("templates", "tid, template", "title = '".$template['title']."' AND sid = '-2'");
            $existing_template = $db->fetch_array($query);

            if($existing_template) {
                if ($existing_template['template'] !== $template['template']) {
                    $db->update_query("templates", array(
                        'template' => $template['template'],
                        'dateline' => TIME_NOW
                    ), "tid = '".$existing_template['tid']."'");
                }
            }   
            else {
                $db->insert_query("templates", $template);
            }
        }
	
    } else {
        foreach ($templates as $template) {
            $check = $db->num_rows($db->simple_select("templates", "title", "title = '".$template['title']."'"));
            if ($check == 0) {
                $db->insert_query("templates", $template);
            }
        }
    }
}

// STYLESHEET MASTER
function away_manager_stylesheet() {

    global $db;
    
    $css = array(
		'name' => 'away_manager.css',
		'tid' => 1,
		'attachedto' => '',
		'stylesheet' =>	'.awaymanager_list-legend {
        display: flex;
        flex-wrap: nowrap;
        gap: 20px;
        justify-content: space-around;
        background: #0f0f0f url(../../../images/tcat.png) repeat-x;
        color: #fff;
        border-top: 1px solid #444;
        border-bottom: 1px solid #000;
        padding: 7px;
        }

        .awaymanager_list-legend-item {
        width: 100%;
        text-align: center;
        }

        .awaymanager_list-user {
        display: flex;
        flex-wrap: nowrap;
        gap: 20px;
        justify-content: space-around;
        padding: 7px;
        }

        .awaymanager_list-user-item {
        width: 100%;
        text-align: center;
        }

        .away_manager_showteam {
        background: #fff;
        width: 100%;
        border-radius: 7px;
        margin-bottom: 20px;
        border: 1px solid #ccc;
        padding: 1px;
        }

        .away_manager_showteam-header {
        font-weight: bold;
        background: #0066a2 url(../../../images/thead.png) top left repeat-x;
        color: #ffffff;
        border-bottom: 1px solid #263c30;
        padding: 8px;
        -moz-border-radius-topleft: 6px;
        -moz-border-radius-topright: 6px;
        -webkit-border-top-left-radius: 6px;
        -webkit-border-top-right-radius: 6px;
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        }

        .away_manager_showteam-user {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        background: #f5f5f5;
        border: 1px solid;
        border-color: #fff #ddd #ddd #fff;
        }

        .away_manager_showteam-name {
        width: 75%;
        }

        .away_manager_showteam-time {
        width: 25%;
        }',
		'cachefile' => 'away_manager.css',
		'lastmodified' => TIME_NOW
	);

    return $css;
}

// STYLESHEET UPDATE
function away_manager_stylesheet_update() {

    // Update-Stylesheet
    // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
    $update = '';

    // Definiere den  Überprüfung-String (muss spezifisch für die Überprüfung sein)
    $update_string = '';

    return array(
        'stylesheet' => $update,
        'update_string' => $update_string
    );
}

// UPDATE CHECK
function away_manager_is_updated(){

    global $db, $mybb;

	if (isset($mybb->settings['away_manager_awaystart'])) {
		return true;
	}
	return false;
}
