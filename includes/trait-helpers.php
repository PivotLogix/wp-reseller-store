<?php

namespace Reseller_Store;

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}

trait Helpers {

	/**
	 * Add the plugin prefix to a string.
	 *
	 * @since NEXT
	 *
	 * @param  string $string
	 *
	 * @return string
	 */
	public static function prefix( $string ) {

		return ( 0 === strpos( $string, self::PREFIX ) ) ? $string : self::PREFIX . $string;

	}

	/**
	 * Return a plugin option.
	 *
	 * @since NEXT
	 *
	 * @param  string $key
	 * @param  mixed  $default (optional)
	 *
	 * @return mixed
	 */
	public static function get_option( $key, $default = false ) {

		return get_option( self::prefix( $key ), $default );

	}

	/**
	 * Update a plugin option.
	 *
	 * @since NEXT
	 *
	 * @param  string $key
	 * @param  mixed  $value
	 *
	 * @return bool
	 */
	public static function update_option( $key, $value ) {

		return update_option( self::prefix( $key ), $value );

	}

	/**
	 * Delete a plugin option.
	 *
	 * @since NEXT
	 *
	 * @param  string $key
	 *
	 * @return bool
	 */
	public static function delete_option( $key ) {

		return delete_option( self::prefix( $key ) );

	}

	/**
	 * Return a transient value, and optionally set it if it doesn't exist.
	 *
	 * @since NEXT
	 *
	 * @param  string       $name
	 * @param  mixed        $default    (optional)
	 * @param  string|array $callback   (optional)
	 * @param  int          $expiration (optional)
	 *
	 * @return mixed
	 */
	public static function get_transient( $name, $default = null, $callback = null, $expiration = DAY_IN_SECONDS ) {

		$name = self::prefix( $name );

		$value = get_transient( $name );

		if ( false !== $value || ! is_callable( $callback ) ) {

			return ( false !== $value ) ? $value : $default;

		}

		$value = $callback();
		$value = ( $value && ! is_wp_error( $value ) ) ? $value : $default;

		self::set_transient( $name, $value, (int) $expiration );

		return $value;

	}

	/**
	 * Set a transient value.
	 *
	 * @since NEXT
	 *
	 * @param  string $name
	 * @param  mixed  $value
	 * @param  int    $expiration (optional)
	 *
	 * @return bool
	 */
	public static function set_transient( $name, $value, $expiration = DAY_IN_SECONDS ) {

		return set_transient( self::prefix( $name ), $value, (int) $expiration );

	}

	/**
	 * Return product meta value, or the global setting fallback.
	 *
	 * @since NEXT
	 *
	 * @param  int    $id
	 * @param  string $key
	 * @param  mixed  $default (optional)
	 *
	 * @return mixed
	 */
	public static function get_product_meta( $id, $key, $default = false ) {

		$key = self::prefix( $key );

		return metadata_exists( 'post', $id, $key ) ? get_post_meta( $id, $key, true ) : get_option( $key, $default );

	}

	/**
	 * Return an array of missing product IDs that can be imported.
	 *
	 * @global wpdb $wpdb
	 * @since  NEXT
	 *
	 * @return array
	 */
	public static function get_missing_products() {

		if ( ! self::is_setup() ) {

			return [];

		}

		$available = (array) self::get_transient( 'products', [], function () {

			return rstore()->api->get( 'catalog/{pl_id}/products' );

		} );

		if ( empty( $available[0]->id ) ) {

			return [];

		}

		$available = wp_list_pluck( $available, 'id' );

		global $wpdb;

		$imported = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT `meta_value` FROM {$wpdb->postmeta} as pm LEFT JOIN {$wpdb->posts} as p ON ( pm.`post_id` = p.`ID` ) WHERE p.`post_type` = %s AND pm.`meta_key` = %s;",
				Post_Type::SLUG,
				Plugin::prefix( 'id' )
			)
		);

		$missing = array_diff( $available, $imported );

		return ! empty( $missing ) ? $missing : [];

	}

	/**
	 * Check if the site is missing products that can be imported.
	 *
	 * @since NEXT
	 *
	 * @return bool
	 */
	public static function is_missing_products() {

		$missing = self::get_missing_products();

		return ! empty( $missing );

	}

	/**
	 * Check if the site has imported all available products.
	 *
	 * @since NEXT
	 *
	 * @return bool
	 */
	public static function has_all_products() {

		return ! self::is_missing_products();

	}

	/**
	 * Check whether products exist.
	 *
	 * @since NEXT
	 *
	 * @return bool
	 */
	public static function has_products() {

		$counts = (array) wp_count_posts( Post_Type::SLUG );

		unset( $counts['auto-draft'] );

		return ( array_sum( $counts ) > 0 );

	}

	/**
	 * Check if the plugin has been setup.
	 *
	 * @since NEXT
	 *
	 * @return bool
	 */
	public static function is_setup() {

		return ( (int) self::get_option( 'pl_id' ) > 0 );

	}

	/**
	 * Check if we are on a specific admin screen.
	 *
	 * @since NEXT
	 *
	 * @param  string $request_uri
	 * @param  bool   $strict      (optional)
	 *
	 * @return bool
	 */
	public static function is_admin_uri( $request_uri, $strict = true ) {

		$strpos = strpos( basename( filter_input( INPUT_SERVER, 'REQUEST_URI' ) ), $request_uri );
		$result = ( $strict ) ? ( 0 === $strpos ) : ( false !== $strpos );

		return ( is_admin() && $result );

	}

	/**
	 * Safe redirect to any admin page.
	 *
	 * @since NEXT
	 *
	 * @param string $endpoint (optional)
	 * @param array  $args (optional)
	 * @param int    status (optional)
	 */
	public static function admin_redirect( $endpoint = '', $args = [], $status = 302 ) {

		wp_safe_redirect(
			esc_url_raw(
				add_query_arg( $args, admin_url( $endpoint ) )
			),
			$status
		);

		exit;

	}

	/**
	 * Insert a value into an array at a specific index point.
	 *
	 * @since NEXT
	 *
	 * @param  array $array
	 * @param  mixed $var
	 * @param  int   $index
	 * @param  bool  $preserve_keys (optional)
	 *
	 * @return array
	 */
	public static function array_insert( array $array, $var, $index, $preserve_keys = true ) {

		if ( 0 === $index ) {

			if ( is_array( $var ) ) {

				return array_merge( $var, $array );

			}

			array_unshift( $array, $var );

			return $array;

		}

		return array_merge(
			array_slice( $array, 0, $index, $preserve_keys ),
			is_array( $var ) ? $var : [ $var ],
			array_slice( $array, $index, count( $array ) - $index, $preserve_keys )
		);

	}

}
