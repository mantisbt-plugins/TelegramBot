<?php
/**
 * MantisBT - A PHP based bugtracking system
 *
 * MantisBT is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MantisBT is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 * @package MantisBT
 *
 * @noinspection PhpUnused
 */
global $g_telegrambot_custom_field_type_definition;

$g_telegrambot_custom_field_type_definition[CUSTOM_FIELD_TYPE_STRING] = array (
	'#display_possible_values' => true,
	'#display_valid_regexp' => true,
	'#display_length_min' => true,
	'#display_length_max' => true,
	'#display_default_value' => true,
	'#function_return_distinct_values' => null,
	# MySQL 4-bytes UTF-8 chars workaround #21101
	'#function_value_to_database' => 'db_mysql_fix_utf8',
	'#function_database_to_value' => null,
	'#function_print_input' => 'telegrambot_cfdef_input_textbox',
	'#function_print_value' => null,
	'#function_string_value' => 'telegrambot_cfdef_prepare_string',
	'#function_string_value_for_email' => null,
);

$g_telegrambot_custom_field_type_definition[CUSTOM_FIELD_TYPE_TEXTAREA] = array (
	'#display_possible_values' => true,
	'#display_valid_regexp' => true,
	'#display_length_min' => true,
	'#display_length_max' => true,
	'#display_default_value' => true,
	'#function_return_distinct_values' => null,
	# MySQL 4-bytes UTF-8 chars workaround #21101
	'#function_value_to_database' => 'db_mysql_fix_utf8',
	'#function_database_to_value' => null,
	'#function_print_input' => 'telegrambot_cfdef_input_textarea',
	'#function_print_value' => 'telegrambot_cfdef_print_textarea',
	'#function_string_value' => 'telegrambot_cfdef_prepare_string',
	'#function_string_value_for_email' => null,
);

$g_telegrambot_custom_field_type_definition[CUSTOM_FIELD_TYPE_NUMERIC] = array (
	'#display_possible_values' => true,
	'#display_valid_regexp' => true,
	'#display_length_min' => true,
	'#display_length_max' => true,
	'#display_default_value' => true,
	'#function_return_distinct_values' => null,
	'#function_value_to_database' => null,
	'#function_database_to_value' => null,
	'#function_print_input' => 'telegrambot_cfdef_input_textbox',
	'#function_print_value' => 'telegrambot_cfdef_print_numeric',
	'#function_string_value' => 'telegrambot_cfdef_prepare_numeric',
	'#function_string_value_for_email' => null,
);

$g_telegrambot_custom_field_type_definition[CUSTOM_FIELD_TYPE_FLOAT] = array (
	'#display_possible_values' => true,
	'#display_valid_regexp' => true,
	'#display_length_min' => true,
	'#display_length_max' => true,
	'#display_default_value' => true,
	'#function_return_distinct_values' => null,
	'#function_value_to_database' => null,
	'#function_database_to_value' => null,
	'#function_print_input' => 'telegrambot_cfdef_input_textbox',
	'#function_print_value' => 'telegrambot_cfdef_print_float',
	'#function_string_value' => 'telegrambot_cfdef_prepare_float',
	'#function_string_value_for_email' => null,
);

$g_telegrambot_custom_field_type_definition[CUSTOM_FIELD_TYPE_ENUM] = array (
	'#display_possible_values' => true,
	'#display_valid_regexp' => true,
	'#display_length_min' => true,
	'#display_length_max' => true,
	'#display_default_value' => true,
	'#function_return_distinct_values' => 'telegrambot_cfdef_prepare_list_distinct_values',
	'#function_value_to_database' => null,
	'#function_database_to_value' => null,
	'#function_print_input' => 'telegrambot_cfdef_input_list',
	'#function_print_value' => null,
	'#function_string_value' => 'telegrambot_cfdef_prepare_list_value',
	'#function_string_value_for_email' => 'telegrambot_cfdef_prepare_list_value_for_email',
);

