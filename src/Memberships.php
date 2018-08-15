<?php
/**
 * WooCommerce Dev Helper
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @author    SkyVerge
 * @copyright Copyright (c) 2015-2018, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\Dev_Helper;

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_2_0 as Framework;

/**
 * Memberships helper.
 *
 * This provides some helpers for development work on WooCommerce Memberships.
 *
 * @since 1.0.0
 */
class Memberships {


	/**
	 * Memberships helper constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->add_reduced_plan_access_period_options();

		// admin-specific hooks
		if ( is_admin() ) {

			$this->add_bulk_generation_screen();
		}
	}


	/**
	 * Adds options to support minute and hour-long access periods in membership plans.
	 *
	 * @since 1.0.0
	 */
	private function add_reduced_plan_access_period_options() {

		// adds support for minutes and hours-long membership plans
		add_filter( 'wc_memberships_plan_access_period_options', function( $periods ) {

			$new_periods = array(
				'minutes' => __( 'minute(s)', 'woocommerce-dev-helper' ),
				'hours'   => __( 'hour(s)', 'woocommerce-dev-helper' ),
			);

			return array_merge( $new_periods, $periods );

		}, 1 );

		// filters the human access length information so it can work with minutes and hours
		add_filter( 'wc_memberships_membership_plan_human_access_length', function( $human_length, $standard_length ) {

			$has_minutes = strpos( $standard_length, 'minute' ) !== false;
			$has_hours   = strpos( $standard_length, 'hour' )   !== false;

			if ( $has_minutes || $has_hours ) {
				$human_length = $standard_length;
			}

			return $human_length;

		}, 1, 2 );
	}


