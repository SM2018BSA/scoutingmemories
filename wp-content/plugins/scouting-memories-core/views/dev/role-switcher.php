<?php
/**
 * The floating "Test role" box of the test-site role switcher (src/Dev/RoleSwitcher.php).
 * A drop-down picks the kind of user. The box can be made small, never closed. Its styles and its few lines of script are here on
 * purpose: the box must look and work the same whatever theme is active, and it goes away
 * with this one file.
 *
 * @var array{roles: array<string,string>, chosen: string, real_names: string[], real_url: string, urls: array<string,string>} $args
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sm_dev_now = '' !== $args['chosen'] ? ( $args['roles'][ $args['chosen'] ] ?? $args['chosen'] ) : implode( ', ', $args['real_names'] );
?>
<style>
	#sm-dev-role { position: fixed; right: 12px; bottom: 12px; z-index: 99999; width: 230px; max-width: calc(100vw - 24px); font: 14px/1.4 system-ui, -apple-system, "Segoe UI", sans-serif; color: #212529; background: #fff8e1; border: 2px solid #b45309; border-radius: 8px; box-shadow: 0 4px 14px rgba(0, 0, 0, .3); }
	#sm-dev-role .sm-dev-head { display: flex; align-items: center; gap: 6px; padding: 6px 8px; background: #b45309; color: #fff; border-radius: 5px 5px 0 0; }
	#sm-dev-role .sm-dev-head b { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
	#sm-dev-role .sm-dev-min { min-width: 30px; min-height: 30px; padding: 0 6px; border: 1px solid #fff; border-radius: 4px; background: transparent; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
	#sm-dev-role .sm-dev-body { padding: 8px; max-height: 60vh; overflow-y: auto; }
	#sm-dev-role .sm-dev-body label { display: block; margin: 0 0 6px; }
	#sm-dev-role .sm-dev-body select { display: block; width: 100%; min-height: 36px; padding: 4px 8px; border: 1px solid #6c757d; border-radius: 4px; background: #fff; color: #212529; font: inherit; }
	#sm-dev-role .sm-dev-body select:focus-visible { outline: 2px solid #b45309; outline-offset: 1px; }
	#sm-dev-role.sm-dev-small .sm-dev-body { display: none; }
	#sm-dev-role.sm-dev-small .sm-dev-head { border-radius: 5px; }
	@media print { #sm-dev-role { display: none; } }
</style>
<aside id="sm-dev-role" aria-label="<?php esc_attr_e( 'Test role', 'scouting-memories-core' ); ?>">
	<div class="sm-dev-head">
		<b><?php echo esc_html( sprintf( /* translators: %s: the kind of user the page is shown as */ __( 'Test role: %s', 'scouting-memories-core' ), $sm_dev_now ) ); ?></b>
		<button type="button" class="sm-dev-min" aria-expanded="true" aria-controls="sm-dev-role-body" title="<?php esc_attr_e( 'Make this box small or big', 'scouting-memories-core' ); ?>">&minus;</button>
	</div>
	<div class="sm-dev-body" id="sm-dev-role-body">
		<label for="sm-dev-role-pick"><?php esc_html_e( 'Test site only. See the pages as:', 'scouting-memories-core' ); ?></label>
		<select id="sm-dev-role-pick">
			<option value="<?php echo esc_url( $args['real_url'] ); ?>"<?php selected( '', $args['chosen'] ); ?>>
				<?php echo esc_html( sprintf( /* translators: %s: the account's real roles */ __( 'My real roles (%s)', 'scouting-memories-core' ), implode( ', ', $args['real_names'] ) ) ); ?>
			</option>
			<?php foreach ( $args['roles'] as $sm_dev_key => $sm_dev_name ) : ?>
				<option value="<?php echo esc_url( $args['urls'][ $sm_dev_key ] ); ?>"<?php selected( $sm_dev_key, $args['chosen'] ); ?>><?php echo esc_html( $sm_dev_name ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
</aside>
<script>
	( function () {
		var box = document.getElementById( 'sm-dev-role' );
		var button = box.querySelector( '.sm-dev-min' );
		var KEY = 'smDevRoleSmall';
		function set( small ) {
			box.classList.toggle( 'sm-dev-small', small );
			button.setAttribute( 'aria-expanded', small ? 'false' : 'true' );
			button.textContent = small ? '+' : '−';
		}
		var saved = false;
		try { saved = window.localStorage.getItem( KEY ) === '1'; } catch ( e ) {}
		set( saved );
		// Picking a kind of user in the drop-down goes to that choice's switch address.
		document.getElementById( 'sm-dev-role-pick' ).addEventListener( 'change', function () {
			window.location.href = this.value;
		} );
		button.addEventListener( 'click', function () {
			var small = ! box.classList.contains( 'sm-dev-small' );
			set( small );
			try { window.localStorage.setItem( KEY, small ? '1' : '0' ); } catch ( e ) {}
		} );
	}() );
</script>
