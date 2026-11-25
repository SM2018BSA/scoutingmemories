<?php
/**
 * The floating "Test role" box of the test-site role switcher (src/Dev/RoleSwitcher.php).
 * A drop-down picks the kind of user. The box can be made small, never closed.
 *
 * Styled with Tailwind utility classes only and driven by Alpine attributes only (the theme's
 * stylesheet and script provide both): no style block, no script block. Small or big is
 * remembered in the browser (localStorage key smDevRoleSmall). The data-sm-dev attributes are
 * only there for the tests to find the parts.
 *
 * @var array{roles: array<string,string>, chosen: string, real_names: string[], real_url: string, urls: array<string,string>} $args
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sm_dev_now = '' !== $args['chosen'] ? ( $args['roles'][ $args['chosen'] ] ?? $args['chosen'] ) : implode( ', ', $args['real_names'] );
?>
<aside
	id="sm-dev-role"
	class="fixed right-3 bottom-3 z-[99999] w-[230px] max-w-[calc(100vw-1.5rem)] rounded-lg border-2 border-amber-700 bg-amber-50 font-sans text-sm leading-[1.4] text-ink shadow-[0_4px_14px_rgb(0_0_0/0.3)] print:hidden"
	aria-label="<?php esc_attr_e( 'Test role', 'scouting-memories-core' ); ?>"
	x-data="testRoleBox"
>
	<div class="flex items-center gap-1.5 rounded-t-[5px] bg-amber-700 px-2 py-1.5 text-white" :class="small &amp;&amp; 'rounded-b-[5px]'" data-sm-dev="head">
		<b class="min-w-0 flex-1 truncate font-semibold"><?php echo esc_html( sprintf( /* translators: %s: the kind of user the page is shown as */ __( 'Test role: %s', 'scouting-memories-core' ), $sm_dev_now ) ); ?></b>
		<button
			type="button"
			class="min-h-[30px] min-w-[30px] cursor-pointer rounded border border-white bg-transparent px-1.5 font-bold text-white"
			data-sm-dev="min"
			aria-expanded="true"
			aria-controls="sm-dev-role-body"
			title="<?php esc_attr_e( 'Make this box small or big', 'scouting-memories-core' ); ?>"
			:aria-expanded="small ? 'false' : 'true'"
			x-text="small ? '+' : '−'"
			x-on:click="toggle()"
		>&minus;</button>
	</div>
	<div class="max-h-[60vh] overflow-y-auto p-2" id="sm-dev-role-body" data-sm-dev="body" x-show="! small">
		<label class="mb-1.5 block" for="sm-dev-role-pick"><?php esc_html_e( 'Test site only. See the pages as:', 'scouting-memories-core' ); ?></label>
		<?php // Picking a kind of user in the drop-down goes to that choice's switch address. ?>
		<select
			id="sm-dev-role-pick"
			class="block min-h-9 w-full rounded border border-grey bg-white px-2 py-1 text-base text-ink focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-amber-700"
			x-on:change="window.location.href = $event.target.value"
		>
			<option value="<?php echo esc_url( $args['real_url'] ); ?>"<?php selected( '', $args['chosen'] ); ?>>
				<?php echo esc_html( sprintf( /* translators: %s: the account's real roles */ __( 'My real roles (%s)', 'scouting-memories-core' ), implode( ', ', $args['real_names'] ) ) ); ?>
			</option>
			<?php foreach ( $args['roles'] as $sm_dev_key => $sm_dev_name ) : ?>
				<option value="<?php echo esc_url( $args['urls'][ $sm_dev_key ] ); ?>"<?php selected( $sm_dev_key, $args['chosen'] ); ?>><?php echo esc_html( $sm_dev_name ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
</aside>