	/**
	 * Provides an admin screen for the bulk generation tool.
	 *
	 * @see \SkyVerge\WooCommerce\Dev_Helper\Tools\Memberships\Bulk_Generator
	 * @see \SkyVerge\WooCommerce\Dev_Helper\Tools\Memberships\Bulk_Destroyer
	 *
	 * @since 1.0.0
	 */
	private function add_bulk_generation_screen() {

		// adds the bulk generation tool to the memberships screen IDs
		add_filter( 'wc_memberships_admin_screen_ids', function( $screens ) {

			if ( isset( $screens['tabs'] ) ) {
				$screens['tabs'][] = 'admin_page_wc_memberships_bulk_generation';
			}

			return $screens;

		} );

		// adds a tool tab to the Memberships admin screen
		add_filter( 'wc_memberships_admin_tabs', function( $tabs ) {

			if ( current_user_can( 'manage_options' ) ) {

				$tabs['bulk-generation'] = array(
					'title' => __( 'Bulk Generation', 'woocommerce-dev-helper' ),
					'url'   => admin_url( 'admin.php?page=wc_memberships_bulk_generation' ),
				);
			}

			return $tabs;

		}, 100, 1 );

		// sets the bulk generation tab as the current one when on the right screen
		add_filter( 'wc_memberships_admin_current_tab', function( $current_tab ) {

			$screen = get_current_screen();

			return $screen && 'admin_page_wc_memberships_bulk_generation' === $screen->id ? 'bulk-generation' : $current_tab;

		}, 100 );

		// sets the WordPress admin title when on the bulk generation screen
		add_filter( 'admin_title', function( $title ) {

			return isset( $_GET['page'] ) && 'wc_memberships_bulk_generation' === $_GET['page'] ? __( 'Memberships Bulk Generation', 'woocommerce-dev-helper' ) . ' ' . $title : $title;

		} );

		// keeps the WooCommerce WordPress menu open and the Memberships menu item highlighted when on the bulk generation screen
		add_filter( 'parent_file', function( $parent_file ) {
			global $menu, $submenu_file;

			if ( isset( $_GET['page'] ) && 'wc_memberships_bulk_generation' === $_GET['page'] ) {

				$submenu_file = 'edit.php?post_type=wc_user_membership';

				if ( ! empty( $menu ) ) {

					foreach ( $menu as $key => $value ) {

						if ( isset( $value[2], $menu[ $key ][4] ) && 'woocommerce' === $value[2] ) {
							$menu[ $key ][4] .= ' wp-has-current-submenu wp-menu-open';
						}
					}
				}
			}

			return $parent_file;

		}, 100 );

		// adds the tool tab content
		add_action( 'admin_menu', function() {

			add_submenu_page(
				'',
				__( 'Bulk Generation', 'woocommerce-dev-helper' ),
				__( 'Bulk Generation', 'woocommerce-dev-helper' ),
				'manage_options',
				'wc_memberships_bulk_generation',
				function () {

					$current_action = isset( $_GET['action'] ) && 'destroy' === $_GET['action'] ? 'destroy' : 'generate';

					?>
					<div class="wrap woocommerce woocommerce-memberships woocommerce-memberships-bulk-generation">

						<ul class="subsubsub">
							<li><a href="<?php echo admin_url( 'admin.php?page=wc_memberships_bulk_generation' ) ?>" <?php if ( 'generate' === $current_action ) { echo 'class="current"'; } ?>><?php esc_html_e( 'Generate Memberships', 'woocommerce-dev-helper' ); ?></a></li> |
							<li><a href="<?php echo admin_url( 'admin.php?page=wc_memberships_bulk_generation&action=destroy' ); ?>" <?php if ( 'destroy' === $current_action ) { echo 'class="current"'; } ?>><?php esc_html_e( 'Destroy Memberships', 'woocommerce-dev-helper' ) ?></a></li>
						</ul>

						<br class="clear" />

						<?php if ( 'generate' === $current_action ) : ?>

							<h2><?php esc_html_e( 'Generate User Memberships in Bulk', 'woocommerce-dev-helper' )?></h2>

							<p><?php esc_html_e( 'Create up to thousands of users, posts, products, categories, membership plans and user memberships in bulk.', 'woocommerce-dev-helper' ) ?></p>
							<p><?php printf( __( 'This tool is intended for testing, development and demonstration purposes only. Memberships objects thus created can be later removed %1$susing the counterpart tool%2$s.', 'woocommerce-dev-helper' ), '<a href="' . admin_url( 'page=wc_memberships_bulk_generation&action=destroy' ) . '">', '</a>' ); ?></p>
							<p><strong><?php esc_html_e( 'It is not advised to use this tool on a production site.', 'woocommerce-dev-helper' ); ?></strong></p>

							<table id="wc-memberships-bulk-generate" class="form-table">
								<tbody>
									<tr valign="top">
										<th scope="row" class="titledesc">
											<label for="memberships-to-generate"><?php esc_html_e( 'Number of Memberships', 'woocommerce-dev-helper' ); ?></label>
										</th>
										<td class="forminp">
											<input
												type="number"
												id="memberships-to-generate"
												min="1"
												max="1000000"
												step="1"
												value="1000"
											/> <span class="description"><?php esc_html_e( 'Enter the total number of memberships to generate in bulk.', 'woocommerce-dev-helper' ); ?></span>
										</td>
									</tr>
									<tr>
										<th scope="row"></th>
										<td class="forminp">
											<span class="description"><?php esc_html_e( 'Once launched, the operation cannot be stopped.', 'woocommerce-dev-helper' ) ?></span>
											<br /><br />
											<button
												id="generate-memberships"
												class="button button-primary"><?php
												esc_html_e( 'Generate', 'woocommerce-dev-helper' ); ?></button>
										</td>
									</tr>
								</tbody>
							</table>

						<?php else : ?>

							<h2><?php esc_html_e( 'Destroy Memberships Created in Bulk', 'woocommerce-dev-helper' )?></h2>

							<p><?php esc_html_e( 'This tool will permanently remove user memberships, membership plans, posts, products and users previously created with the bulk generation tool.', 'woocommerce-dev-helper' ) ?></p>
							<p><?php esc_html_e( 'Membership objects, users and other WordPress content created otherwise will not be affected.', 'woocommerce-dev-helper' ); ?></p>
							<p><strong><?php esc_html_e( 'It is not advised to use this tool on a production site.', 'woocommerce-dev-helper' ); ?></strong></p>

							<table id="wc-memberships-bulk-destroy" class="form-table">
								<tbody>
									<tr>
										<td class="forminp" colspan="2">
											<span class="description"><?php esc_html_e( 'Once launched, the operation cannot be stopped.', 'woocommerce-dev-helper' ) ?></span>
											<br /><br />
											<button
												id="destroy-memberships"
												class="button button-primary"><?php
												esc_html_e( 'Destroy', 'woocommerce-dev-helper' ); ?></button>
										</td>
									</tr>
								</tbody>
							</table>

						<?php endif; ?>

					</div>
					<?php
				}
			);

		}, 100 );
	}


	/**
	 * Determines if the current screen belongs to the bulk generation tool.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action optional, check for a specific sub-screen of the bulk generation too.
	 * @return bool
	 */
	public function is_bulk_generation_screen( $action = '' ) {

		$is_screen = false;

		if ( is_admin() ) {

			$is_screen = isset( $_GET['page'] ) && 'wc_memberships_bulk_generation' === $_GET['page'];

			if ( $is_screen && '' !== $action ) {
				$is_screen = ( ! isset( $_GET['action'] ) && 'generate' === $action ) || ( isset( $_GET['action'] ) && $action === $_GET['action'] );
			}
		}

		return $is_screen;
	}


}
