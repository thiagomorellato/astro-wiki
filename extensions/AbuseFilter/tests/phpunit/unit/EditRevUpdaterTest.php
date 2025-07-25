<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use InvalidArgumentException;
use MediaWiki\Extension\AbuseFilter\CentralDBManager;
use MediaWiki\Extension\AbuseFilter\EditRevUpdater;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\WikiPage;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\UpdateQueryBuilder;

/**
 * @group Test
 * @group AbuseFilter
 * @covers \MediaWiki\Extension\AbuseFilter\EditRevUpdater
 */
class EditRevUpdaterTest extends MediaWikiUnitTestCase {

	public function testConstruct() {
		$this->assertInstanceOf(
			EditRevUpdater::class,
			new EditRevUpdater(
				$this->createMock( CentralDBManager::class ),
				$this->createMock( RevisionLookup::class ),
				$this->createMock( LBFactory::class ),
				''
			)
		);
	}

	/**
	 * @param IDatabase|null $localDB
	 * @param IDatabase|null $centralDB
	 * @param RevisionLookup|null $revLookup
	 * @return EditRevUpdater
	 */
	private function getUpdater(
		?IDatabase $localDB = null,
		?IDatabase $centralDB = null,
		?RevisionLookup $revLookup = null
	): EditRevUpdater {
		$lbFactory = $this->createMock( LBFactory::class );
		$lbFactory->method( 'getPrimaryDatabase' )
			->willReturn( $localDB ?? $this->createMock( IDatabase::class ) );

		$dbManager = $this->createMock( CentralDBManager::class );
		$dbManager->method( 'getConnection' )
			->willReturn( $centralDB ?? $this->createMock( IDatabase::class ) );

		return new EditRevUpdater(
			$dbManager,
			$revLookup ?? $this->createMock( RevisionLookup::class ),
			$lbFactory,
			'fake-wiki-id'
		);
	}

	/**
	 * @param LinkTarget $target
	 * @return array
	 */
	private function getPageAndRev( LinkTarget $target ): array {
		$title = Title::newFromLinkTarget( $target );
		// Legacy code. Yay.
		$title->mArticleID = 123456;

		$wikiPage = $this->createMock( WikiPage::class );
		$wikiPage->method( 'getTitle' )->willReturn( $title );

		return [ $wikiPage, new MutableRevisionRecord( $title ) ];
	}

	public function testUpdateRev_noIDs() {
		$titleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater' );
		$this->assertFalse( $this->getUpdater()->updateRev( ...$this->getPageAndRev( $titleValue ) ) );
	}

	public function testUpdateRev_differentPages() {
		$titleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater' );
		$updater = $this->getUpdater();
		$diffTitleValue = new TitleValue( NS_HELP, 'Foobar' );
		[ $diffPage, ] = $this->getPageAndRev( $diffTitleValue );
		$updater->setLastEditPage( $diffPage );
		$updater->setLogIdsForTarget( $titleValue, [ 'local' => [ 1, 2 ], 'global' => [] ] );
		$this->assertFalse( $updater->updateRev( ...$this->getPageAndRev( $titleValue ) ) );
	}

	public function testUpdateRev_nullEdit() {
		$titleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater' );
		[ $page, $rev ] = $this->getPageAndRev( $titleValue );
		$rev->setParentId( 42 );
		$revLookup = $this->createMock( RevisionLookup::class );
		$revLookup->expects( $this->once() )->method( 'getRevisionById' )->with( 42 )->willReturn( $rev );
		$updater = $this->getUpdater( null, null, $revLookup );
		$updater->setLastEditPage( $page );
		$updater->setLogIdsForTarget( $titleValue, [ 'local' => [ 1 ], 'global' => [ 1 ] ] );

		$this->assertFalse( $updater->updateRev( $page, $rev ) );
	}

