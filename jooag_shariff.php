<?php
/**
 * @package    JooAg Shariff
 * @author     Joomla Agentur <Ufuk Avcu> <info@joomla-agentur.de> 
 * @link       https://joomla-agentur.de
 * @copyright  Copyright (c) 2009 - 2018 Joomla-Agentur All rights reserved.
 * @license    GNU General Public License version 2 or later;
 * @description A small Plugin to share Social Links without compromising their privacy!
 **/
defined('_JEXEC') or die;


/**
 * Class PlgContentJooag_Shariff
 *
 * @since  1.0.0
 **/
class plgSystemJooag_Shariff extends JPlugin
{
	/**
	 * The plugin context
	 *
	 * @var    string
	 */
	protected $context = null;
	
	/**
	 * The cms application
	 *
	 * @var    JApplicationCMS  The application
	 * @since  3.3.0
	 */
	protected $app;

	/**
	 * The constructor
	 *
	 * @param   string  $subject  The subject
	 * @param   object  $config   The Plugin config
	 *
	 * @since   1.0.0
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
	}

	/**
	 * Display the buttons before the article
	 *
	 * @param   string   $context   The context of the content being passed to the plugin.
	 * @param   mixed    &$article  An object with a "text" property
	 * @param   mixed    &$params   Additional parameters. See {@see PlgContentContent()}.
	 * @param   integer  $limitstart      Optional page number. Unused. Defaults to zero.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function onContentBeforeDisplay($context, &$article, &$params, $limitstart = 0)
	{
		if ($this->getAccessGeneral($context, $article, 'BeforeDisplay') == 1 && !$params['show_introtext'])
		{
			return $this->generateHTML($config = array());
		}
	}
	
	/**
	 * Display the buttons after the article
	 *
	 * @param   string   $context   The context of the content being passed to the plugin.
	 * @param   mixed    &$article  An object with a "text" property
	 * @param   mixed    &$params   Additional parameters. See {@see PlgContentContent()}.
	 * @param   integer  $limitstart      Optional page number. Unused. Defaults to zero.
	 *
	 * @return  string
	 */
	public function onContentAfterDisplay($context, &$article, &$params, $limitstart = 0)
	{
		if ($this->getAccessGeneral($context, $article, 'AfterDisplay') == 1 && !$params['show_introtext'])
		{
			return $this->generateHTML($config = array());
		}
	}

	public function onBeforeRender()
	{
		$buffering = '';
		
		if ($this->getAccessGeneral('com_everywhere', '', 'BeforeDisplay') == 1)
		{	
			$buffering .= $this->generateHTML($config = array());
			$buffering .= JFactory::getDocument()->getBuffer('component');
			JFactory::getDocument()->setBuffer($buffering, 'component');
		}

		if ($this->getAccessGeneral('com_everywhere', '', 'AfterDisplay') == 1)
		{
			$buffering .= JFactory::getDocument()->getBuffer('component');
			$buffering .= $this->generateHTML($config = array());
			JFactory::getDocument()->setBuffer($buffering, 'component');
		}
	}

	/**
	 * Place shariff in your articles and modules via {shariff} shorttag
	 */
	public function onContentPrepare($context, &$article, &$params, $page = 0)
	{
		if (preg_match_all('/{shariff\ ([^}]+)\}|\{shariff\}/', $article->text, $matches) &&
			$this->getAccessGeneral('com_shorttag', $article, 'BeforeDisplay') == 1)
		{
			$configs = explode(' ', $matches[1][0]);
			$config = array();
			
			foreach ($configs as $item)
			{
				list($key, $value) = explode("=", $item);
				$config[$key] = $value;
			}

			$this->params->get('com_shorttag') ? $config['shorttag'] = 1 : $config['shorttag'] = 0;
			$article->text = str_replace($matches[0][0], $this->generateHTML($config), $article->text);
		}
	}

	private function getAccessGeneral($context, $article, $position)
	{
		if ($this->app->isClient('administrator'))
		{
			return;
		}

		foreach ($this->params->get('disallow_components') as $component){

			$jinput = JFactory::getApplication()->input;

			if($component->disallow_components == $jinput->get('option')){
				return;
			}
			
		}

		if (in_array($position, (array) $this->params->get('output_position')))
		{
			if ($this->getAccessComContent($context ,$article) == 1 && $this->getAccessMenu($context) == 1)
			{
			    return 1;
			}

			if ($this->params->get('com_everywhere') == 1 && $context == 'com_everywhere' && $this->getAccessMenu('com_everywhere.placeholder') == 1)
			{
				return 1;
			}
		}

		if ($this->params->get('com_shorttag') == 1 && $context == 'com_shorttag')
		{
			return 1;
		}

		return 0;
	}

	private function getAccessMenu($context)
	{
		$menuAccess = 0;
		$menu = $this->app->getMenu()->getActive();
		is_object($menu) ? $actualMenuId = $menu->id : $actualMenuId = $this->app->input->getInt('Itemid', 0);
		$context = explode('.', $context);
		$menuIds = (array) $this->params->get($context[0] . '_menu_select');
		$this->params->get($context[0] . '_menu_assignment') == 0 ? $menuAccess = 0 : '';
		$this->params->get($context[0] . '_menu_assignment') == 1 ? $menuAccess = 1 : '';

		if ($this->params->get($context[0] . '_menu_assignment') == 2)
		{
			$menuAccess = 0;
			in_array($actualMenuId, $menuIds) ? $menuAccess = 1 : '';
		}

		if ($this->params->get($context[0] . '_menu_assignment') == 3)
		{
			$menuAccess = 1;
			in_array($actualMenuId, $menuIds) ? $menuAccess = 0 : '';
		}

		return $menuAccess;
	}

