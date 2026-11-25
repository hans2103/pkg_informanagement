<?php

/**
 * @package     HKweb.Informanagement
 * @subpackage  mod_inforwijzers
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var bool $configured */
/** @var \Joomla\CMS\Application\SiteApplication $app */

if (!$configured) {
	if ($app->getIdentity()?->authorise('core.manage', 'com_modules')) {
		echo '<p>' . Text::_('MOD_INFORWIJZERS_NOT_CONFIGURED') . '</p>';
	}

	return;
}
?>
<div class="c-inforwijzers">
	<?php // Ids and class are set by Informanagement's script. ?>
	<div id="id01"></div>
	<div class="pubLdBXInfor"></div>
	<noscript><p><?php echo Text::_('MOD_INFORWIJZERS_NOSCRIPT'); ?></p></noscript>
</div>
