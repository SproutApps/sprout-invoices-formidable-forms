<?php

class SI_Formidable extends SI_Formidable_Controller {
	const FORMIDABLE_FORM_ID = 'si_formidable_invoice_submissions_id';
	const GENERATION = 'si_formidable_record_generation';
	// Integration options
	protected static $formidable_form_id;
	protected static $generation;

	public static function init() {
		// Store options
		self::$formidable_form_id = get_option( self::FORMIDABLE_FORM_ID, 0 );
		self::$generation = get_option( self::GENERATION, 'estimate' );

		add_filter( 'si_add_options', array( __CLASS__, 'remove_integration_addon_option' ) );

		// filter options
		self::register_settings();

		if ( self::$formidable_form_id ) {
			// Create invoice before confirmation
			add_action( 'frm_after_create_entry', array( __CLASS__, 'maybe_process_formidable_form' ), 10, 2 );

			// Add pre-defined items
			add_filter( 'frm_available_fields', array( __CLASS__, 'add_basic_field' ) );
			add_filter( 'frm_form_fields', array( __CLASS__, 'show_my_front_field' ), 10, 2 );
		}
	}

	///////////////
	// Settings //
	///////////////

	public static function remove_integration_addon_option( $options = array() ) {
		// remove the integration addon ad
		unset( $options['settings']['estimate_submissions'] );
		return $options;
	}

	public static function register_settings() {

		$frdbl_options = array( 0 => __( 'No forms found', 'sprout-invoices' ) );
		$forms = FrmForm::get_published_forms();
		if ( ! empty( $forms ) ) {
			$frdbl_options = array();
			foreach ( $forms as $form ) {
				$frdbl_options[ $form->id ] = ( ! isset( $form->name ) ) ? __( '(no title)', 'formidable' ) : esc_attr( FrmAppHelper::truncate( $form->name, 33 ) );
			}
		}

		$settings = array(
			self::FORMIDABLE_FORM_ID => array(
				'label' => __( 'Formidable Form', 'sprout-invoices' ),
				'option' => array(
					'type' => 'select',
					'options' => $frdbl_options,
					'default' => self::$formidable_form_id,
					'description' => sprintf( __( 'Select the submission form built with <a href="%s">Formidables</a>.', 'sprout-invoices' ), 'https://sproutapps.co/link/formidable-forms' ),
				),
			),
			self::GENERATION => array(
				'label' => __( 'Submission Records', 'sprout-invoices' ),
				'option' => array(
					'type' => 'select',
					'options' => array( 'estimate' => __( 'Estimate', 'sprout-invoices' ), 'invoice' => __( 'Invoice', 'sprout-invoices' ), 'client' => __( 'Client Only', 'sprout-invoices' ) ),
					'default' => self::$generation,
					'description' => __( 'Select the type of records you would like to be created. Note: estimates and invoices create client records.', 'sprout-invoices' ),
				),
			),
			self::FORM_ID_MAPPING => array(
				'label' => __( 'Formidable ID Mapping', 'sprout-invoices' ),
				'option' => array( __CLASS__, 'show_formidable_form_field_mapping' ),
				'sanitize_callback' => array( __CLASS__, 'save_formidable_form_field_mapping' ),
			),
		);

		$all_settings = array(
			'form_submissions' => array(
				'title' => __( 'Formidable Submissions', 'sprout-invoices' ),
				'weight' => 6,
				'tab' => 'settings',
				'settings' => $settings,
			),
		);

		do_action( 'sprout_settings', $all_settings );
	}

	public static function show_formidable_form_field_mapping() {
		return self::show_form_field_mapping( self::mapping_options() );
	}

	public static function save_formidable_form_field_mapping( $mappings = array() ) {
		return self::save_form_field_mapping( self::mapping_options() );
	}

