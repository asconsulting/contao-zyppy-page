<?php

/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */



namespace Zyppy\Page\Tests\Image;

use Contao\CoreBundle\Asset\ContaoContext;
use Contao\CoreBundle\Image\PictureFactoryInterface;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Zyppy\Page\Image\PageImageRenderer;
use Zyppy\Page\Tests\ModelStub;


/**
 * Covers PageImageRenderer.
 *
 * What is NOT covered, and why: renderOne()'s body past the UUID guard calls
 * FilesModel::findByUuid() - a static finder that reaches
 * Database::getInstance() - and finishes with FrontendTemplate::parse(), which
 * needs a booted framework and the template loader. Neither can be exercised
 * without a database and a container, and standing a fake one up would mean
 * asserting against the fake rather than against the renderer. So the
 * findByUuid()-returns-null branch, the getMetadata(...$locales) call itself and
 * the picture_default render are verified on a real install, not here.
 *
 * getLocales() is reached by reflection. It is private and has no
 * framework-free public caller, and it is the single most defect-prone piece of
 * this class: the whole reason it exists is that tl_page.language is populated
 * on ROOT pages only, so a regular page carries '' until loadDetails() has run
 * on that particular model instance. Each locale test therefore asserts its own
 * precondition first, so a fixture that quietly grew a language cannot make the
 * fallback tests pass for the wrong reason.
 */
class PageImageRendererTest extends ContaoTestCase
{
	use ModelStub;


	protected function setUp(): void
	{
		parent::setUp();

		unset($GLOBALS['objPage']);
	}


	protected function tearDown(): void
	{
		unset($GLOBALS['objPage']);

		parent::tearDown();
	}


	private function renderer(RequestStack|null $objRequestStack = null): PageImageRenderer
	{
		return new PageImageRenderer(
			$this->createStub(PictureFactoryInterface::class),
			$this->createStub(ContaoContext::class),
			$objRequestStack ?? new RequestStack(),
			'/project'
		);
	}


	/**
	 * @return array<string>
	 */
	private function getLocales(PageImageRenderer $objRenderer, PageModel|null $objPage): array
	{
		$objMethod = new \ReflectionMethod(PageImageRenderer::class, 'getLocales');

		return $objMethod->invoke($objRenderer, $objPage);
	}


	/**
	 * @param array<string, mixed> $arrProperties
	 */
	private function page(array $arrProperties = array()): PageModel
	{
		return $this->stubWithProperties(PageModel::class, $arrProperties);
	}


	// ---------------------------------------------------------------------
	// renderOne() - only the guard is reachable without a database
	// ---------------------------------------------------------------------

	public function testRenderOneReturnsAnEmptyStringForAnEmptyUuid(): void
	{
		$this->assertSame('', $this->renderer()->renderOne('', null));
	}


	public function testRenderOneReturnsAnEmptyStringForANullUuid(): void
	{
		$this->assertSame('', $this->renderer()->renderOne(null, null));
	}


	// ---------------------------------------------------------------------
	// renderMany()
	// ---------------------------------------------------------------------

	public function testRenderManyReturnsAnEmptyArrayForAnEmptyField(): void
	{
		$this->assertSame(array(), $this->renderer()->renderMany('', null));
		$this->assertSame(array(), $this->renderer()->renderMany(null, null));
		$this->assertSame(array(), $this->renderer()->renderMany(serialize(array()), null));
	}


	public function testRenderManySkipsEntriesThatAreNotNonEmptyStrings(): void
	{
		// A double-serialised blob deserialises to a nested payload, and a legacy
		// row can carry integers or nulls. None of those is a UUID, and handing
		// one to findByUuid() is what used to put a raw value in the markup.
		$objRenderer = $this->createPartialMock(PageImageRenderer::class, array('renderOne'));

		$objRenderer->expects($this->never())->method('renderOne');

		$this->assertSame(
			array(),
			$objRenderer->renderMany(serialize(array('', null, 0, 42, array('nested'), false)), null)
		);
	}