	private function getAccessComContent($context, $article)
	{
		$access = 0;

		if ($this->params->get('com_content') == 1 && $context == 'com_content.article')
		{
			$catIds = (array) $this->params->get('com_content_category_select');
			$this->params->get('com_content_category_assignment') == 0 ? $access = 0 : '';
			$this->params->get('com_content_category_assignment') == 1 ? $access = 1 : '';

			if ($this->params->get('com_content_category_assignment') == 2)
			{
				$access = 0;
				isset($article->catid) && in_array($article->catid, $catIds) ? $access = 1 : '';
			}

			if ($this->params->get('com_content_category_assignment') == 3)
			{
				$access = 1;
				isset($article->catid) && in_array($article->catid, $catIds) ? $access = 0 : '';
			}
		}
		
		return $access;
	}

	/**
	 * Shariff output generation
	 */
	public function generateHTML($config)
	{
		if (!$this->params->get('services'))
		{
			return;
		}
		
		jimport('joomla.filesystem.folder');
		if (!JFolder::exists(JPATH_SITE . '/cache/plg_jooag_shariff') && $this->params->get('shariff_counter'))
		{
			JFolder::create(JPATH_SITE . '/cache/plg_jooag_shariff', 0755);
		}

		$doc = JFactory::getDocument();

		if ($this->params->get('shariffcss') != '-1')
		{
			$doc->addStyleSheet(JURI::root() . 'media/plg_jooag_shariff/assets/' . $this->params->get('shariffcss'));
		}

		if ($this->params->get('shariffjs') != '-1')
		{	
			JHtml::_('jquery.framework');
			$doc->addScript(JURI::root() . 'media/plg_jooag_shariff/assets/' . $this->params->get('shariffjs'));
			$doc->addScriptDeclaration('jQuery(document).ready(function() {var buttonsContainer = jQuery(".shariff");new Shariff(buttonsContainer);});');
		}

		$html  = '<div class="shariff"';
		$html .= ($this->params->get('shariff_counter')) ? ' data-backend-url="/plugins/system/jooag_shariff/backend/"' : '';
		$html .= ' data-lang="' . JLanguageHelper::getLanguages()['0']->sef . '"';
		$html .= (array_key_exists('orientation', $config)) ? ' data-orientation="'.$config['orientation'] . '"' : ' data-orientation="'.$this->params->get('data_orientation') . '"';
		$html .= (array_key_exists('theme', $config)) ? ' data-theme="'.$config['theme'].'"' : ' data-theme="' . $this->params->get('data_theme') . '"';
		$html .= (array_key_exists('style', $config)) ? ' data-button-style="'.$config['style'].'"' : ' data-button-style="' . $this->params->get('data_style') . '"';
		$html .= 'data-media-url="null"';
		
		foreach($this->params->get('services') as $service)
		{
			if($service->special_data_info_display)
			{
				$html .= ' data-info-display="' . $service->special_data_info_display . '"';
			}

			if($service->special_data_info_url)
			{
				jimport('joomla.database.table');
				$item =	JTable::getInstance("content");
				$item->load($service->special_data_info_url);
				require_once JPATH_SITE . '/components/com_content/helpers/route.php';
				$link = JRoute::_(ContentHelperRoute::getArticleRoute($item->id, $item->catid, $item->language));
				$html .= ' data-info-url="' . $link . '"';
			}

			foreach($service as $key => $option)
			{
				if ($option && !preg_match('/^special_/', $key) && !preg_match('/^services/', $key) )
				{
					$html .= $key . '="' . $option . '"';
				}

			}

			if ($service->services)
			{
				$services[] = $service->services;
			}
		}

		$html .= ' data-services="' . htmlspecialchars(json_encode(array_map('strtolower', $services))) . '"';
		$html .= '></div>';
		return $html;
	}

	/**
	 * Generator for shariff.json File
	 *
	 * @return void|string
	 */
	public function onExtensionBeforeSave($context, $table, $isNew)
	{
		if ($table->name == 'PLG_JOOAG_SHARIFF')
		{
			$params = json_decode($table->params);

			$json = new stdClass;

			if ($params->data_url == 0)
			{
				$json->domains = (array) JUri::getInstance()->getHost();
			}
			else
			{
				foreach($params->data_url_custom as $domain)
				{
					$json->domains[] = $domain->custom_domains;
				}
			}

			$services = array('AddThis','Buffer','Facebook','Flattr','Pinterest','Reddit','StumbleUpon','Xing','Vk');

			foreach($params->services as $service)
			{
				$json->services[] = $service->services;

				if ($service->services == 'Facebook')
				{
					if($service->special_facebook_app_id)
					{
						$json->Facebook->app_id = $service->special_facebook_app_id;
					}
					
					if($service->special_facebook_app_secret)
					{
						$json->Facebook->secret = $service->special_facebook_app_secret;
					}
				}
			}

			foreach ($json->services as $i => $service)
			{
				if (!in_array($service, $services))
				{
					unset($json->services[$i]);
				}
			}

			$json->services = array_values($json->services);
			$json->cache->cacheDir = JPATH_SITE . '/cache/plg_jooag_shariff';
			$json->cache->ttl = $params->cache_time;
			$json->client->timeout = $params->client_timeout;

			if ($params->cache)
			{
				$json->cache->adapter = $params->cache_handler;

				if ($params->cache_handler == 'file')
				{
					$json->cache->adapter = 'filesystem';
				}
			}

			$json = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
			JFile::write(JPATH_PLUGINS . '/system/jooag_shariff/backend/shariff.json', $json);
		}
	}
}