$g_telegrambot_custom_field_type_definition[CUSTOM_FIELD_TYPE_EMAIL] = array (
	'#display_possible_values' => true,
	'#display_valid_regexp' => true,
	'#display_length_min' => true,
	'#display_length_max' => true,
	'#display_default_value' => true,
	'#function_return_distinct_values' => null,
	'#function_value_to_database' => null,
	'#function_database_to_value' => null,
	'#function_print_input' => 'telegrambot_cfdef_input_textbox',
	'#function_print_value' => 'telegrambot_cfdef_print_email_value',
	'#function_string_value' => 'telegrambot_cfdef_prepare_email_value',
	'#function_string_value_for_email' => 'telegrambot_cfdef_prepare_email_value_for_email',
);

$g_telegrambot_custom_field_type_definition[CUSTOM_FIELD_TYPE_CHECKBOX] = array (
	'#display_possible_values' => true,
	'#display_valid_regexp' => true,
	'#display_length_min' => true,
	'#display_length_max' => true,
	'#display_default_value' => true,
	'#function_return_distinct_values' => 'telegrambot_cfdef_prepare_list_distinct_values',
	'#function_value_to_database' => 'telegrambot_cfdef_prepare_list_value_to_database',
	'#function_database_to_value' => 'telegrambot_cfdef_prepare_list_database_to_value',
	'#function_print_input' => 'telegrambot_cfdef_input_checkbox',
	'#function_print_value' => null,
	'#function_string_value' => 'telegrambot_cfdef_prepare_list_value',
	'#function_string_value_for_email' => 'telegrambot_cfdef_prepare_list_value_for_email',
);

$g_telegrambot_custom_field_type_definition[CUSTOM_FIELD_TYPE_RADIO] = array (
	'#display_possible_values' => true,
	'#display_valid_regexp' => false,
	'#display_length_min' => false,
	'#display_length_max' => false,
	'#display_default_value' => true,
	'#function_return_distinct_values' => 'telegrambot_cfdef_prepare_list_distinct_values',
	'#function_value_to_database' => null,
	'#function_database_to_value' => null,
	'#function_print_input' => 'telegrambot_cfdef_input_radio',
	'#function_print_value' => null,
	'#function_string_value' => 'telegrambot_cfdef_prepare_list_value',
	'#function_string_value_for_email' => 'telegrambot_cfdef_prepare_list_value_for_email',
);

$g_telegrambot_custom_field_type_definition[CUSTOM_FIELD_TYPE_LIST] = array (
	'#display_possible_values' => true,
	'#display_valid_regexp' => true,
	'#display_length_min' => true,
	'#display_length_max' => true,
	'#display_default_value' => true,
	'#function_return_distinct_values' => 'telegrambot_cfdef_prepare_list_distinct_values',
	'#function_value_to_database' => null,
	'#function_database_to_value' => null,
	'#function_print_input' => 'telegrambot_cfdef_input_list',
	'#function_print_value' => null,
	'#function_string_value' => 'telegrambot_cfdef_prepare_list_value',
	'#function_string_value_for_email' => 'telegrambot_cfdef_prepare_list_value_for_email',
);

$g_telegrambot_custom_field_type_definition[CUSTOM_FIELD_TYPE_MULTILIST] = array (
	'#display_possible_values' => true,
	'#display_valid_regexp' => true,
	'#display_length_min' => true,
	'#display_length_max' => true,
	'#display_default_value' => true,
	'#function_return_distinct_values' => 'telegrambot_cfdef_prepare_list_distinct_values',
	'#function_value_to_database' => 'telegrambot_cfdef_prepare_list_value_to_database',
	'#function_database_to_value' => 'telegrambot_cfdef_prepare_list_database_to_value',
	'#function_print_input' => 'telegrambot_cfdef_input_list',
	'#function_print_value' => null,
	'#function_string_value' => 'telegrambot_cfdef_prepare_list_value',
	'#function_string_value_for_email' => 'telegrambot_cfdef_prepare_list_value_for_email',
);

