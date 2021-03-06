<?php

/**
 * Library of general helper functions
 *
 * @package	Pilau_Base
 * @since	0.1
 */


/* WordPress user stuff
*****************************************************************************/

/**
 * Better default display name for users
 *
 * @since	Pilau_Base 0.1
 *
 * @uses	get_user_meta()
 * @uses	wp_update_user()
 */
if ( ! function_exists( 'pilau_default_user_display_name' ) ) {
	add_action( 'user_register', 'pilau_default_user_display_name' );
	function pilau_default_user_display_name( $user_id ) {
		// Fetch current user meta information
		$first = get_user_meta( $user_id, 'first_name', true );
		$last = get_user_meta( $user_id, 'last_name', true );
		$display = trim( $first . " " . $last );
		// Update
		wp_update_user( array( "ID" => $user_id, "display_name" => $display ) );
	}
}

/**
 * Get a WordPress user's role
 *
 * @since 0.1
 *
 * @uses	$wpdb
 * @uses	maybe_unserialize()
 * @uses	WP_User
 *
 * @param	int|object	$user	Either a user's ID or a user object
 * @param	bool		$manual	Optional. If true, a "manual" check is done that avoids using WP functions; use this if the code calling this function is hooked to something that may be called by WP_User, creating an infinite loop
 * @return	string|null			The user's role if the operation was successful, otherwise null
 */
