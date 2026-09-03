<?php
/**
 * Admin-only helper for creating/resetting a dedicated Joiner test account.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the helper beneath Users so it is available only to administrators.
 */
function qs_test_joiner_admin_menu() {
	add_users_page(
		__( 'Test Joiner', 'quote-system' ),
		__( 'Test Joiner', 'quote-system' ),
		'manage_options',
		'qs-test-joiner',
		'qs_render_test_joiner_admin_page'
	);
}
add_action( 'admin_menu', 'qs_test_joiner_admin_menu' );

/**
 * Create or reset the dedicated test Joiner account.
 *
 * Passwords are generated at request time and are never committed to the repo.
 */
function qs_handle_test_joiner_admin_action() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( empty( $_POST['qs_test_joiner_action'] ) ) {
		return;
	}

	check_admin_referer( 'qs_test_joiner_action', 'qs_test_joiner_nonce' );

	$username = isset( $_POST['qs_test_joiner_username'] )
		? sanitize_user( wp_unslash( $_POST['qs_test_joiner_username'] ), true )
		: 'testjoiner';
	$email = isset( $_POST['qs_test_joiner_email'] )
		? sanitize_email( wp_unslash( $_POST['qs_test_joiner_email'] ) )
		: '';

	if ( '' === $username ) {
		$username = 'testjoiner';
	}

	$password = wp_generate_password( 18, true, true );
	$user     = get_user_by( 'login', $username );
	$created  = false;

	if ( $user instanceof WP_User ) {
		$update = array(
			'ID'       => $user->ID,
			'user_pass'=> $password,
			'role'     => 'joiner',
		);

		if ( $email && $email !== $user->user_email ) {
			$update['user_email'] = $email;
		}

		$result = wp_update_user( $update );
		if ( is_wp_error( $result ) ) {
			$GLOBALS['qs_test_joiner_error'] = $result->get_error_message();
			return;
		}

		$user = get_user_by( 'id', (int) $result );
	} else {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_pass'    => $password,
				'user_email'   => $email,
				'display_name' => 'Test Joiner',
				'role'         => 'joiner',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$GLOBALS['qs_test_joiner_error'] = $user_id->get_error_message();
			return;
		}

		$created = true;
		$user    = get_user_by( 'id', (int) $user_id );
	}

	if ( ! $user instanceof WP_User ) {
		$GLOBALS['qs_test_joiner_error'] = __( 'Unable to load the test Joiner account after saving.', 'quote-system' );
		return;
	}

	// Ensure the role is correct even if the account existed with another role.
	$user->set_role( 'joiner' );

	$GLOBALS['qs_test_joiner_credentials'] = array(
		'created'  => $created,
		'username' => $user->user_login,
		'password' => $password,
		'email'    => $user->user_email,
		'login_url'=> wp_login_url(),
		'dashboard'=> qs_joiner_dashboard_url(),
	);
}
add_action( 'admin_init', 'qs_handle_test_joiner_admin_action' );

/** Render the admin helper and show the freshly generated password once. */
function qs_render_test_joiner_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'quote-system' ) );
	}

	$existing = get_user_by( 'login', 'testjoiner' );
	$defaults = array(
		'username' => $existing instanceof WP_User ? $existing->user_login : 'testjoiner',
		'email'    => $existing instanceof WP_User ? $existing->user_email : '',
	);
	$credentials = isset( $GLOBALS['qs_test_joiner_credentials'] ) && is_array( $GLOBALS['qs_test_joiner_credentials'] )
		? $GLOBALS['qs_test_joiner_credentials']
		: array();
	$error = isset( $GLOBALS['qs_test_joiner_error'] ) ? (string) $GLOBALS['qs_test_joiner_error'] : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Test Joiner Account', 'quote-system' ); ?></h1>
		<p><?php esc_html_e( 'Create or reset a dedicated Joiner account for portal testing. A fresh random password is generated each time.', 'quote-system' ); ?></p>

		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<?php if ( $credentials ) : ?>
			<div class="notice notice-success" style="padding:12px 16px;">
				<p><strong><?php echo esc_html( $credentials['created'] ? 'Test Joiner created.' : 'Test Joiner reset.' ); ?></strong></p>
				<table class="widefat striped" style="max-width:760px;">
					<tbody>
						<tr><th style="width:160px;">Username</th><td><code id="qs-test-joiner-username"><?php echo esc_html( $credentials['username'] ); ?></code></td></tr>
						<tr><th>Password</th><td><code id="qs-test-joiner-password"><?php echo esc_html( $credentials['password'] ); ?></code></td></tr>
						<tr><th>Email</th><td><?php echo esc_html( $credentials['email'] ?: '—' ); ?></td></tr>
						<tr><th>Login URL</th><td><a href="<?php echo esc_url( $credentials['login_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $credentials['login_url'] ); ?></a></td></tr>
						<tr><th>Joiner Dashboard</th><td><a href="<?php echo esc_url( $credentials['dashboard'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $credentials['dashboard'] ); ?></a></td></tr>
					</tbody>
				</table>
				<p>
					<button type="button" class="button button-secondary" id="qs-copy-test-joiner">Copy Credentials</button>
					<span id="qs-copy-test-joiner-status" style="margin-left:8px;"></span>
				</p>
				<p><em><?php esc_html_e( 'Copy the password now. For security, it is only shown on this page response.', 'quote-system' ); ?></em></p>
			</div>
			<script>
			(function(){
				const button=document.getElementById('qs-copy-test-joiner');
				if(!button)return;
				button.addEventListener('click',async function(){
					const username=document.getElementById('qs-test-joiner-username')?.textContent||'';
					const password=document.getElementById('qs-test-joiner-password')?.textContent||'';
					const login=<?php echo wp_json_encode( wp_login_url() ); ?>;
					const text='Joiner Portal Test Account\nLogin: '+login+'\nUsername: '+username+'\nPassword: '+password;
					const status=document.getElementById('qs-copy-test-joiner-status');
					try{
						await navigator.clipboard.writeText(text);
						if(status)status.textContent='Copied.';
					}catch(error){
						if(status)status.textContent='Copy failed — please copy the details manually.';
					}
				});
			}());
			</script>
		<?php endif; ?>

		<form method="post" style="max-width:760px;margin-top:24px;">
			<?php wp_nonce_field( 'qs_test_joiner_action', 'qs_test_joiner_nonce' ); ?>
			<input type="hidden" name="qs_test_joiner_action" value="create_or_reset">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="qs-test-joiner-username-field">Username</label></th>
					<td>
						<input id="qs-test-joiner-username-field" class="regular-text" type="text" name="qs_test_joiner_username" value="<?php echo esc_attr( $defaults['username'] ); ?>" required>
						<p class="description">Default: <code>testjoiner</code>. If this username already exists, its password and role will be reset.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="qs-test-joiner-email">Email</label></th>
					<td>
						<input id="qs-test-joiner-email" class="regular-text" type="email" name="qs_test_joiner_email" value="<?php echo esc_attr( $defaults['email'] ); ?>">
						<p class="description">Optional. Use an LF or testing email address if you want WordPress emails delivered to this account.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( $existing instanceof WP_User ? 'Reset Test Joiner & Generate New Password' : 'Create Test Joiner' ); ?>
		</form>

		<hr>
		<p><strong>Expected test behaviour:</strong> this user has the <code>Joiner</code> role, is redirected to <code>/my-quotes/</code> after login, cannot use wp-admin, and does not see administrator-only developer test tools.</p>
	</div>
	<?php
}
