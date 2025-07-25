<?php

namespace MediaWiki\Extension\AbuseFilter\VariableGenerator;

use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Page\WikiPage;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Storage\PreparedUpdate;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\Utils\MWTimestamp;

/**
 * Class used to generate variables, for instance related to a given user or title.
 */
class VariableGenerator {
	/**
	 * @var VariableHolder
	 */
	protected $vars;

	/** @var AbuseFilterHookRunner */
	protected $hookRunner;
	/** @var UserFactory */
	protected $userFactory;

	/**
	 * @param AbuseFilterHookRunner $hookRunner
	 * @param UserFactory $userFactory
	 * @param VariableHolder|null $vars
	 */
	public function __construct(
		AbuseFilterHookRunner $hookRunner,
		UserFactory $userFactory,
		?VariableHolder $vars = null
	) {
		$this->hookRunner = $hookRunner;
		$this->userFactory = $userFactory;
		$this->vars = $vars ?? new VariableHolder();
	}

	/**
	 * @return VariableHolder
	 */
	public function getVariableHolder(): VariableHolder {
		return $this->vars;
	}

	/**
	 * Computes all variables unrelated to title and user. In general, these variables may be known
	 * even without an ongoing action.
	 *
	 * @param RecentChange|null $rc If the variables should be generated for an RC entry,
	 *   this is the entry. Null if it's for the current action being filtered.
	 * @return $this For chaining
	 */
	public function addGenericVars( ?RecentChange $rc = null ): self {
		$timestamp = $rc
			? MWTimestamp::convert( TS_UNIX, $rc->getAttribute( 'rc_timestamp' ) )
			: wfTimestamp( TS_UNIX );
		$this->vars->setVar( 'timestamp', $timestamp );
		// These are lazy-loaded just to reduce the amount of preset variables, but they
		// shouldn't be expensive.
		$this->vars->setLazyLoadVar( 'wiki_name', 'get-wiki-name', [] );
		$this->vars->setLazyLoadVar( 'wiki_language', 'get-wiki-language', [] );

		$this->hookRunner->onAbuseFilter_generateGenericVars( $this->vars, $rc );
		return $this;
	}

	/**
	 * @param UserIdentity $userIdentity
	 * @param RecentChange|null $rc If the variables should be generated for an RC entry,
	 *   this is the entry. Null if it's for the current action being filtered.
	 * @return $this For chaining
	 */
	public function addUserVars( UserIdentity $userIdentity, ?RecentChange $rc = null ): self {
		$asOf = $rc ? $rc->getAttribute( 'rc_timestamp' ) : wfTimestampNow();
		$user = $this->userFactory->newFromUserIdentity( $userIdentity );

		$this->vars->setLazyLoadVar(
			'user_editcount',
			'user-editcount',
			[ 'user-identity' => $userIdentity ]
		);

		$this->vars->setVar( 'user_name', $user->getName() );

		$this->vars->setLazyLoadVar(
			'user_unnamed_ip',
			'user-unnamed-ip',
			[
				'user' => $user,
				'rc' => $rc,
			]
		);

		$this->vars->setLazyLoadVar(
			'user_type',
			'user-type',
			[ 'user-identity' => $userIdentity ]
		);

		$this->vars->setLazyLoadVar(
			'user_emailconfirm',
			'user-emailconfirm',
			[ 'user' => $user ]
		);

		$this->vars->setLazyLoadVar(
			'user_age',
			'user-age',
			[ 'user' => $user, 'asof' => $asOf ]
		);

		$this->vars->setLazyLoadVar(
			'user_groups',
			'user-groups',
			[ 'user-identity' => $userIdentity ]
		);

		$this->vars->setLazyLoadVar(
			'user_rights',
			'user-rights',
			[ 'user-identity' => $userIdentity ]
		);

		$this->vars->setLazyLoadVar(
			'user_blocked',
			'user-block',
			[ 'user' => $user ]
		);

		$this->hookRunner->onAbuseFilter_generateUserVars( $this->vars, $user, $rc );

		return $this;
	}

	/**
	 * @param Title $title
	 * @param string $prefix
	 * @param RecentChange|null $rc If the variables should be generated for an RC entry,
	 *   this is the entry. Null if it's for the current action being filtered.
	 * @return $this For chaining
	 */
	public function addTitleVars(
		Title $title,
		string $prefix,
		?RecentChange $rc = null
	): self {
		if ( $rc && $rc->getAttribute( 'rc_type' ) == RC_NEW ) {
			$this->vars->setVar( $prefix . '_id', 0 );
		} else {
			$this->vars->setVar( $prefix . '_id', $title->getArticleID() );
		}
		$this->vars->setVar( $prefix . '_namespace', $title->getNamespace() );
		$this->vars->setVar( $prefix . '_title', $title->getText() );
		$this->vars->setVar( $prefix . '_prefixedtitle', $title->getPrefixedText() );

		// We only support the default values in $wgRestrictionTypes. Custom restrictions wouldn't
		// have i18n messages. If a restriction is not enabled we'll just return the empty array.
		$types = [ 'edit', 'move', 'create', 'upload' ];
		foreach ( $types as $action ) {
			$this->vars->setLazyLoadVar(
				"{$prefix}_restrictions_$action",
				'get-page-restrictions',
				[ 'title' => $title, 'action' => $action ]
			);
		}

		$asOf = $rc ? $rc->getAttribute( 'rc_timestamp' ) : wfTimestampNow();

		// TODO: add 'asof' to this as well
		$this->vars->setLazyLoadVar(
			"{$prefix}_recent_contributors",
			'load-recent-authors',
			[ 'title' => $title ]
		);

		$this->vars->setLazyLoadVar(
			"{$prefix}_age",
			'page-age',
			[ 'title' => $title, 'asof' => $asOf ]
		);

		$this->vars->setLazyLoadVar(
			"{$prefix}_first_contributor",
			'load-first-author',
			[ 'title' => $title ]
		);

		$this->hookRunner->onAbuseFilter_generateTitleVars( $this->vars, $title, $prefix, $rc );

		return $this;
	}

