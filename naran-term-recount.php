<?php
/**
 * Plugin Name:  Naran Term Recount
 * Description:  Fix count values of terms.
 * Author:       changwoo
 * Author URI:   https://blog.changwoo.pe.kr
 * Plugin URI:   https://github.com/chwnam/naran-term-recount
 * Version:      1.0.
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_admin() ) {
	return;
}

const NTR_VERSION = '1.0.0';


add_action( 'admin_enqueue_scripts', 'ntr_enqueue_scripts', 100 );
function ntr_enqueue_scripts( string $hook ) {
	if ( 'tools_page_ntr' === $hook ) {
		wp_enqueue_script(
			'ntr-script',
			plugins_url( 'script.js', __FILE__ ),
			[ 'jquery' ],
			NTR_VERSION
		);

		wp_enqueue_style(
			'ntr-style',
			plugins_url( 'style.css', __FILE__ ),
			[],
			NTR_VERSION
		);
	}
}


add_action( 'admin_menu', 'ntr_add_admin_menu' );
function ntr_add_admin_menu() {
	add_submenu_page(
		'tools.php',
		'Term Recount',
		'Term Recount',
		'administrator',
		'ntr',
		'ntr_output_admin_menu'
	);
}


function ntr_output_admin_menu() {
	?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Term Recount</h1>
        <hr class="wp-header-end">
        <form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post">
            <table class="wp-list-table striped fixed widefat ntr-table">
                <thead>
                <tr>
                    <td class="col-checkbox">&nbsp;</td>
                    <td class="col-taxonomy">Taxonomy</td>
                    <td class="col-description">Description.</td>
                    <td class="col-builtin">Builtin</td>
                </tr>
                </thead>
                <tbody>
				<?php foreach ( get_taxonomies( [], 'objects' ) as $taxonomy ) : ?>
                    <tr>
                        <td class="col-checkbox">
                            <input id="ntr-<?php echo esc_attr( $taxonomy->name ); ?>"
                                   name="ntr[<?php echo esc_attr( $taxonomy->name ); ?>]"
                                   class="ntr-checkbox"
                                   type="checkbox"
                                   value="yes">
                        </td>
                        <td class="col-taxonomy">
                            <label for="ntr-<?php echo esc_attr( $taxonomy->name ); ?>">
								<?php echo esc_html( $taxonomy->name ); ?>
                            </label>
                        </td>
                        <td class="col-description">
							<?php echo esc_html( $taxonomy->label ); ?>
                            <span class="description"><?php echo esc_html( $taxonomy->description ); ?></span>
                        </td>
                        <td class="col-builtin <?php echo $taxonomy->_builtin ? 'is-builtin' : ''; ?>">
							<?php echo $taxonomy->_builtin ? 'Yes' : 'No'; ?>
                        </td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
            </table>
            <input type="hidden" name="action" value="ntr_recount">
			<?php
			submit_button( 'Recount' );
			wp_nonce_field( 'ntr', '_ntr_nonce' )
			?>
        </form>
    </div>
	<?php
}

add_action( 'admin_post_ntr_recount', 'ntr_recount' );
function ntr_recount() {
	check_admin_referer( 'ntr', '_ntr_nonce' );

	if ( current_user_can( 'administrator' ) && isset( $_POST['ntr'] ) && is_array( $_POST['ntr'] ) ) {
		$taxonomies = array_filter(
			array_map( 'sanitize_key', array_keys( $_POST['ntr'] ) ),
			function ( $taxonomy ) {
				return taxonomy_exists( $taxonomy );
			}
		);

		if ( empty( $taxonomies ) ) {
			wp_die( 'No valid taxonomies given!', 'Error', [ 'back_link' => true ] );
		}

		global $wpdb;

		$padding = implode( ', ', array_pad( [], count( $taxonomies ), '%s' ) );

		$query = <<< PHP_EOL
UPDATE {$wpdb->term_taxonomy} AS tt
INNER JOIN (
    SELECT
       tt.term_taxonomy_id AS term_taxonomy_id,
       COUNT(tr.object_id) AS real_count
    FROM {$wpdb->term_taxonomy} AS tt
        INNER JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
        LEFT JOIN {$wpdb->term_relationships} AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
        LEFT JOIN {$wpdb->posts} AS p ON p.ID = tr.object_id
    WHERE tt.taxonomy IN ({$padding})
    GROUP BY t.term_id
) AS inspected
ON inspected.term_taxonomy_id = tt.term_taxonomy_id
SET tt.count = inspected.real_count
WHERE tt.count != inspected.real_count
PHP_EOL;

		$query = $wpdb->prepare( $query, $taxonomies );
		$wpdb->query( $query );
		$affected = $wpdb->rows_affected;

		wp_die(
			sprintf( '%d row(s) affected.', $affected ),
			'Success',
			[
				'link_url'  => wp_get_referer(),
				'link_text' => 'Back to admin'
			]
		);
	} else {
		wp_die( 'No valid taxonomies given!', 'Error', [ 'back_link' => true ] );
	}
}