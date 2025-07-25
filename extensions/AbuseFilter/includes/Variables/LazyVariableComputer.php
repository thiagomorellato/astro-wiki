<?php

namespace MediaWiki\Extension\AbuseFilter\Variables;

use InvalidArgumentException;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\TextContent;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Parser\AFPData;
use MediaWiki\Extension\AbuseFilter\TextExtractor;
use MediaWiki\ExternalLinks\ExternalLinksLookup;
use MediaWiki\ExternalLinks\LinkFilter;
use MediaWiki\Language\Language;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPage;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PreparedUpdate;
use MediaWiki\Title\Title;
use MediaWiki\User\ExternalUserNames;
use MediaWiki\User\User;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use Psr\Log\LoggerInterface;
use stdClass;
use StringUtils;
use UnexpectedValueException;
use Wikimedia\Diff\Diff;
use Wikimedia\Diff\UnifiedDiffFormatter;
use Wikimedia\IPUtils;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Service used to compute lazy-loaded variable.
 * @internal
 */
class LazyVariableComputer {
	public const SERVICE_NAME = 'AbuseFilterLazyVariableComputer';

	/**
	 * @var float The amount of time to subtract from profiling
	 * @todo This is a hack
	 */
	public static $profilingExtraTime = 0;

	/** @var TextExtractor */
	private $textExtractor;

	/** @var AbuseFilterHookRunner */
	private $hookRunner;

	/** @var LoggerInterface */
	private $logger;

	/** @var LBFactory */
	private $lbFactory;

	/** @var WANObjectCache */
	private $wanCache;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var Language */
	private $contentLanguage;

	/** @var ParserFactory */
	private $parserFactory;

	/** @var UserEditTracker */
	private $userEditTracker;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var RestrictionStore */
	private $restrictionStore;

	/** @var UserIdentityUtils */
	private $userIdentityUtils;

	/** @var string */
	private $wikiID;

	/**
	 * @param TextExtractor $textExtractor
	 * @param AbuseFilterHookRunner $hookRunner
	 * @param LoggerInterface $logger
	 * @param LBFactory $lbFactory
	 * @param WANObjectCache $wanCache
	 * @param RevisionLookup $revisionLookup
	 * @param RevisionStore $revisionStore
	 * @param Language $contentLanguage
	 * @param ParserFactory $parserFactory
	 * @param UserEditTracker $userEditTracker
	 * @param UserGroupManager $userGroupManager
	 * @param PermissionManager $permissionManager
	 * @param RestrictionStore $restrictionStore
	 * @param UserIdentityUtils $userIdentityUtils
	 * @param string $wikiID
	 */
	public function __construct(
		TextExtractor $textExtractor,
		AbuseFilterHookRunner $hookRunner,
		LoggerInterface $logger,
		LBFactory $lbFactory,
		WANObjectCache $wanCache,
		RevisionLookup $revisionLookup,
		RevisionStore $revisionStore,
		Language $contentLanguage,
		ParserFactory $parserFactory,
		UserEditTracker $userEditTracker,
		UserGroupManager $userGroupManager,
		PermissionManager $permissionManager,
		RestrictionStore $restrictionStore,
		UserIdentityUtils $userIdentityUtils,
		string $wikiID
	) {
		$this->textExtractor = $textExtractor;
		$this->hookRunner = $hookRunner;
		$this->logger = $logger;
		$this->lbFactory = $lbFactory;
		$this->wanCache = $wanCache;
		$this->revisionLookup = $revisionLookup;
		$this->revisionStore = $revisionStore;
		$this->contentLanguage = $contentLanguage;
		$this->parserFactory = $parserFactory;
		$this->userEditTracker = $userEditTracker;
		$this->userGroupManager = $userGroupManager;
		$this->permissionManager = $permissionManager;
		$this->restrictionStore = $restrictionStore;
		$this->userIdentityUtils = $userIdentityUtils;
		$this->wikiID = $wikiID;
	}