	/**
	 * @param array $ids
	 * @dataProvider provideIDsSuccess
	 */
	public function testUpdateRev_success( array $ids ) {
		$titleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater' );
		[ $page, $rev ] = $this->getPageAndRev( $titleValue );
		$localDB = $this->createMock( IDatabase::class );
		$localDB->expects( $ids['local'] ? $this->once() : $this->never() )->method( 'update' );
		$localDB->expects( $ids['local'] ? $this->once() : $this->never() )->method( 'newUpdateQueryBuilder' )
			->willReturnCallback( static function () use ( $localDB ) {
				return new UpdateQueryBuilder( $localDB );
			} );
		$centralDB = $this->createMock( IDatabase::class );
		$centralDB->expects( $ids['global'] ? $this->once() : $this->never() )->method( 'update' );
		$centralDB->expects( $ids['global'] ? $this->once() : $this->never() )->method( 'newUpdateQueryBuilder' )
			->willReturnCallback( static function () use ( $centralDB ) {
				return new UpdateQueryBuilder( $centralDB );
			} );
		$updater = $this->getUpdater( $localDB, $centralDB );
		$updater->setLastEditPage( $page );
		$updater->setLogIdsForTarget( $titleValue, $ids );

		$this->assertTrue( $updater->updateRev( $page, $rev ) );
	}

	public static function provideIDsSuccess(): array {
		return [
			'local only' => [ [ 'local' => [ 1, 2 ], 'global' => [] ] ],
			'global only' => [ [ 'local' => [], 'global' => [ 1, 2 ] ] ],
			'local and global' => [ [ 'local' => [ 1, 2 ], 'global' => [ 1, 2 ] ] ],
		];
	}

	public function testUpdateRev_multipleTitles() {
		$goodTitleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater' );
		$badTitleValue = new TitleValue( NS_PROJECT, 'These should not be used' );
		$goodIDs = [ 'local' => [ 1, 2 ], 'global' => [] ];
		$badIDs = [ 'local' => [], 'global' => [ 1, 2 ] ];
		[ $page, $rev ] = $this->getPageAndRev( $goodTitleValue );
		$localDB = $this->createMock( IDatabase::class );
		$localDB->expects( $this->once() )->method( 'newUpdateQueryBuilder' )
			->willReturnCallback( static function () use ( $localDB ) {
				return new UpdateQueryBuilder( $localDB );
			} );
		$centralDB = $this->createMock( IDatabase::class );
		$centralDB->expects( $this->never() )->method( 'newUpdateQueryBuilder' );
		$updater = $this->getUpdater( $localDB, $centralDB );
		$updater->setLastEditPage( $page );
		$updater->setLogIdsForTarget( $goodTitleValue, $goodIDs );
		$updater->setLogIdsForTarget( $badTitleValue, $badIDs );

		$this->assertTrue( $updater->updateRev( $page, $rev ) );
	}

	public function testClearLastEditPage() {
		$titleValue = new TitleValue( NS_PROJECT, 'EditRevUpdater-clear' );
		[ $page, $revisionRecord ] = $this->getPageAndRev( $titleValue );
		$updater = $this->getUpdater();
		$updater->setLastEditPage( $page );
		$updater->setLogIdsForTarget( $titleValue, [ 'local' => [ 1, 2 ], 'global' => [] ] );
		$updater->clearLastEditPage();
		$this->assertFalse( $updater->updateRev( $page, $revisionRecord ) );
	}

	/**
	 * @param array $ids
	 * @dataProvider provideInvalidIDs
	 */
	public function testSetLogIdsForTarget_invalid( array $ids ) {
		$updater = $this->getUpdater();
		$this->expectException( InvalidArgumentException::class );
		$updater->setLogIdsForTarget( new TitleValue( NS_MAIN, 'x' ), $ids );
	}

	public static function provideInvalidIDs(): array {
		return [
			'empty' => [ [] ],
			'missing key' => [ [ 'local' => [ 1 ] ] ],
			'extra key' => [ [ 'local' => [ 1 ], 'global' => [ 1 ], 'foo' => [ 1 ] ] ],
		];
	}
}
