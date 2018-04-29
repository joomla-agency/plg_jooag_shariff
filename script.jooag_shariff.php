<?php
/**
 * @package    JooAg_Shariff
 *
 * @author     Joomla Agentur <info@joomla-agentur.de>
 * @copyright  Copyright (c) 2009 - 2015 Joomla-Agentur All rights reserved.
 * @license    GNU General Public License version 2 or later;
 * @description A small Plugin to share Social Links without compromising their privacy!
 **/
 
defined('_JEXEC') or die();

class PlgSystemJooag_shariffInstallerScript
{
	public function preflight($type, $parent)
	{
		$minPHP = '5.6.0';
		$minJoomla = '3.8.0';
		$errorCount = '0';
		
		if(!version_compare(PHP_VERSION, $minPHP, 'ge'))
		{
			$error = "<p>You need PHP $minPHP or later to install this extension!<br/>Actual PHP Version:".PHP_VERSION."</p>";
			JLog::add($error, JLog::WARNING, 'jerror');
			$errorCount++;
		}

		if(!version_compare(JVERSION, $minJoomla, 'ge'))
		{
			$error = "<p>You need Joomla! $minJoomla or later to install this extension!<br/>Actual Joomla! Version:".JVERSION."</p>";
			JLog::add($error, JLog::WARNING, 'jerror');
			$errorCount++;
		}

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select($db->quoteName('manifest_cache'));
		$query->from($db->quoteName('#__extensions'));
		$query->where($db->quoteName('element') . ' = '. $db->quote('jooag_shariff'));

		$db->setQuery($query);
		$result = $db->loadResult();
		
		if($result)
		{
			$version = json_decode($result)->version;
			
			if(!$version)
			{
				$version = '4.0.0';
			}

			if(version_compare('4.0', $version) > 0)
			{
				$error ='Old JooAG Shariff Plugin detected: We found the Version '.$version.' of our Plugin. Please uninstall the old version and install the new Plugin again. Please also note your credentials for Facebook API for example. After uninstallation they are also gone. Also Download the latest Version of the Plugin <a href="https://github.com/joomla-agency/plg_jooag_shariff/releases" target="_blank">here</a> before you uninstall the Plugin, because no more updates are displayed after plugin uninstallation. This is a major Release and you need to setup & configure the Plugin again.';
				JLog::add($error, JLog::WARNING, 'jerror');
				$errorCount++;
			}
		}
		


		if($errorCount != 0)
		{
			return false;
		}

		return true;
	}
}