	/**
	 * XXX: $getVarCB is a hack to hide the cyclic dependency with VariablesManager. See T261069 for possible
	 * solutions. This might also be merged into VariablesManager, but it would bring a ton of dependencies.
	 * @todo Should we remove $vars parameter (check hooks)?
	 *
	 * @param LazyLoadedVariable $var
	 * @param VariableHolder $vars
	 * @param callable $getVarCB
	 * @phan-param callable(string $name):AFPData $getVarCB
	 * @return AFPData
	 */
	public function compute( LazyLoadedVariable $var, VariableHolder $vars, callable $getVarCB ) {
		$parameters = $var->getParameters();
		$varMethod = $var->getMethod();
		$result = null;

		if ( !$this->hookRunner->onAbuseFilter_interceptVariable(
			$varMethod,
			$vars,
			$parameters,
			$result
		) ) {
			return $result instanceof AFPData
				? $result : AFPData::newFromPHPVar( $result );
		}

		switch ( $varMethod ) {
			case 'diff':
				$text1Var = $parameters['oldtext-var'];
				$text2Var = $parameters['newtext-var'];
				$text1 = $getVarCB( $text1Var )->toString();
				$text2 = $getVarCB( $text2Var )->toString();
				// T74329: if there's no text, don't return an array with the empty string
				$text1 = $text1 === '' ? [] : explode( "\n", $text1 );
				$text2 = $text2 === '' ? [] : explode( "\n", $text2 );
				$diffs = new Diff( $text1, $text2 );
				$format = new UnifiedDiffFormatter();
				$result = $format->format( $diffs );
				break;
			case 'diff-split':
				$diff = $getVarCB( $parameters['diff-var'] )->toString();
				$line_prefix = $parameters['line-prefix'];
				$diff_lines = explode( "\n", $diff );
				$result = [];
				foreach ( $diff_lines as $line ) {
					if ( ( $line[0] ?? '' ) === $line_prefix ) {
						$result[] = substr( $line, 1 );
					}
				}
				break;
			case 'array-diff':
				$baseVar = $parameters['base-var'];
				$minusVar = $parameters['minus-var'];

				$baseArray = $getVarCB( $baseVar )->toNative();
				$minusArray = $getVarCB( $minusVar )->toNative();

				$result = array_diff( $baseArray, $minusArray );
				break;
			case 'links-from-wikitext':
				// This should ONLY be used when sharing a parse operation with the edit.

				/** @var WikiPage $article */
				$article = $parameters['article'];
				if ( $article->getContentModel() === CONTENT_MODEL_WIKITEXT ) {
					// Shared with the edit, don't count it in profiling
					$startTime = microtime( true );
					$textVar = $parameters['text-var'];

					$new_text = $getVarCB( $textVar )->toString();
					$content = ContentHandler::makeContent( $new_text, $article->getTitle() );
					$editInfo = $article->prepareContentForEdit(
						$content,
						null,
						$parameters['contextUserIdentity']
					);
					$result = LinkFilter::getIndexedUrlsNonReversed(
						array_keys( $editInfo->output->getExternalLinks() )
					);
					self::$profilingExtraTime += ( microtime( true ) - $startTime );
					break;
				}
			// Otherwise fall back to database
			case 'links-from-wikitext-or-database':
				// TODO: use Content object instead, if available!
				/** @var WikiPage $article */
				$article ??= $parameters['article'];

				// this inference is ugly, but the name isn't accessible from here
				// and we only want this for debugging
				$textVar = $parameters['text-var'];
				$varName = str_starts_with( $textVar, 'old_' ) ? 'old_links' : 'all_links';
				if ( $parameters['forFilter'] ?? false ) {
					$this->logger->debug( "Loading $varName from DB" );
					$links = $this->getLinksFromDB( $article );
				} elseif ( $article->getContentModel() === CONTENT_MODEL_WIKITEXT ) {
					$this->logger->debug( "Loading $varName from Parser" );

					$wikitext = $getVarCB( $textVar )->toString();
					$editInfo = $this->parseNonEditWikitext(
						$wikitext,
						$article,
						$parameters['contextUserIdentity']
					);
					$links = LinkFilter::getIndexedUrlsNonReversed(
						array_keys( $editInfo->output->getExternalLinks() )
					);
				} else {
					// TODO: Get links from Content object. But we don't have the content object.
					// And for non-text content, $wikitext is usually not going to be a valid
					// serialization, but rather some dummy text for filtering.
					$links = [];
				}

				$result = $links;
				break;
			case 'links-from-update':
				/** @var PreparedUpdate $update */
				$update = $parameters['update'];
				// Shared with the edit, don't count it in profiling
				$startTime = microtime( true );
				$result = LinkFilter::getIndexedUrlsNonReversed(
					array_keys( $update->getParserOutputForMetaData()->getExternalLinks() )
				);
				self::$profilingExtraTime += ( microtime( true ) - $startTime );
				break;
			case 'links-from-database':
				/** @var PageIdentity $article */
				$article = $parameters['article'];
				$this->logger->debug( 'Loading old_links from DB' );
				$result = $this->getLinksFromDB( $article );
				break;
			case 'parse-wikitext':
				// Should ONLY be used when sharing a parse operation with the edit.
				// TODO: use Content object instead, if available!
				/* @var WikiPage $article */
				$article = $parameters['article'];
				if ( $article->getContentModel() === CONTENT_MODEL_WIKITEXT ) {
					// Shared with the edit, don't count it in profiling
					$startTime = microtime( true );
					$textVar = $parameters['wikitext-var'];

					$new_text = $getVarCB( $textVar )->toString();
					$content = ContentHandler::makeContent( $new_text, $article->getTitle() );
					$editInfo = $article->prepareContentForEdit(
						$content,
						null,
						$parameters['contextUserIdentity']
					);
					if ( isset( $parameters['pst'] ) && $parameters['pst'] ) {
						$result = $editInfo->pstContent->serialize( $editInfo->format );
					} else {
						// Note: as of core change r727361, the PP limit comments (which we don't want to be here)
						// are already excluded.
						$popts = $editInfo->popts;
						$result = $editInfo->getOutput()->runOutputPipeline( $popts, [] )->getContentHolderText();
					}
					self::$profilingExtraTime += ( microtime( true ) - $startTime );
				} else {
					$result = '';
				}
				break;
			case 'pst-from-update':
				/** @var PreparedUpdate $update */
				$update = $parameters['update'];
				$result = $this->textExtractor->revisionToString(
					$update->getRevision(),
					$parameters['contextUser']
				);
				break;
			case 'html-from-update':
				/** @var PreparedUpdate $update */
				$update = $parameters['update'];
				// Shared with the edit, don't count it in profiling
				$startTime = microtime( true );
				$popts = $update->getRenderedRevision()->getOptions();
				$result = $update->getCanonicalParserOutput()->runOutputPipeline( $popts, [] )->getContentHolderText();
				self::$profilingExtraTime += ( microtime( true ) - $startTime );
				break;
			case 'strip-html':
				$htmlVar = $parameters['html-var'];
				$html = $getVarCB( $htmlVar )->toString();
				$stripped = StringUtils::delimiterReplace( '<', '>', '', $html );
				// We strip extra spaces to the right because the stripping above
				// could leave a lot of whitespace.
				// @fixme Find a better way to do this.
				$result = TextContent::normalizeLineEndings( $stripped );
				break;
			case 'load-recent-authors':
				$result = $this->getLastPageAuthors( $parameters['title'] );
				break;
			case 'load-first-author':
				$revision = $this->revisionLookup->getFirstRevision( $parameters['title'] );
				if ( $revision ) {
					// TODO T233241
					$user = $revision->getUser();
					$result = $user === null ? '' : $user->getName();
				} else {
					$result = '';
				}
				break;
			case 'get-page-restrictions':
				$action = $parameters['action'];
				/** @var Title $title */
				$title = $parameters['title'];
				$result = $this->restrictionStore->getRestrictions( $title, $action );
				break;
			case 'user-unnamed-ip':
				$user = $parameters['user'];
				$result = null;

				// Don't return an IP for past events (eg. revisions, logs)
				// This could leak IPs to users who don't have IP viewing rights
				if ( !$parameters['rc'] &&
					// Reveal IPs for:
					// - temporary accounts: temporary account names will replace the IP in the `user_name`
					//   variable. This variable restores this access.
					// - logged-out users: This supports the transition to the use of temporary accounts
					//   so that filter maintainers on pre-transition wikis can migrate `user_name` to `user_unnamed_ip`
					//   where necessary and see no disruption on transition.
					//
					// This variable should only ever be exposed for these use cases and shouldn't be extended
					// to registered accounts, as that would leak account PII to users without the right to see
					// that information
					( $this->userIdentityUtils->isTemp( $user ) || IPUtils::isIPAddress( $user->getName() ) ) ) {
					$result = $user->getRequest()->getIP();
				}
				break;
			case 'user-type':
				/** @var UserIdentity $userIdentity */
				$userIdentity = $parameters['user-identity'];
				if ( $this->userIdentityUtils->isNamed( $userIdentity ) ) {
					$result = 'named';
				} elseif ( $this->userIdentityUtils->isTemp( $userIdentity ) ) {
					$result = 'temp';
				} elseif ( IPUtils::isIPAddress( $userIdentity->getName() ) ) {
					$result = 'ip';
				} elseif ( ExternalUserNames::isExternal( $userIdentity->getName() ) ) {
					$result = 'external';
				} else {
					$result = 'unknown';
				}
				break;
			case 'user-editcount':
				/** @var UserIdentity $userIdentity */
				$userIdentity = $parameters['user-identity'];
				$result = $this->userEditTracker->getUserEditCount( $userIdentity );
				break;
			case 'user-emailconfirm':
				/** @var User $user */
				$user = $parameters['user'];
				$result = $user->getEmailAuthenticationTimestamp();
				break;
			case 'user-groups':
				/** @var UserIdentity $userIdentity */
				$userIdentity = $parameters['user-identity'];
				$result = $this->userGroupManager->getUserEffectiveGroups( $userIdentity );
				break;
			case 'user-rights':
				/** @var UserIdentity $userIdentity */
				$userIdentity = $parameters['user-identity'];
				$result = $this->permissionManager->getUserPermissions( $userIdentity );
				break;
			case 'user-block':
				// @todo Support partial blocks?
				/** @var User $user */
				$user = $parameters['user'];
				$result = (bool)$user->getBlock();
				break;
			case 'user-age':
				/** @var User $user */
				$user = $parameters['user'];
				$asOf = $parameters['asof'];

				if ( !$user->isRegistered() ) {
					$result = 0;
				} else {
					// HACK: If there's no registration date, assume 2008-01-15, Wikipedia Day
					// in the year before the new user log was created. See T243469.
					$registration = $user->getRegistration() ?? "20080115000000";
					$result = (int)wfTimestamp( TS_UNIX, $asOf ) - (int)wfTimestamp( TS_UNIX, $registration );
				}
				break;
			case 'page-age':
				/** @var Title $title */
				$title = $parameters['title'];

				$firstRev = $this->revisionLookup->getFirstRevision( $title );
				$firstRevisionTime = $firstRev ? $firstRev->getTimestamp() : null;
				if ( !$firstRevisionTime ) {
					$result = 0;
					break;
				}

				$asOf = $parameters['asof'];
				$result = (int)wfTimestamp( TS_UNIX, $asOf ) - (int)wfTimestamp( TS_UNIX, $firstRevisionTime );
				break;
			case 'revision-age':
				$revRec = $this->getRevisionFromParameters( $parameters );
				if ( !$revRec ) {
					$result = null;
					break;
				}
				$asOf = $parameters['asof'];
				$result = (int)wfTimestamp( TS_UNIX, $asOf ) - (int)wfTimestamp( TS_UNIX, $revRec->getTimestamp() );
				break;
			case 'length':
				$s = $getVarCB( $parameters['length-var'] )->toString();
				$result = strlen( $s );
				break;
			case 'subtract-int':
				$v1 = $getVarCB( $parameters['val1-var'] )->toInt();
				$v2 = $getVarCB( $parameters['val2-var'] )->toInt();
				$result = $v1 - $v2;
				break;
			case 'content-model':
				$revRec = $this->getRevisionFromParameters( $parameters );
				$result = $this->getContentModelFromRevision( $revRec );
				break;
			case 'revision-text':
				$revRec = $this->getRevisionFromParameters( $parameters );
				$result = $this->textExtractor->revisionToString( $revRec, $parameters['contextUser'] );
				break;
			case 'get-wiki-name':
				$result = $this->wikiID;
				break;
			case 'get-wiki-language':
				$result = $this->contentLanguage->getCode();
				break;
			default:
				if ( $this->hookRunner->onAbuseFilter_computeVariable(
					$varMethod,
					$vars,
					$parameters,
					$result
				) ) {
					throw new UnexpectedValueException( 'Unknown variable compute type ' . $varMethod );
				}
		}

		return $result instanceof AFPData ? $result : AFPData::newFromPHPVar( $result );
	}

