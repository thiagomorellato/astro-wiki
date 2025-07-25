<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use Generator;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Parser\AFPData;
use MediaWiki\Extension\AbuseFilter\VariableGenerator\VariableGenerator;
use MediaWiki\Extension\AbuseFilter\Variables\LazyLoadedVariable;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Page\WikiPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;

/**
 * @group Test
 * @group AbuseFilter
 * @group AbuseFilterGeneric
 *
 * @covers \MediaWiki\Extension\AbuseFilter\VariableGenerator\VariableGenerator
 */
class VariableGeneratorTest extends MediaWikiUnitTestCase {
	public function testGetVariableHolder() {
		$holder = new VariableHolder();
		$generator = new VariableGenerator(
			$this->createMock( AbuseFilterHookRunner::class ),
			$this->createMock( UserFactory::class ),
			$holder
		);
		$this->assertSame( $holder, $generator->getVariableHolder() );
	}

	public function testAddUserVars() {
		$user = $this->createMock( User::class );
		$userName = 'Some user';
		$user->method( 'getName' )->willReturn( $userName );
		$mockUserFactory = $this->createMock( UserFactory::class );
		$mockUserFactory->method( 'newFromUserIdentity' )->willReturnArgument( 0 );

		$expectedKeys = [
			'user_editcount',
			'user_name',
			'user_emailconfirm',
			'user_type',
			'user_groups',
			'user_rights',
			'user_blocked',
			'user_age',
			'user_unnamed_ip',
		];

		$variableHolder = new VariableHolder();
		$generator = new VariableGenerator(
			$this->createMock( AbuseFilterHookRunner::class ),
			$mockUserFactory,
			$variableHolder
		);
		$actualVars = $generator->addUserVars( $user )->getVariableHolder()->getVars();
		$this->assertArrayEquals( $expectedKeys, array_keys( $actualVars ) );
		$this->assertSame( $userName, $actualVars['user_name']->toNative(), 'user_name' );
		unset( $actualVars['user_name'] );
		$this->assertContainsOnlyInstancesOf( LazyLoadedVariable::class, $actualVars, 'lazy-loaded vars' );
	}

	/**
	 * @param string $prefix
	 * @param Title $title
	 * @param array $expected
	 * @dataProvider provideTitleVarsNotLazy
	 */
	public function testAddTitleVars_notLazy( string $prefix, Title $title, array $expected ) {
		$generator = new VariableGenerator(
			$this->createMock( AbuseFilterHookRunner::class ),
			$this->createMock( UserFactory::class )
		);
		$actualVars = $generator->addTitleVars( $title, $prefix )->getVariableHolder()->getVars();
		$computedVars = [];
		foreach ( $actualVars as $name => $value ) {
			if ( $value instanceof AFPData ) {
				$computedVars[$name] = $value->toNative();
			}
		}
		$this->assertArrayEquals( $expected, $computedVars );
	}

	public function provideTitleVarsNotLazy(): Generator {
		$prefixes = [ 'page', 'moved_from', 'moved_to' ];
		foreach ( $prefixes as $prefix ) {
			$title = $this->createMock( Title::class );
			$id = 12345;
			$title->method( 'getArticleID' )->willReturn( $id );
			$namespace = NS_HELP;
			$title->method( 'getNamespace' )->willReturn( $namespace );
			$titleText = 'Foobar';
			$title->method( 'getText' )->willReturn( $titleText );
			$prefixedTitle = 'Help:Foobar';
			$title->method( 'getPrefixedText' )->willReturn( $prefixedTitle );
			$expected = [
				"{$prefix}_id" => $id,
				"{$prefix}_namespace" => $namespace,
				"{$prefix}_title" => $titleText,
				"{$prefix}_prefixedtitle" => $prefixedTitle,
			];
			yield $prefix => [ $prefix, $title, $expected ];
		}
	}

	/**
	 * @param string $prefix
	 * @param array $expectedKeys
	 * @dataProvider provideTitleVarsLazy
	 */
	public function testAddTitleVars_lazy( string $prefix, array $expectedKeys ) {
		$title = $this->createMock( Title::class );
		$generator = new VariableGenerator(
			$this->createMock( AbuseFilterHookRunner::class ),
			$this->createMock( UserFactory::class )
		);
		$actualVars = $generator->addTitleVars( $title, $prefix )->getVariableHolder()->getVars();
		$lazyVars = [];
		foreach ( $actualVars as $name => $value ) {
			if ( $value instanceof LazyLoadedVariable ) {
				$lazyVars[] = $name;
			}
		}
		$this->assertArrayEquals( $expectedKeys, $lazyVars );
	}

	public static function provideTitleVarsLazy(): Generator {
		$prefixes = [ 'page', 'moved_from', 'moved_to' ];
		foreach ( $prefixes as $prefix ) {
			$expectedKeys = [
				"{$prefix}_restrictions_create",
				"{$prefix}_restrictions_edit",
				"{$prefix}_restrictions_move",
				"{$prefix}_restrictions_upload",
				"{$prefix}_age",
				"{$prefix}_first_contributor",
				"{$prefix}_recent_contributors",
			];
			yield $prefix => [ $prefix, $expectedKeys ];
		}
	}

	public function testAddGenericVars() {
		$expectedKeys = [
			'timestamp',
			'wiki_name',
			'wiki_language',
		];

		$generator = new VariableGenerator(
			$this->createMock( AbuseFilterHookRunner::class ),
			$this->createMock( UserFactory::class )
		);
		$actualVars = $generator->addGenericVars()->getVariableHolder()->getVars();
		$this->assertArrayEquals( $expectedKeys, array_keys( $actualVars ) );
	}

	public static function provideLinksFromDatabase() {
		yield [ true ];
		yield [ false ];
	}

	/**
	 * @dataProvider provideLinksFromDatabase
	 */
	public function testAddEditVars( bool $linksFromDatabase ) {
		$expectedKeys = [
			'edit_diff',
			'edit_diff_pst',
			'new_size',
			'old_size',
			'edit_delta',
			'added_lines',
			'removed_lines',
			'added_lines_pst',
			'all_links',
			'old_links',
			'added_links',
			'removed_links',
			'new_text',
			'new_pst',
			'new_html',
		];
		$generator = new VariableGenerator(
			$this->createMock( AbuseFilterHookRunner::class ),
			$this->createMock( UserFactory::class )
		);
		$actualVars = $generator->addEditVars(
			$this->createMock( WikiPage::class ),
			$this->createMock( UserIdentity::class ),
			$linksFromDatabase
		)->getVariableHolder()->getVars();
		$this->assertArrayEquals( $expectedKeys, array_keys( $actualVars ) );
		$this->assertContainsOnlyInstancesOf( LazyLoadedVariable::class, $actualVars, 'lazy-loaded vars' );
	}
}
