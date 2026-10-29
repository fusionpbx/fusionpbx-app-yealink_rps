<?php
/*
	Yealink RPS Management - Settings
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
	if (!permission_exists('yealink_rps_settings') && !if_group('superadmin')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set the domain and user uuids
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';

//current setting values
	$settings_enabled = $settings->get('yealink_rps', 'enabled', false);
	$base_url = $settings->get('yealink_rps', 'base_url', 'https://dm.yealink.com');
	$access_key_id = $settings->get('yealink_rps', 'access_key_id', '');
	$access_key_secret = $settings->get('yealink_rps', 'access_key_secret', '');
	$auth_mode = $settings->get('yealink_rps', 'auth_mode', 'auto');

//csrf token
	$token = new token;

//process the http post
	if (!empty($_POST) && empty($_POST['persistformvar'])) {

		//validate the token
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative', 5000);
			header('Location: rps_settings.php');
			exit;
		}

		//get posted values
		$action = $_POST['action'] ?? '';
		$base_url = trim($_POST['base_url'] ?? '');
		$access_key_id = trim($_POST['access_key_id'] ?? '');
		$access_key_secret = trim($_POST['access_key_secret'] ?? '');
		$auth_mode = trim($_POST['auth_mode'] ?? 'auto');
		if (!in_array($auth_mode, ['auto', 'x-ca', 'developer'], true)) {
			$auth_mode = 'auto';
		}
		//keep the stored secret when the (blank) form field is submitted empty
		if ($access_key_secret === '') {
			$access_key_secret = $settings->get('yealink_rps', 'access_key_secret', '');
		}
		$settings_enabled = !empty($_POST['enabled']) && $_POST['enabled'] == 'true';

		if ($action == 'test') {
			//test the credentials using the values from the form (without saving)
			//the access token is obtained automatically via OAuth2 - not part of the form
			$rps = new yealink_rps($settings, [
				'base_url' => $base_url,
				'access_key_id' => $access_key_id,
				'access_key_secret' => $access_key_secret,
				'auth_mode' => $auth_mode,
			]);
			$result = $rps->test_connection();
			if ($result['success']) {
				message::add($result['message'], 'positive', 5000);
			}
			else {
				message::add($result['message'], 'negative', 5000);
			}
			header('Location: rps_settings.php');
			exit;
		}

		if ($action == 'save') {
			//get the current default settings
			$sql = "select * from v_default_settings ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$sql .= "and default_setting_category = 'yealink_rps' ";
			$parameters['domain_uuid'] = $domain_uuid;
			$result = $database->select($sql, $parameters, 'all');
			$default_settings = [];
			if (is_array($result)) {
				foreach($result as $row) {
					$default_settings[$row['default_setting_subcategory']] = $row;
				}
			}
			unset($sql, $parameters, $result, $row);

			//add the settings to the array
			$i = 0;
			$settings_data = [
				'enabled' => [
					'uuid' => 'b6a2f8c1-4e1a-4d2b-9c3f-1a2b3c4d5e6f',
					'value' => $settings_enabled ? 'true' : 'false',
					'name' => 'boolean',
					'description' => $text['description-enabled'] ?? 'Enable the Yealink RPS Management integration.',
				],
				'base_url' => [
					'uuid' => 'c7b3a9d2-5f2b-4e3c-8d4a-2b3c4d5e6f7a',
					'value' => $base_url,
					'name' => 'text',
					'description' => $text['description-base_url'] ?? 'Base URL for the Yealink RPS JSON API.',
				],
				'access_key_id' => [
					'uuid' => 'd8c4b0e3-6a3c-4f4d-9e5b-3c4d5e6f7a8b',
					'value' => $access_key_id,
					'name' => 'text',
					'description' => $text['description-access_key_id'] ?? 'Yealink RPS AccessKey Id used to sign API requests',
				],
				'access_key_secret' => [
					'uuid' => 'e9d5c1f4-7b4d-4a5e-8f6c-4d5e6f7a8b9c',
					'value' => $access_key_secret,
					'name' => 'text',
					'description' => $text['description-access_key_secret'] ?? 'Yealink RPS AccessKey Secret used to compute the HMAC-SHA256 signature.',
				],
				'auth_mode' => [
					'uuid' => 'a1b7c3d5-2c8d-4b4f-8e6a-7d9c1b2f3a4b',
					'value' => $auth_mode,
					'name' => 'list',
					'description' => $text['description-auth_mode'] ?? 'Authentication mode: auto, x-ca or developer.',
				],
			];
			foreach ($settings_data as $subcategory => $data) {
				$row = $default_settings[$subcategory] ?? [];
				if (!empty($data['value'])) {
					//add the setting to the array for update
					$array['default_settings'][$i]['default_setting_uuid'] = $data['uuid'];
					$array['default_settings'][$i]['default_setting_category'] = 'yealink_rps';
					$array['default_settings'][$i]['default_setting_subcategory'] = $subcategory;
					$array['default_settings'][$i]['default_setting_name'] = $data['name'];
					$array['default_settings'][$i]['default_setting_value'] = $data['value'];
					$array['default_settings'][$i]['default_setting_enabled'] = 'true';
					$array['default_settings'][$i]['default_setting_description'] = $data['description'];
					$i++;
				}
			}
			unset($row);

			//initialize the permission object
			$p = permissions::new();

			//add temporary permissions
			$p->add("default_setting_add", "temp");
			$p->add("default_setting_edit", "temp");

			//save the data
			if (!empty($array)) {
				$database->save($array);
			}

			//remove the temporary permissions
			$p->delete("default_setting_add", "temp");
			$p->delete("default_setting_edit", "temp");

			//clear the settings cache and reload the settings so the UI reflects the change
			settings::clear_cache();
			$settings->reload();

			//set a message for the user
			message::add($text['message-updated'], 'positive', 5000);

			//redirect the browser
			header('Location: rps_settings.php');
			exit;
		}
	}

//document
	$document['title'] = $text['title-yealink_rps_settings'];
	$document['description'] = $text['description-yealink_rps_settings'];

//includes the header
	require_once "resources/header.php";

//create the csrf token for the form
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['title-yealink_rps_settings']."</b>\n";
	echo "</div>\n";
	echo "	<div class='actions'>\n";
	//echo "		" . button::create(['type' => 'submit', 'label' => $text['button-refresh'], 'icon' => $settings->get('theme', 'button_icon_refresh', 'fa-solid fa-rotate')]) . "\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back', ''),'id'=>'btn_back','link'=>'yealink_rps.php'.($query_string ? '?'.$query_string : '')]);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

//display the form
	echo "<form name='frm' id='frm' method='post' action='rps_settings.php'>\n";
	echo "<div class='card'>\n";
	echo "<table width='100%' cellpadding='0' cellspacing='0' border='0'>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	" . $text['label-enabled'] . "\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' id='enabled' name='enabled'>\n";
	echo "		<option value='true' " . ($settings_enabled ? "selected='selected'" : null) . ">" . $text['option-true'] . "</option>\n";
	echo "		<option value='false' " . ($settings_enabled ? null : "selected='selected'") . ">" . $text['option-false'] . "</option>\n";
	echo "	</select>\n";
	echo "<br />\n";
	echo "	" . $text['description-enabled'] . "\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	" . $text['label-base_url'] . "\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='base_url' maxlength='255' value=\"" . escape($base_url) . "\" placeholder='https://dm.yealink.com'>\n";
	echo "<br />\n";
	echo "	" . $text['description-base_url'] . "\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	" . $text['label-access_key_id'] . "\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='access_key_id' maxlength='255' value=\"" . escape($access_key_id) . "\">\n";
	echo "<br />\n";
	echo "	" . $text['description-access_key_id'] . "\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	" . $text['label-access_key_secret'] . "\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='password' name='access_key_secret' maxlength='255' value=\"" . escape($access_key_secret) . "\">\n";
	echo "<br />\n";
	echo "	" . $text['description-access_key_secret'] . "\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	" . $text['label-auth_mode'] . "\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' id='auth_mode' name='auth_mode'>\n";
	echo "		<option value='auto' " . ($auth_mode === 'auto' ? "selected='selected'" : null) . ">" . $text['option-auto'] . "</option>\n";
	echo "		<option value='x-ca' " . ($auth_mode === 'x-ca' ? "selected='selected'" : null) . ">" . $text['option-x-ca'] . "</option>\n";
	echo "		<option value='developer' " . ($auth_mode === 'developer' ? "selected='selected'" : null) . ">" . $text['option-developer'] . "</option>\n";
	echo "	</select>\n";
	echo "<br />\n";
	echo "	" . $text['description-auth_mode'] . "\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>\n";
	echo "</div>\n";
	echo "<br />\n";

	//action buttons
	echo "<div align='center'>\n";
	echo "	" . button::create(['type' => 'submit', 'label' => $text['button-save'], 'icon' => $settings->get('theme', 'button_icon_save'), 'name' => 'action', 'value' => 'save', 'id' => 'btn_save']) . "\n";
	echo "	" . button::create(['type' => 'submit', 'label' => $text['button-test'], 'icon' => $settings->get('theme', 'button_icon_test', 'fa-solid fa-plug'), 'name' => 'action', 'value' => 'test', 'id' => 'btn_test', 'style' => 'margin-left: 5px;']) . "\n";
	echo "</div>\n";

	echo "<input type='hidden' name='" . $token['name'] . "' value='" . $token['hash'] . "'>\n";
	echo "</form>\n";

//include the footer
	require_once "resources/footer.php";

?>