	/**
	 * @param PageIdentity $page
	 * @return array
	 */
	private function getLinksFromDB( PageIdentity $page ): array {
		$id = $page->getId();
		if ( !$id ) {
			return [];
		}

		return ExternalLinksLookup::getExternalLinksForPage(
			$id,
			$this->lbFactory->getReplicaDatabase(),
			__METHOD__
		);
	}

	/**
	 * @todo Move to MW core (T272050)
	 * @param Title $title
	 * @return string[] Usernames of the last 10 (unique) authors from $title
	 */
	private function getLastPageAuthors( Title $title ) {
		if ( !$title->exists() ) {
			return [];
		}

		$fname = __METHOD__;

		return $this->wanCache->getWithSetCallback(
			$this->wanCache->makeKey( 'last-10-authors', 'revision', $title->getLatestRevID() ),
			WANObjectCache::TTL_MINUTE,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $title, $fname ) {
				$dbr = $this->lbFactory->getReplicaDatabase();

				$setOpts += Database::getCacheSetOptions( $dbr );
				// Get the last 100 edit authors with a trivial query (avoid T116557)
				$revQuery = $this->revisionStore->getQueryInfo();
				$revAuthors = $dbr->newSelectQueryBuilder()
					->tables( $revQuery['tables'] )
					->field( $revQuery['fields']['rev_user_text'] )
					->where( [
						'rev_page' => $title->getArticleID(),
						// TODO Should deleted names be counted in the 10 authors? If yes, this check should
						// be moved inside the foreach
						'rev_deleted' => 0
					] )
					->caller( $fname )
					// Some pages have < 10 authors but many revisions (e.g. bot pages)
					->orderBy( [ 'rev_timestamp', 'rev_id' ], SelectQueryBuilder::SORT_DESC )
					->limit( 100 )
					// Force index per T116557
					->useIndex( [ 'revision' => 'rev_page_timestamp' ] )
					->joinConds( $revQuery['joins'] )
					->fetchFieldValues();
				// Get the last 10 distinct authors within this set of edits
				$users = [];
				foreach ( $revAuthors as $author ) {
					$users[$author] = 1;
					if ( count( $users ) >= 10 ) {
						break;
					}
				}

				return array_keys( $users );
			}
		);
	}

	/**
	 * @param array{revid?:int,title?:Title,parent?:true} $params
	 * @return ?RevisionRecord
	 */
	private function getRevisionFromParameters( array $params ): ?RevisionRecord {
		if ( isset( $params['revid'] ) ) {
			$revision = $this->revisionLookup->getRevisionById( $params['revid'] );
		} elseif ( isset( $params['title'] ) ) {
			$revision = $this->revisionLookup->getRevisionByTitle( $params['title'] );
		} else {
			throw new InvalidArgumentException(
				"Either 'revid' or 'title' are mandatory revision specifiers"
			);
		}
		if ( ( $params['parent'] ?? false ) && $revision !== null ) {
			$revision = $this->revisionLookup->getPreviousRevision( $revision );
		}
		return $revision;
	}

	/**
	 * @param ?RevisionRecord $revision
	 * @return string
	 */
	private function getContentModelFromRevision( ?RevisionRecord $revision ): string {
		// this is consistent with what is done on various places in RunVariableGenerator
		// and RCVariableGenerator
		if ( $revision !== null ) {
			$content = $revision->getContent( SlotRecord::MAIN, RevisionRecord::RAW );
			return $content->getModel();
		}
		return '';
	}

	/**
	 * It's like WikiPage::prepareContentForEdit, but not for editing (old wikitext usually)
	 *
	 * @param string $wikitext
	 * @param WikiPage $article
	 * @param UserIdentity $userIdentity Context user
	 *
	 * @return stdClass
	 */
	private function parseNonEditWikitext( $wikitext, WikiPage $article, UserIdentity $userIdentity ) {
		static $cache = [];

		$cacheKey = md5( $wikitext ) . ':' . $article->getTitle()->getPrefixedText();

		if ( !isset( $cache[$cacheKey] ) ) {
			$options = ParserOptions::newFromUser( $userIdentity );
			$cache[$cacheKey] = (object)[
				'output' => $this->parserFactory->getInstance()->parse( $wikitext, $article->getTitle(), $options )
			];
		}

		return $cache[$cacheKey];
	}
}
