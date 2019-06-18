<?php

class Mai_Assessments {

	protected $forms;

	function __construct() {
		$this->forms = class_exists( 'wpforms' ) ? wpforms()->form->get() : false;
		$this->admin();
		$this->hooks();
	}

	function admin() {
		$this->options_page();
		$this->fields();
	}

	function options_page() {
		if ( function_exists( 'acf_add_options_page' ) ) {
			acf_add_options_sub_page( array(
				'title'      => 'Assessment Results',
				'parent'     => 'wpforms-overview',
				'menu_slug'  => 'assessment-results',
				'capability' => 'manage_options',
			));
		}
	}

	function fields() {
		add_filter( 'acf/settings/load_json',                     array( $this, 'load_json' ) );
		add_filter( 'acf/load_field/key=field_5d030114b992e',     array( $this, 'load_field' ) );
		add_filter( 'acf/validate_value/key=field_5d030114b992e', array( $this, 'validate_value' ), 10, 4 );
		add_action( 'acf/save_post',                              array( $this, 'update_option' ) );
	}

	function hooks() {
		add_action( 'admin_footer',                 array( $this, 'ajax' ) );
		add_action( 'init',                         array( $this, 'wpforms_process' ) );
		add_shortcode( 'mai_assessment_no_results', array( $this, 'assessment_no_results' ) );
		add_shortcode( 'mai_assessment_results',    array( $this, 'assessment_results' ) );
	}

	/**
	 * Add path to load acf json files.
	 *
	 * @param    array  The existing acf-json paths.
	 *
	 * @return   array  The modified paths.
	 */
	function load_json( $paths ) {
		$paths[] = untrailingslashit( MAI_ASSESSMENTS_PLUGIN_DIR ) . '/acf-json';
		return $paths;
	}

	function load_field( $field ) {

		// Placeholder text.
		$text = $this->forms ? __( '(Disabled) Choose a form...', 'mai-assessments' ) : __( 'No forms available', 'mai-assessments' );

		// Default empty choices.
		$field['choices'] = array(
			'' => $text,
		);

		// Bail if no forms.
		if ( ! $this->forms ) {
			return $field;
		}

		// Loop through em.
		foreach( $this->forms as $form ) {
			// Set as a choice.
			$field['choices'][ $form->ID ] = $form->post_title;
		}

		return $field;
	}

	function validate_value( $valid, $value, $field, $input ) {

		// Bail if already invalid.
		if( ! $valid ) {
			return $valid;
		}

		// Cache the forms.
		static $forms = array();

		// If we have a form that is already used.
		if ( $value && in_array( $value, $forms ) ) {
			$valid = __( 'Please choose another form, this form is already used in another assessment.', 'mai-assessments' );
		}
		// We have a form, but it's not used yet.
		elseif ( $value ) {
			$forms[] = $value;
		}

		return $valid;
	}

	function update_option( $post_id ) {

		// Bail if no data.
		if ( ! isset( $_POST['acf'] ) || empty( $_POST['acf'] ) ) {
			return;
		}

		// Bail if not saving an options page.
		if ( 'options' !== $post_id ) {
			return;
		}

		// Current screen.
		$screen = get_current_screen();

		// Bail if not our options page.
		if ( false === strpos ( $screen->id, 'wpforms_page_assessment-results' ) ) {
			return;
		}

		// Get assessments.
		$assessments = get_field( 'mai_assessments', 'option' );

		$data = array();

		// If no assessments.
		if ( $assessments ) {

			// Loop through assessments.
			foreach ( $assessments as $assessment ) {

				// Get data.
				$row = ( isset( $assessment['assessment'] ) && ! empty( $assessment['assessment'] ) ) ? $assessment['assessment'] : false;

				// Skip if no data.
				if ( ! $row ) {
					continue;
				}

				// Skip if no form.
				if ( ! isset( $row['form'] ) || empty( $row['form'] ) ) {
					continue;
				}

				$form_id = absint( $row['form'] );
				unset( $row['form'] );
				unset( $row[''] ); // Tab is an empty row.
				$data[ $form_id ] = $row;
			}
		}

		// Save the results data.
		update_option( 'assessment_results', $data );
	}

	function wpforms_process() {

		// Get form IDs.
		$form_ids = $this->get_form_ids();

		// Bail if no forms.
		if ( ! $form_ids ) {
			return;
		}

		// Loop through em.
		foreach ( $form_ids as $form_id ) {
			add_action( "wpforms_process_complete_{$form_id}", array( $this, 'process_complete' ), 10, 4 );
		}
	}

	function process_complete( $fields, $entry, $form_data, $entry_id ) {
		$form_id  = $form_data['id'];
		$meta_key = sprintf( 'mai_assessment_%s', absint( $form_id ) );
		$this->update_user_score( $meta_key, $fields, $entry, $form_data );
	}

