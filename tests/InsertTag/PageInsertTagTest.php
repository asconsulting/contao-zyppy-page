<?php

/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */



namespace Zyppy\Page\Tests\InsertTag;

use Contao\CoreBundle\InsertTag\InsertTagResult;
use Contao\CoreBundle\InsertTag\OutputType;
use Contao\CoreBundle\InsertTag\ResolvedInsertTag;
use Contao\CoreBundle\InsertTag\ResolvedParameters;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Zyppy\Page\Image\PageImageRenderer;
use Zyppy\Page\InsertTag\PageInsertTag;
use Zyppy\Page\Tests\ModelStub;


/**
 * Covers the {{zyppy_page::...}} resolver.
 *
 * Every test drives the resolver through the CURRENT-page branch
 * ($GLOBALS['objPage']). The explicit-pid branch is NOT covered here: it goes
 * through PageModel::findPublishedByIdOrAlias(), a static finder that bottoms
 * out in Database::getInstance(), and faking that would test the fake rather
 * than the resolver. That branch is verified on a real install, not here.
 *
 * The image arms are asserted against a doubled PageImageRenderer, so what is
 * proved here is the resolver's own contract - which field it reads, what it
 * hands the renderer, and that it RETURNS the result. It is not a test of the
 * renderer.
 */
class PageInsertTagTest extends ContaoTestCase
{
	use ModelStub;


	private PageImageRenderer $objRenderer;


	protected function setUp(): void
	{
		parent::setUp();

		// $GLOBALS is process-global and PHPUnit runs one process, so a leftover
		// page from another test class would make the "no current page" test pass
		// vacuously. Start from a known-empty slot every time.
		unset($GLOBALS['objPage']);

		$this->objRenderer = $this->createStub(PageImageRenderer::class);
	}


	protected function tearDown(): void
	{
		unset($GLOBALS['objPage']);

		parent::tearDown();
	}


	/**
	 * Swap the passive stub for a mock the test can put expectations on.
	 */
	private function expectRenderer(): MockObject
	{
		$objMock = $this->createMock(PageImageRenderer::class);

		$this->objRenderer = $objMock;

		return $objMock;
	}


	/**
	 * Build and run a resolved insert tag. $arrTokens is the "::"-separated
	 * parameter list exactly as the parser hands it over:
	 * [sub-command, pid, image size].
	 *
	 * @param array<string> $arrTokens
	 */
	private function resolve(array $arrTokens): InsertTagResult
	{
		$objTag = new ResolvedInsertTag('zyppy_page', new ResolvedParameters($arrTokens), array());

		return (new PageInsertTag($this->objRenderer))($objTag);
	}


	/**
	 * @param array<string, mixed> $arrProperties
	 */
	private function currentPage(array $arrProperties = array()): PageModel
	{
		$objPage = $this->stubWithProperties(PageModel::class, array_merge(array('id' => 42), $arrProperties));

		$GLOBALS['objPage'] = $objPage;

		return $objPage;
	}


	public function testResolvesTheTeaserOfTheCurrentPage(): void
	{
		$this->currentPage(array('page_teaser' => 'A teaser'));

		$objResult = $this->resolve(array('page_teaser'));

		$this->assertSame('A teaser', $objResult->getValue());

		// page_teaser is a plain textarea: no rte, so no allowHtml, so the DCA has
		// never let a tag into the column (4.x stripped them, 5.x and 6.0 store "<"
		// as &#60;). It is text, and the three module templates already escape it.
		// OutputType::text makes the insert tag agree with them - and closes the
		// one real gap, an unescaped quote inside an attribute.
		$this->assertSame(OutputType::text, $objResult->getOutputType());
	}


	/**
	 * The stored value carries the entities the back end widget wrote (&#60; for
	 * a typed "<"), and the parser escapes a text result on its way out. On 5.x
	 * StringUtil::specialchars() leaves an existing entity alone; on 6.0 its
	 * $blnDoubleEncode default flipped to true and the same entity would render
	 * as the visible text "&#60;". Decoding first gives the parser a plain
	 * string to encode exactly once, which is byte-identical on 5.x and correct
	 * on 6.0.
	 */
	public function testTheTeaserIsHandedOverDecodedSoTheParserEncodesItExactlyOnce(): void
	{
		$this->currentPage(array('page_teaser' => 'Salt &#60; pepper &amp; "quotes"'));

		$objResult = $this->resolve(array('page_teaser'));

		$this->assertSame('Salt < pepper & "quotes"', $objResult->getValue());
		$this->assertSame(OutputType::text, $objResult->getOutputType());
	}