	public static function mapping_options() {
		$options = array(
				'subject' => __( 'Subject/Title', 'sprout-invoices' ),
				'line_item_list' => __( 'Pre-defined Item Selection (Checkboxes Field)', 'sprout-invoices' ),
				'email' => __( 'Email', 'sprout-invoices' ),
				'client_name' => __( 'Client/Company Name', 'sprout-invoices' ),
				'first_name' => __( 'First Name', 'sprout-invoices' ),
				'last_name' => __( 'Last Name', 'sprout-invoices' ),
				'contact_street' => __( 'Street Address', 'sprout-invoices' ),
				'contact_city' => __( 'City', 'sprout-invoices' ),
				'contact_zone' => __( 'State/Province', 'sprout-invoices' ),
				'contact_postal_code' => __( 'Zip/Postal', 'sprout-invoices' ),
				'contact_country' => __( 'Country', 'sprout-invoices' ),
			);
		return $options;
	}

	//////////////////////////////
	// Populate Front-end Form //
	//////////////////////////////

	public static function add_basic_field( $fields ) {
		$fields['si_line_items'] = __( 'SI Line Items', 'sprout-invoices' ); // the key for the field and the label
		return $fields;
	}

	public static function show_my_front_field( $field, $field_name ) {
		if ( 'si_line_items' !== $field['type'] ) {
			return;
		}

		$field_id = $field['id'];
		$field['value'] = stripslashes_deep( $field['value'] );

		$items_and_products = Predefined_Items::get_items_and_products();
		$item_groups = apply_filters( 'si_predefined_items_for_submission', $items_and_products );
		$list_options_span_class = apply_filters( 'formidable_display_list_options_span_class', 'si_line_items', $field_id );

		$x = 0;
		?>
			<div id="frm_field_<?php echo esc_attr( $field_id ) ?>_container" class="frm_form_field form-field  frm_top_container">
				<div class="frm_opt_container">
					<div class="frm_checkbox" id="frm_checkbox_<?php echo esc_attr( $field_id ) ?>-0">
						<?php foreach ( $item_groups as $type => $items ) : ?>
							<?php foreach ( $items as $item ) : ?>
								<?php
									$value = $item['id'];
									$label = sprintf( '&nbsp;&nbsp;<b>%s</b><br/><small>%s</small>', $item['title'], $item['content'] );
									printf( '<label id="field_%1$s_%2$s_label"><input id="ninja_forms_field_%1$s_%2$s" name="item_meta[%1$s][]" type="checkbox" class="%5$s field_%1$s" value="%3$s""/>%4$s</label>', $field_id, $x, $value, $label, $list_options_span_class );
									$x++;
										?>
							<?php endforeach ?>
						<?php endforeach ?>
						
					</div>
				</div>
			</div>
		<?php
	}

	////////////////////
	// Process forms //
	////////////////////