$g_telegrambot_custom_field_type_definition[CUSTOM_FIELD_TYPE_DATE] = array (
	'#display_possible_values' => true,
	'#display_valid_regexp' => true,
	'#display_length_min' => true,
	'#display_length_max' => true,
	'#display_default_value' => true,
	'#function_return_distinct_values' => null,
	'#function_value_to_database' => null,
	'#function_database_to_value' => null,
	'#function_default_to_value' => 'telegrambot_cfdef_prepare_date_default',
	'#function_print_input' => 'telegrambot_cfdef_input_date',
	'#function_print_value' => null,
	'#function_string_value' => 'telegrambot_cfdef_prepare_date_value',
	'#function_string_value_for_email' => 'telegrambot_cfdef_prepare_date_value_for_email',
);

/**
 * Prepare List Value for database storage
 * @param string $p_value Value.
 * @return string
 */
function telegrambot_cfdef_prepare_list_database_to_value( $p_value ) {
	return rtrim( ltrim( $p_value, '|' ), '|' );
}

/**
 * Print value of text area custom field with sanitization and link processing.
 * @param string $p_value The custom field value.
 */
function telegrambot_cfdef_print_textarea( $p_value ) {
	echo string_display_links( $p_value );
}

/**
 * Print value of numeric custom field with sanitization and link processing.
 * @param string $p_value The custom field value.
 */
function telegrambot_cfdef_print_numeric( $p_value ) {
	echo is_numeric( $p_value ) ? (int)$p_value : '';
}

/**
 * Print value of float custom field with sanitization and link processing.
 * @param string $p_value The custom field value.
 */
function telegrambot_cfdef_print_float( $p_value ) {
	echo is_numeric( $p_value ) ? (float)$p_value : '';
}

/**
 * Prepare value for custom fields of type numeric.
 * @param string $p_value The string value.
 * @return int The numeric value.
 */
function telegrambot_cfdef_prepare_numeric( $p_value ) {
	return (int)$p_value;
}

/**
 * Prepare value for custom fields of type float.
 * @param string $p_value The string value.
 * @return float The float value.
 */
function telegrambot_cfdef_prepare_float( $p_value ) {
	return (float)$p_value;
}

/**
 * Prepare value for custom fields of type string.
 * @param string $p_value The string value.
 * @return string The string value.
 */
function telegrambot_cfdef_prepare_string( $p_value ) {
	return $p_value;
}

/**
 * Prepare List Value for email
 * @param string $p_value Value.
 * @return string
 */
function telegrambot_cfdef_prepare_list_value_for_email( $p_value ) {
	# strip start and end markers before converting markers to commas
	return str_replace( '|', ', ', mb_substr( str_replace( '||', '|', '|' . $p_value . '|' ), 1, -1 ) );
}

/**
 * Print the value of the email custom field.
 * @param string $p_value The database value
 * @return void
 */
function telegrambot_cfdef_print_email_value( $p_value ) {
	if( !is_blank( $p_value ) ) {
		print_email_link( $p_value, $p_value );
	}
}

/**
 * Format email address for text email
 * @param string $p_value The database value.
 * @return string The plain text value
 */
function telegrambot_cfdef_prepare_email_value_for_email( $p_value ) {
	return is_null( $p_value ) ? '' : $p_value;
}

/**
 * format date value for email
 * @param integer $p_value Value.
 * @return string
 */
function telegrambot_cfdef_prepare_date_value_for_email( $p_value ) {
	return telegrambot_cfdef_prepare_date_value( $p_value );
}

