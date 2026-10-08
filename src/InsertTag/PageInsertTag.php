<?php

/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */



namespace Zyppy\Page\InsertTag;

use Contao\CoreBundle\DependencyInjection\Attribute\AsInsertTag;
use Contao\CoreBundle\InsertTag\InsertTagResult;
use Contao\CoreBundle\InsertTag\OutputType;
use Contao\CoreBundle\InsertTag\Resolver\InsertTagResolverNestedResolvedInterface;
use Contao\CoreBundle\InsertTag\ResolvedInsertTag;
use Contao\PageModel;
use Contao\StringUtil;
use Zyppy\Page\Image\PageImageRenderer;


/**
 * Resolves the {{zyppy_page::...}} insert tags.
 *
 * Documented syntax (see README.md):
 *
 *   {{zyppy_page::page_teaser::<optional pid>}}
 *   {{zyppy_page::page_image::<optional pid>::<optional image size id>}}
 *   {{zyppy_page::page_images:<index>::<optional pid>::<optional image size id>}}
 *   {{zyppy_page::page_image_url::<optional pid>}}
 *   {{zyppy_page::rich_text::<optional pid>}}          (alias of rich_text_1)
 *   {{zyppy_page::rich_text_1..4::<optional pid>}}
 *
 * Note the image *index* is attached to the sub-command with a SINGLE colon,
 * which is what README.md documents; the page id and image size remain normal
 * "::"-separated parameters.
 *
 *
 * THE OUTPUT CONTRACT - why each arm passes the OutputType it passes
 * -----------------------------------------------------------------
 * Read out of the core source on all three supported legs, because this is the
 * reason these arms are NOT interchangeable.
 *
 * OutputType is not a hint. On 5.3.0 (InsertTagParser.php:132 and :154) and on
 * 5.7.13 (:155 and :177), InsertTagParser::replace() and ::replaceInline() map
 * every result through
 *
 *     OutputType::html !== $result->getOutputType()
 *         ? StringUtil::specialchars($result->getValue())
 *         : $result->getValue()
 *
 * On 6.0.0 the same decision moved into InsertTagParser::toOutputType()
 * (:605-628): rendering into an html context returns a result that is already
 * html verbatim and runs everything else through StringUtil::specialchars().
 * Refactored, identical in effect.
 *
 * So OutputType::html is the ONLY value that reaches the page unescaped, and
 * every other value is run through StringUtil::specialchars() with its
 * $blnDoubleEncode DEFAULT - which is false on 5.3 and 5.7 and TRUE on 6.0
 * (StringUtil.php:901 on 6.0.2). Choosing html therefore moves the trust
 * boundary onto whatever wrote the column; choosing text means the resolver
 * must hand over a value with no entities in it, or 6.0 renders them twice.
 *
 * rich_text_1..4 (and the bare rich_text alias) - OutputType::html, DELIBERATE.
 * These are 'inputType' => 'textarea' with 'rte' => 'tinyMCE'. Widget::getAttributesFromDca()
 * derives allowHtml from that (Widget.php:1289 on 5.3 and 5.7, :1280 on 6.0:
 * str_starts_with($rte, 'tiny')) and forces decodeEntities on top, so
 * Widget::getPost() calls Input::postHtml($key, true) (Widget.php:762), which
 * encodes the value in InputEncodingMode::sanitizeHtml - i.e.
 * StringUtil::stripTags() against Config::get('allowedTags') /
 * Config::get('allowedAttributes') (Input.php:87 on 5.3 and 6.0, :87 and :243
 * on 5.7). That is the same rte -> allowHtml -> postHtml -> sanitizeHtml path
 * core puts tl_content.text through; it is NOT byte-identical, because
 * tl_content.text additionally carries 'basicEntities' and these fields do not.
 *
 * So the stored value is sanitised HTML by construction, and OutputType::text
 * would emit it as visible "&lt;p&gt;" entity soup - it would not harden
 * anything, it would break the feature. html is the correct value here.
 *
 * The gate is a back end one: 'exclude' => true on each field means
 * DataContainer::isFieldExcluded() reports true (DataContainer.php:1296) and a
 * non-admin back end user needs the explicit tl_page::rich_text_N field
 * permission to write it. Nothing in this bundle writes tl_page at all, and
 * there is no front end editing path to these columns. Anything that bypasses
 * the DCA - a SQL import, a third-party API - bypasses the sanitiser too, which
 * is the same exposure tl_content.text has and is called out in README.md.
 *
 * page_image / page_images - OutputType::html, because PageImageRenderer builds
 * the <img>/<picture> markup itself; there is no user string in the result.
 *
 * page_image_url - OutputType::text. An external URL is data, not markup, so it
 * gets specialchars()'d on the way out. The column was saved through
 * InputEncodingMode::encodeAll, so a query string's "&" sits in it as &amp;;
 * that is decoded before handing over, so the parser encodes exactly once on
 * every leg (on 6.0 the stored entity would otherwise come out as &amp;amp;).
 *
 * page_teaser - OutputType::text, changed in the 2026-10 review from html.
 * Unlike the rich text slots this is a plain textarea with no 'rte', so
 * allowHtml is false and Widget::getPost() calls Input::post($key, true) ->
 * InputEncodingMode::encodeLessThanSign (Input.php:200 and :88 on 5.7, :217
 * and :89 on 6.0): a tag has never been able to reach the column through the
 * DCA (4.x stripped them outright). It is text, the three module templates
 * escape it, and html left a quote unescaped inside an attribute such as
 * title="{{zyppy_page::page_teaser}}". The stored &#60; is decoded first for
 * the same reason as page_image_url, so what renders is unchanged on 5.x (the
 * parser now writes &lt; where the raw &#60; used to pass through) and no
 * longer double-encoded on 6.0.
 */
