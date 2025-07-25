<?php
// phpcs:disable Generic.Files.LineLength.TooLong
declare( strict_types = 1 );

namespace Cite\Tests\Integration;

use Cite\Parsoid\ReferenceListTagHandler;
use MediaWiki\Config\Config;
use MediaWiki\Registration\ExtensionRegistry;
use Wikimedia\ObjectFactory\ObjectFactory;
use Wikimedia\Parsoid\Config\PageConfig;
use Wikimedia\Parsoid\Core\ContentMetadataCollector;
use Wikimedia\Parsoid\Core\SelserData;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Mocks\MockDataAccess;
use Wikimedia\Parsoid\Mocks\MockPageConfig;
use Wikimedia\Parsoid\Mocks\MockPageContent;
use Wikimedia\Parsoid\Mocks\MockSiteConfig;
use Wikimedia\Parsoid\NodeData\DataMw;
use Wikimedia\Parsoid\NodeData\DataMwBody;
use Wikimedia\Parsoid\Parsoid;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\TitleValue;

/**
 * @coversDefaultClass \Cite\Parsoid\Cite
 * @license GPL-2.0-or-later
 */
class CiteParsoidTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		// Ensure these tests are independent of LocalSettings
		parent::setUp();
		$this->overrideConfigValue( 'CiteResponsiveReferences', true );
		$this->overrideConfigValue( 'CiteResponsiveReferencesThreshold', 10 );
	}

	private function getSiteConfig( $options ) {
		$objectFactory = $this->getServiceContainer()->getObjectFactory();
		$siteConfig = new class( $options, $objectFactory ) extends MockSiteConfig {
			private ObjectFactory $objectFactory;

			public function __construct(
				array $opts,
				ObjectFactory $objectFactory
			) {
				parent::__construct( $opts );
				$this->objectFactory = $objectFactory;
			}

			public function getObjectFactory(): ObjectFactory {
				return $this->objectFactory;
			}
		};
		// Ensure that the Cite module is registered!
		$extensionParsoidModules =
			ExtensionRegistry::getInstance()->getAttribute( 'ParsoidModules' );
		foreach ( $extensionParsoidModules as $configOrSpec ) {
			$siteConfig->registerExtensionModule( $configOrSpec );
		}
		return $siteConfig;
	}

	/**
	 * @param string $wt
	 * @param array $pageOpts
	 * @return Element
	 */
	private function parseWT( string $wt, array $pageOpts = [] ): Element {
		$siteConfig = $this->getSiteConfig( [] );
		$dataAccess = new MockDataAccess( $siteConfig, [] );
		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$content = new MockPageContent( [ 'main' => $wt ] );
		$pageConfig = new MockPageConfig( $siteConfig, $pageOpts, $content );
		$html = $parsoid->wikitext2html( $pageConfig, [ 'wrapSections' => false ] );

		return DOMCompat::getBody( DOMUtils::parseHTML( $html ) );
	}

	private function wtToLint( string $wt ): array {
		$siteConfig = $this->getSiteConfig( [ 'linting' => true ] );
		$dataAccess = new class( $siteConfig, [] ) extends MockDataAccess {
			/** @inheritDoc */
			public function addTrackingCategory(
				PageConfig $pageConfig,
				ContentMetadataCollector $metadata,
				string $key
			): void {
				if ( $key === 'cite-tracking-category-cite-error' ) {
					$tv = TitleValue::tryNew( 14, 'Pages with reference errors' );
					$metadata->addCategory( $tv );
					return;
				}
				parent::addTrackingCategory( $pageConfig, $metadata, $key );
			}
		};
		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$content = new MockPageContent( [ $options['pageName'] ?? 'main' => $wt ] );
		$pageConfig = new MockPageConfig( $siteConfig, [], $content );

		return $parsoid->wikitext2lint( $pageConfig, [] );
	}

	/**
	 * Wikilinks use ./ prefixed urls. For reasons of consistency,
	 * we should use a similar format for internal cite urls.
	 * This spec ensures that we don't inadvertently break that requirement.
	 * should use ./ prefixed urls for cite links
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testWikilinkUseDotSlashPrefix(): void {
		$description = "Regression Specs: should use ./ prefixed urls for cite links";
		$wt = "a [[Foo]] <ref>b</ref>";
		$docBody = $this->parseWT( $wt, [ 'title' => 'Main_Page' ] );

		$attrib = DOMCompat::getAttribute(
			DOMCompat::querySelectorAll( $docBody, ".mw-ref a" )[0],
			'href'
		);
		$this->assertEquals( './Main_Page#cite_note-1', $attrib, $description );

		$attrib = DOMCompat::getAttribute(
			DOMCompat::querySelectorAll( $docBody, "#cite_note-1 a" )[0],
			'href'
		);
		$this->assertEquals( './Main_Page#cite_ref-1', $attrib, $description );
	}

	/**
	 * I1f572f996a7c2b3b852752f5348ebb60d8e21c47 introduced a backwards
	 * incompatibility.  This test asserts that selser will restore content
	 * for invalid follows that would otherwise be dropped since it wasn't
	 * span wrapped.
	 * @covers \Cite\Parsoid\RefTagHandler::domToWikitext
	 */
	public function testSelserFollowsWrap(): void {
		$wt = 'Hi ho <ref follow="123">hi ho</ref>';
		$html = <<<EOT
<p data-parsoid='{"dsr":[0,35,0,0]}'>Hi ho <sup about="#mwt2" class="mw-ref" id="cite_ref-1" rel="dc:references" typeof="mw:Extension/ref mw:Error" data-parsoid='{"dsr":[6,35,18,6]}' data-mw='{"name":"ref","attrs":{"follow":"123"},"body":{"id":"mw-reference-text-cite_note-1"},"errors":[{"key":"cite_error_references_missing_key","params":["123"]}]}'><a href="./Main_Page#cite_note-1" data-parsoid="{}"><span class="mw-reflink-text" data-parsoid="{}">[1]</span></a></sup></p>

<div class="mw-references-wrap" typeof="mw:Extension/references" about="#mwt3" data-parsoid='{"dsr":[36,36,0,0]}' data-mw='{"name":"references","attrs":{},"autoGenerated":true}'><ol class="mw-references references" data-parsoid="{}"><li about="#cite_note-1" id="cite_note-1" data-parsoid="{}"><a href="./Main_Page#cite_ref-1" rel="mw:referencedBy" data-parsoid="{}"><span class="mw-linkback-text" data-parsoid="{}">↑ </span></a> <span id="mw-reference-text-cite_note-1" class="mw-reference-text" data-parsoid="{}">hi ho</span></li></ol></div>
EOT;
		$editedHtml = <<<EOT
<p data-parsoid='{"dsr":[0,35,0,0]}'>Ha ha <sup about="#mwt2" class="mw-ref" id="cite_ref-1" rel="dc:references" typeof="mw:Extension/ref mw:Error" data-parsoid='{"dsr":[6,35,18,6]}' data-mw='{"name":"ref","attrs":{"follow":"123"},"body":{"id":"mw-reference-text-cite_note-1"},"errors":[{"key":"cite_error_references_missing_key","params":["123"]}]}'><a href="./Main_Page#cite_note-1" data-parsoid="{}"><span class="mw-reflink-text" data-parsoid="{}">[1]</span></a></sup></p>

<div class="mw-references-wrap" typeof="mw:Extension/references" about="#mwt3" data-parsoid='{"dsr":[36,36,0,0]}' data-mw='{"name":"references","attrs":{},"autoGenerated":true}'><ol class="mw-references references" data-parsoid="{}"><li about="#cite_note-1" id="cite_note-1" data-parsoid="{}"><a href="./Main_Page#cite_ref-1" rel="mw:referencedBy" data-parsoid="{}"><span class="mw-linkback-text" data-parsoid="{}">↑ </span></a> <span id="mw-reference-text-cite_note-1" class="mw-reference-text" data-parsoid="{}">hi ho</span></li></ol></div>
EOT;

		$siteConfig = $this->getSiteConfig( [] );
		$dataAccess = new MockDataAccess( $siteConfig, [] );
		$parsoid = new Parsoid( $siteConfig, $dataAccess );
		$content = new MockPageContent( [ 'main' => $wt ] );
		$pageConfig = new MockPageConfig( $siteConfig, [], $content );

		// Without selser
		$editedWt = $parsoid->html2wikitext( $pageConfig, $editedHtml, [], null );
		$this->assertEquals( "Ha ha <ref follow=\"123\"></ref>\n\n<references />", $editedWt );

		// // With selser
		$selserData = new SelserData( $wt, $html );
		$editedWt = $parsoid->html2wikitext( $pageConfig, $editedHtml, [], $selserData );
		$this->assertEquals( "Ha ha <ref follow=\"123\">hi ho</ref>\n\n", $editedWt );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testLintIssueInRefTags(): void {
		$desc = "should attribute linter issues to the ref tag";
		$result = $this->wtToLint( "a <ref><b>x</ref> <references/>" );
		$this->assertCount( 1, $result, $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 7, 11, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'b', $result[0]['params']['name'], $desc );

		$desc = "should attribute linter issues to the ref tag even if references is templated";
		$result = $this->wtToLint( "a <ref><b>x</ref> {{1x|<references/>}}" );
		$this->assertCount( 1, $result, $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 7, 11, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'b', $result[0]['params']['name'], $desc );

		$desc = "should attribute linter issues to the ref tag even when " .
			"ref and references are both templated";
		$wt = "a <ref><b>x</ref> b <ref>{{1x|<b>x}}</ref> " .
			"{{1x|c <ref><b>y</ref>}} {{1x|<references/>}}";
		$result = $this->wtToLint( $wt );
		$this->assertCount( 3, $result, $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 7, 11, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'b', $result[0]['params']['name'], $desc );

		$this->assertEquals( 'missing-end-tag', $result[1]['type'], $desc );
		$this->assertEquals( [ 25, 36, null, null ], $result[1]['dsr'], $desc );
		$this->assertTrue( isset( $result[1]['params'] ), $desc );
		$this->assertEquals( 'b', $result[1]['params']['name'], $desc );
		$this->assertTrue( isset( $result[1]['templateInfo'] ), $desc );
		$this->assertEquals( 'Template:1x', $result[1]['templateInfo']['name'], $desc );

		$this->assertEquals( 'missing-end-tag', $result[2]['type'], $desc );
		$this->assertEquals( [ 43, 67, null, null ], $result[2]['dsr'], $desc );
		$this->assertTrue( isset( $result[2]['params'] ), $desc );
		$this->assertEquals( 'b', $result[2]['params']['name'], $desc );
		$this->assertTrue( isset( $result[2]['templateInfo'] ), $desc );
		$this->assertEquals( 'Template:1x', $result[2]['templateInfo']['name'], $desc );

		$desc = "should attribute linter issues properly when ref " .
			"tags are in non-templated references tag";
		$wt = "a <ref><s>x</ref> b <ref name='x' /> <references> " .
			"<ref name='x'>{{1x|<b>boo}}</ref> </references>";
		$result = $this->wtToLint( $wt );
		$this->assertCount( 2, $result, $desc );

		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 7, 11, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 's', $result[0]['params']['name'], $desc );

		$this->assertEquals( 'missing-end-tag', $result[1]['type'], $desc );
		$this->assertEquals( [ 64, 77, null, null ], $result[1]['dsr'], $desc );
		$this->assertTrue( isset( $result[1]['params'] ), $desc );
		$this->assertEquals( 'b', $result[1]['params']['name'], $desc );
		$this->assertTrue( isset( $result[1]['templateInfo'] ), $desc );
		$this->assertEquals( 'Template:1x', $result[1]['templateInfo']['name'], $desc );

		$desc = "should lint inside ref with redefinition";
		$wt = "<ref name=\"test\">123</ref>\n" .
			"<ref name=\"test\"><s>345</ref>\n" .
			"</references>";
		$result = $this->wtToLint( $wt );
		$this->assertCount( 1, $result, $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 44, 50, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 's', $result[0]['params']['name'], $desc );

		// Should not get into a cycle trying to lint ref in ref
		$this->wtToLint(
			"{{#tag:ref|<ref name='y' />|name='x'}}{{#tag:ref|<ref name='x' />|name='y'}}<ref name='x' />"
		);
		$this->wtToLint( "<ref name='x' />{{#tag:ref|<ref name='x' />|name=x}}" );
	}

	/**
	 * @covers \Cite\Parsoid\ReferenceListTagHandler::processAttributeEmbeddedHTML
	 */
	public function testProcessAttributeEmbeddedHTML() {
		$doc = DOMUtils::parseHTML( '' );
		DOMDataUtils::prepareDoc( $doc );
		$elt = $doc->createElement( 'a' );
		DOMDataUtils::setDataMw( $elt, new DataMw( [
			'body' => DataMwBody::new( [ 'html' => 'old' ] ),
		] ) );

		$refs = new ReferenceListTagHandler( $this->createNoOpMock( Config::class ) );
		$refs->processAttributeEmbeddedHTML(
			$this->createNoOpMock( ParsoidExtensionAPI::class ),
			$elt,
			static fn () => 'new'
		);

		$this->assertSame( 'new', DOMDataUtils::getDataMw( $elt )->body->html );
	}
}
