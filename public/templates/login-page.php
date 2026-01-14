<?php
/**
 * Modern Login Page Template
 *
 * Beautiful ChatGPT/Claude-style login page for ChatProjects
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

call_user_func(function () {
    $site_name = get_bloginfo('name');
    $site_logo = get_custom_logo();

    // Read-only GET parameters for display purposes only - no state-changing operations.
    // These are sanitized via filter_input() and additional WordPress sanitization functions.
    // Nonce verification is not required for read-only display parameters.

    // Check for redirect URL from parent template (e.g., project-shell-modern.php)
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Variable set by including template
    if (isset($chatprojects_login_redirect_to) && !empty($chatprojects_login_redirect_to)) {
        $redirect_to = esc_url_raw($chatprojects_login_redirect_to);
    } else {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter, no state changes
        $redirect_param = filter_input(INPUT_GET, 'redirect_to', FILTER_SANITIZE_URL);
        $redirect_to = $redirect_param ? esc_url_raw($redirect_param) : home_url('/');
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter
    $login_param = filter_input(INPUT_GET, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $login_status = $login_param ? sanitize_text_field($login_param) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter
    $logged_out_param = filter_input(INPUT_GET, 'logged_out', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $logged_out_status = $logged_out_param ? sanitize_text_field($logged_out_param) : '';
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="h-full">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Sign In', 'chatprojects'); ?> - <?php echo esc_html($site_name); ?></title>
    <?php wp_head(); ?>
</head>

<body class="h-full bg-gradient-to-br from-primary-50 via-white to-primary-50 dark:from-dark-bg dark:via-dark-surface dark:to-dark-bg">
    <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">

        <!-- Logo & Header -->
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex justify-center mb-6">
                <?php if ($site_logo) : ?>
                    <?php echo wp_kses_post( $site_logo ); ?>
                <?php else : ?>
                    <svg class="w-16 h-16 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                <?php endif; ?>
            </div>

            <h2 class="text-center text-3xl font-bold text-neutral-900 dark:text-white">
                <?php esc_html_e('Welcome to ChatProjects', 'chatprojects'); ?>
            </h2>
            <p class="mt-2 text-center text-sm text-neutral-600 dark:text-neutral-400">
                <?php esc_html_e('AI-powered project management and collaboration', 'chatprojects'); ?>
            </p>
        </div>

        <!-- Login Card -->
        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white dark:bg-dark-surface py-8 px-4 shadow-large sm:rounded-2xl sm:px-10 border border-neutral-200 dark:border-dark-border">

                <?php if ('failed' === $login_status) : ?>
                    <div class="mb-6 p-4 bg-error-50 dark:bg-error-900/20 border border-error-200 dark:border-error-900/50 rounded-xl">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-error-600 dark:text-error-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-error-800 dark:text-error-300">
                                    <?php esc_html_e('Invalid username or password. Please try again.', 'chatprojects'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ('true' === $logged_out_status) : ?>
                    <div class="mb-6 p-4 bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-900/50 rounded-xl">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-success-600 dark:text-success-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-success-800 dark:text-success-300">
                                    <?php esc_html_e('You have been logged out successfully.', 'chatprojects'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" action="<?php echo esc_url(wp_login_url($redirect_to)); ?>" method="post">
                    <div>
                        <label for="user_login" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                            <?php esc_html_e('Username or Email', 'chatprojects'); ?>
                        </label>
                        <div class="vp-login-input-wrapper">
                            <div class="vp-input-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <input
                                id="user_login"
                                name="log"
                                type="text"
                                autocomplete="username"
                                required
                                class="block w-full py-3 border border-neutral-300 dark:border-dark-border rounded-xl text-neutral-900 dark:text-white placeholder-neutral-400 bg-white dark:bg-dark-elevated focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-shadow"
                                placeholder="<?php esc_html_e('Enter your username or email', 'chatprojects'); ?>"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="user_pass" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                            <?php esc_html_e('Password', 'chatprojects'); ?>
                        </label>
                        <div class="vp-login-input-wrapper" x-data="{ showPassword: false }">
                            <div class="vp-input-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input
                                id="user_pass"
                                name="pwd"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                class="block w-full py-3 border border-neutral-300 dark:border-dark-border rounded-xl text-neutral-900 dark:text-white placeholder-neutral-400 bg-white dark:bg-dark-elevated focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-shadow"
                                placeholder="<?php esc_html_e('Enter your password', 'chatprojects'); ?>"
                            >
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="vp-toggle-password"
                            >
                                <svg x-show="!showPassword" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showPassword" x-cloak fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input
                                id="rememberme"
                                name="rememberme"
                                type="checkbox"
                                value="forever"
                                class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-neutral-300 dark:border-dark-border rounded transition-colors"
                            >
                            <label for="rememberme" class="ml-2 block text-sm text-neutral-700 dark:text-neutral-300">
                                <?php esc_html_e('Remember me', 'chatprojects'); ?>
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 transition-colors">
                                <?php esc_html_e('Forgot password?', 'chatprojects'); ?>
                            </a>
                        </div>
                    </div>

                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">

                    <div>
                        <button
                            type="submit"
                            class="w-full flex justify-center items-center gap-2 py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-semibold text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                            </svg>
                            <?php esc_html_e('Sign In', 'chatprojects'); ?>
                        </button>
                    </div>
                </form>

                <?php if (get_option('users_can_register')) : ?>
                    <div class="mt-6">
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-neutral-300 dark:border-dark-border"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-white dark:bg-dark-surface text-neutral-500 dark:text-neutral-400">
                                    <?php esc_html_e('New to ChatProjects?', 'chatprojects'); ?>
                                </span>
                            </div>
                        </div>

                        <div class="mt-6">
                            <a
                                href="<?php echo esc_url(wp_registration_url()); ?>"
                                class="w-full flex justify-center items-center gap-2 py-3 px-4 border border-neutral-300 dark:border-dark-border rounded-xl shadow-sm text-sm font-semibold text-neutral-700 dark:text-neutral-300 bg-white dark:bg-dark-elevated hover:bg-neutral-50 dark:hover:bg-dark-hover focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                </svg>
                                <?php esc_html_e('Create Account', 'chatprojects'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Additional Info -->
            <div class="mt-8 text-center">
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                    <?php esc_html_e('By signing in, you agree to our', 'chatprojects'); ?>
                    <a href="<?php echo esc_url(get_privacy_policy_url()); ?>" class="font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                        <?php esc_html_e('Privacy Policy', 'chatprojects'); ?>
                    </a>
                </p>
            </div>
        </div>

        <!-- Features Section -->
        <div class="mt-12 sm:mx-auto sm:w-full sm:max-w-4xl">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 px-4">
                <div class="text-center">
                    <div class="flex justify-center mb-3">
                        <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900/20 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white"><?php esc_html_e('AI-Powered', 'chatprojects'); ?></h3>
                    <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400"><?php esc_html_e('Leverage multiple AI providers for intelligent assistance', 'chatprojects'); ?></p>
                </div>

                <div class="text-center">
                    <div class="flex justify-center mb-3">
                        <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900/20 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white"><?php esc_html_e('Project Management', 'chatprojects'); ?></h3>
                    <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400"><?php esc_html_e('Organize your work with intelligent project structures', 'chatprojects'); ?></p>
                </div>

                <div class="text-center">
                    <div class="flex justify-center mb-3">
                        <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900/20 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-white"><?php esc_html_e('Secure & Private', 'chatprojects'); ?></h3>
                    <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400"><?php esc_html_e('Your data is encrypted and protected with enterprise-grade security', 'chatprojects'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
<?php
});