/**
 * Translates the default date value into a timestamp.
 *
 * Default date can be any supported Date format
 * {@see https://www.php.net/manual/en/datetime.formats.php}.
 * The legacy style with format wrapped in curly bracket (e.g. {tomorrow}) is
 * still supported for backwards compatibility.
 *
 * @param string $p_value The default date string.
 *
 * @return int Calculated default date's timestamp, or 0 if format is invalid.
 */
function telegrambot_cfdef_prepare_date_default( $p_value ) {
	if( is_blank( $p_value ) ) {
		return 0;
	}

	$t_value = trim( $p_value );

	# Allow legacy "{xxx}" format for dynamic dates
	if( preg_match( '/^{(.*)}$/', $t_value, $t_matches ) ) {
		$t_value = $t_matches[1];
	}

	# Check default date format and calculate actual date
	try {
		$t_date = new DateTimeImmutable( $t_value );
	}
	catch( Exception $e ) {
		return 0;
	}

	return $t_date->getTimestamp();
}

/**
 * string_custom_field_value
 * @param string $p_value Value.
 * @return string
 */
function telegrambot_cfdef_prepare_list_value( $p_value ) {
	# strip start and end markers before converting markers to commas
	return string_display_line( str_replace( '|', ', ', mb_substr( str_replace( '||', '|', '|' . $p_value . '|' ), 1, -1 ) ) );
}

/**
 * Get the value for the email custom field.
 * @param string $p_value The database value
 * @return string The email value.
 */
function telegrambot_cfdef_prepare_email_value( $p_value ) {
	return $p_value;
}

/**
 * Prepare date value
 * @param integer $p_value Date timestamp.
 * @return string
 */
function telegrambot_cfdef_prepare_date_value( $p_value ) {
	if( $p_value && is_numeric( $p_value ) ) {
		return date( config_get( 'short_date_format' ), $p_value );
	}

	return '';
}

/**
 * Split a custom field value into an array of single values.
 *
 * @param string|array|null $p_value Custom field value, single values are separated by "|".
 * @return array
 */
function telegrambot_cfdef_value_to_array( $p_value ) {
	if( is_array( $p_value ) ) {
		return $p_value;
	}

	if( $p_value === null || $p_value === '' ) {
		return array();
	}

	return explode( '|', trim( $p_value, '|' ) );
}

/**
 * Add the pagination buttons of a possible values list to the keyboard.
 *
 * @param Longman\TelegramBot\Entities\InlineKeyboard $p_inline_keyboard Keyboard to add the buttons to.
 * @param integer $p_field_id Custom field identifier.
 * @param integer $p_page     Current page ( 10 values per page ).
 * @param integer $p_count    Total count of possible values.
 * @return void
 */
function telegrambot_cfdef_keyboard_pages_add( $p_inline_keyboard, $p_field_id, $p_page, $p_count ) {
	$t_row = array();

	if( $p_page > 1 ) {
		$t_row[] = array(
			'text'          => '<<',
			'callback_data' => json_encode( array( TelegrambotActions::REPORT_BUG_TAG => array(
				TelegrambotActions::GET_CUSTOM_FIELD => array( $p_field_id => array( 'p' => $p_page - 1 ) )
			) ) )
		);
	}

	if( $p_count > $p_page * 10 ) {
		$t_row[] = array(
			'text'          => '>>',
			'callback_data' => json_encode( array( TelegrambotActions::REPORT_BUG_TAG => array(
				TelegrambotActions::GET_CUSTOM_FIELD => array( $p_field_id => array( 'p' => $p_page + 1 ) )
			) ) )
		);
	}

	if( !empty( $t_row ) ) {
		call_user_func_array( array( $p_inline_keyboard, 'addRow' ), $t_row );
	}
}

/**
 * Add the "skip" button to the keyboard of an optional custom field.
 *
 * @param Longman\TelegramBot\Entities\InlineKeyboard $p_inline_keyboard Keyboard to add the button to.
 * @param array $p_field_def Custom field definition.
 * @return void
 */
