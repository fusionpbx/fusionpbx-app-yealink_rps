<?php
/*
	Yealink RPS Management - Servers
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

//create the RPS client
	$rps = new yealink_rps($settings);

//csrf token
	$token = new token;

//redirect helper
	function rps_servers_redirect() {
		$query = [];
		if (!empty($_GET['page'])) { $query['page'] = (int)$_GET['page']; }
		$qs = !empty($query) ? '?' . http_build_query($query) : '';
		header('Location: rps_servers.php' . $qs);
		exit;
	}

//process the http post
	if (!empty($_POST) && empty($_POST['persistformvar'])) {

		//validate the token
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-error'], 'negative', 5000);
			rps_servers_redirect();
		}

		if (!$rps->is_configured()) {
			$nc_message = $text['label-not_configured'];
			message::add($nc_message, 'negative', 5000);
			rps_servers_redirect();
		}

		$action = $_POST['action'] ?? '';

		switch ($action) {

			case 'add':
				if (permission_exists('yealink_rps_add') || permission_exists('yealink_rps') || if_group('superadmin')) {
					$data = [
						'serverName' => trim($_POST['serverName'] ?? ''),
						'url' => trim($_POST['url'] ?? ''),
						'authName' => trim($_POST['authName'] ?? ''),
						'password' => trim($_POST['password'] ?? ''),
						'certificateUrl' => trim($_POST['certificateUrl'] ?? ''),
						'serverCertificateUrl' => trim($_POST['serverCertificateUrl'] ?? ''),
						'serverCertificateEnable' => (($_POST['serverCertificateEnable'] ?? 'false') === 'true'),
					];
					if (empty($data['serverName'])) {
						message::add($text['message-no_selection'], 'negative', 5000);
						rps_servers_redirect();
					}
					$result = $rps->server_add($data);
					if ($result['success']) {
						message::add($text['message-added'], 'positive', 5000);
					}
					else {
						message::add(($result['error'] ?? $text['message-error']), 'negative', 5000);
					}
				}
				break;

			case 'edit':
				if (permission_exists('yealink_rps_edit') || permission_exists('yealink_rps') || if_group('superadmin')) {
					$id = trim($_POST['id'] ?? '');
					if (empty($id)) {
						message::add($text['message-error'], 'negative', 5000);
						rps_servers_redirect();
					}
					$data = [
						'id' => $id,
						'serverName' => trim($_POST['serverName'] ?? ''),
						'url' => trim($_POST['url'] ?? ''),
						'authName' => trim($_POST['authName'] ?? ''),
						'password' => trim($_POST['password'] ?? ''),
						'certificateUrl' => trim($_POST['certificateUrl'] ?? ''),
						'serverCertificateUrl' => trim($_POST['serverCertificateUrl'] ?? ''),
						'serverCertificateEnable' => (($_POST['serverCertificateEnable'] ?? 'false') === 'true'),
					];
					$result = $rps->server_edit($data);
					if ($result['success']) {
						message::add($text['message-updated'], 'positive', 5000);
					}
					else {
						message::add(($result['error'] ?? $text['message-error']), 'negative', 5000);
					}
				}
				break;

			case 'delete':
				if (permission_exists('yealink_rps_delete') || permission_exists('yealink_rps') || if_group('superadmin')) {
					$id = trim($_POST['id'] ?? '');
					if (empty($id)) {
						message::add($text['message-no_selection'], 'negative', 5000);
						rps_servers_redirect();
					}
					$result = $rps->server_delete([$id]);
					if ($result['success']) {
						message::add($text['message-deleted'], 'positive', 5000);
					}
					else {
						message::add(($result['error'] ?? $text['message-error']), 'negative', 5000);
					}
				}
				break;
		}

		rps_servers_redirect();
	}

//get the query string parameters
	$page = is_numeric($_GET['page'] ?? '') ? (int)$_GET['page'] : 0;

//rows per page
	$rows_per_page = intval($settings->get('domain', 'paging', 50));
	if ($rows_per_page < 1) {
		$rows_per_page = 50;
	}
	$skip = $page * $rows_per_page;

//query string for the paging controls
	$query_params = [];
	if (!empty($page)) { $query_params['page'] = $page; }
	$query_string = http_build_query($query_params);

//fetch the server list
	$servers = [];
	$total = 0;
	$list_error = '';
	if ($rps->is_configured()) {
		$result = $rps->server_list(['skip' => $skip, 'limit' => $rows_per_page, 'autoCount' => true]);
		if ($result['success']) {
			$payload = $result['payload'];
			if (is_array($payload)) {
				$total = isset($payload['total']) ? (int)$payload['total'] : 0;
				if (!empty($payload['data']) && is_array($payload['data'])) {
					$servers = $payload['data'];
				}
			}
		}
		else {
			$list_error = $result['error'] ?? '';
		}
	}

//paging controls
	list($paging_controls, $rows_per_page) = paging($total, $query_string, $rows_per_page);

//document
	$document['title'] = $text['title-yealink_rps_servers'];
	$document['description'] = '';

//includes the header
	require_once "resources/header.php";

//create the csrf token for the form
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

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

//add / edit server form
	$can_add = permission_exists('yealink_rps_add') || permission_exists('yealink_rps') || if_group('superadmin');
	$can_edit = permission_exists('yealink_rps_edit') || permission_exists('yealink_rps') || if_group('superadmin');
	if ($can_add || $can_edit) {
		echo "<div id='server-form-modal' class='modal-window'>\n";
		echo "	<div style='width: 600px; max-width: 90%;'>\n";
		echo "		<span title='" . $text['button-close'] . "' class='modal-close' onclick='modal_close();'>&times;</span>\n";
		echo "		<span class='modal-title'>" . $text['label-add_server'] . "</span>\n";
		echo "<form name='frm_server' id='frm_server' method='post' action='rps_servers.php'>\n";
		echo "<input type='hidden' name='id' id='server_id' value=''>\n";
		echo "<table width='100%' cellpadding='0' cellspacing='0' border='0'>\n";

		//server name
		echo "<tr>\n";
		echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>" . $text['label-server_name'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='serverName' id='server_name' value='' placeholder='example.com'>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//url
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-url'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='url' id='server_url' value='' placeholder='http://example.com'>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//auth name
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-auth_name'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='authName' id='server_auth_name' value=''>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//password
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-password'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='password' id='server_password' value='' placeholder='***'>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//certificate url
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-certificate_url'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='certificateUrl' id='server_certificate_url' value='' placeholder='https://...'>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//server certificate url
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-server_certificate_url'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<input class='formfld' type='text' name='serverCertificateUrl' id='server_certificate_url_2' value='' placeholder='https://...'>\n";
		echo "</td>\n";
		echo "</tr>\n";

		//server certificate enable
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>" . $text['label-server_certificate_enable'] . "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "	<select class='formfld' name='serverCertificateEnable' id='server_certificate_enable'>\n";
		echo "		<option value='false'>" . $text['label-no'] . "</option>\n";
		echo "		<option value='true'>" . $text['label-yes'] . "</option>\n";
		echo "	</select>\n";
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
	echo "	<div class='heading'><b>RPS Servers".$text['header-yealink_rps_servers']."</b>\n";
	echo "</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back', ''),'id'=>'btn_back','link'=>'yealink_rps.php'.($query_string ? '?'.$query_string : '')]);
	if ($can_add) {
		echo button::create(['type'=>'button','label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add', ''),'id'=>'btn_show_add','onclick'=>'yealink_show_add();','style'=>'margin-left: 5px;']);
	}
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

//server list table
	echo "<div class='card'>\n";
	echo "<table class='table' width='100%' cellpadding='0' cellspacing='0' border='0'>\n";
	echo "<thead>\n";
	echo "<tr>\n";
	echo "<th>" . $text['label-server_name'] . "</th>\n";
	echo "<th>" . $text['label-url'] . "</th>\n";
	echo "<th>" . $text['label-auth_name'] . "</th>\n";
	echo "<th class='center'>" . $text['label-server_certificate_enable'] . "</th>\n";
	echo "<th class='center'>" . $text['label-action'] . "</th>\n";
	echo "</tr>\n";
	echo "</thead>\n";
	echo "<tbody>\n";

	if (empty($servers)) {
		echo "<tr><td colspan='5' class='center'>" . $text['label-none'] . "</td></tr>\n";
	}
	foreach ($servers as $server) {
		$server_id = $server['id'] ?? ($server['_id'] ?? '');
		$server_name = $server['serverName'] ?? '';
		$url = $server['url'] ?? '';
		$auth = $server['authName'] ?? '';
		$cert_url = $server['certificateUrl'] ?? '';
		$server_cert_url = $server['serverCertificateUrl'] ?? '';
		$cert_enabled = !empty($server['serverCertificateEnable']);

		echo "<tr data-id='" . escape($server_id) . "' data-name='" . escape($server_name) . "' data-url='" . escape($url) . "' data-auth='" . escape($auth) . "' data-cert-url='" . escape($cert_url) . "' data-server-cert-url='" . escape($server_cert_url) . "' data-cert-enable='" . ($cert_enabled ? 'true' : 'false') . "'>\n";
		echo "	<td class='no-wrap'>" . escape($server_name) . "</td>\n";
		echo "	<td>" . escape($url) . "</td>\n";
		echo "	<td>" . escape($auth) . "</td>\n";
		echo "	<td class='center'>" . ($cert_enabled ? $text['label-yes'] : $text['label-no']) . "</td>\n";
		echo "	<td class='center'>\n";
		if ($can_edit) {
			echo "		" . button::create(['type' => 'button', 'label' => $text['button-edit'], 'class' => 'link', 'onclick' => 'yealink_edit_server(this);']) . "\n";
		}
		if (permission_exists('yealink_rps_delete') || permission_exists('yealink_rps') || if_group('superadmin')) {
			echo "		" . button::create(['type' => 'button', 'label' => $text['button-delete'], 'class' => 'link', 'style' => 'margin-left: 5px;', 'onclick' => 'yealink_delete_server(this);']) . "\n";
		}
		echo "	</td>\n";
		echo "</tr>\n";
	}

	echo "</tbody>\n";
	echo "</table>\n";
	echo "<div align='center'>" . escape($paging_controls) . "</div>\n";
	echo "</div>\n";
	echo "<br />\n";

//javascript
	echo "<script>
	var rps_token_name = '" . escape($token['name']) . "';
	var rps_token = '" . escape($token['hash']) . "';

	function yealink_edit_server(btn) {
		var row = btn.closest('tr');
		document.getElementById('server_id').value = row.dataset.id || '';
		document.getElementById('server_name').value = row.dataset.name || '';
		document.getElementById('server_url').value = row.dataset.url || '';
		document.getElementById('server_auth_name').value = row.dataset.auth || '';
		document.getElementById('server_password').value = '';
		document.getElementById('server_certificate_url').value = row.dataset.certUrl || '';
		document.getElementById('server_certificate_url_2').value = row.dataset.serverCertUrl || '';
		document.getElementById('server_certificate_enable').value = row.dataset.certEnable || 'false';
		var btn_add = document.getElementById('btn_add');
		var btn_edit = document.getElementById('btn_edit');
		if (btn_add) { btn_add.style.display = 'none'; }
		if (btn_edit) { btn_edit.style.display = 'inline-block'; }
		modal_open('server-form-modal');
	}

	function yealink_show_add() {
		document.getElementById('server_id').value = '';
		document.getElementById('server_name').value = '';
		document.getElementById('server_url').value = '';
		document.getElementById('server_auth_name').value = '';
		document.getElementById('server_password').value = '';
		document.getElementById('server_certificate_url').value = '';
		document.getElementById('server_certificate_url_2').value = '';
		document.getElementById('server_certificate_enable').value = 'false';
		var btn_add = document.getElementById('btn_add');
		var btn_edit = document.getElementById('btn_edit');
		if (btn_add) { btn_add.style.display = 'inline-block'; }
		if (btn_edit) { btn_edit.style.display = 'none'; }
		modal_open('server-form-modal', 'server_name');
	}

	function yealink_delete_server(btn) {
		var row = btn.closest('tr');
		var id = row.dataset.id || '';
		if (!id) { return; }
		if (!confirm('Delete server: ' + row.dataset.name)) { return; }
		var form = document.createElement('form');
		form.method = 'post';
		form.action = 'rps_servers.php';
		form.innerHTML = '<input type=\"hidden\" name=\"action\" value=\"delete\">' +
			'<input type=\"hidden\" name=\"id\" value=\"' + id + '\">' +
			'<input type=\"hidden\" name=\"' + rps_token_name + '\" value=\"' + rps_token + '\">' ;
		document.body.appendChild(form);
		form.submit();
	}
	</script>\n";

//includes the footer
	require_once "resources/footer.php";
