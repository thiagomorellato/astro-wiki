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

namespace MediaWiki\Config;

use DnsSrvDiscoverer;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Wikimedia\Http\MultiHttpClient;
use Wikimedia\IPUtils;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectFactory\ObjectFactory;
use Wikimedia\WaitConditionLoop;

/**
 * Interface for configuration instances
 *
 * @since 1.29
 */
class EtcdConfig implements Config, LoggerAwareInterface {
	/** @var MultiHttpClient */
	private $http;
	/** @var BagOStuff */
	private $srvCache;
	/** @var array */
	private $procCache;
	/** @var DnsSrvDiscoverer */
	private $dsd;

	/** @var string */
	private $service;
	/** @var string */
	private $host;
	/** @var ?int */
	private $port;
	/** @var string */
	private $protocol;
	/** @var string */
	private $directory;
	/** @var int */
	private $baseCacheTTL;
	/** @var int */
	private $skewCacheTTL;
	/** @var int */
	private $timeout;

	/**
	 * @param array $params Parameter map:
	 *   - host: the host address
	 *   - directory: the etc "directory" were MediaWiki specific variables are located
	 *   - service: service name used in SRV discovery. Defaults to 'etcd'. [optional]
	 *   - port: custom host port [optional]
	 *   - protocol: one of ("http", "https"). Defaults to http. [optional]
	 *   - cache: BagOStuff instance or ObjectFactory spec thereof for a server cache.
	 *            The cache will also be used as a fallback if etcd is down. [optional]
	 *   - cacheTTL: logical cache TTL in seconds [optional]
	 *   - skewTTL: maximum seconds to randomly lower the assigned TTL on cache save [optional]
	 *   - timeout: seconds to wait for etcd before throwing an error [optional]
	 */
	public function __construct( array $params ) {
		$params += [
			'service' => 'etcd',
			'port' => null,
			'protocol' => 'http',
			'cacheTTL' => 10,
			'skewTTL' => 1,
			'timeout' => 2
		];

		$this->service = $params['service'];
		$this->host = $params['host'];
		$this->port = $params['port'];
		$this->protocol = $params['protocol'];
		$this->directory = trim( $params['directory'], '/' );
		$this->skewCacheTTL = $params['skewTTL'];
		$this->baseCacheTTL = max( $params['cacheTTL'] - $this->skewCacheTTL, 0 );
		$this->timeout = $params['timeout'];

		// For backwards compatibility, check the host for an embedded port
		$hostAndPort = IPUtils::splitHostAndPort( $this->host );

		if ( $hostAndPort ) {
			$this->host = $hostAndPort[0];

			if ( $hostAndPort[1] ) {
				$this->port = $hostAndPort[1];
			}
		}

		// Also for backwards compatibility, check for a host in the format of
		// an SRV record and use the service specified therein
		if ( preg_match( '/^_([^\.]+)\._tcp\.(.+)$/', $this->host, $m ) ) {
			$this->service = $m[1];
			$this->host = $m[2];
		}

		if ( !isset( $params['cache'] ) ) {
			$this->srvCache = new HashBagOStuff();
		} elseif ( $params['cache'] instanceof BagOStuff ) {
			$this->srvCache = $params['cache'];
		} else {
			$this->srvCache = ObjectFactory::getObjectFromSpec( $params['cache'] );
		}

		$this->http = new MultiHttpClient( [
			'connTimeout' => $this->timeout,
			'reqTimeout' => $this->timeout,
		] );
		$this->dsd = new DnsSrvDiscoverer( $this->service, 'tcp', $this->host );
	}

	/**
	 * @deprecated since 1.41 No longer used and did not work in practice
	 */
	public function setLogger( LoggerInterface $logger ): void {
		trigger_error( __METHOD__ . ' is deprecated since 1.41', E_USER_DEPRECATED );
	}

	public function has( $name ) {
		$this->load();

		return array_key_exists( $name, $this->procCache['config'] );
	}

