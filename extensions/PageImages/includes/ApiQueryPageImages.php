<?php

namespace PageImages;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\PageReferenceValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * Expose image information for a page via a new prop=pageimages API.
 *
 * @see https://www.mediawiki.org/wiki/Extension:PageImages#API
 *
 * @license WTFPL
 * @author Max Semenik
 * @author Ryan Kaldari
 * @author Yuvi Panda
 * @author Sam Smith
 */
class ApiQueryPageImages extends ApiQueryBase {
	private RepoGroup $repoGroup;

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		RepoGroup $repoGroup
	) {
		parent::__construct( $query, $moduleName, 'pi' );
		$this->repoGroup = $repoGroup;
	}

	/**
	 * Gets the set of titles to get page images for.
	 *
	 * Note well that the set of titles comprises the set of "good" titles
	 * (see {@see ApiPageSet::getGoodPages}) union the set of "missing"
	 * titles in the File namespace that might correspond to foreign files.
	 * The latter are included because titles in the File namespace are
	 * expected to be found with {@see \RepoGroup::findFile}.
	 *
	 * @return PageReference[] A map of page ID, which will be negative in the case
	 *  of missing titles in the File namespace, to PageReference object
	 */
	protected function getTitles() {
		$pageSet = $this->getPageSet();
		$titles = $pageSet->getGoodPages();

		// T98791: We want foreign files to be treated like local files
		// in #execute, so include the set of missing filespace pages,
		// which were initially rejected in ApiPageSet#execute.
		$missingTitles = $pageSet->getMissingTitlesByNamespace();
		$missingFileTitles = $missingTitles[NS_FILE] ?? [];

		// $titles is a map of ID to title object, which is ideal,
		// whereas $missingFileTitles is a map of title text to ID.
		// Do not use array_merge here as it doesn't preserve keys.
		foreach ( $missingFileTitles as $dbkey => $id ) {
			$titles[$id] = PageReferenceValue::localReference( NS_FILE, $dbkey );
		}

		return $titles;
	}

	/**
	 * Evaluates the parameters, performs the requested retrieval of page images,
	 * and sets up the result
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$prop = array_flip( $params['prop'] );
		if ( !count( $prop ) ) {
			$this->dieWithError(
				[ 'apierror-paramempty', $this->encodeParamName( 'prop' ) ], 'noprop'
			);
		}

		$allTitles = $this->getTitles();

		if ( count( $allTitles ) === 0 ) {
			return;
		}

		// Find the offset based on the continue param
		$offset = 0;
		if ( isset( $params['continue'] ) ) {
			// Get the position (not the key) of the 'continue' page within the
			// array of titles. Set this as the offset.
			$pageIds = array_keys( $allTitles );
			$offset = array_search( intval( $params['continue'] ), $pageIds );
			// If the 'continue' page wasn't found, die with error
			$this->dieContinueUsageIf( !$offset );
		}

		$limit = $params['limit'];
		// Slice the part of the array we want to find images for
		$titles = array_slice( $allTitles, $offset, $limit, true );

		// Get the next item in the title array and use it to set the continue value
		$nextItemArray = array_slice( $allTitles, $offset + $limit, 1, true );
		if ( $nextItemArray ) {
			$this->setContinueEnumParameter( 'continue', key( $nextItemArray ) );
		}

		// Find any titles in the file namespace so we can handle those separately
		$filePageTitles = [];
		foreach ( $titles as $id => $title ) {
			if ( $title->getNamespace() === NS_FILE ) {
				$filePageTitles[$id] = $title;
				unset( $titles[$id] );
			}
		}

		$size = $params['thumbsize'];
		$lang = $params['langcode'];
		// Extract page images from the page_props table
		if ( count( $titles ) > 0 ) {
			$this->addTables( 'page_props' );
			$this->addFields( [ 'pp_page', 'pp_propname', 'pp_value' ] );
			$this->addWhere( [ 'pp_page' => array_keys( $titles ),
				'pp_propname' => PageImages::getPropNames( $params['license'] ) ] );

			$res = $this->select( __METHOD__ );

			$buffer = [];
			$propNameAny = PageImages::getPropName( false );
			foreach ( $res as $row ) {
				$pageId = $row->pp_page;
				if ( !array_key_exists( $pageId, $buffer ) || $row->pp_propname === $propNameAny ) {
					$buffer[$pageId] = $row;
				}
			}

			foreach ( $buffer as $pageId => $row ) {
				$fileName = $row->pp_value;
				$this->setResultValues( $prop, $pageId, $fileName, $size, $lang );
			}
		// End page props image extraction
		}

		// Extract images from file namespace pages. In this case we just use
		// the file itself rather than searching for a page_image. (Bug 50252)
		foreach ( $filePageTitles as $pageId => $title ) {
			$fileName = $title->getDBkey();
			$this->setResultValues( $prop, $pageId, $fileName, $size, $lang );
		}
	}

	/**
	 * Get the cache mode for the data generated by this module
	 *
	 * @param array $params Ignored parameters
	 * @return string Always returns "public"
	 */
	public function getCacheMode( $params ) {
		return 'public';
	}

	/**
	 * For a given page, set API return values for thumbnail and pageimage as needed
	 *
	 * @param array $prop The prop values from the API request
	 * @param int $pageId The ID of the page
	 * @param string $fileName The name of the file to transform
	 * @param int $size The thumbsize value from the API request
	 * @param string $lang The language code from the API request
	 */
	protected function setResultValues( array $prop, $pageId, $fileName, $size, $lang ) {
		$vals = [];
		if ( isset( $prop['thumbnail'] ) || isset( $prop['original'] ) ) {
			$file = $this->repoGroup->findFile( $fileName );
			if ( $file ) {
				if ( isset( $prop['thumbnail'] ) ) {
					$thumb = $file->transform( [
						'width' => $size,
						'lang' => $lang
					] );
					if ( $thumb && $thumb->getUrl() ) {
						// You can request a thumb 1000x larger than the original
						// which (in case of bitmap original) will return a Thumb object
						// that will lie about its size but have the original as an image.
						$reportedSize = $thumb->fileIsSource() ? $file : $thumb;
						$vals['thumbnail'] = [
							'source' => wfExpandUrl( $thumb->getUrl(), PROTO_CURRENT ),
							'width' => $reportedSize->getWidth(),
							'height' => $reportedSize->getHeight(),
						];
					}
				}

				if ( isset( $prop['original'] ) ) {
					$originalSize = [
						'width' => $file->getWidth(),
						'height' => $file->getHeight()
					];
					if ( $lang ) {
						$file = $file->transform( [
							'lang' => $lang,
							'width' => $originalSize['width'],
							'height' => $originalSize['height']
						] );
					}
					$original_url = wfExpandUrl( $file->getUrl(), PROTO_CURRENT );

					$vals['original'] = [
						'source' => $original_url,
						'width' => $originalSize['width'],
						'height' => $originalSize['height']
					];
				}
			}
		}

		if ( isset( $prop['name'] ) ) {
			$vals['pageimage'] = $fileName;
		}

		$this->getResult()->addValue( [ 'query', 'pages' ], $pageId, $vals );
	}

	/**
	 * Return an array describing all possible parameters to this module
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'prop' => [
				ParamValidator::PARAM_TYPE => [ 'thumbnail', 'name', 'original' ],
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_DEFAULT => 'thumbnail|name',
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'thumbsize' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 50,
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 50,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 50,
				IntegerDef::PARAM_MAX2 => 100,
			],
			'license' => [
				ParamValidator::PARAM_TYPE => [ PageImages::LICENSE_FREE, PageImages::LICENSE_ANY ],
				ParamValidator::PARAM_ISMULTI => false,
				ParamValidator::PARAM_DEFAULT => $this->getConfig()->get( 'PageImagesAPIDefaultLicense' ),
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'continue' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
			'langcode' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => null
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&prop=pageimages&titles=Albert%20Einstein&pithumbsize=100' =>
				'apihelp-query+pageimages-example-1',
		];
	}

	/**
	 * @see ApiBase::getHelpUrls()
	 * @return string
	 */
	public function getHelpUrls() {
		return "https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:PageImages#API";
	}

}