	public function testTheShortTeaserAliasResolvesTheSameField(): void
	{
		$this->currentPage(array('page_teaser' => 'A teaser'));

		$this->assertSame('A teaser', $this->resolve(array('teaser'))->getValue());
	}


	public function testEveryResolvedArmCarriesThePageCacheTag(): void
	{
		$this->currentPage(array(
			'page_teaser' => 't',
			'page_image_url' => 'https://example.com/a.png',
			'rich_text_1' => 'r',
		));

		foreach (array('page_teaser', 'page_image', 'page_images:0', 'page_image_url', 'rich_text', 'rich_text_4') as $strCommand)
		{
			$this->assertSame(
				array('contao.db.tl_page.42'),
				$this->resolve(array($strCommand))->getCacheTags(),
				'The "' . $strCommand . '" arm must tag the page it read'
			);
		}
	}


	public function testReturnsAnEmptyResultWithoutCacheTagsWhenThereIsNoCurrentPage(): void
	{
		// setUp() unset it; assert that, so this cannot pass on a stale global
		$this->assertArrayNotHasKey('objPage', $GLOBALS);

		$objResult = $this->resolve(array('page_teaser'));

		$this->assertSame('', $objResult->getValue());
		$this->assertSame(array(), $objResult->getCacheTags());
	}


	public function testReturnsAnEmptyResultForAnUnknownSubCommand(): void
	{
		$this->currentPage(array('page_teaser' => 'A teaser'));

		$objResult = $this->resolve(array('no_such_field'));

		$this->assertSame('', $objResult->getValue());
		$this->assertSame(array(), $objResult->getCacheTags());
	}


	public function testReturnsAnEmptyResultWhenNoSubCommandWasGivenAtAll(): void
	{
		$this->currentPage(array('page_teaser' => 'A teaser'));

		$this->assertSame('', $this->resolve(array())->getValue());
	}


	public function testResolvesTheImageUrlAsPlainText(): void
	{
		$this->currentPage(array('page_image_url' => 'https://example.com/a.png'));

		$objResult = $this->resolve(array('page_image_url'));

		$this->assertSame('https://example.com/a.png', $objResult->getValue());

		// An external URL is a URL, not markup. OutputType::text is what makes
		// Contao escape it on output; html would let a stored "><script> payload
		// straight through.
		$this->assertSame(OutputType::text, $objResult->getOutputType());
	}


	public function testTheImageUrlIsHandedOverDecodedSoTheParserEncodesItExactlyOnce(): void
	{
		// A text field saves through InputEncodingMode::encodeAll, so a query
		// string's "&" is stored as &amp;. Same reasoning as the teaser: hand the
		// parser the plain URL and let it encode once, on every leg.
		$this->currentPage(array('page_image_url' => 'https://example.com/a.png?w=1&amp;h=2'));

		$this->assertSame('https://example.com/a.png?w=1&h=2', $this->resolve(array('page_image_url'))->getValue());
	}


	// ---------------------------------------------------------------------
	// rich_text_1..4 - the regression this suite exists for. All five arms used
	// to construct an InsertTagResult and throw it away without returning it, so
	// they resolved to the empty string even on a page with content in the slot.
	// ---------------------------------------------------------------------

	public function testTheBareRichTextCommandIsAnAliasOfSlotOne(): void
	{
		$this->currentPage(array('rich_text_1' => '<p>slot one</p>', 'rich_text_2' => '<p>slot two</p>'));

		$objResult = $this->resolve(array('rich_text'));

		$this->assertSame('<p>slot one</p>', $objResult->getValue());
		$this->assertSame(OutputType::html, $objResult->getOutputType());
	}


	/**
	 * Deliberately NOT a #[DataProvider]. PHP attributes are inert on PHPUnit
	 * 9.6, which is what the Contao 5.3 floor resolves to (contao/test-case 5.3
	 * requires phpunit ^9.5), so a provider-fed method would be invoked with no
	 * arguments and fatal there. The four cases are spelled out instead. Each one
	 * still has to pick its own slot out of a page where all four are populated,
	 * so an arm that read the wrong field fails exactly as before.
	 *
	 * The OutputType is asserted on EVERY slot, not just on the bare alias. It is
	 * the documented contract of these tags (README.md, "Escaping and the trust
	 * boundary"): OutputType::html is the only value InsertTagParser::replace()
	 * does not run through StringUtil::specialchars(), so a slot silently demoted
	 * to ::text would render the editor's markup as visible entity soup while
	 * every value assertion here stayed green.
	 */
	private function assertRichTextSlotReadsItsOwnField(string $strCommand, string $strExpected): void
	{
		$this->currentPage(array(
			'rich_text_1' => '<p>one</p>',
			'rich_text_2' => '<p>two</p>',
			'rich_text_3' => '<p>three</p>',
			'rich_text_4' => '<p>four</p>',
		));

		$objResult = $this->resolve(array($strCommand));

		$this->assertSame($strExpected, $objResult->getValue());
		$this->assertSame(OutputType::html, $objResult->getOutputType(), $strCommand . ' must stay unescaped; it holds sanitised editor HTML');
	}