function telegrambot_cfdef_keyboard_skip_add( $p_inline_keyboard, array $p_field_def ) {
	if( $p_field_def['require_report'] ) {
		return;
	}

	keyboard_skip_button_add( $p_inline_keyboard, array( TelegrambotActions::REPORT_BUG_TAG => array(
		TelegrambotActions::SKIP_CUSTOM_FIELD => array( (int)$p_field_def['id'] => 1 )
	) ) );
}

/**
 * Build the keyboard of the possible values of a custom field.
 *
 * Buttons carry the index of the value within the possible values list, because
 * the callback data is limited to 64 bytes.
 *
 * @param array   $p_field_def          Custom field definition.
 * @param mixed   $p_custom_field_value Currently selected value(s).
 * @param boolean $p_multi              True when several values can be selected.
 * @param integer $p_page               Page of the possible values list.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function telegrambot_cfdef_keyboard_values( array $p_field_def, $p_custom_field_value, $p_multi, $p_page = 1 ) {
	$t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

	$t_field_id   = (int)$p_field_def['id'];
	$t_values     = explode( '|', custom_field_prepare_possible_values( $p_field_def['possible_values'] ) );
	$t_count      = count( $t_values );
	$t_page       = (int)$p_page < 1 ? 1 : (int)$p_page;
	$t_max_length = config_get( 'max_dropdown_length' );

	$t_selected = telegrambot_cfdef_value_to_array( $p_custom_field_value );
	$t_defaults = telegrambot_cfdef_value_to_array( $p_field_def['default_value'] );

	for( $i = ( $t_page * 10 ) - 10; $i < $t_page * 10 && $i < $t_count; $i++ ) {
		$t_text = string_shorten( $t_values[$i], $t_max_length );

		# Any value press only switches the selection, the choice is confirmed
		# by the done button. A radio style mark shows that only one of the
		# values of a single select field can be picked.
		if( $p_multi ) {
			$t_text = ( in_array( $t_values[$i], $t_selected, true ) ? '☑ ' : '☐ ' ) . $t_text;
		} else {
			$t_text = ( in_array( $t_values[$i], $t_selected, true ) ? '🔘 ' : '⚪ ' ) . $t_text;
		}
		$t_action = TelegrambotActions::TOGGLE_CUSTOM_FIELD;

		if( in_array( $t_values[$i], $t_defaults, true ) ) {
			$t_text .= ' (' . plugin_lang_get( 'custom_field_default_mark' ) . ')';
		}

		$t_inline_keyboard->addRow( array(
			'text'          => $t_text,
			'callback_data' => json_encode( array( TelegrambotActions::REPORT_BUG_TAG => array(
				$t_action => array( $t_field_id => $i )
			) ) )
		) );
	}

	telegrambot_cfdef_keyboard_pages_add( $t_inline_keyboard, $t_field_id, $t_page, $t_count );

	$t_inline_keyboard->addRow( array(
		'text'          => '(' . plugin_lang_get( 'custom_field_done_button' ) . ')',
		'callback_data' => json_encode( array( TelegrambotActions::REPORT_BUG_TAG => array(
			TelegrambotActions::END_CUSTOM_FIELD => array( $t_field_id => 1 )
		) ) )
	) );

	telegrambot_cfdef_keyboard_skip_add( $t_inline_keyboard, $p_field_def );

	return $t_inline_keyboard;
}

/**
 * Build the keyboard of a custom field the value of which is typed in by the user.
 *
 * @param array $p_field_def          Custom field definition.
 * @param mixed $p_custom_field_value Default value of the custom field.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 */
