<?php

/**
 * @package     JooAg Shariff
 * @copyright   (C) 2014 - 2026 Joomla Agentur, newtastic GmbH
 * @license     GNU General Public License version 2 or later; see LICENSE
 *
 * Kept in plain PHP 7.0 syntax on purpose: on a Joomla 3 or 4 site this script must still run and refuse the
 * installation with a message instead of breaking the site.
 */

defined('_JEXEC') or die;

class PlgSystemJooag_shariffInstallerScript
{
	/**
	 * Requirements of this version. Joomla 3 and 4 sites keep JooAg Shariff 6.0.0.
	 */
	private $minimumJoomla = '5.0.0';
	private $minimumPhp = '8.1.0';

	/**
	 * Left-overs of versions 4 – 7 (Heise Shariff, Shariff backend with bundled libraries, old field and language
	 * files). Joomla removes most of them itself on update; these are deleted when they are still there.
	 */
	private $oldFolders = array(
		'plugins/system/jooag_shariff/backend',
		'plugins/system/jooag_shariff/fields',
		'plugins/system/jooag_shariff/assets',
		'media/plg_jooag_shariff',
		'cache/plg_jooag_shariff',
	);
	private $oldFiles = array(
		'plugins/system/jooag_shariff/jooag_shariff.php',
		'plugins/system/jooag_shariff/jooag_shariff-services.xml',
		'plugins/system/jooag_shariff/jooag_shariff-customDomains.xml',
		'plugins/system/jooag_shariff/jooag_shariff-disallowComponents.xml',
		'plugins/system/jooag_shariff/README.md',
		'plugins/system/jooag_shariff/language/en-GB/en-GB.plg_system_jooag_shariff.ini',
		'plugins/system/jooag_shariff/language/en-GB/en-GB.plg_system_jooag_shariff.sys.ini',
	);

	public function preflight($type, $parent)
	{
		if ($type === 'uninstall')
		{
			return true;
		}

		$errors = array();

		if (version_compare(PHP_VERSION, $this->minimumPhp, '<'))
		{
			$errors[] = sprintf('JooAg Shariff 8 needs PHP %s or later, this site runs PHP %s. Nothing was changed.', $this->minimumPhp, PHP_VERSION);
		}

		if (version_compare(JVERSION, $this->minimumJoomla, '<'))
		{
			$errors[] = sprintf('JooAg Shariff 8 needs Joomla %s or later, this site runs Joomla %s. Please keep JooAg Shariff 6.0.0 on this site. Nothing was changed.', $this->minimumJoomla, JVERSION);
		}

		if (!$errors)
		{
			$installed = $this->installedVersion();

			if ($installed !== '' && version_compare($installed, '4.0.0', '<'))
			{
				$errors[] = sprintf('JooAg Shariff %s is too old for an update: please note its settings, uninstall it and install JooAg Shariff 8 afresh.', $installed);
			}
		}

		foreach ($errors as $error)
		{
			$this->message($error, 'error');
		}

		return !$errors;
	}

	public function install($parent)
	{
		return true;
	}

	public function update($parent)
	{
		return true;
	}

	public function uninstall($parent)
	{
		$this->delete(array('cache/plg_jooag_shariff', 'administrator/cache/plg_jooag_shariff', 'media/plg_jooag_shariff'), array());

		return true;
	}

	public function postflight($type, $parent)
	{
		if ($type !== 'install' && $type !== 'update' && $type !== 'discover_install')
		{
			return true;
		}

		$this->delete($this->oldFolders, $this->oldFiles);

		if ($type === 'update')
		{
			$this->updateParams();
		}

		return true;
	}

