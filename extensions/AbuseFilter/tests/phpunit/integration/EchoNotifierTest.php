<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesRegistry;
use MediaWiki\Extension\AbuseFilter\EchoNotifier;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\EchoNotifier
 */
class EchoNotifierTest extends MediaWikiIntegrationTestCase {

	private const USER_IDS = [
		'1' => 1,
		'2' => 42,
	];

	private function getFilterLookup( ?int $userID = null ): FilterLookup {
		$lookup = $this->createMock( FilterLookup::class );
		$lookup->method( 'getFilter' )
			->willReturnCallback( function ( $filter, $global ) use ( $userID ) {
				$userID ??= self::USER_IDS[ $global ? "global-$filter" : $filter ] ?? 0;
				$filterObj = $this->createMock( ExistingFilter::class );
				$filterObj->method( 'getUserID' )->willReturn( $userID );
				return $filterObj;
			} );
		return $lookup;
	}

	public static function provideDataForEvent(): array {
		return [
			[ true, 1, 1 ],
			[ true, 2, 42 ],
			[ false, 1, 1 ],
			[ false, 2, 42 ],
		];
	}

	/**
	 * @dataProvider provideDataForEvent
	 */
	public function testGetDataForEvent( bool $loaded, int $filter, int $userID ) {
		$expectedThrottledActions = [];
		$notifier = new EchoNotifier(
			$this->getFilterLookup(),
			$this->createMock( ConsequencesRegistry::class ),
			$loaded
		);
		[
			'type' => $type,
			'title' => $title,
			'extra' => $extra
		] = $notifier->getDataForEvent( $filter );

		$this->assertSame( EchoNotifier::EVENT_TYPE, $type );
		$this->assertInstanceOf( Title::class, $title );
		$this->assertSame( -1, $title->getNamespace() );
		[ , $subpage ] = explode( '/', $title->getText(), 2 );
		$this->assertSame( (string)$filter, $subpage );
		$this->assertSame( [ 'user' => $userID, 'throttled-actions' => $expectedThrottledActions ], $extra );
	}

	public function testNotifyForFilter() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Echo' );
		// Use a real user, or Echo will throw an exception.
		$user = $this->getTestUser()->getUserIdentity();
		$notifier = new EchoNotifier(
			$this->getFilterLookup( $user->getId() ),
			$this->createMock( ConsequencesRegistry::class ),
			true
		);
		$this->assertInstanceOf( Event::class, $notifier->notifyForFilter( 1 ) );
	}

	public function testNotifyForFilter_EchoNotLoaded() {
		$notifier = new EchoNotifier(
			$this->createNoOpMock( FilterLookup::class ),
			$this->createMock( ConsequencesRegistry::class ),
			false
		);
		$this->assertFalse( $notifier->notifyForFilter( 1 ) );
	}

}
