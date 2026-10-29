<?php

	//application details
		$apps[$x]['name'] = "Yealink RPS";
		$apps[$x]['uuid'] = "d4c9e8f7-6a5b-4c3d-8e2f-1a0b9c8d7e6f";
		$apps[$x]['category'] = "Vendor";
		$apps[$x]['subcategory'] = "";
		$apps[$x]['version'] = "1.0";
		$apps[$x]['license'] = "Mozilla Public License 1.1";
		$apps[$x]['url'] = "http://www.fusionpbx.com";
		$apps[$x]['description']['en-us'] = "Manage Yealink phones through the Yealink RPS (Remote Provisioning Service) Management Platform JSON REST API.";
		$apps[$x]['description']['en-gb'] = "Manage Yealink phones through the Yealink RPS (Remote Provisioning Service) Management Platform JSON REST API.";
		$apps[$x]['description']['de-de'] = "Verwalten Sie Yealink-Telefone über die Yealink-RPS-Management-Plattform (JSON REST API).";
		$apps[$x]['description']['es-cl'] = "Administra teléfonos Yealink a través de la plataforma de gestión Yealink RPS (JSON REST API).";
		$apps[$x]['description']['fr-fr'] = "Gérez les téléphones Yealink via la plateforme de gestion Yealink RPS (JSON REST API).";
		$apps[$x]['description']['pt-pt'] = "Gerir telefones Yealink através da plataforma de gestão Yealink RPS (JSON REST API).";

	//permissions
		$y=0;
		$apps[$x]['permissions'][$y]['name'] = "yealink_rps";
		$apps[$x]['permissions'][$y]['menu']['uuid'] = "f1a2b3c4-5d6e-4f7a-8b9c-1d2e3f4a5b6c";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "yealink_rps_view";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "yealink_rps_add";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "yealink_rps_edit";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "yealink_rps_delete";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;
		$apps[$x]['permissions'][$y]['name'] = "yealink_rps_settings";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$y++;


	//default settings
	//the first 4 uuids match the rows already present in v_default_settings;
	//existing rows are never overwritten (domains::settings() only inserts missing uuids)
		$y=0;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "b6a2f8c1-4e1a-4d2b-9c3f-1a2b3c4d5e6f";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "yealink_rps";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "enabled";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "boolean";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "false";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "false";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Enable or disable the Yealink RPS integration.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "c7b3a9d2-5f2b-4e3c-8d4a-2b3c4d5e6f7a";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "yealink_rps";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "base_url";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "https://dm.yealink.com";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "false";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Base URL of the Yealink RPS API, for example https://dm.yealink.com or https://us-api.ymcs.yealink.com.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "d8c4b0e3-6a3c-4f4d-9e5b-3c4d5e6f7a8b";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "yealink_rps";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "access_key_id";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "false";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Your Yealink RPS AccessKey Id.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "e9d5c1f4-7b4d-4a5e-8f6c-4d5e6f7a8b9c";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "yealink_rps";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "access_key_secret";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "false";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Your Yealink RPS AccessKey Secret.";
		$y++;
		$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "a1b7c3d5-2c8d-4b4f-8e6a-7d9c1b2f3a4b";
		$apps[$x]['default_settings'][$y]['default_setting_category'] = "yealink_rps";
		$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "auth_mode";
		$apps[$x]['default_settings'][$y]['default_setting_name'] = "list";
		$apps[$x]['default_settings'][$y]['default_setting_value'] = "auto";
		$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "false";
		$apps[$x]['default_settings'][$y]['default_setting_description'] = "Authentication mode: auto, x-ca or developer.";