	public function addDerivedEditVars(): self {
		$this->vars->setLazyLoadVar( 'edit_diff', 'diff',
			[ 'oldtext-var' => 'old_wikitext', 'newtext-var' => 'new_wikitext' ] );
		$this->vars->setLazyLoadVar( 'edit_diff_pst', 'diff',
			[ 'oldtext-var' => 'old_wikitext', 'newtext-var' => 'new_pst' ] );
		$this->vars->setLazyLoadVar( 'new_size', 'length', [ 'length-var' => 'new_wikitext' ] );
		$this->vars->setLazyLoadVar( 'old_size', 'length', [ 'length-var' => 'old_wikitext' ] );
		$this->vars->setLazyLoadVar( 'edit_delta', 'subtract-int',
			[ 'val1-var' => 'new_size', 'val2-var' => 'old_size' ] );

		// Some more specific/useful details about the changes.
		$this->vars->setLazyLoadVar( 'added_lines', 'diff-split',
			[ 'diff-var' => 'edit_diff', 'line-prefix' => '+' ] );
		$this->vars->setLazyLoadVar( 'removed_lines', 'diff-split',
			[ 'diff-var' => 'edit_diff', 'line-prefix' => '-' ] );
		$this->vars->setLazyLoadVar( 'added_lines_pst', 'diff-split',
			[ 'diff-var' => 'edit_diff_pst', 'line-prefix' => '+' ] );

		// Links
		$this->vars->setLazyLoadVar( 'added_links', 'array-diff',
			[ 'base-var' => 'all_links', 'minus-var' => 'old_links' ] );
		$this->vars->setLazyLoadVar( 'removed_links', 'array-diff',
			[ 'base-var' => 'old_links', 'minus-var' => 'all_links' ] );

		// Text
		$this->vars->setLazyLoadVar( 'new_text', 'strip-html',
			[ 'html-var' => 'new_html' ] );

		return $this;
	}

	/**
	 * Add variables for an edit action when a PreparedUpdate instance is available.
	 * This is equivalent to ::addEditVars, and the preferred method.
	 *
	 * @param PreparedUpdate $update
	 * @param User $contextUser
	 * @return $this For chaining
	 */
	public function addEditVarsFromUpdate( PreparedUpdate $update, User $contextUser ): self {
		$this->addDerivedEditVars();

		$this->vars->setLazyLoadVar( 'all_links', 'links-from-update',
			[ 'update' => $update ] );
		$this->vars->setLazyLoadVar( 'old_links', 'links-from-database',
			[ 'article' => $update->getPage() ] );
		$this->vars->setLazyLoadVar( 'new_pst', 'pst-from-update',
			[ 'update' => $update, 'contextUser' => $contextUser ] );
		$this->vars->setLazyLoadVar( 'new_html', 'html-from-update',
			[ 'update' => $update ] );

		return $this;
	}

	/**
	 * Add variables for an edit action. The method assumes that old_wikitext and new_wikitext
	 * will have been set prior to filter execution.
	 *
	 * @note This is a legacy method. Code using it likely relies on legacy hooks.
	 *
	 * @param WikiPage $page
	 * @param UserIdentity $userIdentity The current user
	 * @param bool $linksFromDatabase Whether links variables should be loaded
	 *   from the database. If set to false, they will be parsed from the text variables.
	 * @return $this For chaining
	 */
	public function addEditVars(
		WikiPage $page,
		UserIdentity $userIdentity,
		bool $linksFromDatabase = true
	): self {
		$this->addDerivedEditVars();

		$this->vars->setLazyLoadVar( 'all_links', 'links-from-wikitext',
			[
				'text-var' => 'new_wikitext',
				'article' => $page,
				// XXX: this has never made sense
				'forFilter' => $linksFromDatabase,
				'contextUserIdentity' => $userIdentity
			] );

		if ( $linksFromDatabase ) {
			$this->vars->setLazyLoadVar( 'old_links', 'links-from-database',
				[ 'article' => $page ] );
		} else {
			// Note: this claims "or database" but it will never reach it
			$this->vars->setLazyLoadVar( 'old_links', 'links-from-wikitext-or-database',
				[
					'article' => $page,
					'text-var' => 'old_wikitext',
					'contextUserIdentity' => $userIdentity
				] );
		}

		$this->vars->setLazyLoadVar( 'new_pst', 'parse-wikitext',
			[
				'wikitext-var' => 'new_wikitext',
				'article' => $page,
				'pst' => true,
				'contextUserIdentity' => $userIdentity
			] );

		$this->vars->setLazyLoadVar( 'new_html', 'parse-wikitext',
			[
				'wikitext-var' => 'new_wikitext',
				'article' => $page,
				'contextUserIdentity' => $userIdentity
			] );

		return $this;
	}
}