	public function testRenderManyPassesOnlyTheValidUuidsInStoredOrder(): void
	{
		$objRenderer = $this->createPartialMock(PageImageRenderer::class, array('renderOne'));
		$objPage = $this->page(array('id' => 7));

		$arrSeen = array();

		$objRenderer
			->expects($this->exactly(2))
			->method('renderOne')
			->willReturnCallback(
				function ($varUuid, $strSize, $objArg) use (&$arrSeen, $objPage) {
					$this->assertSame('5', $strSize);
					$this->assertSame($objPage, $objArg);

					$arrSeen[] = $varUuid;

					return '<picture>' . $varUuid . '</picture>';
				}
			)
		;

		$arrResult = $objRenderer->renderMany(serialize(array('uuid-a', 42, 'uuid-b')), '5', $objPage);

		$this->assertSame(array('uuid-a', 'uuid-b'), $arrSeen);
		$this->assertSame(array('<picture>uuid-a</picture>', '<picture>uuid-b</picture>'), $arrResult);
	}


	public function testRenderManyDropsUuidsThatDoNotResolveToAFile(): void
	{
		// A dangling UUID makes renderOne() return '' - it must be dropped, not
		// padded into the list as an empty string and never as the raw binary.
		$objRenderer = $this->createPartialMock(PageImageRenderer::class, array('renderOne'));

		$objRenderer
			->expects($this->exactly(3))
			->method('renderOne')
			->willReturnCallback(static fn ($varUuid) => 'dangling' === $varUuid ? '' : '<picture/>')
		;

		$arrResult = $objRenderer->renderMany(serialize(array('dangling', 'good', 'dangling')), null);

		$this->assertSame(array('<picture/>'), $arrResult);
		$this->assertNotContains('', $arrResult);
		$this->assertStringNotContainsString('dangling', implode('', $arrResult));
	}


	public function testRenderManyTreatsAnUnserialisedSingleUuidAsOneEntry(): void
	{
		// StringUtil::deserialize($v, true) wraps a plain string in an array, which
		// is how a single-file value stored without serialisation still renders.
		$objRenderer = $this->createPartialMock(PageImageRenderer::class, array('renderOne'));

		$objRenderer
			->expects($this->once())
			->method('renderOne')
			->with($this->identicalTo('lone-uuid'), $this->identicalTo(null), $this->identicalTo(null))
			->willReturn('<picture/>')
		;

		$this->assertSame(array('<picture/>'), $objRenderer->renderMany('lone-uuid', null));
	}


	// ---------------------------------------------------------------------
	// getLocales() - the metadata language chain
	// ---------------------------------------------------------------------

	public function testNoPageAndNoRequestYieldNoLocalesAtAll(): void
	{
		$this->assertArrayNotHasKey('objPage', $GLOBALS);

		// An empty RequestStack really does give null; a pushed Request would not,
		// because Request::getLocale() falls back to the default locale ('en').
		$objStack = new RequestStack();
		$this->assertNull($objStack->getCurrentRequest());

		$this->assertSame(array(), $this->getLocales($this->renderer($objStack), null));
	}


	public function testARootPageContributesItsOwnLanguageFirst(): void
	{
		$objPage = $this->page(array('language' => 'de', 'rootLanguage' => 'de'));

		$this->assertSame(array('de'), $this->getLocales($this->renderer(), $objPage));
	}


	public function testALocaleWithARegionAlsoContributesItsBareTwoLetterCode(): void
	{
		// The file meta table is keyed by whatever the site's language codes are.
		// de-CH content must still find de-only metadata rather than nothing.
		$objPage = $this->page(array('language' => 'de-CH', 'rootLanguage' => 'de'));

		$this->assertSame(array('de-CH', 'de'), $this->getLocales($this->renderer(), $objPage));
	}


	public function testAnUndetailedPageFallsBackToItsRootLanguage(): void
	{
		// THE case this method exists for. tl_page.language is filled on root
		// pages only; a regular page carries '' until loadDetails() has run on
		// that very model instance, and the renderer must not depend on some
		// earlier caller having triggered it.
		$objPage = $this->page(array('rootLanguage' => 'fr'));

		$this->assertSame('', (string) $objPage->language, 'precondition: the fixture page is undetailed');

		$this->assertSame(array('fr'), $this->getLocales($this->renderer(), $objPage));
	}