	public function get( $name ) {
		$this->load();

		if ( !array_key_exists( $name, $this->procCache['config'] ) ) {
			throw new ConfigException( "No entry found for '$name'." );
		}

		return $this->procCache['config'][$name];
	}

	public function getModifiedIndex() {
		$this->load();
		return $this->procCache['modifiedIndex'];
	}

	/**
	 * @throws ConfigException
	 */
	private function load() {
		if ( $this->procCache !== null ) {
			return; // already loaded
		}

		$now = microtime( true );
		$key = $this->srvCache->makeGlobalKey(
			__CLASS__,
			$this->host,
			$this->directory
		);

		// Get the cached value or block until it is regenerated (by this or another thread)...
		$data = null; // latest config info
		$error = null; // last error message
		$loop = new WaitConditionLoop(
			function () use ( $key, $now, &$data, &$error ) {
				// Check if the values are in cache yet...
				$data = $this->srvCache->get( $key );
				if ( is_array( $data ) && $data['expires'] > $now ) {
					return WaitConditionLoop::CONDITION_REACHED;
				}

				// Cache is either empty or stale;
				// refresh the cache from etcd, using a mutex to reduce stampedes...
				if ( $this->srvCache->lock( $key, 0, $this->baseCacheTTL ) ) {
					try {
						$etcdResponse = $this->fetchAllFromEtcd();
						$error = $etcdResponse['error'];
						if ( is_array( $etcdResponse['config'] ) ) {
							// Avoid having all servers expire cache keys at the same time
							$expiry = microtime( true ) + $this->baseCacheTTL;
							// @phan-suppress-next-line PhanTypeMismatchArgumentInternal
							$expiry += mt_rand( 0, 1e6 ) / 1e6 * $this->skewCacheTTL;
							$data = [
								'config' => $etcdResponse['config'],
								'expires' => $expiry,
								'modifiedIndex' => $etcdResponse['modifiedIndex']
							];
							$this->srvCache->set( $key, $data, BagOStuff::TTL_INDEFINITE );

							return WaitConditionLoop::CONDITION_REACHED;
						} else {
							trigger_error( "EtcdConfig failed to fetch data: $error", E_USER_WARNING );
							if ( !$etcdResponse['retry'] && !is_array( $data ) ) {
								// Fail fast since the error is likely to keep happening
								return WaitConditionLoop::CONDITION_FAILED;
							}
						}
					} finally {
						$this->srvCache->unlock( $key ); // release mutex
					}
				} else {
					$error = 'lost lock';
				}

				if ( is_array( $data ) ) {
					trigger_error( "EtcdConfig using stale data: $error", E_USER_NOTICE );

					return WaitConditionLoop::CONDITION_REACHED;
				}

				return WaitConditionLoop::CONDITION_CONTINUE;
			},
			$this->timeout
		);

		if ( $loop->invoke() !== WaitConditionLoop::CONDITION_REACHED ) {
			// No cached value exists and etcd query failed; throw an error
			// @phan-suppress-next-line PhanTypeSuspiciousStringExpression WaitConditionLoop throws or error set
			throw new ConfigException( "Failed to load configuration from etcd: $error" );
		}

		// @phan-suppress-next-line PhanTypeMismatchProperty WaitConditionLoop throws ore data set
		$this->procCache = $data;
	}

	/**
	 * @return array (containing the keys config, error, retry, modifiedIndex)
	 */
	public function fetchAllFromEtcd() {
		$servers = $this->dsd->getServers() ?: [ [ $this->host, $this->port ] ];

		foreach ( $servers as [ $host, $port ] ) {
			// Try to load the config from this particular server
			$response = $this->fetchAllFromEtcdServer( $host, $port );
			if ( is_array( $response['config'] ) || $response['retry'] ) {
				break;
			}
		}

		return $response;
	}

