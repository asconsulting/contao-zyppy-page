<?php

/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */



namespace Zyppy\Page\Image;

use Contao\CoreBundle\Asset\ContaoContext;
use Contao\CoreBundle\Image\PictureFactoryInterface;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\RequestStack;


/**
 * Renders the tl_page image fields (page_image, page_images) as parsed
 * picture_default templates.
 *
 * This is the single implementation behind all six copies of the old
 * "uuid -> FilesModel -> picture_factory -> picture_default" block that used to
 * live in PagePreview, RelatedPages and PageInsertTag.
 *
 * Metadata locale: the alt/title text is read from the file's own metadata in
 * the language of the page the image BELONGS TO first (its `language`, then its
 * `rootLanguage`), falling back to the page currently being requested and then
 * to the request locale. So on a cross-page insert tag such as
 * {{zyppy_page::page_image::17}} image 17 is described in page 17's language,
 * and on a monolingual site every route gives the same answer. See getLocales()
 * for why the fallbacks are required rather than cosmetic.
 */
class PageImageRenderer
{
	public function __construct(
		private readonly PictureFactoryInterface $pictureFactory,
		private readonly ContaoContext $filesContext,
		private readonly RequestStack $requestStack,
		private readonly string $projectDir,
	) {
	}


	/**
	 * Render a single image field.
	 *
	 * @param string|null    $varUuid Binary (or string) file UUID as stored on tl_page
	 * @param string|int|null $strSize Image size id, or null for the original size
	 * @param PageModel|null $objPage The page the image belongs to - supplies the
	 *                                metadata locale and the alt/title overrides
	 *
	 * @return string The parsed picture_default markup, or '' when the UUID does
	 *                not resolve to a file. Never the raw UUID.
	 */
	public function renderOne(string|null $varUuid, string|int|null $strSize, PageModel|null $objPage = null): string
	{
		if (!$varUuid)
		{
			return '';
		}

		// findByUuid() accepts a binary UUID directly - it only converts when
		// Validator::isStringUuid(), so the binToUuid() round trip is pointless.
		$objFile = FilesModel::findByUuid($varUuid);

		if ($objFile === null)
		{
			return '';
		}

		$strStaticUrl = $this->filesContext->getStaticUrl();

		$objPicture = $this->pictureFactory->create(
			$this->projectDir . '/' . $objFile->path,
			$strSize ?: null
		);

		$arrPicture = array
		(
			'img' => $objPicture->getImg($this->projectDir, $strStaticUrl),
			'sources' => $objPicture->getSources($this->projectDir, $strStaticUrl),
			'alt' => '',
			'title' => '',
			'class' => 'page_image',
		);

		// File metadata is keyed by locale: ['<locale>' => ['title' => .., 'alt' => ..]].
		// The old code read $arrMeta['page_image_title'], which was never a key, so
		// alt and title came out empty on every image the modules rendered.
		$objMetadata = $objFile->getMetadata(...$this->getLocales($objPage));

		if ($objMetadata !== null)
		{
			$arrPicture['alt'] = $objMetadata->getAlt();
			$arrPicture['title'] = $objMetadata->getTitle();
		}

		// The per-page overrides win over the file's own metadata
		if ($objPage !== null && $objPage->page_image_overwrite_meta)
		{
			if ($objPage->page_image_alt)
			{
				$arrPicture['alt'] = $objPage->page_image_alt;
			}

			if ($objPage->page_image_title)
			{
				$arrPicture['title'] = $objPage->page_image_title;
			}
		}

		$objPictureTemplate = new FrontendTemplate('picture_default');
		$objPictureTemplate->setData($arrPicture);

		return $objPictureTemplate->parse();
	}


	/**
	 * Render a serialized multi-file field (page_images).
	 *
	 * Files that no longer exist are skipped, so the returned list can be shorter
	 * than the stored one - it is never padded with empty strings or raw UUIDs.
	 *
	 * @param mixed          $varUuids Serialized array of file UUIDs, or an array
	 * @param string|int|null $strSize
	 * @param PageModel|null $objPage
	 *
	 * @return array<string>
	 */
	public function renderMany($varUuids, string|int|null $strSize, PageModel|null $objPage = null): array
	{
		$arrRendered = array();

		foreach (StringUtil::deserialize($varUuids, true) as $varUuid)
		{
			// A double-serialized blob deserializes to a string, not a uuid - skip it
			// rather than handing a nested payload to findByUuid()
			if (!\is_string($varUuid) || $varUuid === '')
			{
				continue;
			}

			$strRendered = $this->renderOne($varUuid, $strSize, $objPage);

			if ($strRendered !== '')
			{
				$arrRendered[] = $strRendered;
			}
		}

		return $arrRendered;
	}


	/**
	 * Locale preference for the file metadata, most specific first: the language of
	 * the page the image belongs to, then the language of the page being requested,
	 * then the request locale. Each contributes its bare two-letter code as well.
	 *
	 * The fallbacks are not decoration. `tl_page.language` is populated only on a
	 * ROOT page - a regular page carries '' until `loadDetails()` has run on that
	 * particular model instance. Both callers happen to trigger it (core navigation
	 * and RelatedPages both call `getFrontendUrl()` first), but relying on that
	 * would make the alt/title text depend on call ordering somewhere else, which
	 * is exactly the class of defect this service exists to remove. Resolving it
	 * here by calling `loadDetails()` is not an option either: it detaches the
	 * model, so the renderer would silently make its caller's page unsaveable.
	 *
	 * @return array<string>
	 */
	private function getLocales(PageModel|null $objPage): array
	{
		$arrLocales = array();

		if ($objPage !== null)
		{
			$this->addLocales($arrLocales, $objPage->language, $objPage->rootLanguage);
		}

		$objCurrent = $GLOBALS['objPage'] ?? null;

		if ($objCurrent instanceof PageModel && $objCurrent !== $objPage)
		{
			$this->addLocales($arrLocales, $objCurrent->language, $objCurrent->rootLanguage);
		}

		$objRequest = $this->requestStack->getCurrentRequest();

		if ($objRequest !== null)
		{
			$this->addLocales($arrLocales, $objRequest->getLocale());
		}

		return array_values(array_unique($arrLocales));
	}


	/**
	 * Append each non-empty candidate, plus its bare two-letter code.
	 *
	 * @param array<string> $arrLocales
	 */
	private function addLocales(array &$arrLocales, string|null ...$arrCandidates): void
	{
		foreach ($arrCandidates as $strLocale)
		{
			if (!$strLocale)
			{
				continue;
			}

			$arrLocales[] = $strLocale;

			if (\strlen($strLocale) > 2)
			{
				$arrLocales[] = substr($strLocale, 0, 2);
			}
		}
	}
}
