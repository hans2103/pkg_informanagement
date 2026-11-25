<?php

/**
 * @package     HKweb.Informanagement
 * @subpackage  mod_inforwijzers
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Module\Inforwijzers\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Shows the Informanagement InforWijzers (advice guides). The scripts only load where the module is.
 *
 * @since  1.0.0
 */
final class Dispatcher extends AbstractModuleDispatcher
{
	private const CDN = 'https://cdn.informanagement.com/Content/ePrint/Assets/Accountants/';

	/**
	 * Adds 'configured' to the layout data and loads the InforWijzers styles and script when it is.
	 *
	 * @return  array<string, mixed>
	 *
	 * @since   1.0.0
	 */
	protected function getLayoutData(): array
	{
		$data   = parent::getLayoutData();
		$params = $data['params'];
		$id     = (int) $params->get('office_id', 0);
		$code   = (string) $params->get('code', '');

		$data['configured'] = $id > 0 && preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $code) === 1;

		if ($data['configured'])
		{
			// @phpstan-ignore method.notFound (getDocument() is on the site application, not on CMSApplicationInterface.)
			$wa = $this->getApplication()->getDocument()->getWebAssetManager();

			if ($params->get('office_styles', 1))
			{
				$wa->registerAndUseStyle('mod_inforwijzers.settings', self::CDN . $id . '/SiteStyles/Settings.css');
			}

			$wa->registerAndUseStyle('mod_inforwijzers.modals', self::CDN . 'Common/CSS/CommonModals.css');

			// Integrationjs.js reads window.inforwijzers_url and renders into #id01; defer, so the container exists.
			$wa->registerAndUseScript('mod_inforwijzers.integration', self::CDN . 'Common/JS/Integrationjs.js', [], ['defer' => true]);
			$url = 'https://eprint.informanagement.com/Int/AccountantOnlineTemplates?ui=' . rawurlencode($code);
			$wa->addInlineScript(
				'window.inforwijzers_url = ' . json_encode($url, JSON_UNESCAPED_SLASHES) . ';',
				['position' => 'before'],
				[],
				['mod_inforwijzers.integration']
			);

			// The widget gives its decorative thumbnails alt=" ": a screen reader then announces the file name.
			// Make that alt="" (WCAG 1.1.1), also for images it adds later.
			$wa->addInlineScript(
				'document.addEventListener("DOMContentLoaded",()=>{const r=document.getElementById("id01");if(!r)return;'
				. 'const f=()=>r.querySelectorAll("img[alt]").forEach(i=>{if(i.alt.trim()===""&&i.alt!=="")i.alt=""});'
				. 'new MutationObserver(f).observe(r,{childList:true,subtree:true});f()});',
				['position' => 'after'],
				[],
				['mod_inforwijzers.integration']
			);
		}

		return $data;
	}
}
