<?php
 
/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */
 
 
 
namespace Zyppy\Page\Module;

use Contao\BackendTemplate;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Zyppy\Page\Image\PageImageRenderer;


class RelatedPages extends \Contao\Module
{
 
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_related_pages';


	/**
	 * Do not display the module if there are no menu items
	 *
	 * @return string
	 */
	public function generate()
	{	
		$request = System::getContainer()->get('request_stack')->getCurrentRequest();

		if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
		{
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['related_pages'][0] . ' ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', array('do'=>'themes', 'table'=>'tl_module', 'act'=>'edit', 'id'=>$this->id)));

			return $objTemplate->parse();
		}
		$strBuffer = parent::generate();
		return $strBuffer;
	}


	/**
	 * Generate the module
	 */
	protected function compile()
	{		
		/** @var \PageModel $objPage */
		global $objPage;
		$objContainer = System::getContainer();

		$arrRelated = StringUtil::deserialize($objPage->page_related, true);
	
		$arrPages = array();
		$objSecurity = $objContainer->get('security.helper');
		$objRenderer = $objContainer->get(PageImageRenderer::class);

		foreach ($arrRelated as $intPage) {
			$objRelatedPage = PageModel::findPublishedById($intPage);

			// Skip protected pages the visitor is not a member of (see core Module::compileNavigationRow())
			if ($objRelatedPage && $objRelatedPage->protected && !$objSecurity->isGranted(ContaoCorePermissions::MEMBER_IN_GROUPS, $objRelatedPage->groups)) {
				continue;
			}

			if ($objRelatedPage) {
				$row = $objRelatedPage->row();
				$row['url'] = $objRelatedPage->getFrontendUrl();
				
				

				// A dangling UUID must never survive into the template: mod_related_pages.html5
				// echoes these raw, so the old "only overwrite inside if ($objFile)" shape
				// printed the raw binary(16) value into the page.
				$strSize = $this->preview_image_size ?: null;

				$row['page_image'] = $objRenderer->renderOne($row['page_image'] ?: null, $strSize, $objRelatedPage);
				$row['page_images'] = $objRenderer->renderMany($row['page_images'] ?? null, $strSize, $objRelatedPage);

				$arrPages[] = $row;
			}
		}

		$this->Template->pages = $arrPages;
	}

}
