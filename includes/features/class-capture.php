<?php
/**
 * APCuManager capture
 *
 * Handles all captures operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace APCuManager\Plugin\Feature;

use APCuManager\System\Cache;
use APCuManager\System\Logger;
use APCuManager\Plugin\Feature\Schema;

/**
 * Define the captures functionality.
 *
 * Handles all captures operations.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Capture {

	/**
	 * Delta time.
	 *
	 * @since  1.0.0
	 * @var    integer    $delta    The authorized delta time in seconds.
	 */
	public static $delta = 59;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Add a new 5 minutes interval capacity to the WP cron feature.
	 *
	 * @since 1.0.0
	 */
	public static function add_cron_05_minutes_interval( $schedules ) {
		$schedules['five_minutes'] = [
			'interval' => 300,
			'display'  => __( 'Every five minutes', 'apcu-manager' ),
		];
		return $schedules;
	}

	/**
	 * Check status and record it if needed.
	 *
	 * @since    1.0.0
	 */
	public static function check() {
		$schema = new Schema();
		$record = $schema->init_record();
		$time   = time();
		if ( function_exists( 'apcu_get_status' ) ) {
			$cache_id = '/Data/LastCheck';
			$old      = Cache::get_global( $cache_id );
			if ( ! isset( $old ) ) {
				Logger::debug( 'No APCu transient.' );
			} elseif ( ! array_key_exists( 'timestamp', $old ) ) {
				Logger::debug( 'No APCu timestamp.' );
			} elseif ( 300 - self::$delta > $time - $old['timestamp'] ) {
				Logger::debug( sprintf( 'Delta time too short: %d sec. Launching recycling process.', $time - $old['timestamp'] ) );
			} elseif ( 300 + self::$delta < $time - $old['timestamp'] ) {
				Logger::debug( sprintf( 'Delta time too long: %d sec. Launching recycling process.', $time - $old['timestamp'] ) );
			}
			if ( isset( $old ) && array_key_exists( 'timestamp', $old ) && ( 300 - self::$delta < $time - $old['timestamp'] ) && ( 300 + self::$delta - $old['timestamp'] ) ) {
				try {
					$restart            = false;
					$value              = [];
					$value['raw']       = apcu_get_status( false );
					$value['timestamp'] = $time;
					$record['status']   = 'enabled';
					// Trying to figure out the status.
					if ( array_key_exists( 'cache_full', $value['raw'] ) && (bool) $value['raw']['cache_full'] ) {
						$record['status'] = 'cache_full';
					}
					if ( array_key_exists( 'restart_pending', $value['raw'] ) && (bool) $value['raw']['restart_pending'] ) {
						$record['status'] = 'restart_pending';
					}
					if ( array_key_exists( 'restart_in_progress', $value['raw'] ) && (bool) $value['raw']['restart_in_progress'] ) {
						$record['status'] = 'restart_in_progress';
					}
					$warmup_timestamp = Cache::get_global( '/Data/WarmupTimestamp' );
					if ( false !== $warmup_timestamp ) {
						if ( 300 > $time - $warmup_timestamp ) {
							$record['status'] = 'warmup';
						}
					}
					$reset_warmup_timestamp = Cache::get_global( '/Data/ResetWarmupTimestamp' );
					if ( false !== $reset_warmup_timestamp ) {
						if ( 300 > $time - $reset_warmup_timestamp ) {
							$record['status'] = 'reset_warmup';
						}
					}
					// Trying to figure out the restart type.
					if ( array_key_exists( 'apcu_statistics', $old['raw'] ) && array_key_exists( 'apcu_statistics', $value['raw'] ) && array_key_exists( 'oom_restarts', $old['raw']['apcu_statistics'] ) && array_key_exists( 'oom_restarts', $value['raw']['apcu_statistics'] ) ) {
						if ( (int) $old['raw']['apcu_statistics']['oom_restarts'] !== (int) $value['raw']['apcu_statistics']['oom_restarts'] ) {
							$restart         = true;
							$record['reset'] = 'oom';
						}
					}
					if ( array_key_exists( 'apcu_statistics', $old['raw'] ) && array_key_exists( 'apcu_statistics', $value['raw'] ) && array_key_exists( 'hash_restarts', $old['raw']['apcu_statistics'] ) && array_key_exists( 'hash_restarts', $value['raw']['apcu_statistics'] ) ) {
						if ( (int) $old['raw']['apcu_statistics']['hash_restarts'] !== (int) $value['raw']['apcu_statistics']['hash_restarts'] ) {
							$restart         = true;
							$record['reset'] = 'hash';
						}
					}
					if ( array_key_exists( 'apcu_statistics', $old['raw'] ) && array_key_exists( 'apcu_statistics', $value['raw'] ) && array_key_exists( 'manual_restarts', $old['raw']['apcu_statistics'] ) && array_key_exists( 'manual_restarts', $value['raw']['apcu_statistics'] ) ) {
						if ( (int) $old['raw']['apcu_statistics']['manual_restarts'] !== (int) $value['raw']['apcu_statistics']['manual_restarts'] ) {
							$restart         = true;
							$record['reset'] = 'manual';
						}
					}
					if ( array_key_exists( 'memory_usage', $value['raw'] ) && array_key_exists( 'used_memory', $value['raw']['memory_usage'] ) ) {
						$record['mem_used'] = (int) $value['raw']['memory_usage']['used_memory'];
					}
					if ( array_key_exists( 'memory_usage', $value['raw'] ) && array_key_exists( 'wasted_memory', $value['raw']['memory_usage'] ) ) {
						$record['mem_wasted'] = (int) $value['raw']['memory_usage']['wasted_memory'];
					}
					if ( array_key_exists( 'memory_usage', $value['raw'] ) && array_key_exists( 'free_memory', $value['raw']['memory_usage'] ) ) {
						$free = (int) $value['raw']['memory_usage']['free_memory'];
					} else {
						$free = 0;
					}
					$record['mem_total'] = $free + $record['mem_used'] + $record['mem_wasted'];
					if ( array_key_exists( 'apcu_statistics', $value['raw'] ) && array_key_exists( 'max_cached_keys', $value['raw']['apcu_statistics'] ) ) {
						$record['key_total'] = (int) $value['raw']['apcu_statistics']['max_cached_keys'];
					}
					if ( array_key_exists( 'apcu_statistics', $value['raw'] ) && array_key_exists( 'num_cached_keys', $value['raw']['apcu_statistics'] ) ) {
						$record['key_used'] = (int) $value['raw']['apcu_statistics']['num_cached_keys'];
					}
					if ( array_key_exists( 'interned_strings_usage', $value['raw'] ) && array_key_exists( 'buffer_size', $value['raw']['interned_strings_usage'] ) ) {
						$record['buf_total'] = (int) $value['raw']['interned_strings_usage']['buffer_size'];
					}
					if ( array_key_exists( 'interned_strings_usage', $value['raw'] ) && array_key_exists( 'used_memory', $value['raw']['interned_strings_usage'] ) ) {
						$record['buf_used'] = (int) $value['raw']['interned_strings_usage']['used_memory'];
					}
					if ( $restart ) {
						if ( array_key_exists( 'apcu_statistics', $value['raw'] ) && array_key_exists( 'hits', $value['raw']['apcu_statistics'] ) ) {
							$record['hit'] = (int) $value['raw']['apcu_statistics']['hits'];
						}
						if ( array_key_exists( 'apcu_statistics', $value['raw'] ) && array_key_exists( 'misses', $value['raw']['apcu_statistics'] ) ) {
							$record['miss'] = (int) $value['raw']['apcu_statistics']['misses'];
						}
					} else {
						if ( array_key_exists( 'apcu_statistics', $old['raw'] ) && array_key_exists( 'apcu_statistics', $value['raw'] ) && array_key_exists( 'hits', $old['raw']['apcu_statistics'] ) && array_key_exists( 'hits', $value['raw']['apcu_statistics'] ) ) {
							$record['hit'] = (int) $value['raw']['apcu_statistics']['hits'] - (int) $old['raw']['apcu_statistics']['hits'];
						}
						if ( array_key_exists( 'apcu_statistics', $old['raw'] ) && array_key_exists( 'apcu_statistics', $value['raw'] ) && array_key_exists( 'misses', $old['raw']['apcu_statistics'] ) && array_key_exists( 'misses', $value['raw']['apcu_statistics'] ) ) {
							$record['miss'] = (int) $value['raw']['apcu_statistics']['misses'] - (int) $old['raw']['apcu_statistics']['misses'];
						}
					}
					if ( array_key_exists( 'apcu_statistics', $value['raw'] ) && array_key_exists( 'num_cached_scripts', $value['raw']['apcu_statistics'] ) ) {
						$record['scripts'] = (int) $value['raw']['apcu_statistics']['num_cached_scripts'];
					}
					if ( array_key_exists( 'interned_strings_usage', $value['raw'] ) && array_key_exists( 'number_of_strings', $value['raw']['interned_strings_usage'] ) ) {
						$record['strings'] = (int) $value['raw']['interned_strings_usage']['number_of_strings'];
					}
					Cache::set_global( $cache_id, $value, 'check' );
					$schema->write_statistics_record_to_database( $record );
					Logger::debug( 'APCu is enabled. Statistics recorded.' );
				} catch ( \Throwable $e ) {
					Logger::error( sprintf( 'Unable to query APCu status: %s.', $e->getMessage() ), $e->getCode() );
				}
			} else {
				try {
					$value              = [];
					$value['raw']       = apcu_get_status( false );
					$value['timestamp'] = $time;
					Cache::set_global( $cache_id, $value, 'check' );
					$record['status'] = 'recycle_in_progress';
					$schema->write_statistics_record_to_database( $record );
					Logger::debug( 'APCu is enabled. Recovery cycle.' );
				} catch ( \Throwable $e ) {
					Logger::error( sprintf( 'Unable to query APCu status: %s.', $e->getMessage() ), $e->getCode() );
				}
			}
		} else {
			$schema->write_statistics_record_to_database( $record );
			Logger::debug( 'APCu is disabled. No statistics to record.' );
		}
	}

}