	/**
	 * Settings of older versions: the stylesheet and script choice now has one included file each, and the button
	 * style "color" (a wrong default up to 7.0.0) becomes "standard". Services that no longer exist are reported.
	 */
	private function updateParams()
	{
		$db    = $this->db();
		$query = $db->createQuery()
			->select($db->quoteName(array('extension_id', 'params')))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
			->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
			->where($db->quoteName('element') . ' = ' . $db->quote('jooag_shariff'));
		$row = $db->setQuery($query)->loadObject();

		if (!$row)
		{
			return;
		}

		$params = json_decode((string) $row->params, true);

		if (!is_array($params))
		{
			return;
		}

		foreach (array('shariffcss' => 'shariff.complete.css', 'shariffjs' => 'shariff.complete.js') as $key => $included)
		{
			if (isset($params[$key]) && (string) $params[$key] !== '-1')
			{
				$params[$key] = $included;
			}
		}

		if (!isset($params['data_style']) || !in_array($params['data_style'], array('standard', 'icon', 'icon-count'), true))
		{
			$params['data_style'] = 'standard';
		}

		// Up to version 7 one list "output_position" served articles and "show everywhere"; now each place has its own
		// position. An empty list showed nothing automatically, which is now "off" for both places.
		if (!isset($params['com_content_position']) || !isset($params['com_everywhere_position']))
		{
			$old    = isset($params['output_position']) ? (array) $params['output_position'] : array('BeforeDisplay');
			$before = in_array('BeforeDisplay', $old, true);
			$after  = in_array('AfterDisplay', $old, true);

			if (!$before && !$after)
			{
				$params['com_content']    = '0';
				$params['com_everywhere'] = '0';
			}

			$position = $before && $after ? 'both' : ($after ? 'after' : 'before');
			$params  += array('com_content_position' => $position, 'com_everywhere_position' => $position);
		}

		$retired = array();

		foreach ((array) (isset($params['services']) ? $params['services'] : array()) as $service)
		{
			$name = isset($service['services']) ? strtolower((string) $service['services']) : '';

			if (in_array($name, array('addthis', 'flattr', 'googleplus', 'pocket', 'stumbleupon', 'tencent-weibo'), true))
			{
				$retired[] = $service['services'];
			}
		}

		$query = $db->createQuery()
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
			->where($db->quoteName('extension_id') . ' = ' . (int) $row->extension_id);
		$db->setQuery($query)->execute();

		if ($retired)
		{
			$this->message(sprintf('JooAg Shariff: %s no longer exist and are no longer shown. You can remove them under Services.', implode(', ', array_unique($retired))), 'notice');
		}
	}

	private function installedVersion()
	{
		$db    = $this->db();
		$query = $db->createQuery()
			->select($db->quoteName('manifest_cache'))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
			->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
			->where($db->quoteName('element') . ' = ' . $db->quote('jooag_shariff'));
		$cache = json_decode((string) $db->setQuery($query)->loadResult());

		return isset($cache->version) ? (string) $cache->version : '';
	}

	private function delete(array $folders, array $files)
	{
		foreach ($folders as $folder)
		{
			$path = JPATH_ROOT . '/' . $folder;

			if (is_dir($path))
			{
				try
				{
					\Joomla\Filesystem\Folder::delete($path);
				}
				catch (\Throwable $e)
				{
					$this->message(sprintf('JooAg Shariff: could not remove %s (%s).', $folder, $e->getMessage()), 'warning');
				}
			}
		}

		foreach ($files as $file)
		{
			$path = JPATH_ROOT . '/' . $file;

			if (is_file($path) && !@unlink($path))
			{
				$this->message(sprintf('JooAg Shariff: could not remove %s.', $file), 'warning');
			}
		}
	}

	private function db()
	{
		return \Joomla\CMS\Factory::getContainer()->get('Joomla\\Database\\DatabaseInterface');
	}

	private function message($text, $type)
	{
		if (class_exists('Joomla\\CMS\\Factory'))
		{
			\Joomla\CMS\Factory::getApplication()->enqueueMessage($text, $type);
		}
		elseif (class_exists('JFactory'))
		{
			\JFactory::getApplication()->enqueueMessage($text, $type);
		}
	}
}