	public function testAnUndetailedPageWithNoRootLanguageEitherFallsBackToTheCurrentPage(): void
	{
		$objPage = $this->page(array('id' => 17));

		$this->assertSame('', (string) $objPage->language, 'precondition: the fixture page is undetailed');
		$this->assertSame('', (string) $objPage->rootLanguage, 'precondition: it has no root language either');

		$GLOBALS['objPage'] = $this->page(array('id' => 1, 'language' => 'it', 'rootLanguage' => 'it'));

		$this->assertSame(array('it'), $this->getLocales($this->renderer(), $objPage));
	}


	public function testTheChainIsAdditiveAndOrderedMostSpecificFirst(): void
	{
		$objPage = $this->page(array('id' => 17, 'language' => 'de-CH', 'rootLanguage' => 'de'));

		$GLOBALS['objPage'] = $this->page(array('id' => 1, 'language' => 'fr', 'rootLanguage' => 'fr'));

		$objRequest = new Request();
		$objRequest->setLocale('es');

		$objStack = new RequestStack();
		$objStack->push($objRequest);

		// The owning page wins, but the others still contribute: a file described
		// only in the request language is better than an image with no alt text.
		$this->assertSame(
			array('de-CH', 'de', 'fr', 'es'),
			$this->getLocales($this->renderer($objStack), $objPage)
		);
	}


	public function testTheCurrentPageIsNotCountedTwiceWhenItIsAlsoTheOwningPage(): void
	{
		// Same instance in both roles - the usual case for
		// {{zyppy_page::page_image}} with no pid. This asserts the OUTCOME, not
		// the mechanism: removing the explicit $objCurrent !== $objPage guard
		// leaves this green, because array_unique() collapses the repeat anyway.
		// The guard is belt and braces over the dedupe and no unit test can
		// separate the two - recorded here rather than claimed as coverage.
		$objPage = $this->page(array('language' => 'nl', 'rootLanguage' => 'nl'));

		$GLOBALS['objPage'] = $objPage;

		$this->assertSame(array('nl'), $this->getLocales($this->renderer(), $objPage));
	}


	public function testDuplicateLocalesAcrossTheChainAreDeduplicatedKeepingTheFirstPosition(): void
	{
		$objPage = $this->page(array('id' => 17, 'language' => 'de-AT', 'rootLanguage' => 'de'));

		$GLOBALS['objPage'] = $this->page(array('id' => 1, 'language' => 'de-CH', 'rootLanguage' => 'de'));

		$objRequest = new Request();
		$objRequest->setLocale('de');

		$objStack = new RequestStack();
		$objStack->push($objRequest);

		// 'de' is contributed four times over; it must appear once, in the slot it
		// first earned, and the list must stay a list (array_values) because
		// getMetadata() is called with the spread operator.
		$arrLocales = $this->getLocales($this->renderer($objStack), $objPage);

		$this->assertSame(array('de-AT', 'de', 'de-CH'), $arrLocales);
		$this->assertSame(array_keys($arrLocales), range(0, \count($arrLocales) - 1), 'the keys must stay sequential for the spread');
	}


	public function testTheRequestLocaleIsTheLastResort(): void
	{
		$this->assertArrayNotHasKey('objPage', $GLOBALS);

		$objRequest = new Request();
		$objRequest->setLocale('pt-BR');

		$objStack = new RequestStack();
		$objStack->push($objRequest);

		$this->assertSame(array('pt-BR', 'pt'), $this->getLocales($this->renderer($objStack), null));
	}


	public function testAGlobalPageThatIsNotAPageModelIsIgnored(): void
	{
		// $GLOBALS['objPage'] is untyped and other code can leave anything in it
		$GLOBALS['objPage'] = 'not a page model';

		$objRequest = new Request();
		$objRequest->setLocale('sv');

		$objStack = new RequestStack();
		$objStack->push($objRequest);

		$this->assertSame(array('sv'), $this->getLocales($this->renderer($objStack), null));
	}
}
