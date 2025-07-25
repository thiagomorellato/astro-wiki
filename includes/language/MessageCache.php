<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\Content;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Language\ILanguageConverter;
use MediaWiki\Language\Language;
use MediaWiki\Language\MessageCacheUpdate;
use MediaWiki\Language\MessageInfo;
use MediaWiki\Language\MessageParser;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\StubObject\StubObject;
use MediaWiki\StubObject\StubUserLang;
use MediaWiki\Title\Title;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\RequestTimeout\TimeoutException;
use Wikimedia\ScopedCallback;

/**
 * Cache messages that are defined by MediaWiki-namespace pages or by hooks.
 *
 * @ingroup Language
 */
class MessageCache implements LoggerAwareInterface {
	/**
	 * Options to be included in the ServiceOptions
	 */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::UseDatabaseMessages,
		MainConfigNames::MaxMsgCacheEntrySize,
		MainConfigNames::AdaptiveMessageCache,
		MainConfigNames::UseXssLanguage,
		MainConfigNames::RawHtmlMessages,
	];

	/**
	 * Bump this whenever the cache format changes.
	 */
	private const CACHE_VERSION = 2;

	/**
	 * The size of the MapCacheLRU which stores message data. The maximum
	 * number of languages which can be efficiently loaded in a given request.
	 */
	public const MAX_REQUEST_LANGUAGES = 10;

	/** Force message reload */
	private const FOR_UPDATE = 1;

	/** How long to wait for locks */
	private const LOCK_WAIT_TIME = 15;
	/** How long locks last */
	private const LOCK_TTL = 30;

	/**
	 * Lifetime for cache, for keys stored in $wanCache, in seconds.
	 */
	private const WAN_TTL = BagOStuff::TTL_DAY;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * Process cache of loaded messages that are defined in MediaWiki namespace
	 *
	 * @var MapCacheLRU Map of (language code => key => " <MESSAGE>" or "!TOO BIG" or "!ERROR")
	 */
	private $cache;

	/**
	 * Map of (lowercase message key => unused) for all software-defined messages
	 *
	 * @var array
	 */
	private $systemMessageNames;

	/**
	 * Map of (language code => boolean). Whether a message was updated in the
	 * last minute in a manner which risks a stampede.
	 * @var bool[]
	 */
	private $isCacheVolatile = [];

	/**
	 * If this is true, disable fetching from the MediaWiki namespace, including
	 * via the cache. Fall back to loading from the LocalisationCache only.
	 * @var bool
	 */
	private $disabled;

	/** @var int Maximum entry size in bytes */
	private $maxEntrySize;
	/** @var bool */
	private $adaptive;
	/** @var bool */
	private $useXssLanguage;
	/** @var string[] */
	private $rawHtmlMessages;

	/** @var WANObjectCache */
	private $wanCache;
	/** @var BagOStuff */
	private $mainCache;
	/** @var BagOStuff */
	private $srvCache;
	/** @var Language */
	private $contLang;
	/** @var string */
	private $contLangCode;
	/** @var ILanguageConverter */
	private $contLangConverter;
	/** @var LocalisationCache */
	private $localisationCache;
	/** @var LanguageNameUtils */
	private $languageNameUtils;
	/** @var LanguageFallback */
	private $languageFallback;
	/** @var HookRunner */
	private $hookRunner;
	/** @var MessageParser */
	private $messageParser;

	/** @var (string|callable)[]|null */
	private $messageKeyOverrides;

	/**
	 * @internal For use by ServiceWiring
	 * @param WANObjectCache $wanCache
	 * @param BagOStuff $mainCache
	 * @param BagOStuff $serverCache
	 * @param Language $contLang Content language of site
	 * @param LanguageConverterFactory $langConverterFactory
	 * @param LoggerInterface $logger
	 * @param ServiceOptions $options
	 * @param LocalisationCache $localisationCache
	 * @param LanguageNameUtils $languageNameUtils
	 * @param LanguageFallback $languageFallback
	 * @param HookContainer $hookContainer
	 * @param MessageParser $messageParser
	 */
	public function __construct(
		WANObjectCache $wanCache,
		BagOStuff $mainCache,
		BagOStuff $serverCache,
		Language $contLang,
		LanguageConverterFactory $langConverterFactory,
		LoggerInterface $logger,
		ServiceOptions $options,
		LocalisationCache $localisationCache,
		LanguageNameUtils $languageNameUtils,
		LanguageFallback $languageFallback,
		HookContainer $hookContainer,
		MessageParser $messageParser
	) {
		$this->wanCache = $wanCache;
		$this->mainCache = $mainCache;
		$this->srvCache = $serverCache;
		$this->contLang = $contLang;
		$this->contLangConverter = $langConverterFactory->getLanguageConverter( $contLang );
		$this->contLangCode = $contLang->getCode();
		$this->logger = $logger;
		$this->localisationCache = $localisationCache;
		$this->languageNameUtils = $languageNameUtils;
		$this->languageFallback = $languageFallback;
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->messageParser = $messageParser;

		$this->cache = new MapCacheLRU( self::MAX_REQUEST_LANGUAGES );

		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		if ( !$options->get( MainConfigNames::UseDatabaseMessages ) ) {
			$this->disable( 'config' );
		}
		$this->maxEntrySize = $options->get( MainConfigNames::MaxMsgCacheEntrySize );
		$this->adaptive = $options->get( MainConfigNames::AdaptiveMessageCache );
		$this->useXssLanguage = $options->get( MainConfigNames::UseXssLanguage );
		$this->rawHtmlMessages = $options->get( MainConfigNames::RawHtmlMessages );
	}

	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * Try to load the cache from APC.
	 *
	 * @param string $code
	 * @return array|false The cache array, or false if not in cache.
	 */
	private function getLocalCache( $code ) {
		$cacheKey = $this->srvCache->makeKey( __CLASS__, $code );

		return $this->srvCache->get( $cacheKey );
	}

	/**
	 * Save the cache to APC.
	 *
	 * @param string $code
	 * @param array $cache The cache array
	 */
	private function saveToLocalCache( $code, $cache ) {
		$cacheKey = $this->srvCache->makeKey( __CLASS__, $code );
		$this->srvCache->set( $cacheKey, $cache );
	}

	/**
	 * Loads messages from caches or from database in this order:
	 * (1) local message cache (if $wgUseLocalMessageCache is enabled)
	 * (2) the main cache
	 * (3) the database.
	 *
	 * When successfully loading from (2) or (3), all higher level caches are
	 * updated for the newest version.
	 *
	 * Nothing is loaded if member variable mDisable is true, either manually
	 * set by calling code or if message loading fails (is this possible?).
	 *
	 * Returns true if cache is already populated, or it was successfully populated,
	 * or false if populating empty cache fails. Also returns true if MessageCache
	 * is disabled.
	 *
	 * @param string $code Which language to load messages for
	 * @param int|null $mode Use MessageCache::FOR_UPDATE to skip process cache [optional]
	 * @return bool
	 */
	private function load( string $code, $mode = null ) {
		// Check if loading is done already
		if ( $this->disabled ||
			( $mode !== self::FOR_UPDATE && $this->isLanguageLoaded( $code ) )
		) {
			return true;
		}

		try {
			return $this->loadUnguarded( $code, $mode );
		} catch ( Throwable $e ) {
			// Don't try to load again during the exception handler
			$this->disable();
			throw $e;
		}
	}

	/**
	 * Load messages from the cache or database, without exception guarding.
	 *
	 * @param string $code Which language to load messages for
	 * @param int|null $mode Use MessageCache::FOR_UPDATE to skip process cache [optional]
	 * @return bool
	 */
	private function loadUnguarded( $code, $mode ) {
		$success = false; // Keep track of success
		$staleCache = false; // a cache array with expired data, or false if none has been loaded
		$where = []; // Debug info, delayed to avoid spamming debug log too much

		// A hash of the expected content is stored in a WAN cache key, providing a way
		// to invalidate the local cache on every server whenever a message page changes.
		[ $hash, $isCacheVolatile ] = $this->getValidationHash( $code );
		$this->isCacheVolatile[$code] = $isCacheVolatile;
		$isStaleDueToVolatility = false;

		// Try the local cache and check against the main cache hash key...
		$cache = $this->getLocalCache( $code );
		if ( !$cache ) {
			$where[] = 'local cache is empty';
		} elseif ( !isset( $cache['HASH'] ) || $cache['HASH'] !== $hash ) {
			$where[] = 'local cache has the wrong hash';
			$staleCache = $cache;
		} elseif ( $this->isCacheExpired( $cache ) ) {
			$where[] = 'local cache is expired';
			$staleCache = $cache;
		} elseif ( $isCacheVolatile ) {
			// Some recent message page changes might not show due to DB lag
			$where[] = 'local cache validation key is expired/volatile';
			$staleCache = $cache;
			$isStaleDueToVolatility = true;
		} else {
			$where[] = 'got from local cache';
			$this->cache->set( $code, $cache );
			$success = true;
		}

		if ( !$success ) {
			// Try the main cache, using a lock for regeneration...
			$cacheKey = $this->mainCache->makeKey( 'messages', $code );
			for ( $failedAttempts = 0; $failedAttempts <= 1; $failedAttempts++ ) {
				if ( $isStaleDueToVolatility ) {
					// While the main cache *might* be more up-to-date, we do not want
					// the I/O strain of every application server fetching the key here during
					// the volatility period. Either this thread wins the lock and regenerates
					// the cache or the stale local cache value gets reused.
					$where[] = 'global cache is presumed expired';
				} else {
					$cache = $this->mainCache->get( $cacheKey );
					if ( !$cache ) {
						$where[] = 'global cache is empty';
					} elseif ( $this->isCacheExpired( $cache ) ) {
						$where[] = 'global cache is expired';
						$staleCache = $cache;
					} elseif ( $isCacheVolatile ) {
						// Some recent message page changes might not show due to DB lag
						$where[] = 'global cache is expired/volatile';
						$staleCache = $cache;
					} else {
						$where[] = 'got from global cache';
						$this->cache->set( $code, $cache );
						$this->saveToCaches( $cache, 'local-only', $code );
						$success = true;
						break;
					}
				}

				// We need to call loadFromDB(). Limit the concurrency to one thread.
				// This prevents the site from going down when the cache expires.
				// Note that the DB slam protection lock here is non-blocking.
				$loadStatus = $this->loadFromDBWithMainLock( $code, $where, $mode );
				if ( $loadStatus === true ) {
					$success = true;
					break;
				} elseif ( $staleCache ) {
					// Use the stale cache while some other thread constructs the new one
					$where[] = 'using stale cache';
					$this->cache->set( $code, $staleCache );
					$success = true;
					break;
				} elseif ( $failedAttempts > 0 ) {
					$where[] = 'failed to find cache after waiting';
					// Already blocked once, so avoid another lock/unlock cycle.
					// This case will typically be hit if memcached is down, or if
					// loadFromDB() takes longer than LOCK_WAIT.
					break;
				} elseif ( $loadStatus === 'cant-acquire' ) {
					// Wait for the other thread to finish, then retry. Normally,
					// the memcached get() will then yield the other thread's result.
					$where[] = 'waiting for other thread to complete';
					[ , $ioError ] = $this->getReentrantScopedLock( $code );
					if ( $ioError ) {
						$where[] = 'failed waiting';
						// Call loadFromDB() with concurrency limited to one thread per server.
						// It should be rare for all servers to lack even a stale local cache.
						$success = $this->loadFromDBWithLocalLock( $code, $where, $mode );
						break;
					}
				} else {
					// Disable cache; $loadStatus is 'disabled'
					break;
				}
			}
		}

		if ( !$success ) {
			$where[] = 'loading FAILED - cache is disabled';
			$this->disable();
			$this->cache->set( $code, [] );
			$this->logger->error( __METHOD__ . ": Failed to load $code" );
			// This used to throw an exception, but that led to nasty side effects like
			// the whole wiki being instantly down if the memcached server died
		}

		if ( !$this->isLanguageLoaded( $code ) ) {
			throw new LogicException( "Process cache for '$code' should be set by now." );
		}

		$info = implode( ', ', $where );
		$this->logger->debug( __METHOD__ . ": Loading $code... $info" );

		return $success;
	}

	/**
	 * @param string $code
	 * @param string[] &$where List of debug comments
	 * @param int|null $mode Use MessageCache::FOR_UPDATE to use DB_PRIMARY
	 * @return true|string One of: true, "cant-acquire" or "disabled".
	 */
	private function loadFromDBWithMainLock( $code, array &$where, $mode = null ) {
		// If cache updates on all levels fail, give up on message overrides.
		// This is to avoid easy site outages; see $saveSuccess comments below.
		$statusKey = $this->mainCache->makeKey( 'messages', $code, 'status' );
		$status = $this->mainCache->get( $statusKey );
		if ( $status === 'error' ) {
			$where[] = "could not load; method is still globally disabled";
			return 'disabled';
		}

		// Now let's regenerate
		$where[] = 'loading from DB';

		// Lock the cache to prevent conflicting writes.
		// This lock is non-blocking so stale cache can quickly be used.
		// Note that load() will call a blocking getReentrantScopedLock()
		// after this if it really needs to wait for any current thread.
		[ $scopedLock ] = $this->getReentrantScopedLock( $code, 0 );
		if ( !$scopedLock ) {
			$where[] = 'could not acquire main lock';
			return 'cant-acquire';
		}

		$cache = $this->loadFromDB( $code, $mode );
		$this->cache->set( $code, $cache );
		$saveSuccess = $this->saveToCaches( $cache, 'all', $code );

		if ( !$saveSuccess ) {
			/**
			 * Cache save has failed. Most likely this is because the cache is
			 * more than the maximum size (typically 1MB compressed).
			 *
			 * If there is a local cache, nothing bad will happen. If there is no local
			 * cache, disabling the message cache for all requests avoids incurring a
			 * loadFromDB() overhead on every request, and thus saves the wiki from
			 * complete downtime under moderate traffic conditions.
			 */
			if ( $this->srvCache instanceof EmptyBagOStuff ) {
				$this->mainCache->set( $statusKey, 'error', 60 * 5 );
				$where[] = 'could not save cache, disabled globally for 5 minutes';
			} else {
				$where[] = "could not save global cache";
			}
		}

		return true;
	}

	/**
	 * @param string $code
	 * @param string[] &$where List of debug comments
	 * @param int|null $mode Use MessageCache::FOR_UPDATE to use DB_PRIMARY
	 * @return bool Success
	 */
	private function loadFromDBWithLocalLock( $code, array &$where, $mode = null ) {
		$success = false;
		$where[] = 'loading from DB using local lock';

		$scopedLock = $this->srvCache->getScopedLock(
			$this->srvCache->makeKey( 'messages', $code ),
			self::LOCK_WAIT_TIME,
			self::LOCK_TTL,
			__METHOD__
		);
		if ( $scopedLock ) {
			$cache = $this->loadFromDB( $code, $mode );
			$this->cache->set( $code, $cache );
			$this->saveToCaches( $cache, 'local-only', $code );
			$success = true;
		}

		return $success;
	}

	/**
	 * Loads cacheable messages from the database. Messages bigger than
	 * $wgMaxMsgCacheEntrySize are assigned a special value, and are loaded
	 * on-demand from the database later.
	 *
	 * @param string $code Language code
	 * @param int|null $mode Use MessageCache::FOR_UPDATE to skip process cache
	 * @return array Loaded messages for storing in caches
	 */
	private function loadFromDB( $code, $mode = null ) {
		$icp = MediaWikiServices::getInstance()->getConnectionProvider();

		$dbr = ( $mode === self::FOR_UPDATE ) ? $icp->getPrimaryDatabase() : $icp->getReplicaDatabase();

		$cache = [];

		$mostUsed = []; // list of "<cased message key>/<code>"
		if ( $this->adaptive && $code !== $this->contLangCode ) {
			if ( !$this->cache->has( $this->contLangCode ) ) {
				$this->load( $this->contLangCode );
			}
			$mostUsed = array_keys( $this->cache->get( $this->contLangCode ) );
			foreach ( $mostUsed as $key => $value ) {
				$mostUsed[$key] = "$value/$code";
			}
		}

		// Common conditions
		$conds = [
			// Treat redirects as not existing (T376398)
			'page_is_redirect' => 0,
			'page_namespace' => NS_MEDIAWIKI,
		];
		if ( count( $mostUsed ) ) {
			$conds['page_title'] = $mostUsed;
		} elseif ( $code !== $this->contLangCode ) {
			$conds[] = $dbr->expr(
				'page_title',
				IExpression::LIKE,
				new LikeValue( $dbr->anyString(), '/', $code )
			);
		} else {
			// Effectively disallows use of '/' character in NS_MEDIAWIKI for uses
			// other than language code.
			$conds[] = $dbr->expr(
				'page_title',
				IExpression::NOT_LIKE,
				new LikeValue( $dbr->anyString(), '/', $dbr->anyString() )
			);
		}

		// Set the stubs for oversized software-defined messages in the main cache map
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'page_title', 'page_latest' ] )
			->from( 'page' )
			->where( $conds )
			->andWhere( $dbr->expr( 'page_len', '>', intval( $this->maxEntrySize ) ) )
			->caller( __METHOD__ . "($code)-big" )->fetchResultSet();
		foreach ( $res as $row ) {
			// Include entries/stubs for all keys in $mostUsed in adaptive mode
			if ( $this->adaptive || $this->isMainCacheable( $row->page_title ) ) {
				$cache[$row->page_title] = '!TOO BIG';
			}
			// At least include revision ID so page changes are reflected in the hash
			$cache['EXCESSIVE'][$row->page_title] = $row->page_latest;
		}

		// RevisionStore cannot be injected as it would break the installer since
		// it instantiates MessageCache before the DB.
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		// Set the text for small software-defined messages in the main cache map
		$revQuery = $revisionStore->getQueryInfo( [ 'page' ] );

		// T231196: MySQL/MariaDB (10.1.37) can sometimes irrationally decide that querying `actor` then
		// `revision` then `page` is somehow better than starting with `page`. Tell it not to reorder the
		// query (and also reorder it ourselves because as generated by RevisionStore it'll have
		// `revision` first rather than `page`).
		$revQuery['joins']['revision'] = $revQuery['joins']['page'];
		unset( $revQuery['joins']['page'] );
		// It isn't actually necessary to reorder $revQuery['tables'] as Database does the right thing
		// when join conditions are given for all joins, but Gergő is wary of relying on that so pull
		// `page` to the start.
		$revQuery['tables'] = array_merge(
			[ 'page' ],
			array_diff( $revQuery['tables'], [ 'page' ] )
		);

		$res = $dbr->newSelectQueryBuilder()
			->queryInfo( $revQuery )
			->where( $conds )
			->andWhere( [
				$dbr->expr( 'page_len', '<=', intval( $this->maxEntrySize ) ),
				'page_latest = rev_id' // get the latest revision only
			] )
			->caller( __METHOD__ . "($code)-small" )
			->straightJoinOption()
			->fetchResultSet();

		// Don't load content from uncacheable rows (T313004)
		[ $cacheableRows, $uncacheableRows ] = $this->separateCacheableRows( $res );
		$result = $revisionStore->newRevisionsFromBatch( $cacheableRows, [
			'slots' => [ SlotRecord::MAIN ],
			'content' => true
		] );
		$revisions = $result->isOK() ? $result->getValue() : [];

		foreach ( $cacheableRows as $row ) {
			try {
				$rev = $revisions[$row->rev_id] ?? null;
				$content = $rev ? $rev->getContent( SlotRecord::MAIN ) : null;
				$text = $this->getMessageTextFromContent( $content );
			} catch ( TimeoutException $e ) {
				throw $e;
			} catch ( Exception $ex ) {
				$text = false;
			}

			if ( !is_string( $text ) ) {
				$entry = '!ERROR';
				$this->logger->error(
					__METHOD__
					. ": failed to load message page text for {$row->page_title} ($code)"
				);
			} else {
				$entry = ' ' . $text;
			}
			$cache[$row->page_title] = $entry;
		}

		foreach ( $uncacheableRows as $row ) {
			// T193271: The cache object gets too big and slow to generate.
			// At least include revision ID, so that page changes are reflected in the hash.
			$cache['EXCESSIVE'][$row->page_title] = $row->page_latest;
		}

		$cache['VERSION'] = self::CACHE_VERSION;
		ksort( $cache );

		// Hash for validating local cache (APC). No need to take into account
		// messages larger than $wgMaxMsgCacheEntrySize, since those are only
		// stored and fetched from memcache.
		$cache['HASH'] = md5( serialize( $cache ) );
		$cache['EXPIRY'] = wfTimestamp( TS_MW, time() + self::WAN_TTL );
		unset( $cache['EXCESSIVE'] ); // only needed for hash

		return $cache;
	}

	/**
	 * Whether the language was loaded and its data is still in the process cache.
	 *
	 * @param string $lang
	 * @return bool
	 */
	private function isLanguageLoaded( $lang ) {
		// It is important that this only returns true if the cache was fully
		// populated by load(), so that callers can assume all cache keys exist.
		// It is possible for $this->cache to be only partially populated through
		// methods like MessageCache::replace(), which must not make this method
		// return true (T208897). And this method must cease to return true
		// if the language was evicted by MapCacheLRU (T230690).
		return $this->cache->hasField( $lang, 'VERSION' );
	}

	/**
	 * Can the given DB key be added to the main cache blob? To reduce the
	 * abuse impact of the MediaWiki namespace by {{int:}} and CentralNotice,
	 * this is only true if the page overrides a predefined message.
	 *
	 * @param string $name Message name (possibly with /code suffix)
	 * @param string|null $code The language code. If this is null, message
	 *   presence will be bulk loaded for the content language. Otherwise,
	 *   presence will be detected by loading the specified message.
	 * @return bool
	 */
	private function isMainCacheable( $name, $code = null ) {
		// Convert the first letter to lowercase, and strip /code suffix
		$name = $this->contLang->lcfirst( $name );
		// Include common conversion table pages. This also avoids problems with
		// Installer::parse() bailing out due to disallowed DB queries (T207979).
		if ( strpos( $name, 'conversiontable/' ) === 0 ) {
			return true;
		}
		$msg = preg_replace( '/\/[a-z0-9-]{2,}$/', '', $name );

		if ( $code === null ) {
			// Bulk load
			if ( $this->systemMessageNames === null ) {
				$this->systemMessageNames = array_fill_keys(
					$this->localisationCache->getSubitemList( $this->contLangCode, 'messages' ),
					true );
			}
			return isset( $this->systemMessageNames[$msg] );
		} else {
			// Use individual subitem
			return $this->localisationCache->getSubitem( $code, 'messages', $msg ) !== null;
		}
	}

	/**
	 * Separate cacheable from uncacheable rows in a page/revision query result.
	 *
	 * @param IResultWrapper $res
	 * @return array{0:IResultWrapper|stdClass[],1:stdClass[]} An array with the cacheable
	 *    rows in the first element and the uncacheable rows in the second.
	 */
	private function separateCacheableRows( $res ) {
		if ( $this->adaptive ) {
			// Include entries/stubs for all keys in $mostUsed in adaptive mode
			return [ $res, [] ];
		}
		$cacheableRows = [];
		$uncacheableRows = [];
		foreach ( $res as $row ) {
			if ( $this->isMainCacheable( $row->page_title ) ) {
				$cacheableRows[] = $row;
			} else {
				$uncacheableRows[] = $row;
			}
		}
		return [ $cacheableRows, $uncacheableRows ];
	}

	/**
	 * Update the cache as necessary when a message page is changed
	 *
	 * @param string $title Message cache key with the initial uppercase letter
	 * @param string|false $text New contents of the page (false if deleted)
	 */
	public function replace( $title, $text ) {
		if ( $this->disabled ) {
			return;
		}

		[ $msg, $code ] = $this->figureMessage( $title );
		if ( strpos( $title, '/' ) !== false && $code === $this->contLangCode ) {
			// Content language overrides do not use the /<code> suffix
			return;
		}

		// (a) Update the process cache with the new message text
		if ( $text === false ) {
			// Page deleted
			$this->cache->setField( $code, $title, '!NONEXISTENT' );
		} else {
			// Ignore $wgMaxMsgCacheEntrySize so the process cache is up-to-date
			$this->cache->setField( $code, $title, ' ' . $text );
		}

		// (b) Update the shared caches in a deferred update with a fresh DB snapshot
		DeferredUpdates::addUpdate(
			new MessageCacheUpdate( $code, $title, $msg ),
			DeferredUpdates::PRESEND
		);
	}

	/**
	 * @internal Entry point for MessageCacheUpdate
	 * @param string $code
	 * @param array[] $replacements List of (title, message key) pairs
	 */
	public function refreshAndReplaceInternal( string $code, array $replacements ) {
		// Allow one caller at a time to avoid race conditions
		[ $scopedLock ] = $this->getReentrantScopedLock( $code );
		if ( !$scopedLock ) {
			foreach ( $replacements as [ $title ] ) {
				$this->logger->error(
					__METHOD__ . ': could not acquire lock to update {title} ({code})',
					[ 'title' => $title, 'code' => $code ] );
			}

			return;
		}

		// Load the existing cache to update it in the local DC cache.
		// The other DCs will see a hash mismatch.
		if ( $this->load( $code, self::FOR_UPDATE ) ) {
			$cache = $this->cache->get( $code );
		} else {
			// Err? Fall back to loading from the database.
			$cache = $this->loadFromDB( $code, self::FOR_UPDATE );
		}
		// Check if individual cache keys should exist and update cache accordingly
		$newTextByTitle = []; // map of (title => content)
		$newBigTitles = []; // map of (title => latest revision ID), like EXCESSIVE in loadFromDB()
		// Can not inject the WikiPageFactory as it would break the installer since
		// it instantiates MessageCache before the DB.
		$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		foreach ( $replacements as [ $title ] ) {
			$page = $wikiPageFactory->newFromTitle( Title::makeTitle( NS_MEDIAWIKI, $title ) );
			$page->loadPageData( IDBAccessObject::READ_LATEST );
			$text = $this->getMessageTextFromContent( $page->getContent() );
			// Remember the text for the blob store update later on
			$newTextByTitle[$title] = $text ?? '';
			// Note that if $text is false, then $cache should have a !NONEXISTENT entry
			if ( !is_string( $text ) ) {
				$cache[$title] = '!NONEXISTENT';
			} elseif ( strlen( $text ) > $this->maxEntrySize ) {
				$cache[$title] = '!TOO BIG';
				$newBigTitles[$title] = $page->getLatest();
			} else {
				$cache[$title] = ' ' . $text;
			}
		}
		// Update HASH for the new key. Incorporates various administrative keys,
		// including the old HASH (and thereby the EXCESSIVE value from loadFromDB()
		// and previous replace() calls), but that doesn't really matter since we
		// only ever compare it for equality with a copy saved by saveToCaches().
		$cache['HASH'] = md5( serialize( $cache + [ 'EXCESSIVE' => $newBigTitles ] ) );
		// Update the too-big WAN cache entries now that we have the new HASH
		foreach ( $newBigTitles as $title => $id ) {
			// Match logic of loadCachedMessagePageEntry()
			$this->wanCache->set(
				$this->bigMessageCacheKey( $cache['HASH'], $title ),
				' ' . $newTextByTitle[$title],
				self::WAN_TTL
			);
		}
		// Mark this cache as definitely being "latest" (non-volatile) so
		// load() calls do not try to refresh the cache with replica DB data
		$cache['LATEST'] = time();
		// Update the process cache
		$this->cache->set( $code, $cache );
		// Pre-emptively update the local datacenter cache so things like edit filter and
		// prevented changes are reflected immediately; these often use MediaWiki: pages.
		// The datacenter handling replace() calls should be the same one handling edits
		// as they require HTTP POST.
		$this->saveToCaches( $cache, 'all', $code );
		// Release the lock now that the cache is saved
		ScopedCallback::consume( $scopedLock );

		// Relay the purge. Touching this check key expires cache contents
		// and local cache (APC) validation hash across all datacenters.
		$this->wanCache->touchCheckKey( $this->getCheckKey( $code ) );

		// Purge the messages in the message blob store and fire any hook handlers
		$blobStore = MediaWikiServices::getInstance()->getResourceLoader()->getMessageBlobStore();
		foreach ( $replacements as [ $title, $msg ] ) {
			$blobStore->updateMessage( $this->contLang->lcfirst( $msg ) );
			$this->hookRunner->onMessageCacheReplace( $title, $newTextByTitle[$title] );
		}
	}

	/**
	 * Is the given cache array expired due-to-time passing or a version change?
	 *
	 * @param array $cache
	 * @return bool
	 */
	private function isCacheExpired( $cache ) {
		return !isset( $cache['VERSION'] ) ||
			!isset( $cache['EXPIRY'] ) ||
			$cache['VERSION'] !== self::CACHE_VERSION ||
			$cache['EXPIRY'] <= wfTimestampNow();
	}

	/**
	 * Store data in the local and main caches, and update the validation hash in
	 * the WAN cache.
	 *
	 * @param array $cache Cached messages with a version.
	 * @param string $dest Either "local-only" to save to local caches only
	 *   or "all" to save to all caches.
	 * @param string|false $code Language code (default: false)
	 * @return bool
	 */
	private function saveToCaches( array $cache, $dest, $code = false ) {
		if ( $dest === 'all' ) {
			$cacheKey = $this->mainCache->makeKey( 'messages', $code );
			$success = $this->mainCache->set( $cacheKey, $cache );
			$this->setValidationHash( $code, $cache );
		} else {
			$success = true;
		}

		$this->saveToLocalCache( $code, $cache );

		return $success;
	}

	/**
	 * Get the MD5 hash used to validate the local server cache
	 *
	 * @param string $code
	 * @return array (hash or false, bool expiry/volatility status)
	 */
	private function getValidationHash( $code ) {
		$curTTL = null;
		$value = $this->wanCache->get(
			$this->wanCache->makeKey( 'messages', $code, 'hash', 'v1' ),
			$curTTL,
			[ $this->getCheckKey( $code ) ]
		);

		if ( $value ) {
			$hash = $value['hash'];
			if ( ( time() - $value['latest'] ) < WANObjectCache::TTL_MINUTE ) {
				// Cache was recently updated via replace() and should be up-to-date.
				// That method is only called in the primary datacenter and uses FOR_UPDATE.
				$isCacheVolatile = false;
			} else {
				// See if the "check" key was bumped after the hash was generated
				$isCacheVolatile = ( $curTTL < 0 );
			}
		} else {
			// No hash found at all; cache must regenerate to be safe
			$hash = false;
			$isCacheVolatile = true;
		}

		return [ $hash, $isCacheVolatile ];
	}

	/**
	 * Set the MD5 hash used to validate the local server cache
	 *
	 * If $cache has a 'LATEST' UNIX timestamp key, then the hash will not
	 * be treated as "volatile" by getValidationHash() for the next few seconds.
	 * This is triggered when $cache is generated using FOR_UPDATE mode.
	 *
	 * @param string $code
	 * @param array $cache Cached messages with a version
	 */
	private function setValidationHash( $code, array $cache ) {
		$this->wanCache->set(
			$this->wanCache->makeKey( 'messages', $code, 'hash', 'v1' ),
			[
				'hash' => $cache['HASH'],
				'latest' => $cache['LATEST'] ?? 0
			],
			WANObjectCache::TTL_INDEFINITE
		);
	}

	/**
	 * @param string $code The language code being loaded
	 * @param int $timeout Wait timeout in seconds
	 * @return array (ScopedCallback or null, whether locking failed due to an I/O error)
	 * @phan-return array{0:ScopedCallback|null,1:bool}
	 */
	private function getReentrantScopedLock( $code, $timeout = self::LOCK_WAIT_TIME ) {
		$key = $this->mainCache->makeKey( 'messages', $code );

		$watchPoint = $this->mainCache->watchErrors();
		$scopedLock = $this->mainCache->getScopedLock(
			$key,
			$timeout,
			self::LOCK_TTL,
			__METHOD__
		);
		$error = ( !$scopedLock && $this->mainCache->getLastError( $watchPoint ) );

		return [ $scopedLock, $error ];
	}

	/**
	 * Normalize message key input
	 *
	 * @param string $key Input message key to be normalized
	 * @return string Normalized message key
	 */
	public function normalizeKey( string $key ): string {
		if ( $key === '' ) {
			return '';
		}
		$lckey = strtr( $key, ' ', '_' );
		if ( ord( $lckey ) < 128 ) {
			$lckey[0] = strtolower( $lckey[0] );
		} else {
			$lckey = $this->contLang->lcfirst( $lckey );
		}

		return $lckey;
	}

	/**
	 * Get a message from either the content language or the user language.
	 *
	 * First, assemble a list of languages to attempt getting the message from. This
	 * chain begins with the requested language and its fallbacks and then continues with
	 * the content language and its fallbacks. For each language in the chain, the following
	 * process will occur (in this order):
	 *  1. If a language-specific override, i.e., [[MW:msg/lang]], is available, use that.
	 *     Note: for the content language, there is no /lang subpage.
	 *  2. Fetch from LocalisationCache (the i18n JSON file store).
	 *  3. If available, check the database for fallback language overrides.
	 *
	 * This process provides a number of guarantees. When changing this code, make sure all
	 * of these guarantees are preserved.
	 *  * If the requested language is *not* the content language, then the CDB cache for that
	 *    specific language will take precedence over the root database page ([[MW:msg]]).
	 *  * Fallbacks will be just that: fallbacks. A fallback language will never be reached if
	 *    the message is available *anywhere* in the language for which it is a fallback.
	 *
	 * @param string $key The message key
	 * @param bool $useDB If true, look for the message in the DB, false
	 *   to use only the LocalisationCache.
	 * @param bool|string|Language|null $language Code of the language to get the message for.
	 *   This should be a string or null in new code. If null is given, the content language
	 *   will be used.
	 * @param MessageInfo|null $info If a default-constructed MessageInfo is passed, it will be
	 *   populated with information about the retrieved message.
	 *
	 * @return string|false False if the message doesn't exist, otherwise the
	 *   message (which can be empty)
	 */
	public function get( $key, $useDB = true, $language = null, $info = null ) {
		if ( is_int( $key ) ) {
			// Fix numerical strings that somehow become ints on their way here
			$key = (string)$key;
		} elseif ( !is_string( $key ) ) {
			throw new TypeError( 'Message key must be a string' );
		} elseif ( $key === '' ) {
			// Shortcut: the empty key is always missing
			return false;
		}

		// Ignore legacy $usedKey parameter
		if ( $info && !( $info instanceof MessageInfo ) ) {
			$info = null;
		}

		$langCode = $this->getLanguageCode( $language ?? $this->contLangCode );

		// Normalise title-case input (with some inlining)
		$lckey = $this->normalizeKey( $key );

		// Initialize the overrides here to prevent calling the hook too early.
		if ( $this->messageKeyOverrides === null ) {
			$this->messageKeyOverrides = [];
			$this->hookRunner->onMessageCacheFetchOverrides( $this->messageKeyOverrides );
		}

		if ( isset( $this->messageKeyOverrides[$lckey] ) ) {
			$override = $this->messageKeyOverrides[$lckey];

			// Strings are deliberately interpreted as message keys,
			// to prevent ambiguity between message keys and functions.
			if ( is_string( $override ) ) {
				$lckey = $override;
			} else {
				$lckey = $override( $lckey, $this );
			}
		}

		$this->hookRunner->onMessageCache__get( $lckey );

		if ( $info ) {
			$info->usedKey = $lckey;
		}

		// Loop through each language in the fallback list until we find something useful
		$message = $this->getMessageFromFallbackChain(
			$langCode,
			$lckey,
			!$this->disabled && $useDB,
			$info
		);

		// If we still have no message, maybe the key was in fact a full key, so try that
		if ( $message === false ) {
			$parts = explode( '/', $lckey );
			// We may get calls for things that are HTTP URLs from the sidebar
			// Let's not load nonexistent languages for those
			// They usually have more than one slash.
			if ( count( $parts ) === 2 && $parts[1] !== '' ) {
				$message = $this->localisationCache->getSubitem( $parts[1], 'messages', $parts[0] ) ?? false;
				if ( $message !== false && $info ) {
					$info->usedKey = $parts[0];
					$info->langCode = $parts[1];
				}
			}
		}

		// Post-processing if the message exists
		if ( $message !== false ) {
			// Fix whitespace
			$message = str_replace(
				[
					// Fix for trailing whitespace, removed by textarea
					'&#32;',
					// Fix for NBSP, converted to space by firefox
					'&nbsp;',
					'&#160;',
					'&shy;'
				],
				[
					' ',
					"\u{00A0}",
					"\u{00A0}",
					"\u{00AD}"
				],
				$message
			);
		}

		return $message;
	}

	/**
	 * Return a Language code from legacy input
	 *
	 * @param Language|string|bool $lang Either:
	 *   - a Language object
	 *   - code of the language to get the message for, with a fall back to the
	 *     content language if it is invalid.
	 *   - a boolean: if it's false then use the global object for the current
	 *     user's language (as a fallback for the old parameter functionality),
	 *     or if it is true then use global object for the wiki's content language.
	 * @return string
	 */
	private function getLanguageCode( $lang ): string {
		if ( is_object( $lang ) ) {
			StubObject::unstub( $lang );
			if ( $lang instanceof Language ) {
				return $lang->getCode();
			} else {
				throw new InvalidArgumentException( 'Invalid language object of class ' .
					get_class( $lang ) );
			}
		} elseif ( is_string( $lang ) ) {
			if ( $this->languageNameUtils->isValidCode( $lang ) ) {
				return $lang;
			}
			// $lang is a string, but not a valid language code; use content language.
			$this->logger->debug( 'Invalid language code passed to' . __METHOD__ .
				', falling back to content language.' );
			return $this->contLangCode;
		} elseif ( is_bool( $lang ) ) {
			wfDeprecatedMsg( 'Calling MessageCache::get with a boolean language parameter ' .
				'was deprecated in MediaWiki 1.43', '1.43' );
			if ( $lang ) {
				return $this->contLangCode;
			} else {
				global $wgLang;
				return $wgLang->getCode();
			}
		} else {
			throw new InvalidArgumentException( 'Invalid language' );
		}
	}

	/**
	 * Given a language, try to fetch messages for that language, fallbacks of
	 * that language, the site language, or fallbacks of the site language.
	 *
	 * @see MessageCache::get
	 * @param string $code Preferred language
	 * @param string $lckey Lowercase key for the message (as for localisation cache)
	 * @param bool $useDB Whether to include messages from the wiki database
	 * @param MessageInfo|null $info
	 * @return string|false The message, or false if not found
	 */
	private function getMessageFromFallbackChain( $code, $lckey, $useDB, $info ) {
		$alreadyTried = [];

		// First try the requested language.
		$message = $this->getMessageForLang( $code, $lckey, $useDB, $alreadyTried, $info );
		if ( $message !== false ) {
			return $message;
		}

		// Now try checking the site language.
		$message = $this->getMessageForLang( $this->contLangCode, $lckey, $useDB, $alreadyTried, $info );
		return $message;
	}

	/**
	 * Given a language, try to fetch messages for that language and its fallbacks.
	 *
	 * @see MessageCache::get
	 * @param string $langCode Preferred language
	 * @param string $lckey Lowercase key for the message (as for localisation cache)
	 * @param bool $useDB Whether to include messages from the wiki database
	 * @param bool[] &$alreadyTried Contains true for each language that has been tried already
	 * @param MessageInfo|null $info
	 * @return string|false The message, or false if not found
	 */
	private function getMessageForLang( $langCode, $lckey, $useDB, &$alreadyTried, $info ) {
		// Try checking the database for the requested language
		if ( $useDB ) {
			$uckey = $this->contLang->ucfirst( $lckey );

			if ( !isset( $alreadyTried[$langCode] ) ) {
				$message = $this->getMsgFromNamespace(
					$this->getMessagePageName( $langCode, $uckey ),
					$langCode
				);
				if ( $message !== false ) {
					if ( $info ) {
						$info->langCode = $langCode;
					}
					return $message;
				}
				$alreadyTried[$langCode] = true;
			}
		} else {
			$uckey = null;
		}

		// Return a special value handled in Message::format() to display the message key
		// (and fallback keys) and the parameters passed to the message.
		// TODO: Move to a better place.
		if ( $langCode === 'qqx' ) {
			return '($*)';
		} elseif (
			$langCode === 'x-xss' &&
			$this->useXssLanguage &&
			!in_array( $lckey, $this->rawHtmlMessages, true )
		) {
			$xssViaInnerHtml = "<script>alert('$lckey')</script>";
			$xssViaAttribute = '">' . $xssViaInnerHtml . '<x y="';
			return $xssViaInnerHtml . $xssViaAttribute . '($*)';
		}

		// Check the localisation cache
		[ $defaultMessage, $messageSource ] =
			$this->localisationCache->getSubitemWithSource( $langCode, 'messages', $lckey );
		if ( $messageSource === $langCode ) {
			if ( $info ) {
				$info->langCode = $langCode;
			}
			return $defaultMessage;
		}

		// Try checking the database for all of the fallback languages
		if ( $useDB ) {
			$fallbackChain = $this->languageFallback->getAll( $langCode );

			foreach ( $fallbackChain as $code ) {
				if ( isset( $alreadyTried[$code] ) ) {
					continue;
				}

				$message = $this->getMsgFromNamespace(
					// @phan-suppress-next-line PhanTypeMismatchArgumentNullable uckey is set when used
					$this->getMessagePageName( $code, $uckey ), $code );

				if ( $message !== false ) {
					if ( $info ) {
						$info->langCode = $code;
					}
					return $message;
				}
				$alreadyTried[$code] = true;

				// Reached the source language of the default message. Don't look for DB overrides
				// further back in the fallback chain. (T229992)
				if ( $code === $messageSource ) {
					if ( $info ) {
						$info->langCode = $code;
					}
					return $defaultMessage;
				}
			}
		}

		if ( $defaultMessage !== null && $info ) {
			$info->langCode = $messageSource;
		}
		return $defaultMessage ?? false;
	}

	/**
	 * Get the message page name for a given language
	 *
	 * @param string $langCode
	 * @param string $uckey Uppercase key for the message
	 * @return string The page name
	 */
	private function getMessagePageName( $langCode, $uckey ) {
		if ( $langCode === $this->contLangCode ) {
			// Messages created in the content language will not have the /lang extension
			return $uckey;
		} else {
			return "$uckey/$langCode";
		}
	}

	/**
	 * Get a message from the MediaWiki namespace, with caching. The key must
	 * first be converted to two-part lang/msg form if necessary.
	 *
	 * Unlike self::get(), this function doesn't resolve fallback chains, and
	 * some callers require this behavior. LanguageConverter::parseCachedTable()
	 * and self::get() are some examples in core.
	 *
	 * @param string $title Message cache key with the initial uppercase letter
	 * @param string $code Code denoting the language to try
	 * @return string|false The message, or false if it does not exist or on error
	 */
	public function getMsgFromNamespace( $title, $code ) {
		// Load all MediaWiki page definitions into cache. Note that individual keys
		// already loaded into the cache during this request remain in the cache, which
		// includes the value of hook-defined messages.
		$this->load( $code );

		$entry = $this->cache->getField( $code, $title );

		if ( $entry !== null ) {
			// Message page exists as an override of a software messages
			if ( substr( $entry, 0, 1 ) === ' ' ) {
				// The message exists and is not '!TOO BIG' or '!ERROR'
				return substr( $entry, 1 );
			} elseif ( $entry === '!NONEXISTENT' ) {
				// The text might be '-' or missing due to some data loss
				return false;
			}
			// Load the message page, utilizing the individual message cache.
			// If the page does not exist, there will be no hook handler fallbacks.
			$entry = $this->loadCachedMessagePageEntry(
				$title,
				$code,
				$this->cache->getField( $code, 'HASH' )
			);
		} else {
			// Message page either does not exist or does not override a software message
			if ( !$this->isMainCacheable( $title, $code ) ) {
				// Message page does not override any software-defined message. A custom
				// message might be defined to have content or settings specific to the wiki.
				// Load the message page, utilizing the individual message cache as needed.
				$entry = $this->loadCachedMessagePageEntry(
					$title,
					$code,
					$this->cache->getField( $code, 'HASH' )
				);
			}
			if ( $entry === null || substr( $entry, 0, 1 ) !== ' ' ) {
				// Message does not have a MediaWiki page definition; try hook handlers
				$message = false;
				// @phan-suppress-next-line PhanTypeMismatchArgument Type mismatch on pass-by-ref args
				$this->hookRunner->onMessagesPreLoad( $title, $message, $code );
				if ( $message !== false ) {
					$this->cache->setField( $code, $title, ' ' . $message );
				} else {
					$this->cache->setField( $code, $title, '!NONEXISTENT' );
				}

				return $message;
			}
		}

		if ( $entry !== false && substr( $entry, 0, 1 ) === ' ' ) {
			if ( $this->isCacheVolatile[$code] ) {
				// Make sure that individual keys respect the WAN cache holdoff period too
				$this->logger->debug(
					__METHOD__ . ': loading volatile key \'{titleKey}\'',
					[ 'titleKey' => $title, 'code' => $code ] );
			} else {
				$this->cache->setField( $code, $title, $entry );
			}
			// The message exists, so make sure a string is returned
			return substr( $entry, 1 );
		}

		$this->cache->setField( $code, $title, '!NONEXISTENT' );

		return false;
	}

	/**
	 * @param string $dbKey
	 * @param string $code
	 * @param string $hash
	 * @return string Either " <MESSAGE>" or "!NONEXISTENT"
	 */
	private function loadCachedMessagePageEntry( $dbKey, $code, $hash ) {
		$fname = __METHOD__;
		return $this->srvCache->getWithSetCallback(
			$this->srvCache->makeKey( 'messages-big', $hash, $dbKey ),
			BagOStuff::TTL_HOUR,
			function () use ( $code, $dbKey, $hash, $fname ) {
				return $this->wanCache->getWithSetCallback(
					$this->bigMessageCacheKey( $hash, $dbKey ),
					self::WAN_TTL,
					function ( $oldValue, &$ttl, &$setOpts ) use ( $dbKey, $code, $fname ) {
						// Try loading the message from the database
						$setOpts += Database::getCacheSetOptions(
							MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase()
						);
						// Use newKnownCurrent() to avoid querying revision/user tables
						$title = Title::makeTitle( NS_MEDIAWIKI, $dbKey );
						// Injecting RevisionStore breaks installer since it
						// instantiates MessageCache before DB.
						$revision = MediaWikiServices::getInstance()
							->getRevisionLookup()
							->getKnownCurrentRevision( $title );
						if ( !$revision ) {
							// The wiki doesn't have a local override page. Cache absence with normal TTL.
							// When overrides are created, self::replace() takes care of the cache.
							return '!NONEXISTENT';
						}
						$content = $revision->getContent( SlotRecord::MAIN );
						if ( $content ) {
							$message = $this->getMessageTextFromContent( $content );
						} else {
							$this->logger->warning(
								$fname . ': failed to load page text for \'{titleKey}\'',
								[ 'titleKey' => $dbKey, 'code' => $code ]
							);
							$message = null;
						}

						if ( !is_string( $message ) ) {
							// Revision failed to load Content, or Content is incompatible with wikitext.
							// Possibly a temporary loading failure.
							$ttl = 5;

							return '!NONEXISTENT';
						}

						return ' ' . $message;
					}
				);
			}
		);
	}

	/**
	 * @deprecated since 1.44 use MessageParser::transform()
	 *
	 * @param string $message
	 * @param bool $interface
	 * @param Language|null $language
	 * @param PageReference|null $page
	 * @return string
	 */
	public function transform( $message, $interface = false, $language = null, ?PageReference $page = null ) {
		return $this->messageParser->transform(
			$message, $interface, $language, $page );
	}

	/**
	 * @deprecated since 1.44 use MessageParser::parse()
	 * @internal
	 *
	 * @param string $text
	 * @param PageReference $contextPage
	 * @param bool $linestart Whether this should be parsed in start-of-line
	 *  context (defaults to true)
	 * @param bool $interface Whether this is an interface message
	 *  (defaults to false)
	 * @param Language|StubUserLang|string|null $language Language code
	 * @return ParserOutput
	 */
	public function parseWithPostprocessing(
		string $text, PageReference $contextPage,
		bool $linestart = true,
		bool $interface = false,
		$language = null
	): ParserOutput {
		return $this->messageParser->parse(
			$text, $contextPage, $linestart, $interface, $language );
	}

	/**
	 * @deprecated since 1.44 use MessageParser::parseWithoutPostprocessing()
	 *
	 * @param string $text
	 * @param PageReference|null $page
	 * @param bool $linestart Whether this is at the start of a line
	 * @param bool $interface Whether this is an interface message
	 * @param Language|StubUserLang|string|null $language Language code
	 * @return ParserOutput
	 */
	public function parse( $text, ?PageReference $page = null,
		$linestart = true, $interface = false, $language = null
	) {
		// phpcs:ignore MediaWiki.Usage.DeprecatedGlobalVariables.Deprecated$wgTitle
		global $wgTitle;
		if ( !$page ) {
			$logger = LoggerFactory::getInstance( 'GlobalTitleFail' );
			$logger->info(
				__METHOD__ . ' called with no title set.',
				[ 'exception' => new RuntimeException ]
			);
			$page = $wgTitle;
		}
		// Sometimes $wgTitle isn't set either...
		if ( !$page ) {
			// It's not uncommon having a null $wgTitle in scripts. See r80898
			// Create a ghost title in such case
			$page = PageReferenceValue::localReference(
				NS_SPECIAL,
				'Badtitle/title not set in ' . __METHOD__
			);
		}

		return $this->messageParser->parseWithoutPostprocessing(
			$text, $page, $linestart, $interface, $language );
	}

	/**
	 * Disable loading of messages from the MediaWiki namespace. Use the
	 * LocalisationCache only.
	 *
	 * @param string $logReason If given, log a message including this reason
	 */
	public function disable( $logReason = '' ) {
		if ( $logReason !== '' ) {
			$this->logger->debug( "disabling MessageCache: $logReason" );
		}
		$this->disabled = true;
	}

	/**
	 * Re-enable the MessageCache if it was disabled by a call to disable()
	 */
	public function enable() {
		$this->logger->debug( "re-enabling MessageCache" );
		$this->disabled = false;
	}

	/**
	 * Whether DB/cache usage is disabled for determining messages
	 *
	 * If so, this typically indicates either:
	 *   - a) load() failed to find a cached copy nor query the DB
	 *   - b) we are in a special context or error mode that cannot use the DB
	 *
	 * If the DB is ignored, any derived HTML output or cached objects may be wrong.
	 * To avoid long-term cache pollution, TTLs can be adjusted accordingly.
	 *
	 * @return bool
	 * @since 1.27
	 */
	public function isDisabled() {
		return $this->disabled;
	}

	/**
	 * Clear all stored messages in global and local cache
	 *
	 * Mainly used after a mass rebuild
	 */
	public function clear() {
		$langs = $this->languageNameUtils->getLanguageNames();
		foreach ( $langs as $code => $_ ) {
			$this->wanCache->touchCheckKey( $this->getCheckKey( $code ) );
		}
		$this->cache->clear();
	}

	/**
	 * Given a title string possibly containing a slash, determine the message
	 * key and language code. No initial letter case normalisation is done.
	 *
	 * @param string $key
	 * @return array
	 */
	public function figureMessage( $key ) {
		$pieces = explode( '/', $key );
		if ( count( $pieces ) < 2 ) {
			return [ $key, $this->contLangCode ];
		}

		$lang = array_pop( $pieces );
		if ( !$this->languageNameUtils->getLanguageName(
			$lang,
			LanguageNameUtils::AUTONYMS,
			LanguageNameUtils::DEFINED
		) ) {
			return [ $key, $this->contLangCode ];
		}

		$message = implode( '/', $pieces );

		return [ $message, $lang ];
	}

	/**
	 * Get all message keys stored in the message cache for a given language.
	 * If $code is the content language code, this will return all message keys
	 * for which MediaWiki:msgkey exists. If $code is another language code, this
	 * will ONLY return message keys for which MediaWiki:msgkey/$code exists.
	 *
	 * @param string $code Language code
	 * @return string[]|null Array of message keys
	 */
	public function getAllMessageKeys( $code ) {
		$this->load( $code );
		if ( !$this->cache->has( $code ) ) {
			// Apparently load() failed
			return null;
		}
		// Remove administrative keys
		$cache = $this->cache->get( $code );
		unset( $cache['VERSION'] );
		unset( $cache['EXPIRY'] );
		unset( $cache['EXCESSIVE'] );
		// Remove any !NONEXISTENT keys
		$cache = array_diff( $cache, [ '!NONEXISTENT' ] );

		// Keys may appear with a capital first letter. lcfirst them.
		return array_map( [ $this->contLang, 'lcfirst' ], array_keys( $cache ) );
	}

	/**
	 * Purge message caches when a MediaWiki: page is created, updated, or deleted
	 *
	 * @param PageIdentity $page Message page
	 * @param Content|null $content New content for edit/create, null on deletion
	 *
	 * @since 1.29
	 */
	public function updateMessageOverride( $page, ?Content $content = null ) {
		// treat null as not existing
		$msgText = $this->getMessageTextFromContent( $content ) ?? false;

		$this->replace( $page->getDBkey(), $msgText );

		if ( $this->contLangConverter->hasVariants() ) {
			$this->contLangConverter->updateConversionTable( $page );
		}
	}

	/**
	 * @param string $code Language code
	 * @return string WAN cache key usable as a "check key" against language page edits
	 */
	public function getCheckKey( $code ) {
		return $this->wanCache->makeKey( 'messages', $code );
	}

	/**
	 * @param Content|null $content Content or null if the message page does not exist
	 * @return string|false|null Returns false if $content is null and null on error
	 */
	private function getMessageTextFromContent( ?Content $content = null ) {
		// @TODO: could skip pseudo-messages like js/css here, based on content model
		if ( $content && $content->isRedirect() ) {
			// Treat redirects as not existing (T376398)
			$msgText = false;
		} elseif ( $content ) {
			// Message page exists...
			// XXX: Is this the right way to turn a Content object into a message?
			// NOTE: $content is typically either WikitextContent, JavaScriptContent or
			//       CssContent.
			$msgText = $content->getWikitextForTransclusion();
			if ( $msgText === false || $msgText === null ) {
				// This might be due to some kind of misconfiguration...
				$msgText = null;
				$this->logger->warning(
					__METHOD__ . ": message content doesn't provide wikitext "
					. "(content model: " . $content->getModel() . ")" );
			}
		} else {
			// Message page does not exist...
			$msgText = false;
		}

		return $msgText;
	}

	/**
	 * @param string $hash Hash for this version of the entire key/value overrides map
	 * @param string $title Message cache key with the initial uppercase letter
	 * @return string
	 */
	private function bigMessageCacheKey( $hash, $title ) {
		return $this->wanCache->makeKey( 'messages-big', $hash, $title );
	}
}
