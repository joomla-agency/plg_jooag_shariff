<?php

/**
 * @package     JooAg Shariff
 * @copyright   (C) 2014 - 2026 Joomla Agentur, newtastic GmbH
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace JoomlaAgency\Plugin\System\JooagShariff\Field;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

\defined('_JEXEC') or die;

/**
 * The components of the website (folders in /components, third-party ones included) for "Disallow components".
 * Joomla's folderlist field reads "components" relative to the current folder, which is /administrator in the backend.
 */
final class SitecomponentsField extends ListField
{
    protected $type = 'Sitecomponents';

    protected function getOptions()
    {
        $options = parent::getOptions();
        $names   = array_map('basename', glob(JPATH_SITE . '/components/com_*', GLOB_ONLYDIR) ?: []);

        // A value saved earlier that is not a site component any more stays selectable
        if (\is_string($this->value) && $this->value !== '' && !\in_array($this->value, $names, true)) {
            $names[] = $this->value;
        }

        sort($names);

        foreach ($names as $name) {
            $options[] = HTMLHelper::_('select.option', $name, $name);
        }

        return $options;
    }
}
