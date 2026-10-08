<?php

/**
 * @copyright 	Andrew Stevens
 * @author 		Andrew Stevens
 * @license 	AGPL-3.0-only
 * @package 	Zyppy Page
 * @link 		https://github.com/asconsulting/contao-zyppy-page
 */



namespace Zyppy\Page\Tests;

use Contao\CoreBundle\ContaoCoreBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Zyppy\Page\Backend\ImageSizeOptions;
use Zyppy\Page\ContaoManager\Plugin;
use Zyppy\Page\Image\PageImageRenderer;
use Zyppy\Page\InsertTag\PageInsertTag;
use Zyppy\Page\Module\PagePreview;
use Zyppy\Page\Module\RelatedPages;
use Zyppy\Page\PageBundle;


/**
 * Guards the two bundle facts that break silently.
 */
class PageBundleTest extends TestCase
{
	public function testTheBundlePathIsTheRepositoryRootSoPublicIsPublished(): void
	{
		$strPath = (new PageBundle())->getPath();

		$this->assertDirectoryExists($strPath . '/public');
		$this->assertFileExists($strPath . '/composer.json');
		$this->assertFileExists($strPath . '/src/PageBundle.php');
	}


	public function testTheAssetDirectoryNameIsTheOneTheSliderAssetsAreServedFrom(): void
	{
		// assets:install derives the public directory from the bundle CLASS name:
		// preg_replace('/bundle$/', '', strtolower($bundle->getName()))
		// (symfony/framework-bundle AssetsInstallCommand:126). The slider markup
		// asks for /bundles/page/css/page_slider.css and /bundles/page/js/
		// page_slider.js, so renaming PageBundle 404s both with no error anywhere
		// in PHP - neither the container build nor php -l notices.
		//
		// Only the derivation and the source files are asserted, NOT the contents
		// of any template: the Twig port (Step 6) replaces mod_pagepreview.html5,
		// and a test that read it would ambush that PR. WHERE the URLs are written
		// is the template's business; that 'page' is the right word is this
		// bundle's.
		$objBundle = new PageBundle();

		$this->assertSame('PageBundle', $objBundle->getName());
		$this->assertSame('page', preg_replace('/bundle$/', '', strtolower($objBundle->getName())));

		$this->assertFileExists($objBundle->getPath() . '/public/css/page_slider.css');
		$this->assertFileExists($objBundle->getPath() . '/public/js/page_slider.js');
	}


	/**
	 * Existence is not the contract - an empty file passes assertFileExists() and
	 * ships a dead slider. Both assets make claims README.md relies on, so assert
	 * the claims:
	 *
	 * - The CSS is the reason the slider is visible at all. page_slider.js only
	 *   toggles .active_preview / .inactive_preview; with no rule that hides an
	 *   inactive body item, every item stays on screen and the slider appears to
	 *   do nothing. That was one of the original defects.
	 * - The JS must NOT load its own jQuery (a second copy breaks every plugin
	 *   bound to the first), and must pair the two lists by the rel attribute -
	 *   which is what makes rel="{{ item.alias }}" load-bearing in the two nav
	 *   templates.
	 */
	public function testTheSliderAssetsCarryTheBehaviourTheReadmeDocuments(): void
	{
		$strPath = (new PageBundle())->getPath();

		$strCss = file_get_contents($strPath . '/public/css/page_slider.css');

		$this->assertMatchesRegularExpression(
			'/\.body\s+ul\.level_1\s*>\s*li\.inactive_preview\s*\{[^}]*display:\s*none/',
			$strCss,
			'without a rule hiding an inactive body item the slider is invisible',
		);

		$strJs = file_get_contents($strPath . '/public/js/page_slider.js');

		$this->assertStringContainsString('$(this).attr(\'rel\')', $strJs, 'the slider pairs the header and body lists by the rel attribute');
		$this->assertStringContainsString('pagePreviewSlider', $strJs, 'the plugin has to be defined and called, or nothing ever starts it');
		$this->assertStringContainsString('nav.mod_pagepreview[data-show]', $strJs, 'the auto-start selector must match what mod_pagepreview emits');

		// The jQuery guard, both halves: it must bail rather than run, and it must
		// never fetch a copy of its own.
		$this->assertStringContainsString("typeof window.jQuery === 'undefined'", $strJs);
		$this->assertDoesNotMatchRegularExpression('#(src\s*=|createElement\()[^\n]*jquery#i', $strJs, 'the bundle must never load a second jQuery');
	}


	public function testThePluginRegistersTheBundleAfterTheContaoCoreBundle(): void
	{
		$arrBundles = (new Plugin())->getBundles($this->createStub(\Contao\ManagerPlugin\Bundle\Parser\ParserInterface::class));

		$this->assertCount(1, $arrBundles);
		$this->assertSame(PageBundle::class, $arrBundles[0]->getName());
		$this->assertSame(array(ContaoCoreBundle::class), $arrBundles[0]->getLoadAfter());
	}


	/**
	 * Loads config/services.yaml into a bare container, exactly as the bundle
	 * extension does, and asserts what the glob registers and what it leaves out.
	 *
	 * The classes Contao constructs itself must NOT be services. Registered by
	 * the glob they become private definitions nothing references, which the
	 * removal pass deletes - and System::importStatic(), which is how the DCA
	 * options_callback reaches ImageSizeOptions, throws ServiceNotFoundException
	 * ("removed or inlined") for a removed id whenever kernel.debug is on, instead
	 * of falling through to new. So the tl_module edit form of both module types
	 * 500s with the back end debug mode on, and only there. Excluded from the
	 * glob, the class is plainly constructed on every path.
	 */
	public function testTheContaoConstructedClassesAreNotRegisteredAsServices(): void
	{
		$objContainer = new ContainerBuilder();
		$objLoader = new YamlFileLoader($objContainer, new FileLocator((new PageBundle())->getPath() . '/config'));
		$objLoader->load('services.yaml');

		// The glob did run: the two real services are there, and the one the
		// legacy modules fetch by hand out of the container is public.
		$this->assertTrue($objContainer->hasDefinition(PageInsertTag::class));
		$this->assertTrue($objContainer->hasDefinition(PageImageRenderer::class));
		$this->assertTrue($objContainer->getDefinition(PageImageRenderer::class)->isPublic());

		foreach (array(ImageSizeOptions::class, PagePreview::class, RelatedPages::class, Plugin::class) as $strClass)
		{
			$this->assertFalse($objContainer->hasDefinition($strClass), $strClass . ' is constructed by Contao and must stay out of the service glob');
		}
	}
}