	function update_user_score( $meta_key, $fields, $entry, $form_data ) {

		// Bail if user not logged in.
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Get current user ID.
		$user_id = get_current_user_id();

		// Bail if no user.
		if ( ! $user_id ) {
			return;
		}

		// Bail if no fields.
		if ( ! $fields ) {
			return;
		}

		// Bail if no fields.
		if ( ! isset( $form_data['fields'] ) || empty( $form_data['fields'] ) ) {
			return;
		}

		$likert = false;
		$data   = array();
		$max = $total = 0;

		// Loop through the form data fields.
		foreach ( $form_data['fields'] as $field_id => $values ) {

			// Skip if not a likert field.
			if ( 'likert_scale' !== $values['type'] ) {
				continue;
			}

			// We have a likert field.
			$likert = true;

			// Add field ID to data.
			$data[] = $field_id;

			// Save max values (amount of rows/questions times the highest/last value).
			$max += ( count( $values['rows'] ) * (int) $this->array_key_last( $values['columns'] ) );
		}

		// Bail if no likert fields.
		if ( ! $likert ) {
			return;
		}

		// Bail if no data.
		if ( ! $data ) {
			return;
		}

		// Loop through our new data.
		foreach ( $data as $field_id ) {

			// Skip if no field ID scored.
			if ( ! isset( $entry['fields'][ $field_id ] ) ) {
				continue;
			}

			// Loop through liker fields.
			foreach ( $entry['fields'][ $field_id ] as $score ) {

				// Add to our total.
				$total += (int) $score;
			}
		}

		/**
		 * Get total score.
		 * Total score divided by total possible score,
		 * then times by 100 to get percentage out of 100.
		 * Rounded up.
		 */
		$score = ceil( ( $total / $max ) * 100 );

		// Update user meta by key.
		update_user_meta( $user_id, $meta_key, $score );
	}

	function assessment_no_results( $atts, $content ) {

		// TODO: ALL OF THIS.
		return;

		// Shortcode attributes.
		$atts = shortcode_atts( array(
			'ids' => '', // Comma separated IDs.
		), $atts, 'mai_assessment_no_results' );

		// Sanitize attributes.
		$atts = array(
			'ids' => $atts['ids'] ? array_map( 'trim', explode( ',', $atts['ids'] ) ) : '',
		);

		// $form_ids = $this->get_form_ids();
	}

	function assessment_results( $atts ) {

		// Bail if not logged in.
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Shortcode attributes.
		$atts = shortcode_atts( array(
			'ids' => '', // Comma separated form IDs.
		), $atts, 'mai_assessment_results' );

		// Sanitize attributes.
		$atts = array(
			'ids' => $atts['ids'] ? array_map( 'trim', explode( ',', $atts['ids'] ) ) : '',
		);

		// Get data.
		$results = get_option( 'assessment_results' );

		// Bail if no results.
		if ( ! $results ) {
			return;
		}

		// Get current user ID.
		$user_id = get_current_user_id();

		// Loop through results.
		foreach ( $results as $form_id => $result ) {

			// Skip if no result.
			if ( ! $result ) {
				continue;
			}

			// Skip if showing results for specific assessments, and this is not one of them.
			if ( $atts['ids'] && ! in_array( $form_id, $atts['ids'] ) ) {
				continue;
			}

			// Build the key.
			$meta_key = sprintf( 'mai_assessment_%s', absint( $form_id ) );

			// Get users score.
			$score = get_user_meta( $user_id, $meta_key, true );

			// Bail if no score.
			if ( ! $score ) {
				continue;
			}

			// Get the name.
			$name       = ( isset( $result['name'] ) && ! empty( $result['name'] ) ) ? esc_html( $result['name'] ) : '';
			$name_html  = $name ? sprintf( '<span class="maia-name">%s</span>', $name ) : '';
			$score_html = sprintf( '<span class="maia-score">%s</span>', $score );


			// Low.
			if ( isset( $result['low_max'] ) && ( $score <= $result['low_max'] ) ) {
				if ( isset( $result['low_content'] ) && ! empty( $result['low_content'] ) ) {
					$content = wp_kses_post( $result['low_content'] );
					$content = mai_get_processed_content( $content );
					$content = str_replace( '{name}', $name_html, $content );
					$content = str_replace( '{score}', $score_html, $content );
					return sprintf( '<div class="maia-results low-score">%s</div>', $content );
				}
			}

			// Medium.
			if ( isset( $result['medium_max'] ) && ( $score <= $result['medium_max'] ) ) {
				if ( isset( $result['medium_content'] ) && ! empty( $result['medium_content'] ) ) {
					$content = wp_kses_post( $result['medium_content'] );
					$content = mai_get_processed_content( $content );
					$content = str_replace( '{name}', $name_html, $content );
					$content = str_replace( '{score}', $score_html, $content );
					return sprintf( '<div class="maia-results medium-score">%s</div>', $content );
				}
			}

			// High.
			if ( isset( $result['high_max'] ) && ( $score <= $result['high_max'] ) ) {
				if ( isset( $result['high_content'] ) && ! empty( $result['high_content'] ) ) {
					$content = wp_kses_post( $result['high_content'] );
					$content = mai_get_processed_content( $content );
					$content = str_replace( '{name}', $name_html, $content );
					$content = str_replace( '{score}', $score_html, $content );
					return sprintf( '<div class="maia-results high-score">%s</div>', $content );
				}
			}
		}
	}

	function get_form_ids() {
		$results = get_option( 'assessment_results' );
		// Bail if no results.
		if ( ! $results ) {
			return false;
		}
		// Return the IDs only.
		return array_keys( $results );
	}

	function array_key_first( $array ) {
		if ( ! function_exists( 'array_key_first' ) ) {
			function array_key_first( array $array ) {
				foreach( $array as $key => $unused ) {
					return $key;
				}
				return null;
			}
		} else {
			return array_key_first( $array );
		}
	}

	function array_key_last( $array ) {
		if ( ! function_exists( 'array_key_last' ) ) {
			function array_key_last( array $array ) {
				if ( ! is_array( $array ) || empty( $array ) ) {
					return null;
				}
				return array_keys( $array )[ count( $array ) - 1 ];
			}
		} else {
			return array_key_last( $array );
		}
	}

}

// Get it started.
add_action( 'after_setup_theme', function() {
	new Mai_Assessments;
});