	public static function maybe_process_formidable_form( $entry_id, $form_id ) {
		/**
		 * Only a specific form do this process
		 */
		if ( (int) $form_id !== (int) self::$formidable_form_id ) {
			return;
		}
		/**
		 * Set variables
		 * @var string
		 */
		$subject = isset( $_POST['item_meta'][ self::get_form_map_id( 'subject' ) ] ) ? $_POST['item_meta'][ self::get_form_map_id( 'subject' ) ] : '';
		$email = isset( $_POST['item_meta'][ self::get_form_map_id( 'email' ) ] ) ? $_POST['item_meta'][ self::get_form_map_id( 'email' ) ] : '';
		$client_name = isset( $_POST['item_meta'][ self::get_form_map_id( 'client_name' ) ] ) ? $_POST['item_meta'][ self::get_form_map_id( 'client_name' ) ] : '';
		$full_name = isset( $_POST['item_meta'][ self::get_form_map_id( 'first_name' ) ] ) ? $_POST['item_meta'][ self::get_form_map_id( 'first_name' ) ] . ' ' . $_POST['item_meta'][ self::get_form_map_id( 'last_name' ) ] : '';
		$website = isset( $_POST['item_meta'][ self::get_form_map_id( 'website' ) ] ) ? $_POST['item_meta'][ self::get_form_map_id( 'website' ) ] : '';
		$contact_street = isset( $_POST['item_meta'][ self::get_form_map_id( 'contact_street' ) ] ) ? $_POST['item_meta'][ self::get_form_map_id( 'contact_street' ) ] : '';
		$contact_city = isset( $_POST['item_meta'][ self::get_form_map_id( 'contact_city' ) ] ) ? $_POST['item_meta'][ self::get_form_map_id( 'contact_city' ) ] : '';
		$contact_zone = isset( $_POST['item_meta'][ self::get_form_map_id( 'contact_zone' ) ] ) ? $_POST['item_meta'][ self::get_form_map_id( 'contact_zone' ) ] : '';
		$contact_postal_code = isset( $_POST['item_meta'][ self::get_form_map_id( 'contact_postal_code' ) ] ) ? $_POST['item_meta'][ self::get_form_map_id( 'contact_postal_code' ) ] : '';
		$contact_country = isset( $_POST['item_meta'][ self::get_form_map_id( 'contact_country' ) ] ) ? $_POST['item_meta'][ self::get_form_map_id( 'contact_country' ) ] : '';

		/**
		 * Build line item array
		 * @var array
		 */
		$line_item_list = array();
		if ( ! empty( $_POST['item_meta'][ self::get_form_map_id( 'line_item_list' ) ] ) ) {
			$line_item_list = $_POST['item_meta'][ self::get_form_map_id( 'line_item_list' ) ];
			if ( ! is_array( $line_item_list ) ) {
				$line_item_list = array( $line_item_list );
			}
		}

		$doc_id = 0;

		if ( 'invoice' === self::$generation ) {
			/**
			 * Create invoice
			 * @var array
			 */
			$invoice_args = array(
				'status' => SI_Invoice::STATUS_PENDING,
				'subject' => $subject,
				'line_item_list' => $line_item_list,
				'fields' => $_POST['item_meta'],
				'form' => $_POST['item_meta'],
				'history_link' => sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'post' => $entry_id ), admin_url( 'post.php?action=edit' ) ), $entry_id ),
			);
			$invoice = self::maybe_create_invoice( $invoice_args, $entry_id );
			$doc_id = $invoice->get_id();
		}

		if ( 'estimate' === self::$generation ) {
			/**
			 * Create estimate
			 * @var array
			 */
			$estimate_args = array(
				'status' => SI_Estimate::STATUS_PENDING,
				'subject' => $subject,
				'line_item_list' => $line_item_list,
				'fields' => $_POST['item_meta'],
				'form' => $_POST['item_meta'],
				'history_link' => sprintf( '<a href="%s">#%s</a>', add_query_arg( array( 'post' => $entry_id ), admin_url( 'post.php?action=edit' ) ), $entry_id ),
			);
			$estimate = self::maybe_create_estimate( $estimate_args, $entry_id );
			$doc_id = $estimate->get_id();
		}

		/**
		 * Make sure an invoice was created, if so create a client
		 */
		$client_args = array(
			'email' => $email,
			'client_name' => $client_name,
			'full_name' => $full_name,
			'website' => $website,
			'contact_street' => $contact_street,
			'contact_city' => $contact_city,
			'contact_zone' => $contact_zone,
			'contact_postal_code' => $contact_postal_code,
			'contact_country' => $contact_country,
		);

		if ( 'estimate' === self::$generation ) {
			$client_args = apply_filters( 'si_estimate_submission_maybe_process_formidable_client_args', $client_args, $_POST['item_meta'], $entry_id, $form_id );
			$doc = $estimate;
		} elseif ( 'invoice' === self::$generation ) {
			$client_args = apply_filters( 'si_invoice_submission_maybe_process_formidable_client_args', $client_args, $_POST['item_meta'], $entry_id, $form_id );
			$doc = $invoice;
		}

		self::maybe_create_client( $doc_id, $client_args );

		do_action( 'si_formidable_submission_complete', $doc_id );

		self::maybe_redirect_after_submission( $doc_id );
	}

	public static function maybe_redirect_after_submission( $doc_id ) {
		if ( apply_filters( 'si_invoice_submission_redirect_to_invoice', true ) ) {
			if ( get_post_type( $doc_id ) == ( SI_Invoice::POST_TYPE || SI_Estimate::POST_TYPE ) ) {
				$url = get_permalink( $doc_id );
				wp_redirect( $url );
				die();
			}
		}
	}
}
SI_Formidable::init();