#[AsInsertTag('zyppy_page')]
#[AsInsertTag('page_preview')]
class PageInsertTag implements InsertTagResolverNestedResolvedInterface
{
	public function __construct(private readonly PageImageRenderer $pageImageRenderer)
	{
	}


	public function __invoke(ResolvedInsertTag $insertTag): InsertTagResult
	{
		$arrTokens = $insertTag->getParameters()->all();

		$strCommand = (string) ($arrTokens[0] ?? '');
		$strIndex = null;

		// "page_images:2" - the index is attached with a single colon
		if (str_contains($strCommand, ':'))
		{
			list($strCommand, $strIndex) = explode(':', $strCommand, 2);
		}

		$strPid = isset($arrTokens[1]) && $arrTokens[1] !== '' ? (string) $arrTokens[1] : null;
		$strSize = isset($arrTokens[2]) && $arrTokens[2] !== '' ? (string) $arrTokens[2] : null;

		$objPage = $this->resolvePage($strPid);

		if (!$objPage instanceof PageModel)
		{
			return new InsertTagResult('');
		}

		$arrCacheTags = array('contao.db.tl_page.' . $objPage->id);

		switch ($strCommand)
		{
			case 'page_teaser':
			case 'teaser':
				return new InsertTagResult(StringUtil::decodeEntities((string) $objPage->page_teaser), OutputType::text, null, $arrCacheTags);

			case 'page_image':
			case 'image':
				return new InsertTagResult(
					$this->pageImageRenderer->renderOne($objPage->page_image, $strSize, $objPage),
					OutputType::html,
					null,
					$arrCacheTags
				);

			case 'page_images':
			case 'images':
				$arrImages = StringUtil::deserialize($objPage->page_images, true);

				if ($strIndex === null || !isset($arrImages[$strIndex]))
				{
					return new InsertTagResult('', OutputType::html, null, $arrCacheTags);
				}

				return new InsertTagResult(
					$this->pageImageRenderer->renderOne($arrImages[$strIndex], $strSize, $objPage),
					OutputType::html,
					null,
					$arrCacheTags
				);

			case 'page_image_url':
			case 'image_url':
				return new InsertTagResult(StringUtil::decodeEntities((string) $objPage->page_image_url), OutputType::text, null, $arrCacheTags);

			case 'rich_text':
			case 'rich_text_1':
			case 'rich_text_2':
			case 'rich_text_3':
			case 'rich_text_4':
				// The bare "rich_text" is an alias of slot 1
				$strField = $strCommand === 'rich_text' ? 'rich_text_1' : $strCommand;

				// The value is returned VERBATIM. A back end editor can store live
				// {{...}} in these fields (Input::encodeInput() only encodes insert
				// tags when the request is a front end one - Input.php:200/243), and
				// the DCA offers the insert-tag help wizard, but the parser never
				// re-scans what a resolver returned: executeReplace() appends
				// renderSubscription()'s InsertTagResult straight to its output
				// (InsertTagParser.php:436). resolveNestedTags(), which this class
				// opts into via InsertTagResolverNestedResolvedInterface, applies to
				// the tag's PARAMETERS, not to its result. A nested tag therefore
				// resolves only if the consuming template asks for it, e.g. Twig's
				// |insert_tag_raw - which is exactly what core does for
				// tl_content.text in component/_rich_text.html.twig.

				return new InsertTagResult((string) $objPage->$strField, OutputType::html, null, $arrCacheTags);
		}

		// Unknown sub-command
		return new InsertTagResult('');
	}


	/**
	 * Resolve the target page: an explicit id/alias, or the current page.
	 */
	private function resolvePage(string|null $strPid): PageModel|null
	{
		if ($strPid !== null)
		{
			// findPublishedByIdOrAlias() goes through findBy() and therefore returns a
			// Collection, not a single model - an id and an alias are both matched.
			$objPages = PageModel::findPublishedByIdOrAlias($strPid);

			if ($objPages === null)
			{
				return null;
			}

			$objMatch = $objPages->first()->current();

			return $objMatch instanceof PageModel ? $objMatch : null;
		}

		global $objPage;

		return $objPage instanceof PageModel ? $objPage : null;
	}


}
