<?php

/**
 * Newsman Newsletter Sync
 *
 * @author Teamweb <razvan@teamweb.ro>
 */
class ControllerModuleNewsmanImport extends Controller
{

	private $_name = 'newsman_import';

	/**
	 * Generate messages
	 */
	private function _messages()
	{
		/**
		 * Warnings
		 */
		if (isset($this->session->data['error']))
		{
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} else
		{
			if (empty($data['error_warning']))
			{
				$data['error_warning'] = '';
			}
		}

		/**
		 * Posts
		 */

		if (isset($this->session->data['success']))
		{
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else
		{
			if (empty($data['success']))
			{
				$data['success'] = '';
			}
		}
	}

	/**
	 * __construct()
	 *
	 * @param type $registry
	 */
	public function __construct($registry)
	{
		parent::__construct($registry);

		$this->load->language('module/' . $this->_name);

		$this->document->setTitle($this->language->get('heading_title'));

		// No need for this (index());
		/*	$data['breadcrumbs'] = array();

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_form'),
				'href' => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
				'separator' => false
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_module'),
				'href' => $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'),
				'separator' => ' :: '
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('module/' . $this->_name, 'token=' . $this->session->data['token'], 'SSL'),
				'separator' => ' :: '
			);

			$data['back'] = $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL');
			$data['token'] = $this->session->data['token'];
			$data['_name'] = $this->_name;
		*/

		// $this->_messages();
	}

	/**
	 * Main
	 */


	/*
	 *Insert into settings override
	 */
	public function insertSetting($code, $data, $store_id = 0)
	{
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "'");

		foreach ($data as $key => $value)
		{
			if (!is_array($value))
			{
				$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
			} else
			{
				$this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape(json_encode($value)) . "', serialized = '1'");
			}
		}
	}

	public function index()
	{
		// Load models
		$this->load->model('setting/setting');
		$this->load->model('module/newsman_import');

		$this->isOauth($data);

		$data['step'] = 1;

		if ($this->request->server['REQUEST_METHOD'] == 'POST')
		{
			if ($this->request->post['step'] == "2")
			{
				$settings = (array)$this->model_setting_setting->getSetting($this->_name);

				$settings['list_id'] = $this->request->post['list'];
				$settings['api_key'] = $this->request->post['api_key'];
				$settings['user_id'] = $this->request->post['user_id'];

				if (!array_key_exists("syncFlag", $settings))
				{
					$settings['syncFlag'] = false;
				}

				$this->insertSetting($this->_name, $settings);

				$data['step'] = 2;
				$data['customer_groups'] = $this->model_module_newsman_import->get_customer_groups();
				$data['customer_groups'][] = array('customer_group_id' => 0, 'name' => 'Newsletter');

			} else
			{
				if ($this->request->post['step'] == "1")
				{
					$settings = (array)$this->model_setting_setting->getSetting($this->_name);

					$settings['import_type'] = $this->request->post['import_type'];
					if ($this->request->post['import_type'] == 2)
					{
						$settings['segments'] = $this->request->post['segments'];
					}
					if ($this->request->post['sync'] == 1)
					{
						$settings["syncFlag"] = true;
						$this->session->data['sync'] = 1;
					}
					elseif($this->request->post['sync'] == 2){
						$this->session->data['sync'] = 2;
					}
					else
					{
						$settings["syncFlag"] = false;
					}
					if (!isset($settings['last_data_time']))
					{
						$settings['last_data_time'] = date("Y-m-d H:i:s", strtotime('-2 hour'));
					}
					if ($this->request->post['reset'] == '1')
					{
						$this->model_setting_setting->deleteSetting($this->_name, $settings);
						$newSettings = array();
						$newSettings['last_data_time'] = $settings['last_data_time'];
						$this->insertSetting($this->_name, $newSettings);
					} else
					{
						$this->insertSetting($this->_name, $settings);
						$data['success'] = $this->language->get('text_success');
					}
					//this line below will redirect to modules page after post (no need for it)
					//$this->response->redirect($this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'));
				}
			}
		}

		$data['settings'] = (array)$this->model_setting_setting->getSetting($this->_name);
	
		if (isset($this->session->data['sync']))
		{
			if($this->session->data['sync'] == 1 || $this->session->data['sync'] == 2)
			{
				$data['queries'] = $this->get_queries($data['settings']);
				unset($this->session->data['sync']);
			}
		}

		$data['action'] = $this->url->link('module/' . $this->_name, 'token=' . $this->session->data['token'], 'SSL');

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_module'),
			'href' => $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('module/' . $this->_name, 'token=' . $this->session->data['token'], 'SSL'),
			'separator' => ' :: '
		);

		$data['back'] = $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL');
		$data['token'] = $this->session->data['token'];
		$data['_name'] = $this->_name;


		/**
		 * Warnings
		 */
		if (isset($this->session->data['error']))
		{
			$data['error_warning'] = $this->session->data['error'];

			unset($this->session->data['error']);
		} else
		{
			if (empty($data['error_warning']))
			{
				$data['error_warning'] = '';
			}
		}

		/**
		 * Posts
		 */
		if (isset($this->session->data['success']))
		{
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else
		{
			if (empty($data['success']))
			{
				$data['success'] = '';
			}
		}

		/**
		 * Posts
		 */

		// Template settings
		//$this->template = 'module/' . $this->_name . '.tpl';
		$data['token'] = $this->session->data['token'];
		$data['cancel'] = $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL');
		$data['text_connected'] = 'Success: Authentication successfull!';
		$data['text_list'] = $this->language->get('text_list');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_back'] = $this->language->get('button_back');
		$data['text_connect'] = $this->language->get('text_connect');
		$data['entry_api_key'] = $this->language->get('entry_api_key');
		$data['entry_user_id'] = $this->language->get('entry_user_id');
		$data['button_connect'] = $this->language->get('button_connect');
		$data['heading_title'] = $this->language->get('heading_title');
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$data['error_not_connected'] = 'Error connecting...';
		$data['button_cancel'] = $this->language->get('button_cancel');

		/*
		 *Text import list
		 */
		$data['text_import_list'] = 'Import customers in the main List';
		$data['text_import_segments'] = 'Import customer groups to Newsman Segments ';
		$data['text_sync'] = 'Manual Sync';
		$data['button_sync_now'] = 'Sync Now';
		$data['button_reset'] = 'Reset';
		$data['text_autosync'] = 'auto';
		$data['text_segment'] = "Newsman segment";
		$data['text_import_in'] = "import in";
		$data['text_customer_group'] = "import in";

		//$data['breadcrumbs'] = $this->load->controller('common/breadcrumbs');

		$this->isOauth($data);

		$this->response->setOutput($this->load->view('module/' . $this->_name . '.tpl', $data));

		/*	$this->children = array(
				'common/header',
				'common/footer'
			);
	*/
		// $this->response->setOutput($this->load->view('catalog/category_form.tpl', $data));
	}

	public function isOauth(&$data, $checkOnlyIsOauth = false){
		$this->load->model('setting/setting');
		$this->load->model('module/newsman_import');

		$redirUri = urlencode("https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
		$redirUri = str_replace("amp%3B", "", $redirUri);
		$data["oauthUrl"] = "https://newsman.app/admin/oauth/authorize?response_type=code&client_id=nzmplugin&nzmplugin=Opencart&scope=api&redirect_uri=" . $redirUri;

		//oauth processing

		$error = "";
		$dataLists = array();
		$data["oauthStep"] = 1;
		$viewState = array();

		if(!empty($_GET["error"])){
			switch($error){
				case "access_denied":
					$error = "Access is denied";
					break;
				case "missing_lists":
					$error = "There are no lists in your NewsMAN account";
					break;
			}
		}else if(!empty($_GET["code"])){

			$authUrl = "https://newsman.app/admin/oauth/token";

			$code = $_GET["code"];

			$redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

			$body = array(
				"grant_type" => "authorization_code",
				"code" => $code,
				"client_id" => "nzmplugin",
				"redirect_uri" => $redirect
			);
			
			$ch = curl_init($authUrl);
			
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			
			$response = curl_exec($ch);
			
			if (curl_errno($ch)) {
				$error .= 'cURL error: ' . curl_error($ch);
			}
			
			curl_close($ch);
			
			if ($response !== false) {

				$response = json_decode($response);

				$data["creds"] = json_encode(array(
					"newsman_userid" => $response->user_id,
					"newsman_apikey" => $response->access_token
					)
				);

				foreach($response->lists_data as $list => $l){
					$dataLists[] = array(
						"id" => $l->list_id,
						"name" => $l->name
					);
				}	

				$data["dataLists"] = $dataLists;

				$data["oauthStep"] = 2;
			} else {
				$error .= "Error sending cURL request.";
			}  
		}

		if(!empty($_POST["oauthstep2"]) && $_POST['oauthstep2'] == 'Y')
		{
			if(empty($_POST["newsman_list"]) || $_POST["newsman_list"] == 0)
			{
				$step = 1;
			}
			else
			{
				$creds = stripslashes($_POST["creds"]);
				$creds = html_entity_decode($creds);
				$creds = json_decode($creds, true);

				$this->load->model('module/newsman_import');
				$client = $this->model_module_newsman_import->getNewsmanClient($creds["newsman_userid"], $creds["newsman_apikey"]);
				$ret = $client->remarketing->getSettings($_POST["newsman_list"]);

				$remarketingId = $ret["site_id"] . "-" . $ret["list_id"] . "-" . $ret["form_id"] . "-" . $ret["control_list_hash"];

				//set feed
				$url = "https://" . $_SERVER['SERVER_NAME'] . "/index.php?route=module/newsman_import&newsman=products.json&apikey=" . $creds["newsman_apikey"];		

				try{
					$ret = $client->feeds->setFeedOnList($_POST["newsman_list"], $url, $_SERVER['SERVER_NAME'], "NewsMAN");	
				}
				catch(Exception $ex)
				{			
					//the feed already exists
				}

				$settings = (array) $this->model_setting_setting->getSetting($this->_name);
				$settings['list_id'] = $_POST["newsman_list"];
				$settings['api_key'] = $creds["newsman_apikey"];
				$settings['user_id'] = $creds["newsman_userid"];

				$this->insertSetting($this->_name, $settings);

				$settings = [
					"analytics_newsmanremarketing" . '_register' => "newsmanremarketing",
					"analytics_newsmanremarketing" . '_trackingid' => $remarketingId
				];
	
				$settingsStatus = [
					'newsmanremarketing' . '_status' => 1
				];
			
				$this->model_setting_setting->editSetting("analytics_newsmanremarketing", $settings);
				$this->model_setting_setting->editSetting("newsmanremarketing", $settingsStatus);
			}
		}

		$settings = (array) $this->model_setting_setting->getSetting($this->_name);

		if(empty($settings['api_key']))
		{
			$data["isOauth"] = true;
		}
		else{
			$data["isOauth"] = false;
		}
	}

	/**
	 * Get lists
	 *
	 * @return string
	 */
	public function get_lists()
	{
		$this->load->model('module/newsman_import');
		$lists = $this->model_module_newsman_import->get_lists();
		echo json_encode($lists);
	}

	/**
	 * Get segments
	 *
	 * @return string
	 */
	public function get_segments()
	{
		$this->load->model('module/newsman_import');
		$segments = $this->model_module_newsman_import->get_segments();
		echo json_encode($segments);
	}

	/**
	 * Get queries
	 *
	 * @return string
	 */
	public function get_queries($data)
	{
		$this->load->model('module/newsman_import');
		$queries = $this->model_module_newsman_import->get_queries($data);
		return json_encode($queries);
	}

	/**
	 * Run query
	 *
	 * @return string
	 */
	public function run_query()
	{
		$this->load->model('module/newsman_import');
		$this->load->model('setting/setting');
		$settings = (array)$this->model_setting_setting->getSetting($this->_name);
		$settings['last_data_time'] = date("Y-m-d H:i:s", strtotime('-2 hour'));
		$this->insertSetting($this->_name, $settings);
		echo $this->model_module_newsman_import->run_query($_POST['api_key'], $_POST['user_id'], $_POST['list_id'], $_POST['query']);
	}

	/**
	 * Check the credentials of the user
	 *
	 * @param string $permission
	 * @return boolean
	 */
	private function userPermission($permission = 'modify')
	{
		$this->language->load('module/' . $this->_name);

		if (!$this->user->hasPermission($permission, 'module/' . $this->_name))
		{
			$this->session->data['error'] = $this->language->get('error_permission');
			return false;
		} else
		{
			return true;
		}
	}

	/**
	 * Module installation
	 */
	public function install()
	{
		/**
		 * Check whether the user has permissions
		 */
		if ($this->userPermission())
		{
			$this->load->model('module/newsman_import');

			$this->model_module_newsman_import->install();

			$this->session->data['success'] = $this->language->get('success_install');

			unset($this->session->data['error']);

			/**
			 * Make sure the plug is on the list
			 */

			$this->load->model('extension/extension');

			if (!in_array($this->_name, $this->model_extension_extension->getInstalled('module')))
			{

				$this->model_extension_extension->install('module', $this->_name);
			}
		} else
		{
			if (!isset($this->session->data['error_install']))
			{
				$this->session->data['error_install'] = true;

				$this->load->model('extension/extension');
				$this->model_extension_extension->uninstall('module', $this->_name);

				$this->response->redirect($this->url->link('extension/module/install', 'token=' . $this->session->data['token'] . '&extension=' . $this->_name, 'SSL'));
			} else
			{
				$this->session->data['error'] = $this->language->get('error_permission');

				$this->response->redirect($this->url->link('extension/module/uninstall', 'token=' . $this->session->data['token'] . '&extension=' . $this->_name, 'SSL'));
			}
		}

		// Redirect module
		$this->response->redirect($this->url->link('module/' . $this->_name, 'token=' . $this->session->data['token'], 'SSL'));
	}

	/**
	 * Uninstalling the extensions
	 */
	public function uninstall()
	{
		/**
		 * Check whether the user has permissions
		 */
		if ($this->userPermission())
		{
			$this->load->model('module/newsman_import');

			$this->model_module_newsman_import->uninstall();

			if (isset($this->session->data['error_install']))
			{
				unset($this->session->data['error_install']);
			} else
			{
				$this->session->data['success'] = $this->language->get('success_uninstall');
			}

			$this->load->model('extension/extension');
			$this->model_extension_extension->uninstall('module', $this->_name);
		}

		// redirect to the list of modules
		$this->response->redirect($this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'));
	}
}