	/**
	 * @param string $address Host
	 * @param ?int $port Port
	 * @return array (containing the keys config, error, retry, modifiedIndex)
	 */
	protected function fetchAllFromEtcdServer( string $address, ?int $port = null ) {
		$host = $address;

		if ( $port !== null ) {
			$host = IPUtils::combineHostAndPort( $address, $port );
		}

		// Retrieve all the values under the MediaWiki config directory
		[ $rcode, $rdesc, /* $rhdrs */, $rbody, $rerr ] = $this->http->run( [
			'method' => 'GET',
			'url' => "{$this->protocol}://{$host}/v2/keys/{$this->directory}/?recursive=true",
			'headers' => [
				'content-type' => 'application/json',
			]
		] );

		$response = [ 'config' => null, 'error' => null, 'retry' => false, 'modifiedIndex' => 0 ];

		static $terminalCodes = [ 404 => true ];
		if ( $rcode < 200 || $rcode > 399 ) {
			$response['error'] = strlen( $rerr ?? '' ) ? $rerr : "HTTP $rcode ($rdesc)";
			$response['retry'] = empty( $terminalCodes[$rcode] );
			return $response;
		}

		try {
			$parsedResponse = $this->parseResponse( $rbody );
		} catch ( EtcdConfigParseError $e ) {
			$parsedResponse = [ 'error' => $e->getMessage() ];
		}
		return array_merge( $response, $parsedResponse );
	}

	/**
	 * Parse a response body, throwing EtcdConfigParseError if there is a validation error
	 *
	 * @param string $rbody
	 * @return array
	 */
	protected function parseResponse( $rbody ) {
		$info = json_decode( $rbody, true );
		if ( $info === null ) {
			throw new EtcdConfigParseError( "Error unserializing JSON response." );
		}
		if ( !isset( $info['node'] ) || !is_array( $info['node'] ) ) {
			throw new EtcdConfigParseError(
				"Unexpected JSON response: Missing or invalid node at top level." );
		}
		$config = [];
		$lastModifiedIndex = $this->parseDirectory( '', $info['node'], $config );
		return [ 'modifiedIndex' => $lastModifiedIndex, 'config' => $config ];
	}

	/**
	 * Recursively parse a directory node and populate the array passed by
	 * reference, throwing EtcdConfigParseError if there is a validation error
	 *
	 * @param string $dirName The relative directory name
	 * @param array $dirNode The decoded directory node
	 * @param array &$config The output array
	 * @return int lastModifiedIndex The maximum last modified index across all keys in the directory
	 */
	protected function parseDirectory( $dirName, $dirNode, &$config ) {
		$lastModifiedIndex = 0;
		if ( !isset( $dirNode['nodes'] ) ) {
			throw new EtcdConfigParseError(
				"Unexpected JSON response in dir '$dirName'; missing 'nodes' list." );
		}
		if ( !is_array( $dirNode['nodes'] ) ) {
			throw new EtcdConfigParseError(
				"Unexpected JSON response in dir '$dirName'; 'nodes' is not an array." );
		}

		foreach ( $dirNode['nodes'] as $node ) {
			'@phan-var array $node';
			$baseName = basename( $node['key'] );
			$fullName = $dirName === '' ? $baseName : "$dirName/$baseName";
			if ( !empty( $node['dir'] ) ) {
				$lastModifiedIndex = max(
					$this->parseDirectory( $fullName, $node, $config ),
					$lastModifiedIndex );
			} else {
				$value = $this->unserialize( $node['value'] );
				if ( !is_array( $value ) || !array_key_exists( 'val', $value ) ) {
					throw new EtcdConfigParseError( "Failed to parse value for '$fullName'." );
				}
				$lastModifiedIndex = max( $node['modifiedIndex'], $lastModifiedIndex );
				$config[$fullName] = $value['val'];
			}
		}
		return $lastModifiedIndex;
	}

	/**
	 * @param string $string
	 * @return mixed
	 */
	private function unserialize( $string ) {
		return json_decode( $string, true );
	}
}

/** @deprecated class alias since 1.41 */
class_alias( EtcdConfig::class, 'EtcdConfig' );
