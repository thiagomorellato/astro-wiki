<?php

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Degroup;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\User\UserIdentityValue;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Degroup
 * @todo Make this a unit test once ManualLogEntry is servicified (T253717) and DI is possible for User::newSystemUser
 */
class DegroupTest extends MediaWikiIntegrationTestCase {
	use ConsequenceGetMessageTestTrait;

	private function getMsgLocalizer(): MessageLocalizer {
		$ml = $this->createMock( MessageLocalizer::class );
		$ml->method( 'msg' )->willReturnCallback( function ( $k, ...$p ) {
			return $this->getMockMessage( $k, $p );
		} );
		return $ml;
	}

	private function getFilterUser(): FilterUser {
		// TODO: Can't use mocks until ManualLogEntry is servicified (T253717)
		return AbuseFilterServices::getFilterUser();
	}

	public function testExecute() {
		$user = new UserIdentityValue( 1, 'Degrouped user' );
		$params = $this->provideGetMessageParameters( $user )->current()[0];
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->expects( $this->once() )
			->method( 'removeUserFromGroup' )
			->with( $user, 'sysop' );
		$filterUser = $this->getFilterUser();
		$userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtils->method( 'isNamed' )->willReturn( true );
		$degroup = new Degroup(
			$params,
			VariableHolder::newFromArray( [ 'user_groups' => [ '*', 'user', 'sysop' ] ] ),
			$userGroupManager,
			$userIdentityUtils,
			$filterUser,
			$this->getMsgLocalizer()
		);
		$this->assertTrue( $degroup->execute() );
	}

	public function testExecute_noGroups() {
		$params = $this->provideGetMessageParameters()->current()[0];
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->expects( $this->never() )
			->method( 'removeUserFromGroup' );

		$degroup = new Degroup(
			$params,
			VariableHolder::newFromArray( [ 'user_groups' => [ '*', 'user' ] ] ),
			$userGroupManager,
			$this->createMock( UserIdentityUtils::class ),
			$this->createMock( FilterUser::class ),
			$this->getMsgLocalizer()
		);
		$this->assertFalse( $degroup->execute() );
	}

	public function testExecute_variableNotSet() {
		$user = new UserIdentityValue( 1, 'Degrouped user' );
		$params = $this->provideGetMessageParameters( $user )->current()[0];
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->method( 'getUserEffectiveGroups' )
			->with( $user )
			->willReturn( [ '*', 'user', 'sysop' ] );
		$userGroupManager->expects( $this->once() )
			->method( 'removeUserFromGroup' )
			->with( $user, 'sysop' );
		$filterUser = $this->getFilterUser();
		$userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtils->method( 'isNamed' )->willReturn( true );
		$degroup = new Degroup(
			$params,
			new VariableHolder(),
			$userGroupManager,
			$userIdentityUtils,
			$filterUser,
			$this->getMsgLocalizer()
		);
		$this->assertTrue( $degroup->execute() );
	}

	public function testExecute_anonymous() {
		$user = new UserIdentityValue( 0, 'Anonymous user' );
		$params = $this->provideGetMessageParameters( $user )->current()[0];

		$degroup = new Degroup(
			$params,
			new VariableHolder(),
			$this->createNoOpMock( UserGroupManager::class ),
			$this->createMock( UserIdentityUtils::class ),
			$this->createNoOpMock( FilterUser::class ),
			$this->getMsgLocalizer()
		);
		$this->assertFalse( $degroup->execute() );
	}

	public function testExecute_temp() {
		$user = new UserIdentityValue( 10, '*12345' );
		$params = $this->provideGetMessageParameters( $user )->current()[0];
		$userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtils->method( 'isNamed' )->willReturn( false );

		$degroup = new Degroup(
			$params,
			new VariableHolder(),
			$this->createNoOpMock( UserGroupManager::class ),
			$userIdentityUtils,
			$this->createNoOpMock( FilterUser::class ),
			$this->getMsgLocalizer()
		);
		$this->assertFalse( $degroup->execute() );
	}

	public static function provideRevert(): array {
		return [
			[ true, [ '*', 'user', 'sysop' ] ],
			[ true, [ '*', 'user', 'canceled', 'sysop' ] ],
			[ false, [ '*', 'user', 'sysop' ], [ 'sysop' ] ],
			[ false, [ '*', 'user', 'canceled' ] ],
		];
	}

	/**
	 * @dataProvider provideRevert
	 */
	public function testRevert( bool $success, array $hadGroups, array $hasGroups = [] ) {
		$user = new UserIdentityValue( 1, 'Degrouped user' );
		$params = $this->provideGetMessageParameters( $user )->current()[0];
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->method( 'getUserGroups' )
			->with( $user )
			->willReturn( $hasGroups );
		$userGroupManager->method( 'addUserToGroup' )
			->willReturnCallback( static function ( $_, $group ) use ( $hasGroups ) {
				return $group === 'sysop';
			} );
		$degroup = new Degroup(
			$params,
			VariableHolder::newFromArray( [ 'user_groups' => $hadGroups ] ),
			$userGroupManager,
			$this->createMock( UserIdentityUtils::class ),
			$this->createMock( FilterUser::class ),
			$this->getMsgLocalizer()
		);

		$performer = new SimpleAuthority( new UserIdentityValue( 42, 'Foo' ), [] );
		$this->assertSame(
			$success,
			$degroup->revert( $performer, 'reason' )
		);
	}

	/**
	 * @dataProvider provideGetMessageParameters
	 */
	public function testGetMessage( Parameters $params ) {
		$rangeBlock = new Degroup(
			$params,
			new VariableHolder(),
			$this->createMock( UserGroupManager::class ),
			$this->createMock( UserIdentityUtils::class ),
			$this->createMock( FilterUser::class ),
			$this->getMsgLocalizer()
		);
		$this->doTestGetMessage( $rangeBlock, $params, 'abusefilter-degrouped' );
	}
}
