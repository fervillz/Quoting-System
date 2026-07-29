<?php
/**
 * Standalone joiner login shortcode.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function qs_quote_login_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'redirect' => site_url( '/my-quotes/' ),
		),
		$atts,
		'quote_login'
	);

	if ( is_user_logged_in() ) {
		return '<div class="qs-container qs-login-already"><p>You are already logged in.</p><a class="qs-btn" href="' . esc_url( $atts['redirect'] ) . '">Open My Quotes</a></div>';
	}

	$error = '';
	if ( isset( $_POST['qs_quote_login'] ) ) {
		$nonce = isset( $_POST['qs_login_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['qs_login_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'qs_quote_login' ) ) {
			$error = 'Security check failed. Please try again.';
		} else {
			$credentials = array(
				'user_login'    => isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '',
				'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '',
				'remember'      => true,
			);
			$user = wp_signon( $credentials, is_ssl() );
			if ( is_wp_error( $user ) ) {
				$error = 'The email address or password is incorrect.';
			} else {
				wp_safe_redirect( $atts['redirect'] );
				exit;
			}
		}
	}

	ob_start();
	?>
	<div class="qs-container qs-login-page">
		<section class="qs-login-information">
			<h1>Joiner Quoting Portal</h1>
			<p class="qs-login-lead">Access the Loughlin Furniture quoting system to generate cabinet door quotes quickly and accurately.</p>
			<p>Designed for approved joiners and trade partners, this tool allows you to select profiles, materials, and finishes to build quotes for your projects.</p>
			<strong>You could include:</strong>
			<ul>
				<li>Generate cabinet door quotes in minutes</li>
				<li>Select profiles, timbers and finishes</li>
				<li>View pricing instantly</li>
				<li>Submit quotes directly to Loughlin Furniture</li>
			</ul>
			<p>This helps first-time users understand the tool.</p>
		</section>

		<section class="qs-login-form-panel">
			<h2>Log in to your trade account</h2>
			<?php if ( $error ) : ?><div class="qs-login-error"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<form method="post" class="qs-login-form">
				<?php wp_nonce_field( 'qs_quote_login', 'qs_login_nonce' ); ?>
				<label for="qs-login-email">Email address</label>
				<span class="qs-login-control">
					<input id="qs-login-email" type="text" name="log" autocomplete="username" placeholder="Johndoes@email.com" required>
					<svg class="qs-login-valid" aria-hidden="true" viewBox="0 0 16 16" focusable="false"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M7 11.414l5.207-5.207-.707-.707L7 10 4.5 7.5l-.707.707z"/></svg>
				</span>

				<label for="qs-login-password">Password</label>
				<span class="qs-login-control">
					<input id="qs-login-password" type="password" name="pwd" autocomplete="current-password" required>
					<button type="button" class="qs-password-toggle" aria-label="Show password" aria-pressed="false">
						<svg aria-hidden="true" viewBox="0 0 16 16" focusable="false"><path d="m13.359 11.238 2.122 2.122-.707.707-12.96-12.96.707-.707 2.07 2.07A8.8 8.8 0 0 1 8 1.75c3.337 0 6.085 2.16 7.707 5.568a.75.75 0 0 1 0 .664 12 12 0 0 1-2.348 3.256M11.297 9.176a3.5 3.5 0 0 0-4.473-4.473l.842.842a2.3 2.3 0 0 1 2.789 2.789zM5.545 6.666l3.789 3.789a2.3 2.3 0 0 1-3.789-3.789M3.259 3.966C1.934 4.958.93 6.36.293 7.318a.75.75 0 0 0 0 .664C1.915 11.39 4.663 13.55 8 13.55a8.7 8.7 0 0 0 2.678-.424l-.85-.85A7.5 7.5 0 0 1 8 12.55c-2.767 0-5.184-1.72-6.696-4.9.63-1.32 1.48-2.36 2.5-3.115z"/></svg>
					</button>
				</span>
				<a class="qs-forgot-password" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Forgot your password?</a>

				<button class="qs-btn qs-login-submit" type="submit" name="qs_quote_login">Log In</button>
			</form>
			<p class="qs-login-access"><strong>Need access?</strong><br>Contact Loughlin Furniture to request a trade account.</p>
		</section>
	</div>
	<script>
	(function(){
		document.querySelectorAll('.qs-login-page').forEach(function(page){
			const password=page.querySelector('#qs-login-password');
			const toggle=page.querySelector('.qs-password-toggle');
			if(!password||!toggle)return;
			toggle.addEventListener('click',function(){
				const showing=password.type==='text';
				password.type=showing?'password':'text';
				toggle.setAttribute('aria-pressed',showing?'false':'true');
				toggle.setAttribute('aria-label',showing?'Show password':'Hide password');
			});
		});
	}());
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode( 'quote_login', 'qs_quote_login_shortcode' );
add_shortcode( 'joiner_login', 'qs_quote_login_shortcode' );