if ( ! function_exists( 'pilau_get_user_role' ) ) {
	function pilau_get_user_role( $user, $manual = false ) {
		global $wpdb;
		$role = null;
		if ( is_int( $user ) || ctype_digit( $user ) ) {
			if ( $manual ) {
				// Manual check
				global $wpdb;
				$caps = $wpdb->get_var( $wpdb->prepare("
				SELECT	meta_value
				FROM	$wpdb->usermeta
				WHERE	user_id		= %d
				AND		meta_key	= %s
			", intval( $user ), $wpdb->prefix . "capabilities" ) );
				if ( $caps ) {
					$user = new StdClass;
					$user->roles = array_keys( maybe_unserialize( $caps ) );
				}
			} else {
				// Standard WP User
				$user = new WP_User( $user );
			}
		}
		if ( is_object( $user ) ) {
			$caps_field = $wpdb->prefix . 'capabilities';
			if ( property_exists( $user, 'roles' ) && is_array( $user->roles ) && ! empty( $user->roles ) ) {
				$role = $user->roles[0];
			} else if ( property_exists( $user, $caps_field ) && is_array( $user->$caps_field ) && ! empty( $user->$caps_field ) ) {
				$role = array_shift( array_keys( $user->$caps_field ) );
			}
		}
		return $role;
	}
}

/**
 * Get a user with metadata
 *
 * Currently doesn't work with meta fields that have multiple values -
 * only the first is returned.
 *
 * @since 0.1
 *
 * @uses	get_userdata()
 * @uses	get_user_meta()
 * @uses	maybe_unserialize()
 *
 * @param	int		$id	The user's ID
 * @return	object
 */
if ( ! function_exists( 'pilau_get_user_with_meta' ) ) {
	function pilau_get_user_with_meta( $id ) {
		$user = get_userdata( $id );
		if ( $user ) {
			$user = $user->data;
			$user_meta = get_user_meta( $id );
			foreach ( $user_meta as $user_meta_key => $user_meta_value ) {
				$user->{$user_meta_key} = maybe_unserialize( $user_meta_value[0] );
			}
		}
		return $user;
	}
}


/* Array and object functions
*****************************************************************************/

/**
 * Return an array of values from a specific key in each object in an array of objects
 *
 * @since 0.1
 *
 * @param	string		$needle_key	The property to search for inside the array's objects
 * @param	array		$haystack	The array of objects
 * @return	bool|array				False if no match, or an array of values
 */
if ( ! function_exists( 'pilau_objects_array_values' ) ) {
	function pilau_objects_array_values( $needle_key, $haystack ) {
		// Check we have the right kind of input
		if ( ! is_array( $haystack ) || empty ( $haystack ) )
			return false;
		$values = array();
		// Iterate through our haystack
		foreach ( $haystack as $object ) {
			// Ensure this array element is an object and has a key that matches our needle's key
			if ( is_object( $object ) && property_exists( $object, $needle_key ) )
				$values[] = $object->$needle_key;
		}
		return $values;
	}
}

/**
 * Search an array of objects for property value
 *
 * @since 0.1
 *
 * @param	string		$needle_key		The key being searched for
 * @param	string		$needle_val		The value being searched for
 * @param	array		$haystack		An array of objects
 * @param	bool		$case_sensitive	Optional. Whether to make the value matching case-sensitive.
 * @return	bool|int					False if no match found, otherwise the index of the object in the array that has the key / value combination
 */
if ( ! function_exists( 'pilau_search_object_array' ) ) {
	function pilau_search_object_array( $needle_key, $needle_val, $haystack, $case_sensitive = false ) {
		// Check we have the right kind of input
		if ( ! is_array( $haystack ) || empty ( $haystack ) )
			return false;
		// Iterate through our haystack
		foreach ( $haystack as $i => $value ) {
			// Ensure this array element is an object and has a key that matches our needle's key
			if ( is_object( $value ) && property_exists( $value, $needle_key ) ) {
				// Case-insensitive comparison?
				if ( $case_sensitive ) {
					if ( strcmp( $needle_val, $value->$needle_key ) == 0 )
						return $i;
				} else {
					if ( strcasecmp( $needle_val, $value->$needle_key ) == 0 )
						return $i;
				}
			}
		}
		// No match found
		return false;
	}
}

/**
 * Check that array key or object property exists and there's a value
 *
 * @since 0.1
 *
 * @param	string	$needle		The key or property name
 * @param	mixed	$haystack	The array or object
 * @return	bool
 */
if ( ! function_exists( 'pilau_value_exists' ) ) {
	function pilau_value_exists( $needle, $haystack ) {
		if ( is_array( $haystack ) ) {
			return array_key_exists( $needle, $haystack ) && ! empty( $haystack[ $needle ] );
		} else if ( is_object( $haystack ) ) {
			return property_exists( $haystack, $needle ) && ! empty( $haystack->$needle );
		}
		return false;
	}
}

/**
 * Search arrays in an array for a value, and return the key of the first matching array
 *
 * @since 0.1
 *
 * @param	string			$needle		The value being searched for
 * @param	array			$haystack	An array of arrays
 * @return	bool|string|int				False if no match found, otherwise the index of the object in the array that has the key / value combination
 */
if ( ! function_exists( 'pilau_search_arrays_in_array' ) ) {
	function pilau_search_arrays_in_array( $needle, $haystack ) {
		if ( is_array( $haystack ) ) {
			foreach ( $haystack as $key => $value ) {
				if ( is_array( $value ) && array_search( $needle, $value ) !== false )
					return $key;
			}
		}
		return false;
	}
}

/**
 * Trim every string value in an array
 *
 * @since 0.1
 *
 * @param	array	$array
 * @param	string	$charlist	Optional. List of characters to trim. Null (default) trims whitespace
 * @return	array				If non-array data is passed, it will be returned intact
 */
if ( ! function_exists( 'pilau_trim_array' ) ) {
	function pilau_trim_array( $array, $charlist = null ) {
		if ( is_array( $array ) ) {
			foreach ( $array as &$value ) {
				if ( is_string( $value ) ) {
					if ( $charlist ) {
						$value = trim( $value, $charlist );
					} else {
						$value = trim( $value );
					}
				}
			}
		}
		return $array;
	}
}

/**
 * A quick way to explode lists stored in constants into the global scope
 *
 * @since 0.1
 *
 * @param	array	$constants	An array of the names of constants to explode
 * @param	string	$sep		Optional. The character used as a separator
 * @return	void
 */
if ( ! function_exists( 'pilau_explode_constants' ) ) {
	function pilau_explode_constants( $constants = array(), $sep = ',' ) {
		if ( is_array( $constants ) && count( $constants ) ) {
			foreach ( $constants as $constant ) {
				if ( is_string( $constant ) && defined( $constant ) ) {
					$var_name = strtolower( $constant );
					global $$var_name;
					$$var_name = explode( $sep, constant( $constant ) );
				}
			}
		}
	}
}


/* String functions
*****************************************************************************/

/**
 * Email obfuscator
 *
 * @since	Pilau_Base 0.1
 *
 * @link	http://bla.st/
 * @link	http://macromates.com/
 *
 * @param	string	$string
 * @param	string	$noscript_contact	The URL for a contact form in the no-JS fallback link
 * @return	string
 */
if ( ! function_exists( 'pilau_obfuscate_text' ) ) {
	function pilau_obfuscate_text( $string, $noscript_contact = '/contact/' ) {
		// Returns javascript code
		$new_string = str_rot13( $string );
		$new_string = str_replace( '@', '&#64;', $new_string ); // swap @ for the html character code
		$new_string = str_replace( '"', '\\"', $new_string ); // escape doublequotes
		$new_string = str_replace( '.', '\056', $new_string ); // swap the dots with javascript . characters
		$result = '<script type="text/javascript">document.write("' . $new_string . '".replace(/[a-zA-Z]/g, function(c){return String.fromCharCode((c<="Z"?90:122)>=(c=c.charCodeAt(0)+13)?c:c-26);}));</script>';
		if ( $noscript_contact )
			$result .= '<noscript><a href="' . esc_url( $noscript_contact ) . '">' . __( 'Our contact form' ) . '</a></noscript>';
		return $result;
	}
}

/**
 * Given an email address, creates a nice obfuscated <a href="mailto:email">email</a> style address
 *
 * @since	Pilau_Base 0.1
 * @uses	pilau_obfuscate_text()
 * @param	string	$email
 * @param	bool	$icon
 * @param	string	$at_sign	The text representing the @ sign in the email, if not "@"
 * @param	string	$text		The link text - defaults to the email address
 * @param	array	$classes	Any extra classes for the a tag
 * @return	string
 */
if ( ! function_exists( 'pilau_obfuscate_email' ) ) {
	function pilau_obfuscate_email( $email, $icon = true, $at_sign = "@", $text = "", $classes = array() ) {
		if ( $at_sign != "@" )
			$email = str_replace( $at_sign, "@", $email );
		if ( ! $text )
			$text = $email;
		$string = '<a href="mailto:' . esc_attr( $email ) . '"';
		if ( ! $icon )
			$classes[] = 'no-icon';
		if ( $classes )
			$string .= ' class="' . implode( " ", $classes ) . '"';
		$string .= '>' . wp_kses( $text, array() ) .'</a>';
		return pilau_obfuscate_text( $string );
	}
}

/**
 * Create a hyperlink for phone numbers
 *
 * @since	Pilau_Base 0.1
 *
 * @param	string		$number
 * @param	string		$country_code
 * @return	string
 */
function pilau_phone_link( $number, $country_code = '44' ) {
	return 'tel:+' . $country_code .  preg_replace( '/[^0-9]/', '', ltrim( $number, 0 ) );
}

/**
 * Get an extract from a string, trimming by words or paragraphs
 *
 * @since	Pilau_Base 0.1
 *
 * @uses	strip_shortcodes()
 * @uses	do_shortcode()
 *
 * @param	string	$string
 * @param	int		$max_words
 * @param	int		$max_paras	If set to zero, trims to words
 * @param	bool	$strip_tags	Strip tags or not. If true, strips WP shortcodes too; if false, parses shortcodes.
 * @return	string
 */
if ( ! function_exists( 'pilau_extract' ) ) {
	function pilau_extract( $string, $max_words = 30, $max_paras = 0, $strip_tags = true ) {
		if ( $strip_tags ) {
			$string = strip_shortcodes( $string );
			$string = trim( strip_tags( $string ) );
		} else {
			$string = do_shortcode( $string );
		}
		if ( $max_paras ) {
			// Strip to paras limit
			$paras = preg_split( "/\n\r/", $string );
			if ( count( $paras ) > $max_paras ) {
				$paras = array_slice( $paras, 0, $max_paras );
			}
			return implode( "\n\r", $paras );
		} else {
			// Strip to word limit
			$words = explode( " ", $string );
			if ( count( $words ) > $max_words ) {
				$words = array_slice( $words, 0, $max_words );
				return implode( " ", $words ) . "...";
			} else {
				return implode( " ", $words );
			}
		}
	}
}


/* URLs
*****************************************************************************/

/**
 * Get the current URL
 *
 * @since	Pilau_Base 0.1
 *
 * @uses	trailingslashit()
 *
 * @param	bool	$keep_qs		Keep query string?
 * @param	bool	$strip_wp_vars	Strip standard WordPress variables?
 * @param	bool	$return_path	Return relative path, or URL?
 * @param	array	$strip_qs_vars	Every query string variable passed in this array will be stripped
 * @return	string
 */
if ( ! function_exists( 'pilau_get_current_url' ) ) {
	function pilau_get_current_url( $keep_qs = true, $strip_wp_vars = false, $return_path = false, $strip_qs_vars = array() ) {
		$url = '';

		if ( ! $return_path ) {
			$url = 'http';
			if ( array_key_exists( "HTTPS", $_SERVER ) && $_SERVER['HTTPS'] == "on" )
				$url .= "s";
			$url .= "://" . $_SERVER["SERVER_NAME"];
		}

		$url .= $_SERVER["REQUEST_URI"];

		// Strip query string
		$url_qs_parts = explode( '?', $url );
		$url = $url_qs_parts[0];
		$qs = count( $url_qs_parts ) > 1 ? $url_qs_parts[1] : '';
		if ( $strip_wp_vars ) {
			// Strip WP vars (could be extended)
			$url_parts = explode( '/', $url );
			foreach ( array( 'page' ) as $wp_var ) {
				if ( $var_key = array_search( $wp_var, $url_parts ) )
					$url_parts = array_slice( $url_parts, 0, $var_key );
			}
			$url = trailingslashit( implode( '/', $url_parts ) );
		}

		// Strip query string vars?
		if ( $strip_qs_vars && $qs ) {
			$qs_parts = explode( '&', $qs );
			foreach ( $qs_parts as $key => $qs_part ) {
				$qs_var = explode( '=', $qs_part );
				if ( in_array( $qs_var[0], $strip_qs_vars ) ) {
					unset( $qs_parts[ $key ] );
				}
			}
			$qs = implode( '&', $qs_parts );
		}

		// Put query string back?
		if ( $keep_qs && $qs )
			$url .= '?' . $qs;

		// Trim leading slash if a relative path
		if ( $return_path )
			$url = ltrim( $url, '/' );

		return $url;
	}
}

/**
 * Return a path from a URL
 *
 * @since	Pilau_Base 0.1
 *
 * @uses	pilau_get_current_url()
 *
 * @param	string	$url	If nothing is passed, the current URL is used
 * @return	string			Path, with no leading or trailing slashes
 */
if ( ! function_exists( 'pilau_path_from_url' ) ) {
	function pilau_path_from_url( $url = null ) {
		if ( $url === null )
			return pilau_get_current_url( false, true, true );
		$url_parts = parse_url( $url );
		return trim( $url_parts['path'], '/' );
	}
}

/**
 * Wrapper that extends the core url_to_postid() function
 *
 * @since	Pilau_Base 0.1
 *
 * @uses	url_to_postid()
 * @uses	get_post_types()
 * @uses	pilau_path_from_url()
 * @uses	WP_Query
 * @uses	wp_reset_postdata()
 *
 * @param	string	$url
 * @return	int				The post ID
 */
if ( ! function_exists( 'pilau_url_to_postid' ) ) {
	function pilau_url_to_postid( $url ) {

		// Try the core function
		$post_id = url_to_postid( $url );

		if ( $post_id == 0 ) {

			// Try custom post types
			$cpts = get_post_types( array(
				'public'   => true,
				'_builtin' => false
			), 'objects', 'and' );
			$path = pilau_path_from_url( $url );
			foreach ( $cpts as $cpt_name => $cpt ) {
				$cpt_slug = $cpt->rewrite['slug'];
				if ( strlen( $path ) > strlen( $cpt_slug ) && substr( $path, 0, strlen( $cpt_slug ) ) == $cpt_slug ) {
					$slug = substr( $path, strlen( $cpt_slug ) );
					$query = new WP_Query( array(
						'post_type'			=> $cpt_name,
						'name'				=> $slug,
						'posts_per_page'	=> 1
					));
					if ( is_object( $query->post ) )
						$post_id = $query->post->ID;
					// Reset loop
					wp_reset_postdata();
				}
			}

		}

		return $post_id;
	}
}

/**
 * Get URL of an image
 *
 * @since	Pilau_Base 0.1
 *
 * @uses	wp_get_attachment_image_src()
 *
 * @param	int		$attachment_id	The ID of the image attachment
 * @param	string	$size
 * @return	string
 */
if ( ! function_exists( 'pilau_get_image_url' ) ) {
	function pilau_get_image_url( $attachment_id, $size = "thumbnail" ) {
		$image_infos = wp_get_attachment_image_src( $attachment_id, $size );
		return $image_infos[0];
	}
}

/**
 * Get URL of a post's featured image
 *
 * @since	Pilau_Base 0.1
 *
 * @uses	pilau_get_image_url()
 * @uses	get_post_thumbnail_id()
 *
 * @param	int		$post_id
 * @param	string	$size
 * @return	string
 */
if ( ! function_exists( 'pilau_get_featured_image_url' ) ) {
	function pilau_get_featured_image_url( $post_id = 0, $size = "thumbnail" ) {
		global $post;
		$post_id = $post_id ? $post_id : $post->ID;
		return pilau_get_image_url( get_post_thumbnail_id( $post_id ), $size );
	}
}

/**
 * Construct URL for website based on user ID
 *
 * @since	Pilau_Base 0.1
 * @param	string	$website	'facebook' | 'twitter' | 'google+' | 'pinterest' | 'linkedin' | 'youtube' | 'instagram' | 'foursquare'
 * @param	string	$id
 * @return	string
 */
if ( ! function_exists( 'pilau_construct_website_url' ) ) {
	function pilau_construct_website_url( $website, $id ) {
		$url = null;

		switch ( $website ) {

			case 'facebook': {
				$url = 'https://www.facebook.com/';
				if ( ctype_digit( $id ) ) {
					$url .= 'profile.php?id=' . $id;
				} else {
					$url .= $id;
				}
				break;
			}

			case 'twitter': {
				$url = 'https://twitter.com/' . $id;
				break;
			}

			case 'google+': {
				$url = 'https://plus.google.com/' . $id;
				break;
			}

			case 'pinterest': {
				$url = 'http://pinterest.com/' . $id;
				break;
			}

			case 'linkedin': {
				$url = 'http://www.linkedin.com/profile/view?id=' . $id;
				break;
			}

			case 'youtube': {
				$url = 'http://www.youtube.com/user/' . $id;
				break;
			}

			case 'instagram': {
				$url = 'http://instagram.com/' . $id;
				break;
			}

			case 'foursquare': {
				$url = 'https://foursquare.com/' . $id;
				break;
			}

		}

		return $url;
	}
}

/**
 * Replace links in text with html links
 *
 * @link	http://stackoverflow.com/questions/1960461/convert-plain-text-urls-into-html-hyperlinks-in-php
 *
 * @since	Pilau_Base 0.1
 *
 * @param	string	$s
 * @return	string
 */
if ( ! function_exists( 'pilau_link_urls' ) ) {
	function pilau_link_urls( $s ) {
		return preg_replace('@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@', '<a href="$1" target="_blank">$1</a>', $s );
	}
}


/* Navigation
*****************************************************************************/

/**
 * Get nav menu without markup containers
 *
 * @since	Pilau_Base 0.1
 *
 * @uses	wp_nav_menu()
 *
 * @param	string	$menu	The name given to the menu in Appearance > Menus
 * @param	integer	$depth
 * @return	string
 */
if ( ! function_exists( 'pilau_menu_without_containers' ) ) {
	function pilau_menu_without_containers( $menu, $depth = 1 ) {
		$menu_items = wp_nav_menu( array(
			'menu'				=> $menu,
			'container'			=> '',
			'echo'				=> false,
			'depth'				=> $depth
		));
		// Strip ul wrapper
		$menu_items = trim( $menu_items );
		$menu_items = preg_replace( '#<ul[^>]*>#i', '', $menu_items, 1 );
		$menu_items = substr( $menu_items, 0, -5 );
		return $menu_items;
	}
}


/* Plugin-related
*****************************************************************************/

/**
 * Is a plugin installed?
 *
 * @since	Pilau_Base 0.1
 *
 * @uses	$pilau_wp_plugins
 *
 * @param	string	$plugin		The path to the plugin file, relative to the plugins directory
 * @return	bool
 */
if ( ! function_exists( 'pilau_is_plugin_installed' ) ) {
	function pilau_is_plugin_installed( $plugin ) {
		global $pilau_wp_plugins;
		return in_array( $plugin, array_keys( $pilau_wp_plugins ) );
	}
}


/* Miscellaneous
*****************************************************************************/

/**
 * Remove magic quotes slashes
 *
 * @since 0.1
 *
 * @param	string	$string
 * @return	string
 */
if ( ! function_exists( 'pilau_undo_magic_quotes' ) ) {
	function pilau_undo_magic_quotes( $string ) {
		if ( is_string( $string ) ) {
			$string = str_replace( array( "\'", '\"' ), array( "'", '"' ), $string );
		}
		return $string;
	}
}

/**
 * Return the formatted size of a file.
 *
 * @since 0.1
 *
 * @uses size_format()
 *
 * @param	string|int	$input			Either the path to a valid file, or a number in bytes
 * @param	string		$default_output	Optional. The string to output if the input can't be used (e.g. the file doesn't exist)
 * @return	string						The size, formatted
 */
if ( ! function_exists( 'pilau_format_filesize' ) ) {
	function pilau_format_filesize( $input, $default_output = '??' ) {
		$size = null;
		$output = $default_output;
		// Set up some common file size measurements
		$kb = 1024;         // Kilobyte
		$mb = 1024 * $kb;   // Megabyte
		$gb = 1024 * $mb;   // Gigabyte
		$tb = 1024 * $gb;   // Terabyte
		if ( is_file( $input ) ) {
			// Get the file size in bytes
			$size = filesize( $input );
		} else if ( is_numeric( $input ) ) {
			$size = (int) $input;
		}
		if ( $size ) {
			$output = size_format( $size );
		}
		return $output;
	}
}

/**
 * Make sure file type is simple
 *
 * @since	Pilau_Base 0.1
 *
 * @param	string	$type
 * @return	string
 */
function pilau_simple_file_type( $type ) {
	if ( strlen( $type ) > 4 ) {
		switch ( strtolower( $type ) ) {
			case 'vnd.openxmlformats-officedocument.wordprocessingml.document': {
				$type = 'docx';
				break;
			}
			case 'vnd.openxmlformats-officedocument.spreadsheetml.sheet': {
				$type = 'xlsx';
				break;
			}
			case 'vnd.openxmlformats-officedocument.presentationml.presentation': {
				$type = 'pptx';
				break;
			}
			case 'x-ms-wmv': {
				$type = 'wmv';
				break;
			}
			case 'quicktime': {
				$type = 'mov';
				break;
			}
		}
	}
	return $type;
}

/**
 * Test for being on login page
 *
 * @link	http://stackoverflow.com/questions/5266945/wordpress-how-detect-if-current-page-is-the-login-page
 *
 * @since	Pilau_Base 0.1
 *
 * @return	bool
 */
function pilau_is_login_page() {
	return in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) );
}
