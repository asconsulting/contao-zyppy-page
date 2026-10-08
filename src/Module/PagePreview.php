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
use Contao\Environment;
use Contao\ModuleNavigation;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Zyppy\Page\Image\PageImageRenderer;


class PagePreview extends ModuleNavigation
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'mod_pagepreview';


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

			$objTemplate->wildcard = '### ' . $GLOBALS['TL_LANG']['FMD']['pagepreview'][0] . ' ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', array('do'=>'themes', 'table'=>'tl_module', 'act'=>'edit', 'id'=>$this->id)));

			return $objTemplate->parse();
		}

		$strBuffer = parent::generate();

		return ($this->Template->items != '') ? $strBuffer : '';
	}


	/**
	 * Generate the module
	 *
	 * The header/body two-pass is pagepreview-specific (feeds public/js/page_slider.js)
	 * and has no equivalent in core's ModuleNavigation::compile(), so it stays fully
	 * overridden here instead of calling parent::compile().
	 */
	protected function compile()
	{
		/** @var PageModel $objPage */
		global $objPage;

		// Set the trail and level
		if ($this->defineRoot && $this->rootPage > 0)
		{
			$trail = array($this->rootPage);
			$level = 0;
		}
		else
		{
			$trail = $objPage->trail;
			$level = ($this->levelOffset > 0) ? $this->levelOffset : 0;
		}

		$lang = null;
		$host = null;

		// Overwrite the domain and language if the reference page belongs to a different root page (see #3765)
		if ($this->defineRoot && $this->rootPage > 0 && ($objRootPage = PageModel::findWithDetails($this->rootPage)))
		{
			$lang = $objRootPage->rootLanguage;
			$host = $objRootPage->domain;
		}

		$this->Template->request = StringUtil::ampersand(Environment::get('requestUri'));
		$this->Template->skipId = 'skipNavigation' . $this->id;
		$this->Template->skipNavigation = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['skipNavigation']);

		// tl_module.previewSliderItems drives public/js/page_slider.js. The template used to
		// emit show="" from $this->numberOfItems, which nothing ever assigned, so the slider
		// read an empty attribute and switched itself off on every page. 0 = no slider, and
		// the template then emits neither the attribute nor the assets.
		$this->Template->sliderItems = (int) $this->previewSliderItems;

		$this->navigationTpl = ($this->navHeaderTpl ?: 'nav_pagepreview_header');
		$this->Template->headerItems = isset($trail[$level]) ? $this->renderNavigation($trail[$level], 1, $host, $lang) : '';

		$this->navigationTpl = ($this->navBodyTpl ?: 'nav_pagepreview_body');
		$this->Template->bodyItems = isset($trail[$level]) ? $this->renderNavigation($trail[$level], 1, $host, $lang) : '';

		$this->Template->items = $this->Template->bodyItems;
	}


	/**
	 * Select the sub-level template before delegating to core.
	 *
	 * Core's ModuleNavigation has no equivalent to the pagepreview module's
	 * `navSubitemTpl` field (tl_module.php), so that one bit of template
	 * selection has to stay overridden here. Everything else about the walk
	 * (the query, the guests/protected checks, hasSubpages, cache tags, ...)
	 * is inherited unmodified from Contao\Module::renderNavigation().
	 *
	 * $this->navigationTpl is instance state that core itself mutates and
	 * reads back across the recursive calls, so the override must save and
	 * restore it around the parent call rather than leaving it set — otherwise
	 * a level-1 sibling walked after a level-2+ subtree would inherit the
	 * subitem template instead of the header/body template compile() chose.
	 *
	 * navSubitemTpl's own DCA default (contao/dca/tl_module.php) is the
	 * literal string 'nav_default' — the identical sentinel core falls back
	 * to for an empty navigationTpl (Module.php: `$this->navigationTpl ?:
	 * 'nav_default'`). Any module that has never had navSubitemTpl explicitly
	 * changed therefore holds a genuinely truthy value here that collides
	 * with "not configured". Honouring it as a real choice would route every
	 * sub-level through core's bare nav_default.html5, which carries none of
	 * our page_image/page_teaser/nofollow markup and, critically, none of the
	 * navSubitemLimit cap (that only lives in nav_pagepreview_header/body.html5).
	 * So only a genuine choice away from that default is honoured; the
	 * unedited default falls through and keeps using whichever header/body
	 * template is already active, exactly like an empty value does.
	 *
	 * @param integer $pid
	 * @param integer $level
	 * @param string  $host
	 * @param string  $language
	 *
	 * @return string
	 */
	protected function renderNavigation($pid, $level=1, $host=null, $language=null)
	{
		if ($level < 2 || !$this->navSubitemTpl || $this->navSubitemTpl === 'nav_default')
		{
			return parent::renderNavigation($pid, $level, $host, $language);
		}

		$strPrevious = $this->navigationTpl;
		$this->navigationTpl = $this->navSubitemTpl;

		try
		{
			return parent::renderNavigation($pid, $level, $host, $language);
		}
		finally
		{
			$this->navigationTpl = $strPrevious;
		}
	}


	/**
	 * Add the pagepreview-specific image/teaser fields to the navigation row.
	 *
	 * @param PageModel $objPage
	 * @param PageModel $objSubpage
	 * @param string    $subitems
	 * @param string    $href
	 *
	 * @return array
	 */
	protected function compileNavigationRow(PageModel $objPage, PageModel $objSubpage, $subitems, $href): array
	{
		$row = parent::compileNavigationRow($objPage, $objSubpage, $subitems, $href);

		// Core's compileNavigationRow() no longer computes this; the pagepreview
		// templates (nav_pagepreview_header/body) still read $item['nofollow'].
		$row['nofollow'] = (strncmp((string) $objSubpage->robots, 'noindex,nofollow', 16) === 0);

		// $row['page_teaser'] needs no extra work: it is a plain tl_page column
		// and already survives via $objSubpage->row() inside parent::compileNavigationRow().

		// A dangling UUID must never survive into the template: the .html5 templates
		// echo $item['page_image'] raw, so leaving the row untouched printed the raw
		// binary(16) value into the page.
		$objRenderer = System::getContainer()->get(PageImageRenderer::class);
		$strSize = $this->preview_image_size ?: null;

		$row['page_image'] = $objRenderer->renderOne($row['page_image'] ?: null, $strSize, $objSubpage);
		$row['page_images'] = $objRenderer->renderMany($row['page_images'] ?? null, $strSize, $objSubpage);

		return $row;
	}

}
