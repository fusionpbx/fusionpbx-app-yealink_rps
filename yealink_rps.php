<?php
/*
	Yealink RPS Management - Dashboard
	for FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2026
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!(permission_exists('yealink_rps_view') || permission_exists('yealink_rps') || if_group('superadmin'))) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//connection status (read from the stored settings - no live API call)
	$rps_enabled = $settings->get('yealink_rps', 'enabled', false);
	$rps_base_url = $settings->get('yealink_rps', 'base_url', 'https://dm.yealink.com');
	$rps_has_keys = ($settings->get('yealink_rps', 'access_key_id', '') !== '' && $settings->get('yealink_rps', 'access_key_secret', '') !== '');
	$rps_configured = $rps_has_keys;

//show the settings card only to users allowed to open the settings page
//(same check as rps_settings.php; note permission_exists() auto-passes in CLI tests)
	$rps_settings_allowed = (permission_exists('yealink_rps_settings') || if_group('superadmin'));

//document
	$document['title'] = $text['title-yealink_rps'];
	$document['description'] = $text['description-yealink_rps'];

//includes the header
	require_once "resources/header.php";

//dashboard styles
	echo "<style>\n";
	echo "	#rps_dashboard .rps_intro { border-left: 4px solid #358edd; }\n";
	echo "	#rps_dashboard .rps_intro .rps_intro_icon { float: left; margin: 4px 18px 0 0; font-size: 34px; color: #358edd; }\n";
	echo "	#rps_dashboard .rps_badge { display: inline-block; padding: 2px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; color: #fff; vertical-align: middle; margin-left: 8px; }\n";
	echo "	#rps_dashboard .rps_badge_ok { background: #28a745; }\n";
	echo "	#rps_dashboard .rps_badge_warn { background: #f0ad4e; }\n";
	echo "	#rps_dashboard .rps_grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-top: 20px; }\n";
	echo "	#rps_dashboard .rps_card { display: block; text-decoration: none; color: inherit; border: 1px solid rgba(0,0,0,0.08); border-radius: 8px; padding: 20px; transition: box-shadow 0.15s ease, transform 0.15s ease, border-color 0.15s ease; }\n";
	echo "	#rps_dashboard .rps_card:hover { text-decoration: none; color: inherit; box-shadow: 0 4px 14px rgba(0,0,0,0.12); transform: translateY(-2px); border-color: #358edd; }\n";
	echo "	#rps_dashboard .rps_card .rps_card_icon { width: 46px; height: 46px; border-radius: 10px; background: rgba(53,142,221,0.12); color: #358edd; font-size: 20px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }\n";
	echo "	#rps_dashboard .rps_card_title { font-size: 16px; font-weight: bold; margin-bottom: 6px; }\n";
	echo "	#rps_dashboard .rps_card_desc { font-size: 13px; opacity: 0.75; line-height: 1.45; margin-bottom: 12px; }\n";
	echo "	#rps_dashboard .rps_card_link { font-size: 13px; font-weight: bold; color: #358edd; }\n";
	echo "	#rps_dashboard .rps_card_link i { transition: margin-left 0.15s ease; margin-left: 2px; }\n";
	echo "	#rps_dashboard .rps_card:hover .rps_card_link i { margin-left: 6px; }\n";
	echo "</style>\n";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>Yealink RPS".$text['header-yealink_rps']."</b>\n";
	if ($rps_configured) {
		echo "	<div class='count'><span class='rps_badge rps_badge_ok' style='width: 200px;'>" . $text['label-configured'] . "</span></div>\n";
	}
	else {
		echo "	<div style='font-size: 13px; opacity: 0.75;'><i class='fa-solid fa-circle-exclamation' style='margin-right: 6px;'></i>" . $text['description-add_credentials'] . "</div>\n";
	}
	echo "</div>\n";
	echo "	<div class='actions'>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

//intro card
	echo "<div id='rps_dashboard'>\n";
	echo "<div class='card rps_intro'>\n";
	echo "	<div style='overflow: hidden; line-height: 1.55; margin-bottom: 8px;'>\n";
	echo "		" . $text['description-what_is_rps'] . "\n";
	echo "	</div>\n";
	echo "</div>\n";

//page cards
	echo "<div class='rps_grid'>\n";

	//servers card
	echo "<div class='card rps_card'>\n";
	echo "	<a class='' href='rps_servers.php'>\n";
	echo "		<div class='rps_card_icon'><i class='fa-solid fa-server'></i></div>\n";
	echo "		<div class='rps_card_title'>" . $text['label-rps_servers'] . "</div>\n";
	echo "		<div class='rps_card_desc'>" . $text['description-card_servers'] . "</div>\n";
	echo "		<div class='rps_card_link'>" . $text['label-manage'] . " <i class='fa-solid fa-arrow-right'></i></div>\n";
	echo "	</a>\n";
	echo "</div>\n";

	//devices card
	echo "<div class='card rps_card'>\n";
	echo "	<a class='' href='rps_devices.php'>\n";
	echo "		<div class='rps_card_icon'><i class='fa-solid fa-phone-volume'></i></div>\n";
	echo "		<div class='rps_card_title'>" . $text['label-rps_devices'] . "</div>\n";
	echo "		<div class='rps_card_desc'>" . $text['description-card_devices'] . "</div>\n";
	echo "		<div class='rps_card_link'>" . $text['label-manage'] . " <i class='fa-solid fa-arrow-right'></i></div>\n";
	echo "	</a>\n";
	echo "</div>\n";

	//settings card (hidden unless yealink_rps_settings / superadmin, matching rps_settings.php)
	if ($rps_settings_allowed) {
		echo "<div class='card rps_card'>\n";
		echo "	<a class='' href='rps_settings.php'>\n";
		echo "		<div class='rps_card_icon'><i class='fa-solid fa-gear'></i></div>\n";
		echo "		<div class='rps_card_title'>" . $text['label-rps_settings'] . "</div>\n";
		echo "		<div class='rps_card_desc'>" . $text['description-card_settings'] . "</div>\n";
		echo "		<div class='rps_card_link'>" . $text['label-manage'] . " <i class='fa-solid fa-arrow-right'></i></div>\n";
		echo "	</a>\n";
		echo "</div>\n";
	}

	echo "</div>\n";
	echo "</div>\n";

//include the footer
	require_once "resources/footer.php";

?>
