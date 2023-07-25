<?php

class ControllerModuleNewsmanremarketing extends Controller
{
	private $module_name = "newsmanremarketing";
	private $error = array();
	private $location = [
		'module' => 'extension/module',
		'marketplace' => 'marketplace/extension'
	];
	private $names = [
		'setting' => 'analytics_newsmanremarketing',
		'action' => 'action',
		'template_extension' => ''
	];

	public function index()
	{
		$this->load->language($this->location['module'] . '/' . $this->module_name);
		$this->load->model('setting/setting');
		$this->document->setTitle($this->language->get('heading_title'));

		// Initialize $data with values from the settings
		$data = [
			'newsmanremarketing_status' =>
				$this->model_setting_setting->getSetting('newsmanremarketing', $this->request->get['store_id'] ?? ""),
			$this->names['setting'] . '_trackingid' =>
				$this->model_setting_setting->getSetting($this->names['setting'] . '_trackingid', $this->request->get['store_id'] ?? "")
		];

		// If form is submitted
		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate())
		{
			$settings = [
				$this->names['setting'] . '_register' => $this->module_name,
				//$this->names['setting'] . '_status' => $this->request->post[$this->names['setting'] . '_status'],
				$this->names['setting'] . '_trackingid' => $this->request->post[$this->names['setting'] . '_trackingid']
			];

			$settingsStatus = [
				'newsmanremarketing' . '_status' => $this->request->post["newsmanremarketing" . '_status']
			];
		
			$this->insertSetting($this->names['setting'], $settings);
			$this->insertSetting("newsmanremarketing", $settingsStatus);
			$this->session->data['success'] = $this->language->get('text_success');

			//$this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=analytics', true));
			$this->response->redirect($this->url->link('extension/module', 'token=' . $this->session->data['token'], true));
		}

		if (isset($this->error['warning']))
		{
			$data['error_warning'] = $this->error['warning'];
		} else
		{
			$data['error_warning'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=analytics', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('analytics/newsmanremarketing', 'token=' . $this->session->data['token'], true)
		);

		// form
		$data['action'] = $this->url->link('module/newsmanremarketing', 'token=' . $this->session->data['token'], true);
	
		$data['cancel'] = $this->url->link('extension/module', 'token=' . $this->session->data['token'], true);

		// check if form submitted. Load settings posted if we reached this point.
		if ($this->request->server['REQUEST_METHOD'] == 'POST')
		{
			$data['newsmanremarketing' . '_status'] = $this->request->post['newsmanremarketing' . '_status'];
			$data[$this->names['setting'] . '_trackingid'] = $this->request->post[$this->names['setting'] . '_trackingid'];
		}

		// Load translations
		if (VERSION < '3')
		{
			foreach ([
				         'text_edit',
				         'text_status',
				         'text_enabled',
				         'text_disabled',
				         'text_button_save',
				         'text_button_cancel',
				         'heading_title',
				         'text_success',
				         'text_signup',
				         'entry_tracking',
				         'entry_status',
				         'error_code'
			         ] as $text)
			{
				$data[$text] = $this->language->get($text);
			}
		}

		$tracking_id = $this->config->get('analytics_newsmanremarketing_trackingid');
		$data['analytics_newsmanremarketing_trackingid'] = $tracking_id;

		//$this->response->setOutput($this->load->view($this->location['module'] . '/' . $this->module_name . $this->names['template_extension'], $data));

		$data["entry_tracking"] = "Remarketing ID";		

		$this->response->setOutput($this->load->view('module/newsmanremarketing.tpl', $data));
	}

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

	protected function validate()
	{
		if (!$this->user->hasPermission('modify', 'module/newsmanremarketing')) 
		{
			$this->error['warning'] = $this->language->get('error_permission');
		}
		if (!$this->request->post[$this->names['setting'] . '_trackingid'])
		{
			$this->error['warning'] = 'Newsman Remarketing code required';
		}

		return !$this->error;
	}
}

?>