function telegrambot_cfdef_keyboard_text( array $p_field_def, $p_custom_field_value ) {
	$t_inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

	if( !is_blank( $p_custom_field_value ) ) {
		$t_inline_keyboard->addRow( array(
			'text'          => sprintf(
				plugin_lang_get( 'custom_field_default_button' ),
				string_shorten( $p_custom_field_value, config_get( 'max_dropdown_length' ) )
			),
			'callback_data' => json_encode( array( TelegrambotActions::REPORT_BUG_TAG => array(
				TelegrambotActions::SET_CUSTOM_FIELD => array( (int)$p_field_def['id'] => TelegrambotActions::CUSTOM_FIELD_DEFAULT_MARK )
			) ) )
		) );
	}

	telegrambot_cfdef_keyboard_skip_add( $t_inline_keyboard, $p_field_def );

	return $t_inline_keyboard;
}

/**
 * Keyboard of a list custom field.
 *
 * @param array $p_field_def          Custom field definition.
 * @param mixed $p_custom_field_value Custom field value.
 * @param string $p_required          (Unused) The "required" attribute to add to the field
 * @param integer $p_page             Page of the possible values list.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 *
 * @noinspection PhpUnusedParameterInspection
 */
function telegrambot_cfdef_input_list( array $p_field_def, $p_custom_field_value, $p_required = '', $p_page = 1 ) {
	return telegrambot_cfdef_keyboard_values(
		$p_field_def,
		$p_custom_field_value,
		$p_field_def['type'] == CUSTOM_FIELD_TYPE_MULTILIST,
		$p_page
	);
}

/**
 * Keyboard of a checkbox custom field.
 *
 * @param array $p_field_def          Custom field definition.
 * @param mixed $p_custom_field_value Custom field value.
 * @param string $p_required          (Unused) The "required" attribute to add to the field
 * @param integer $p_page             Page of the possible values list.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 *
 * @noinspection PhpUnusedParameterInspection
 */
function telegrambot_cfdef_input_checkbox( array $p_field_def, $p_custom_field_value, $p_required = '', $p_page = 1 ) {
	return telegrambot_cfdef_keyboard_values( $p_field_def, $p_custom_field_value, true, $p_page );
}

/**
 * Keyboard of a radio custom field.
 *
 * @param array $p_field_def          Custom field definition.
 * @param mixed $p_custom_field_value Custom field value.
 * @param string $p_required          (Unused) The "required" attribute to add to the field
 * @param integer $p_page             Page of the possible values list.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 *
 * @noinspection PhpUnusedParameterInspection
 */
function telegrambot_cfdef_input_radio( array $p_field_def, $p_custom_field_value, $p_required = '', $p_page = 1 ) {
	return telegrambot_cfdef_keyboard_values( $p_field_def, $p_custom_field_value, false, $p_page );
}

/**
 * Keyboard of a custom field the value of which is typed in by the user.
 *
 * @param array $p_field_def          Custom field definition.
 * @param mixed $p_custom_field_value Custom field value.
 * @param string $p_required          (Unused) The "required" attribute to add to the field
 * @param integer $p_page             (Unused) Page of the possible values list.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 *
 * @noinspection PhpUnusedParameterInspection
 */
function telegrambot_cfdef_input_textbox( array $p_field_def, $p_custom_field_value, $p_required = '', $p_page = 1 ) {
	return telegrambot_cfdef_keyboard_text( $p_field_def, $p_custom_field_value );
}

/**
 * Keyboard of a textarea custom field, the value of which is typed in by the user.
 *
 * @param array $p_field_def          Custom field definition.
 * @param mixed $p_custom_field_value Custom field value.
 * @param string $p_required          (Unused) The "required" attribute to add to the field
 * @param integer $p_page             (Unused) Page of the possible values list.
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 *
 * @noinspection PhpUnusedParameterInspection
 */
function telegrambot_cfdef_input_textarea( array $p_field_def, $p_custom_field_value, $p_required = '', $p_page = 1 ) {
	return telegrambot_cfdef_keyboard_text( $p_field_def, $p_custom_field_value );
}

