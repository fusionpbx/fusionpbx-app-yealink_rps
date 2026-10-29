<?php
/*
	Yealink RPS Management - Devices
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
	require_once "resources/paging.php";

//check permissions
	if (!(permission_exists('yealink_rps_view') || permission_exists('yealink_rps') || if_group('superadmin'))) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set the domain and user uuids
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';

//create the RPS client
	$rps = new yealink_rps($settings);

//csrf token
	$token = new token;

//redirect helper
	function rps_redirect() {
		$query = [];
		if (!empty($_GET['page'])) { $query['page'] = (int)$_GET['page']; }
		if (!empty($_GET['key'])) { $query['key'] = $_GET['key']; }
		if (!empty($_GET['status'])) { $query['status'] = $_GET['status']; }
		$qs = !empty($query) ? '?' . http_build_query($query) : '';
		header('Location: rps_devices.php' . $qs);
		exit;
	}

//process the http post
	if (!empty($_POST) && empty($_POST['persistformvar'])) {

		//validate the token
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative', 5000);
			rps_redirect();
		}

		if (!$rps->is_configured()) {
			$nc_message = $text['label-not_configured'];
			message::add($nc_message, 'negative', 5000);
			rps_redirect();
		}

		$action = $_POST['action'] ?? '';

		switch ($action) {

			case 'add':
				if (permission_exists('yealink_rps_add') || permission_exists('yealink_rps') || if_group('superadmin')) {
					//parse the macs (one per line)
					$macs_raw = trim($_POST['macs'] ?? '');
					$macs = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', $macs_raw)), 'strlen'));
					if (empty($macs)) {
						message::add($text['message-no_selection'], 'negative', 5000);
						rps_redirect();
					}
					$data = [
						'macs' => $macs,
						'serverId' => trim($_POST['serverId'] ?? ''),
						'uniqueServerUrl' => trim($_POST['uniqueServerUrl'] ?? ''),
						'remark' => trim($_POST['remark'] ?? ''),
						'authName' => trim($_POST['authName'] ?? ''),
						'password' => trim($_POST['password'] ?? ''),
						'email' => trim($_POST['email'] ?? ''),
						'number' => trim($_POST['number'] ?? ''),
					];
					$result = $rps->device_add($data);
					if ($result['success']) {
						message::add($text['message-added'], 'positive', 5000);
					}
					else {
						message::add(($result['error'] ?? $text['message-error']), 'negative', 5000);
					}
				}
				break;

			case 'delete':
				if (permission_exists('yealink_rps_delete') || permission_exists('yealink_rps') || if_group('superadmin')) {
					$ids = [];
					if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
						$ids = array_values(array_filter($_POST['ids'], 'strlen'));
					}
					if (empty($ids)) {
						message::add($text['message-no_selection'], 'negative', 5000);
						rps_redirect();
					}
					$result = $rps->device_delete($ids);
					if ($result['success']) {
						message::add($text['message-deleted'], 'positive', 5000);
					}
					else {
						message::add(($result['error'] ?? $text['message-error']), 'negative', 5000);
					}
				}
				break;

			case 'migrate':
				if (permission_exists('yealink_rps_edit') || permission_exists('yealink_rps') || if_group('superadmin')) {
					$ids = [];
					if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
						$ids = array_values(array_filter($_POST['ids'], 'strlen'));
					}
					$server_id = trim($_POST['serverId'] ?? '');
					if (empty($ids) || empty($server_id)) {
						message::add($text['message-no_selection'], 'negative', 5000);
						rps_redirect();
					}
					$result = $rps->device_migrate($ids, $server_id);
					if ($result['success']) {
						message::add($text['message-migrated'], 'positive', 5000);
					}
					else {
						message::add(($result['error'] ?? $text['message-error']), 'negative', 5000);
					}
				}
				break;

			case 'reset':
				if (permission_exists('yealink_rps_edit') || permission_exists('yealink_rps') || if_group('superadmin')) {
					$ids = [];
					if (!empty($_POST['ids']) && is_array($_POST['ids'])) {
						$ids = array_values(array_filter($_POST['ids'], 'strlen'));
					}
					if (empty($ids)) {
						message::add($text['message-no_selection'], 'negative', 5000);
						rps_redirect();
					}
					$result = $rps->device_batch_enable_init($ids);
					if ($result['success']) {
						message::add($text['message-reset_done'], 'positive', 5000);
					}
					else {
						message::add(($result['error'] ?? $text['message-error']), 'negative', 5000);
					}
				}
				break;

			case 'check':
				$mac = trim($_POST['mac'] ?? '');
				if (empty($mac)) {
					message::add($text['message-error'], 'negative', 5000);
					rps_redirect();
				}
				$result = $rps->device_check($mac);
				if ($result['success'] && is_string($result['payload'])) {
					$status_val = $result['payload'];
					$map = [
						'Registered' => 'label-registered',
						'Unregistered' => 'label-unregistered',
						'Registered Elsewhere' => 'label-registered_elsewhere',
						'Unknown' => 'label-unknown',
					];
					$label = $map[$status_val] ?? 'label-unknown';
					message::add($mac . ': ' . ($text[$label] ?? $status_val), 'positive', 5000);
				}
				else {
					message::add($mac . ': ' . ($result['error'] ?? $text['label-unknown']), 'negative', 5000);
				}
				break;
		}

		rps_redirect();
	}

//get the query string parameters
	$page = is_numeric($_GET['page'] ?? '') ? (int)$_GET['page'] : 0;
	$key = trim($_GET['key'] ?? '');
	$status = trim($_GET['status'] ?? '');
	if (!in_array($status, ['bound', 'unbound'], true)) {
		$status = '';
	}

//rows per page
	$rows_per_page = intval($settings->get('domain', 'paging', 50));
	if ($rows_per_page < 1) {
		$rows_per_page = 50;
	}
	$skip = $page * $rows_per_page;

//query string for the paging controls
	$query_params = [];
	if (!empty($page)) { $query_params['page'] = $page; }
	if (!empty($key)) { $query_params['key'] = $key; }
	if (!empty($status)) { $query_params['status'] = $status; }
	$query_string = http_build_query($query_params);

//fetch the device list
	$devices = [];
	$total = 0;
	$list_error = '';
	if ($rps->is_configured()) {
		$filters = [
			'key' => $key,
			'skip' => $skip,
			'limit' => $rows_per_page,
			'autoCount' => true,
		];
		if (!empty($status)) {
			$filters['status'] = $status;
		}
		$result = $rps->device_list($filters);
		if ($result['success']) {
			$payload = $result['payload'];
			if (is_array($payload)) {
				$total = isset($payload['total']) ? (int)$payload['total'] : 0;
				if (!empty($payload['data']) && is_array($payload['data'])) {
					$devices = $payload['data'];
				}
			}
		}
		else {
			$list_error = $result['error'] ?? '';
		}
	}

//fetch the server list for the dropdowns
	$servers = [];
	if ($rps->is_configured()) {
		$result = $rps->device_server_list();
		if ($result['success'] && is_array($result['payload'])) {
			foreach ($result['payload'] as $server) {
				if (!empty($server['id']) && !empty($server['serverName'])) {
					$servers[$server['id']] = $server['serverName'];
				}
			}
		}
	}

//paging controls
	list($paging_controls, $rows_per_page) = paging($total, $query_string, $rows_per_page);

//document
	$document['title'] = $text['title-yealink_rps_devices'];
	$document['description'] = '';

//includes the header
	require_once "resources/header.php";

//create the csrf token for the form
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//message alerts are rendered automatically by resources/footer.php

//not configured banner
	if (!$rps->is_configured()) {
		$nc_message = $text['label-not_configured'];
		echo "<div class='alert alert-warning' role='alert'>\n";
		echo "	" . $nc_message . "\n";
		if (permission_exists('yealink_rps_settings') || if_group('superadmin')) {
			echo "	<a href='rps_settings.php'>" . $text['title-yealink_rps_settings'] . "</a>\n";
		}
		echo "</div>\n";
	}
	elseif (!empty($list_error)) {
		echo "<div class='alert alert-danger' role='alert'>\n";
		echo "	" . escape($list_error) . "\n";
		echo "</div>\n";
	}

//add / edit device form
	$can_add = permission_exists('yealink_rps_add') || permission_exists('yealink_rps') || if_group('superadmin');
	$can_edit = permission_exists('yealink_rps_edit') || permission_exists('yealink_rps') || if_group('superadmin');
	if ($can_add || $can_edit) {
		echo "<div id='device-form-modal' class='modal-window'>\n";
		echo "	<div style='width: 600px; max-width: 90%;'>\n";
		echo "		<span title='" . $text['button-close'] . "' class='modal-close' onclick='modal_close();'>&times;</span>\n";
		echo "		<span class='modal-title' id='device-form-title'>" . $text['label-add_device'] . "</span>\n";
		echo "<form name='frm_device' id='frm_device' method='post' action='rps_devices.php'>\n";
		echo "<input type='hidden' name='form_action' id='device_form_action' value='add'>\n";
		echo "<input type='hidden' name='id' id='device_id' value=''>\n";
		echo "<table width='100%' cellpadding='0' cellspacing='0' border='0'>\n";

		//macs (add only)
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>" . $text['label-macs'] . "</td>\n";
		echo "<td class='vtable' align='left' id='row-macs'>\n";
		echo "	<textarea class='formfld' name='macs' id='device_macs' rows='3' placeholder='AABBCCDDEEFF'></textarea>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//server
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-server'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<select class='formfld' name='serverId' id='device_server_id'>\n";
		echo "		<option value=''>" . $text['label-select_server'] . "</option>\n";
		foreach ($servers as $server_id => $server_name) {
			echo "		<option value='" . escape($server_id) . "'>" . escape($server_name) . "</option>\n";
		}
		echo "	</select>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//unique server url
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-unique_server_url'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='uniqueServerUrl' id='device_unique_server_url' value=''>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//remark
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-remark'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='remark' id='device_remark' value=''>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//auth name
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-auth_name'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='authName' id='device_auth_name' value=''>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//password
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-password'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='password' id='device_password' value='' placeholder='***'>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//email (add only)
		echo "<tr id='row-email'>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-email'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='email' id='device_email' value=''>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//number (add only)
		echo "<tr id='row-number'>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-number'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='number' id='device_number' value=''>\n";
		echo "</td>\n";
		echo "</tr>\n";

		echo "</table>\n";
		echo "<br />\n";
		echo "<div align='center'>\n";
		if ($can_add) {
			echo "	" . button::create(['type' => 'submit', 'label' => $text['button-add'], 'icon' => $settings->get('theme', 'button_icon_add'), 'name' => 'action', 'value' => 'add', 'id' => 'btn_add']) . "\n";
		}
		if ($can_edit) {
			echo "	" . button::create(['type' => 'submit', 'label' => $text['button-edit'], 'icon' => $settings->get('theme', 'button_icon_edit'), 'name' => 'action', 'value' => 'edit', 'id' => 'btn_edit', 'style' => 'margin-left: 5px; display: none;']) . "\n";
		}
		echo "</div>\n";
		echo "<input type='hidden' name='" . $token['name'] . "' value='" . $token['hash'] . "'>\n";
		echo "</form>\n";
		echo "	</div>\n";
		echo "</div>\n";
	}

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>RPS Devices".$text['header-rps_devices']."</b>\n";
	echo "</div>\n";
	echo "	<div class='actions'>\n";
	//echo "		" . button::create(['type' => 'submit', 'label' => $text['button-refresh'], 'icon' => $settings->get('theme', 'button_icon_refresh', 'fa-solid fa-rotate')]) . "\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back', ''),'id'=>'btn_back','link'=>'yealink_rps.php'.($query_string ? '?'.$query_string : '')]);
	if ($can_add) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add', ''),'id'=>'btn_show_add','onclick'=>'yealink_show_add();','style'=>'margin-left: 5px;']);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

//search / filter bar
	echo "<div class='card'>\n";
	echo "<form name='frm_search' method='get' action='rps_devices.php'>\n";
	echo "<table width='100%' cellpadding='0' cellspacing='0' border='0'>\n";
	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-search'] . "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='key' value='" . escape($key) . "' placeholder='" . escape($text['label-mac']) . "'>\n";
	echo "</td>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-status'] . "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='status'>\n";
	echo "		<option value=''>" . $text['label-none'] . "</option>\n";
	echo "		<option value='bound' " . ($status == 'bound' ? "selected='selected'" : null) . ">" . $text['label-bound'] . "</option>\n";
	echo "		<option value='unbound' " . ($status == 'unbound' ? "selected='selected'" : null) . ">" . $text['label-unbound'] . "</option>\n";
	echo "	</select>\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "</form>\n";
	echo "</div>\n";
	echo "<br />\n";

//device list card
	$can_migrate = permission_exists('yealink_rps_edit') || permission_exists('yealink_rps') || if_group('superadmin');
	$can_delete = permission_exists('yealink_rps_delete') || permission_exists('yealink_rps') || if_group('superadmin');
	echo "<div class='card'>\n";
	if ($can_migrate || $can_delete) {
		echo "<form name='frm_bulk' method='post' action='rps_devices.php' id='frm_bulk'>\n";
	}
	else {
		echo "<div>\n";
	}

	//bulk action toolbar
	if (($can_migrate || $can_delete) && !empty($servers)) {
		echo "<div style='margin-bottom: 10px;'>\n";
		if ($can_migrate) {
			echo "	" . $text['button-migrate'] . ":\n";
			echo "	<select class='formfld' name='serverId' style='margin-right: 5px;'>\n";
			echo "		<option value=''>" . $text['label-select_server'] . "</option>\n";
			foreach ($servers as $server_id => $server_name) {
				echo "		<option value='" . escape($server_id) . "'>" . escape($server_name) . "</option>\n";
			}
			echo "	</select>\n";
			echo "	" . button::create(['type' => 'submit', 'label' => $text['button-migrate'], 'name' => 'action', 'value' => 'migrate', 'id' => 'btn_migrate']) . "\n";
		}
		if ($can_migrate) {
			echo "	" . button::create(['type' => 'submit', 'label' => $text['button-reset'], 'name' => 'action', 'value' => 'reset', 'id' => 'btn_reset', 'style' => 'margin-left: 5px;']) . "\n";
		}
		if ($can_delete) {
			echo "	" . button::create(['type' => 'submit', 'label' => $text['button-delete'], 'name' => 'action', 'value' => 'delete', 'id' => 'btn_delete', 'style' => 'margin-left: 5px;', 'onclick' => "return confirm('" . escape($text['button-delete'] . '?') . "');"]) . "\n";
		}
		echo "</div>\n";
	}

	//table header
	echo "<table class='table' width='100%' cellpadding='0' cellspacing='0' border='0'>\n";
	echo "<thead>\n";
	echo "<tr>\n";
	if ($can_migrate || $can_delete) {
		echo "<th class='checkbox'><input type='checkbox' id='checkbox_all' onclick='if (this.checked) {document.querySelectorAll(\"#frm_bulk input[name='ids[]']\").forEach(function(c){c.checked=true;});} else {document.querySelectorAll(\"#frm_bulk input[name='ids[]']\").forEach(function(c){c.checked=false;});}'></th>\n";
	}
	echo "<th>" . $text['label-mac'] . "</th>\n";
	echo "<th>" . $text['label-server'] . "</th>\n";
	echo "<th>" . $text['label-enterprise'] . "</th>\n";
	echo "<th>" . $text['label-ip_address'] . "</th>\n";
	echo "<th>" . $text['label-auth_name'] . "</th>\n";
	echo "<th>" . $text['label-remark'] . "</th>\n";
	echo "<th>" . $text['label-last_connected'] . "</th>\n";
	echo "<th class='center'>" . $text['label-action'] . "</th>\n";
	echo "</tr>\n";
	echo "</thead>\n";
	echo "<tbody>\n";

//helper to format a ms timestamp
	function rps_format_ms($ms) {
		if (!is_numeric($ms) || (int)$ms <= 0) {
			return '';
		}
		return date('Y-m-d H:i:s', (int)floor($ms / 1000));
	}

	if (empty($devices)) {
		echo "<tr><td colspan='9' class='center'>" . $text['label-none'] . "</td></tr>\n";
	}
	foreach ($devices as $device) {
		$device_mac = $device['mac'] ?? '';
		$device_id = $device['id'] ?? ($device['_id'] ?? '');
		$server_name = $device['serverName'] ?? '';
		$enterprise = $device['enterpriseName'] ?? '';
		$ip = $device['ipAddress'] ?? '';
		$auth = $device['authName'] ?? '';
		$remark = $device['remark'] ?? '';
		$last_connected = rps_format_ms($device['lastConnected'] ?? 0);
		$unique_url = $device['uniqueServerUrl'] ?? '';
		$server_id = $device['serverId'] ?? '';

		echo "<tr data-mac='" . escape($device_mac) . "' data-id='" . escape($device_id) . "' data-server='" . escape($server_id) . "' data-url='" . escape($unique_url) . "' data-remark='" . escape($remark) . "' data-auth='" . escape($auth) . "'>\n";
		if ($can_migrate || $can_delete) {
			echo "	<td class='checkbox'><input type='checkbox' name='ids[]' value='" . escape($device_id) . "'></td>\n";
		}
		echo "	<td class='no-wrap'>" . escape($device_mac) . "</td>\n";
		echo "	<td>" . (!empty($server_name) ? escape($server_name) : escape($text['label-unbound'])) . "</td>\n";
		echo "	<td>" . escape($enterprise) . "</td>\n";
		echo "	<td>" . escape($ip) . "</td>\n";
		echo "	<td>" . escape($auth) . "</td>\n";
		echo "	<td>" . escape($remark) . "</td>\n";
		echo "	<td>" . escape($last_connected) . "</td>\n";
		echo "	<td class='center'>\n";
		if ($can_edit) {
			echo "		" . button::create(['type' => 'button', 'label' => $text['button-edit'], 'class' => 'link', 'onclick' => 'yealink_edit_device(this);']) . "\n";
		}
		echo "		" . button::create(['type' => 'button', 'label' => $text['button-check'], 'class' => 'link', 'onclick' => 'yealink_check_mac(this);']) . "\n";
		echo "	</td>\n";
		echo "</tr>\n";
	}

	echo "</tbody>\n";
	echo "</table>\n";
	echo "<div align='center'>" . escape($paging_controls) . "</div>\n";
	echo "<input type='hidden' name='" . $token['name'] . "' value='" . $token['hash'] . "'>\n";
	if ($can_migrate || $can_delete) {
		echo "</form>\n";
	}
	else {
		echo "</div>\n";
	}
	echo "</div>\n";
	echo "<br />\n";

//javascript
	echo "<script>
	var rps_token_name = '" . escape($token['name']) . "';
	var rps_token = '" . escape($token['hash']) . "';
	var rps_title_add = '" . escape($text['label-add_device']) . "';
	var rps_title_edit = '" . escape($text['label-edit_device']) . "';

	function yealink_edit_device(btn) {
		var row = btn.closest('tr');
		document.getElementById('device_id').value = row.dataset.id || '';
		document.getElementById('device_server_id').value = row.dataset.server || '';
		document.getElementById('device_unique_server_url').value = row.dataset.url || '';
		document.getElementById('device_remark').value = row.dataset.remark || '';
		document.getElementById('device_auth_name').value = row.dataset.auth || '';
		document.getElementById('device_password').value = '';
		document.getElementById('device_macs').value = row.dataset.mac || '';
		document.getElementById('device_macs').readOnly = true;
		document.getElementById('row-macs').style.display = '';
		document.getElementById('row-email').style.display = 'none';
		document.getElementById('row-number').style.display = 'none';
		var btn_add = document.getElementById('btn_add');
		var btn_edit = document.getElementById('btn_edit');
		if (btn_add) { btn_add.style.display = 'none'; }
		if (btn_edit) { btn_edit.style.display = 'inline-block'; }
		document.getElementById('device-form-title').innerHTML = rps_title_edit;
		modal_open('device-form-modal');
	}

	function yealink_show_add() {
		document.getElementById('device_id').value = '';
		document.getElementById('device_macs').value = '';
		document.getElementById('device_macs').readOnly = false;
		document.getElementById('device_server_id').value = '';
		document.getElementById('device_unique_server_url').value = '';
		document.getElementById('device_remark').value = '';
		document.getElementById('device_auth_name').value = '';
		document.getElementById('device_password').value = '';
		document.getElementById('device_email').value = '';
		document.getElementById('device_number').value = '';
		document.getElementById('row-macs').style.display = '';
		document.getElementById('row-email').style.display = '';
		document.getElementById('row-number').style.display = '';
		var btn_add = document.getElementById('btn_add');
		var btn_edit = document.getElementById('btn_edit');
		if (btn_add) { btn_add.style.display = 'inline-block'; }
		if (btn_edit) { btn_edit.style.display = 'none'; }
		document.getElementById('device-form-title').innerHTML = rps_title_add;
		modal_open('device-form-modal', 'device_macs');
	}

	function yealink_check_mac(btn) {
		var row = btn.closest('tr');
		var mac = row.dataset.mac || '';
		if (!mac) { return; }
		var form = document.createElement('form');
		form.method = 'post';
		form.action = 'rps_devices.php';
		form.innerHTML = '<input type=\"hidden\" name=\"action\" value=\"check\">' +
			'<input type=\"hidden\" name=\"mac\" value=\"' + mac + '\">' +
			'<input type=\"hidden\" name=\"' + rps_token_name + '\" value=\"' + rps_token + '\">' ;
		document.body.appendChild(form);
		form.submit();
	}
	</script>\n";

//includes the footer
	require_once "resources/footer.php";