	public function testTheFirstNumberedRichTextSlotReturnsItsOwnField(): void
	{
		$this->assertRichTextSlotReadsItsOwnField('rich_text_1', '<p>one</p>');
	}


	public function testTheSecondNumberedRichTextSlotReturnsItsOwnField(): void
	{
		$this->assertRichTextSlotReadsItsOwnField('rich_text_2', '<p>two</p>');
	}


	public function testTheThirdNumberedRichTextSlotReturnsItsOwnField(): void
	{
		$this->assertRichTextSlotReadsItsOwnField('rich_text_3', '<p>three</p>');
	}


	public function testTheFourthNumberedRichTextSlotReturnsItsOwnField(): void
	{
		$this->assertRichTextSlotReadsItsOwnField('rich_text_4', '<p>four</p>');
	}


	public function testAFifthRichTextSlotIsRejectedRatherThanReadOffThePage(): void
	{
		// The fixture page DOES carry the property, so an arm that interpolated
		// the suffix instead of whitelisting it would hand it back. '' is only
		// reachable through the explicit list of four.
		$objPage = $this->currentPage(array('rich_text_5' => '<p>five</p>'));

		$this->assertSame('<p>five</p>', (string) $objPage->rich_text_5, 'precondition: the fixture really has a slot 5');
		$this->assertSame('', $this->resolve(array('rich_text_5'))->getValue());
	}


	/**
	 * The resolver must hand the stored value back byte for byte. It is the
	 * PARSER that decides whether the result is escaped, from the OutputType
	 * asserted above; a resolver that "helpfully" escaped or stripped here would
	 * double up on that and mangle the editor's markup.
	 *
	 * The fixture is deliberately markup an escaper would visibly damage: quoted
	 * attributes, an ampersand and a non-void tag.
	 */
	public function testASlotIsReturnedByteForByteWithNoEscapingOfItsOwn(): void
	{
		$strStored = '<p class="lead">Salt &amp; pepper <a href="/x?a=1&amp;b=2" title="It\'s">link</a></p>';

		$this->currentPage(array('rich_text_2' => $strStored));

		$objResult = $this->resolve(array('rich_text_2'));

		$this->assertSame($strStored, $objResult->getValue());
		$this->assertSame(OutputType::html, $objResult->getOutputType());
	}


	/**
	 * An insert tag stored INSIDE a slot comes back verbatim.
	 *
	 * A back end editor can save one - Input::encodeInput() only encodes insert
	 * tags on front end requests - and the DCA offers the insert-tag help wizard,
	 * so this WILL be tried. Contao's parser never re-scans the value a resolver
	 * returned (InsertTagParser::executeReplace() appends renderSubscription()'s
	 * result straight to its output), and the nested resolution this class opts
	 * into applies to the tag's PARAMETERS, not to its result.
	 *
	 * Pinning it here so that behaviour is a decision and not an accident:
	 * README.md documents it and tells the reader to use |insert_tag_raw where
	 * they print the value if they want it resolved.
	 */
	public function testAnInsertTagStoredInsideASlotIsNotResolvedByThisResolver(): void
	{
		$this->currentPage(array('rich_text_3' => 'before {{link_url::17}} after'));

		$this->assertSame('before {{link_url::17}} after', $this->resolve(array('rich_text_3'))->getValue());
	}


	// ---------------------------------------------------------------------
	// Image arms
	// ---------------------------------------------------------------------

	public function testTheImageArmHandsTheRendererThePageImageAndReturnsItsMarkup(): void
	{
		$objRenderer = $this->expectRenderer();
		$objPage = $this->currentPage(array('page_image' => 'binary-uuid'));

		$objRenderer
			->expects($this->once())
			->method('renderOne')
			->with($this->identicalTo('binary-uuid'), $this->identicalTo(null), $this->identicalTo($objPage))
			->willReturn('<picture>one</picture>')
		;

		$objResult = $this->resolve(array('page_image'));

		$this->assertSame('<picture>one</picture>', $objResult->getValue());
		$this->assertSame(OutputType::html, $objResult->getOutputType());
	}