/**
 * Keyboard of a date custom field: a calendar, the current date and the default
 * value of the field.
 *
 * @param array  $p_field_def          The custom field definition.
 * @param string $p_custom_field_value The custom field value ( timestamp ).
 * @param string $p_required           (Unused) The "required" attribute to add to the field
 * @param string $p_page               Month shown by the calendar, "Y-m".
 * @return Longman\TelegramBot\Entities\InlineKeyboard
 *
 * @noinspection PhpUnusedParameterInspection
 */
function telegrambot_cfdef_input_date( $p_field_def, $p_custom_field_value, $p_required = '', $p_page = 1 ) {
	if( !is_numeric( $p_custom_field_value ) ) {
		$p_custom_field_value = 0;
	}

	$t_field_id = (int)$p_field_def['id'];

	# A 'd' prefixed year requests the overview of the years, a bare year the
	# overview of its months, a year-month the grid of the month
	if( is_string( $p_page ) && preg_match( '/^d\d{4}$/', $p_page ) ) {
		$t_calendar = new TelegramBotInlineKeyboardCalendar( $p_page );
		$t_inline_keyboard = $t_calendar->getYearsKeyboard( 'cf', $t_field_id );
	} else if( is_string( $p_page ) && preg_match( '/^\d{4}$/', $p_page ) ) {
		$t_calendar = new TelegramBotInlineKeyboardCalendar( $p_page );
		$t_inline_keyboard = $t_calendar->getYearKeyboard( 'cf', $t_field_id );
	} else {
		if( is_string( $p_page ) && preg_match( '/^\d{4}-\d{1,2}$/', $p_page ) ) {
			$t_month = $p_page;
		} else {
			$t_month = date( 'Y-n', time() );
		}

		$t_calendar = new TelegramBotInlineKeyboardCalendar( $t_month );
		$t_inline_keyboard = $t_calendar->getKeyboard( 'cf', $t_field_id );
	}

	$t_inline_keyboard->addRow( array(
		'text'          => plugin_lang_get( 'custom_field_today_button' ),
		'callback_data' => json_encode( array( TelegrambotActions::REPORT_BUG_TAG => array(
			TelegrambotActions::SET_CUSTOM_FIELD => array( $t_field_id => date( 'Y-m-d', time() ) )
		) ) )
	) );

	if( $p_custom_field_value != 0 ) {
		$t_inline_keyboard->addRow( array(
			'text'          => date( 'Y-m-d', $p_custom_field_value ) . ' (' . plugin_lang_get( 'custom_field_default_mark' ) . ')',
			'callback_data' => json_encode( array( TelegrambotActions::REPORT_BUG_TAG => array(
				TelegrambotActions::SET_CUSTOM_FIELD => array( $t_field_id => date( 'Y-m-d', $p_custom_field_value ) )
			) ) )
		) );
	}

	telegrambot_cfdef_keyboard_skip_add( $t_inline_keyboard, $p_field_def );

	return $t_inline_keyboard;
}

/**
 * value to database
 * @param string $p_value Value.
 * @return string
 */
function telegrambot_cfdef_prepare_list_value_to_database( $p_value ) {
	if( '' == $p_value ) {
		return '';
	} else {
		return '|' . $p_value . '|';
	}
}

/**
 * Prepare possible values for option list
 * @param array $p_field_def Custom field definition.
 * @return array|boolean
 */
function telegrambot_cfdef_prepare_list_distinct_values( array $p_field_def ) {
	db_param_push();
	$t_query = 'SELECT possible_values FROM {custom_field} WHERE id=' . db_param();
	$t_result = db_query( $t_query, array( $p_field_def['id'] ) );

	$t_row = db_fetch_array( $t_result );
	if( !$t_row ) {
		return false;
	}

	$t_possible_values = custom_field_prepare_possible_values( $t_row['possible_values'] );
	$t_values_arr = explode( '|', $t_possible_values );
	$t_return_arr = array();

	foreach( $t_values_arr as $t_option ) {
		array_push( $t_return_arr, $t_option );
	}
	return $t_return_arr;
}