<?php

namespace WPE\ContentPerformance;

/**
 * Content Performance Upgrade
 */
class Upgrade {

	private $dbversion = 1;

	/**
	 * Constructor
	 *
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'upgrade' ), '1' );
	}

	/**
	 * Preform upgrades based on dbversion.
	 */
	public function upgrade() {
		$current_version = Core::get( 'dbversion' );

		// Check to see if we need to upgrade.
		if ( $current_version < $this->dbversion ) {
			// Add view_cperf to administrator user.
			if ( $current_version < 1 ) {
				$r = get_role( 'administrator' );
				$r->add_cap( Admin::capability );
			}

			Core::update( 'dbversion', $this->dbversion );
		}
	}
}

new Upgrade();