	public function testTheThirdParameterIsPassedThroughAsTheImageSize(): void
	{
		$objRenderer = $this->expectRenderer();
		$objPage = $this->currentPage(array('page_image' => 'binary-uuid'));

		$objRenderer
			->expects($this->once())
			->method('renderOne')
			->with($this->identicalTo('binary-uuid'), $this->identicalTo('7'), $this->identicalTo($objPage))
			->willReturn('<picture>sized</picture>')
		;

		// {{zyppy_page::page_image::::7}} - empty pid, explicit size
		$this->assertSame('<picture>sized</picture>', $this->resolve(array('page_image', '', '7'))->getValue());
	}


	public function testAnEmptyImageSizeParameterIsNormalisedToNull(): void
	{
		$objRenderer = $this->expectRenderer();
		$objPage = $this->currentPage(array('page_image' => 'binary-uuid'));

		// '' would ask PictureFactory for a size named "", which is not the same
		// request as "no size given". identicalTo() is required throughout this
		// file: with() defaults to loose equality and '' == null in PHP, so a
		// plain null would happily accept the un-normalised value.
		$objRenderer
			->expects($this->once())
			->method('renderOne')
			->with($this->identicalTo('binary-uuid'), $this->identicalTo(null), $this->identicalTo($objPage))
			->willReturn('')
		;

		$this->assertSame('', $this->resolve(array('page_image', '', ''))->getValue());
	}


	public function testTheImagesArmSplitsTheIndexOffTheSubCommandWithASingleColon(): void
	{
		$objRenderer = $this->expectRenderer();
		$objPage = $this->currentPage(array('page_images' => serialize(array('uuid-a', 'uuid-b', 'uuid-c'))));

		$objRenderer
			->expects($this->once())
			->method('renderOne')
			->with($this->identicalTo('uuid-b'), $this->identicalTo(null), $this->identicalTo($objPage))
			->willReturn('<picture>b</picture>')
		;

		$this->assertSame('<picture>b</picture>', $this->resolve(array('page_images:1'))->getValue());
	}


	public function testTheImagesArmStillTakesThePidAndSizeAsNormalParameters(): void
	{
		$objRenderer = $this->expectRenderer();
		$objPage = $this->currentPage(array('page_images' => serialize(array('uuid-a', 'uuid-b'))));

		$objRenderer
			->expects($this->once())
			->method('renderOne')
			->with($this->identicalTo('uuid-a'), $this->identicalTo('3'), $this->identicalTo($objPage))
			->willReturn('<picture>a</picture>')
		;

		$this->assertSame('<picture>a</picture>', $this->resolve(array('page_images:0', '', '3'))->getValue());
	}


	public function testTheImagesArmYieldsNothingWhenTheIndexIsOutOfRange(): void
	{
		$objRenderer = $this->expectRenderer();
		$this->currentPage(array('page_images' => serialize(array('uuid-a'))));

		$objRenderer->expects($this->never())->method('renderOne');

		$objResult = $this->resolve(array('page_images:9'));

		$this->assertSame('', $objResult->getValue());

		// A page was still resolved, so the result still has to be tagged with it
		$this->assertSame(array('contao.db.tl_page.42'), $objResult->getCacheTags());
	}


	public function testTheImagesArmYieldsNothingWhenNoIndexWasAttached(): void
	{
		$objRenderer = $this->expectRenderer();
		$this->currentPage(array('page_images' => serialize(array('uuid-a'))));

		$objRenderer->expects($this->never())->method('renderOne');

		$this->assertSame('', $this->resolve(array('page_images'))->getValue());
	}


	public function testTheImagesArmYieldsNothingWhenTheFieldIsEmpty(): void
	{
		$objRenderer = $this->expectRenderer();
		$this->currentPage(array('page_images' => ''));

		$objRenderer->expects($this->never())->method('renderOne');

		$this->assertSame('', $this->resolve(array('page_images:0'))->getValue());
	}


	public function testTheShortImageAliasesResolveTheSameFields(): void
	{
		$objRenderer = $this->expectRenderer();

		$this->currentPage(array(
			'page_image' => 'uuid-single',
			'page_images' => serialize(array('uuid-a')),
			'page_image_url' => 'https://example.com/a.png',
		));

		$objRenderer
			->expects($this->exactly(2))
			->method('renderOne')
			->willReturn('<picture/>')
		;

		$this->assertSame('<picture/>', $this->resolve(array('image'))->getValue());
		$this->assertSame('<picture/>', $this->resolve(array('images:0'))->getValue());
		$this->assertSame('https://example.com/a.png', $this->resolve(array('image_url'))->getValue());
	}
}
