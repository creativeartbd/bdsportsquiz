<?php

/**
 * Hestia functions and definitions
 *
 * @package Hestia
 * @since   Hestia 1.0
 */
if (!session_id()) {
	session_start();
}

define('HESTIA_VERSION', '3.0.8');
define('HESTIA_VENDOR_VERSION', '1.0.2');
define('HESTIA_PHP_INCLUDE', trailingslashit(get_template_directory()) . 'inc/');
define('HESTIA_CORE_DIR', HESTIA_PHP_INCLUDE . 'core/');

if (!defined('HESTIA_DEBUG')) {
	define('HESTIA_DEBUG', false);
}

// Load hooks
require_once(HESTIA_PHP_INCLUDE . 'hooks/hooks.php');

// Load Helper Globally Scoped Functions
require_once(HESTIA_PHP_INCLUDE . 'helpers/sanitize-functions.php');
require_once(HESTIA_PHP_INCLUDE . 'helpers/layout-functions.php');

if (class_exists('WooCommerce', false)) {
	require_once(HESTIA_PHP_INCLUDE . 'compatibility/woocommerce/functions.php');
}

if (function_exists('max_mega_menu_is_enabled')) {
	require_once(HESTIA_PHP_INCLUDE . 'compatibility/max-mega-menu/functions.php');
}

/**
 * Adds notice for PHP < 5.3.29 hosts.
 */
function hestia_no_support_5_3()
{
	$message = __('Hey, we\'ve noticed that you\'re running an outdated version of PHP which is no longer supported. Make sure your site is fast and secure, by upgrading PHP to the latest version.', 'hestia');

	printf('<div class="error"><p>%1$s</p></div>', esc_html($message));
}


if (version_compare(PHP_VERSION, '5.3.29') < 0) {
	/**
	 * Add notice for PHP upgrade.
	 */
	add_filter('template_include', '__return_null', 99);
	switch_theme(WP_DEFAULT_THEME);
	unset($_GET['activated']);
	add_action('admin_notices', 'hestia_no_support_5_3');

	return;
}

/**
 * Begins execution of the theme core.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function hestia_run()
{

	require_once HESTIA_CORE_DIR . 'class-hestia-autoloader.php';
	$autoloader = new Hestia_Autoloader();

	spl_autoload_register(array($autoloader, 'loader'));

	new Hestia_Core();

	$vendor_file = trailingslashit(get_template_directory()) . 'vendor/composer/autoload_files.php';
	if (is_readable($vendor_file)) {
		$files = require_once $vendor_file;
		foreach ($files as $file) {
			if (is_readable($file)) {
				include_once $file;
			}
		}
	}
	add_filter('themeisle_sdk_products', 'hestia_load_sdk');

	if (class_exists('Ti_White_Label', false)) {
		Ti_White_Label::instance(get_template_directory() . '/style.css');
	}
}

/**
 * Loads products array.
 *
 * @param array $products All products.
 *
 * @return array Products array.
 */
function hestia_load_sdk($products)
{
	$products[] = get_template_directory() . '/style.css';

	return $products;
}

require_once(HESTIA_CORE_DIR . 'class-hestia-autoloader.php');

/**
 * The start of the app.
 *
 * @since   1.0.0
 */
hestia_run();

/**
 * Append theme name to the upgrade link
 * If the active theme is child theme of Hestia
 *
 * @param string $link - Current link.
 *
 * @return string $link - New upgrade link.
 * @package hestia
 * @since   1.1.75
 */
function hestia_upgrade_link($link)
{

	$theme_name = wp_get_theme()->get_stylesheet();

	$hestia_child_themes = array(
		'orfeo',
		'fagri',
		'tiny-hestia',
		'christmas-hestia',
		'jinsy-magazine',
	);

	if ($theme_name === 'hestia') {
		return $link;
	}

	if (!in_array($theme_name, $hestia_child_themes, true)) {
		return $link;
	}

	$link = add_query_arg(
		array(
			'theme' => $theme_name,
		),
		$link
	);

	return $link;
}

add_filter('hestia_upgrade_link_from_child_theme_filter', 'hestia_upgrade_link');

/**
 * Check if $no_seconds have passed since theme was activated.
 * Used to perform certain actions, like displaying upsells or add a new recommended action in About Hestia page.
 *
 * @param integer $no_seconds number of seconds.
 *
 * @return bool
 * @since  1.1.45
 * @access public
 */
function hestia_check_passed_time($no_seconds)
{
	$activation_time = get_option('hestia_time_activated');
	if (!empty($activation_time)) {
		$current_time    = time();
		$time_difference = (int) $no_seconds;
		if ($current_time >= $activation_time + $time_difference) {
			return true;
		} else {
			return false;
		}
	}

	return true;
}

/**
 * Legacy code function.
 */
function hestia_setup_theme()
{
	return;
}

add_action('qsm_after_all_section', 'qsm_after_all_section_func');
function qsm_after_all_section_func()
{

	$ds_get_quiz = get_posts(array(
		'post_type'      => 'qsm_quiz',
		'post_status'    => 'publish'
	));

	$quiz_title = '';
	if ($ds_get_quiz) {
		$quiz_titles = [];
		foreach ($ds_get_quiz as $quiz_title) {
			$quiz_titles[] = $quiz_title->post_name;
		}
	}

	$_SESSION['skip_title'][] = basename($_SERVER['REQUEST_URI']);
	$new_array = array_diff($quiz_titles, $_SESSION['skip_title']);

	if (empty($new_array)) {
		unset($_SESSION['skip_title']);
		$skip_post_title = $quiz_titles[0];
	} else {
		$skip_post_title = $new_array[array_key_first($new_array)];
	}

	$skip_post_title = site_url() . '/quiz/' . $skip_post_title;
	echo '<hr/>';
	echo "<a href='{$skip_post_title}'>" . apply_filters('skip_quiz_text', 'Skip this Quiz') . "</a>&nbsp;";
}


add_action('hestia_before_single_post_content', 'hestia_before_single_post_content_func');
function hestia_before_single_post_content_func()
{
	global $wpdb;
	$post_meta = get_post_meta(get_the_ID());
	$quiz_id   = isset($post_meta['quiz_id'][0]) ? $post_meta['quiz_id'][0] : '';

	if ($quiz_id) {
		$db_table = $wpdb->prefix . 'mlw_quizzes';
		$quiz_settings = $wpdb->get_col($wpdb->prepare("SELECT quiz_settings FROM $db_table WHERE quiz_id =  %d ", $quiz_id));
		$quiz_options = unserialize($quiz_settings[0]);
		$quiz_options = unserialize($quiz_options['quiz_options']);
		$scheduled_time_start = $quiz_options['scheduled_time_start'];
		$scheduled_time_end = $quiz_options['scheduled_time_end'];
		$scheduled_time_end_str = date('M d, Y H:i:s', strtotime($scheduled_time_end));
		wp_localize_script(
			'ds-quiz-counter',
			'ds',
			array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'scheduled_time_end' => $scheduled_time_end_str,
			)
		);
		if (!empty($scheduled_time_end)) {
			echo "<div class='quiz-time-wrapper text-danger'><h3><span id='quiz-counter'></span></h3></div>";
		}
	}
}

add_action('wp_enqueue_scripts', 'ds_script');
function ds_script()
{
	wp_enqueue_script('ds-quiz-counter', get_template_directory_uri() . '/assets/js/quiz-counter.js', array(), '1.0.0', true);
}

add_filter('qmn_begin_shortcode', 'add_register_link', 10, 3);
function add_register_link($display)
{
	if (!is_user_logged_in()) {
		$register_link = site_url('/wp-login.php?action=register');
		$display .= "<p>Don't have account? Please register <a href='$register_link'>here</a>.</p>";
	}
	return $display;
}

// Remove color option from user profile page
if (is_admin()) {
	if (current_user_can('subscriber')) {
		remove_action("admin_color_scheme_picker", "admin_color_scheme_picker");
	}
}

// Adjust dashboard widget
add_action('wp_dashboard_setup', 'wp_dashboard_setup_func');
function wp_dashboard_setup_func()
{
	if (current_user_can('subscriber')) {
		remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
		remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
		remove_meta_box('dashboard_primary',       'dashboard', 'side');      //WordPress.com Blog
		remove_meta_box('dashboard_secondary',     'dashboard', 'side');      //Other WordPress News
		remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');    //Incoming Links
		remove_meta_box('dashboard_plugins',       'dashboard', 'normal');    //Plugins
		remove_meta_box('dashboard_activity', 'dashboard', 'normal');
		remove_meta_box('e-dashboard-overview', 'dashboard', 'normal');
	}
}

// Add new dashboard widget
add_action('wp_dashboard_setup', 'wpdocs_add_dashboard_widgets');
function wpdocs_add_dashboard_widgets()
{
	$user_id = get_current_user_id();
	wp_add_dashboard_widget('dashboard_widget', 'Your latest Quiz result', 'dashboard_widget_function');
	wp_add_dashboard_widget('dashboard_quiz_widget', 'All Quiz', 'dashboard_all_quiz_widget', null, null, 'side');
}

function dashboard_all_quiz_widget($post, $callback_args)
{
	global $wpdb;
	$args = array(
		'post_type'   => 'qsm_quiz',
		'sort_order' => 'desc'
	);

	$get_quiz = get_posts($args);
	if ($get_quiz) {
		echo "<table class='widefat'>";
		echo "<thead>";
		echo "<tr>";
		echo "<th><b>Sl.</b></th>";
		echo "<th><b>Quiz Name</b></th>";
		echo "<th><b>Play Quiz</b></th>";
		echo "</tr>";
		echo "</thead>";
		echo "<tbody>";
		$count = 0;
		foreach ($get_quiz as $quiz) {
			$count++;
			$quiz_title = $quiz->post_title;
			$quiz_name = $quiz->post_name;
			$quiz_link = site_url() . '/quiz/' . $quiz_name;

			echo "<tr>";
			echo "<td>{$count}</td>";
			echo "<td>{$quiz_title}</td>";
			echo "<td><a href='{$quiz_link}' target='_blank'>Play</a></td>";
			echo "</tr>";
		}
		echo "</tbody>";
		echo "</table>";
	}
}

function dashboard_widget_function($post, $callback_args)
{
	//if (current_user_can('subscriber')) {
	global $wpdb;
	$table = $wpdb->base_prefix . 'mlw_results';
	$user_id = get_current_user_id();
	$quiz_results = $wpdb->get_results("SELECT quiz_results, quiz_system, correct_score, point_score, quiz_name, time_taken FROM {$table} WHERE user = $user_id AND deleted = 0 ORDER BY result_id DESC ", OBJECT);

	if ($quiz_results) {
		echo "<table class='widefat'>";
		echo "<thead>";
		echo "<tr>";
		echo "<th><b>Sl.</b></th>";
		echo "<th><b>Quiz Name</b></th>";
		echo "<th><b>Score</b></th>";
		echo "<th><b>Time Taken</b></th>";
		echo "<th><b>Played On</b></th>";
		echo "</tr>";
		echo "</thead>";
		echo "<tbody>";

		$count = 0;
		foreach ($quiz_results as $result) {
			$count++;
			$mlw_qmn_results_array = unserialize($result->quiz_results);

			// Calculate hours
			$mlw_complete_hours = floor($mlw_qmn_results_array[0] / 3600);
			if ($mlw_complete_hours > 0) {
				$actual_hour = str_pad($mlw_complete_hours, 2, '0', STR_PAD_LEFT) . 'Hours';
			} else {
				$actual_hour = 0;
			}

			// Calculate minutes
			$mlw_complete_minutes = floor(($mlw_qmn_results_array[0] % 3600) / 60);
			if ($mlw_complete_minutes > 0) {
				$actual_minutes = str_pad($mlw_complete_minutes, 2, '0', STR_PAD_LEFT);
			} else {
				$actual_minutes = 0;
			}

			// Calculate seconds
			$mlw_complete_seconds = $mlw_qmn_results_array[0] % 60;
			$actual_seconds = str_pad($mlw_complete_seconds, 2, '0', STR_PAD_LEFT);

			$quiz_system = $result->quiz_system; // 0 = Correct/Incorrect, 1 = Point, 3 = Correct/Incorect and Point
			$correct_score = $result->correct_score; // Score for Correct/Incorrect
			$point_score = $result->point_score; // Score for Point

			if (0 == $quiz_system) {
				$final_score = $correct_score . '%';
			} elseif (1 == $quiz_system) {
				$final_score = $point_score;
			} elseif (3 == $quiz_system) {
				$final_score = 'Point(' . $point_score . ') | Correct(' . $correct_score . '%)';
			}

			echo "<tr>";
			echo "<td>{$count}</td>";
			echo "<td>{$result->quiz_name}</td>";
			echo "<td>{$final_score}</td>";
			echo "<td>{$actual_hour}h {$actual_minutes}m {$actual_seconds}</td>";
			echo "<td>{$result->time_taken}</td>";
			echo "</tr>";
		}
		echo "</tbody>";
		echo "</table>";
	} else {
		echo "We couldn't found any quiz. Please check our home page to play quiz.";
	}
	//}
}

// Change Dashboard Text from admin panel
add_action('admin_head', 'my_custom_dashboard_name');
function my_custom_dashboard_name()
{
	if (current_user_can('subscriber')) {
		if ($GLOBALS['title'] != 'Dashboard') {
			return;
		}
		$current_user = wp_get_current_user();
		$user_nickname = $current_user->data->user_nicename;
		$GLOBALS['title'] =  __('Welcome ' . ucfirst($user_nickname));
	}
}
// Replace english number to bangla number
function make_bangla_number($str)
{
	$engNumber = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 0);
	$bangNumber = array('১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯', '০');
	$converted = str_replace($engNumber, $bangNumber, $str);

	return $converted;
}

add_action('register_form', 'wporg_myplugin_add_registration_fields');
function wporg_myplugin_add_registration_fields()
{
	echo do_shortcode("[nextend_social_login provider='facebook']");
}

add_shortcode('ds_total_played_quiz', 'ds_total_played_quiz_func');
function ds_total_played_quiz_func($atts)
{

	$atts = shortcode_atts([
		'pre_title' => 'So far',
		'post_title' => 'person has played this quiz',
		'tag' => 'h2'
	], $atts);

	$pre_title = isset($atts['pre_title']) ? sanitize_text_field($atts['pre_title']) : '';
	$post_title = isset($atts['post_title']) ? sanitize_text_field($atts['post_title']) : '';
	$tag = isset($atts['tag']) ? sanitize_text_field($atts['tag']) : '';

	$my_postid = get_the_ID();
	$content_post = get_post($my_postid);
	$content = $content_post->post_content;
	$explode = explode('=', $content);
	$quiz_id = intval($explode[1]);

	global $wpdb;
	$table = $wpdb->base_prefix . 'mlw_results';
	$quiz_played = $wpdb->get_var("SELECT COUNT(DISTINCT user)  FROM {$table} WHERE quiz_id = $quiz_id AND deleted = 0 ");
	echo "<$tag>" . ' ' . $pre_title . ' ' . num_to_bangla_callback($quiz_played) . ' ' . $post_title . ' ' . "</$tag>";
}

// Convert egnlish to bangla number
function num_to_bangla_callback($number)
{
	$engNumber = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 0);
	$bangNumber = array('১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯', '০');
	$converted = str_replace($engNumber, $bangNumber, $number);

	return $converted;
}

// Shortcode for show all quiz winner
add_shortcode('ds_all_winner', 'ds_all_winner_func');
function ds_all_winner_func()
{
	if (is_user_logged_in()) {
		global $wpdb;
		$result_table = $wpdb->base_prefix . 'mlw_results';
		$quiz_table = $wpdb->base_prefix . 'mlw_quizzes';

		$get_quizs = $wpdb->get_results("SELECT quiz_id, quiz_name, quiz_settings FROM {$quiz_table} WHERE deleted = 0 ");
		foreach ($get_quizs as $quiz) {
			$quiz_id = $quiz->quiz_id;
			$quiz_name = $quiz->quiz_name;
			$quiz_results = $wpdb->get_results("SELECT * FROM {$result_table} WHERE deleted = 0 AND quiz_id = $quiz_id ORDER BY result_id DESC LIMIT 3 ", OBJECT);
			$is_published = unserialize(unserialize($quiz->quiz_settings)['quiz_options'])['is_publish_result'];
			if ($quiz_results) {
				echo "<h5>$quiz_name</h5>";
				echo "<table class='table table-bordered' style='table-layout: fixed;'>";
				echo "<tr>";
				echo "<td><b>Sl.</b></td>";
				echo "<td><b>Name</b></td>";
				echo "<td><b>Score</b></td>";
				echo "<td><b>Time Taken</b></td>";
				echo "<td><b>Played On</b></td>";
				echo "</tr>";

				$count = 0;
				foreach ($quiz_results as $result) {
					$username = get_user_by('id', $result->user);
					$username = $username->user_login;
					$count++;
					$mlw_qmn_results_array = unserialize($result->quiz_results);

					// Calculate hours
					$mlw_complete_hours = floor($mlw_qmn_results_array[0] / 3600);
					if ($mlw_complete_hours > 0) {
						$actual_hour = str_pad($mlw_complete_hours, 2, '0', STR_PAD_LEFT) . 'Hours';
					} else {
						$actual_hour = 0;
					}

					// Calculate minutes
					$mlw_complete_minutes = floor(($mlw_qmn_results_array[0] % 3600) / 60);
					if ($mlw_complete_minutes > 0) {
						$actual_minutes = str_pad($mlw_complete_minutes, 2, '0', STR_PAD_LEFT);
					} else {
						$actual_minutes = 0;
					}

					// Calculate seconds
					$mlw_complete_seconds = $mlw_qmn_results_array[0] % 60;
					$actual_seconds = str_pad($mlw_complete_seconds, 2, '0', STR_PAD_LEFT);

					$quiz_system = $result->quiz_system; // 0 = Correct/Incorrect, 1 = Point, 3 = Correct/Incorect and Point
					$correct_score = $result->correct_score; // Score for Correct/Incorrect
					$point_score = $result->point_score; // Score for Point

					if (0 == $quiz_system) {
						$final_score = $correct_score . '%';
					} elseif (1 == $quiz_system) {
						$final_score = $point_score;
					} elseif (3 == $quiz_system) {
						$final_score = 'Point(' . $point_score . ') | Correct(' . $correct_score . '%)';
					}

					if ($is_published) {
						$final_score = $final_score;
					} else {
						$final_score = '<span class="text-danger"><b>Result is not published</b></span>';
					}

					echo '</pre>';
					echo "<tr>";
					echo "<td>{$count}</td>";
					echo "<td><a href='" . site_url() . '/author/' . $username . "'>{$result->name}</a></td>";
					echo "<td>{$final_score}</td>";
					echo "<td>{$actual_hour}h {$actual_minutes}m {$actual_seconds}</td>";
					echo "<td>{$result->time_taken}</td>";
					echo "</tr>";
				}
				echo "</table>";
			}
		}
	} else {
		$login_url = wp_login_url();
		echo '<br/>';
		echo "<div class='text-center'>";
		echo '<p class="text-danger">' . __("Please <a href='{$login_url}'>login</a> to view all quiz results", "dew") . '</p>';
		echo '<h6>-Or-</h6>';
		echo "<div style='display: block; margin-left: auto; margin-right: auto; width: fit-content;'>";
		echo do_shortcode('[nextend_social_login provider="facebook"]');
		echo "</div>";
		echo "</div>";
	}
}

// Choose where to redirect after login
function admin_default_page()
{
	return site_url();
}

add_filter('login_redirect', 'admin_default_page');


// Show next quiz after play the quiz
add_action('qsm_after_results_page', 'qsm_after_results_page_func');
function qsm_after_results_page_func()
{
	// Change skip quiz text
	add_filter('skip_quiz_text', 'skip_quiz_text_func');
	echo qsm_after_all_section_func();
}

// Change skip quiz text
function skip_quiz_text_func($text)
{
	$text = 'Play New Quiz';
	return  $text;
}


// Quiz page top ad section 
add_action('hestia_before_single_post_wrap', 'hestia_before_single_post_wrap_func');
function hestia_before_single_post_wrap_func()
{
	if (is_active_sidebar('top-quiz')) {
		dynamic_sidebar('top-quiz');
	}
}

// Quiz page bottom ad section 
add_action('quiz_ad_after_single_post_article', 'quiz_ad_after_single_post_article_func');
function quiz_ad_after_single_post_article_func()
{
	if (is_active_sidebar('bottom-quiz')) {
		dynamic_sidebar('bottom-quiz');
	}
}



// Register new widget for advertisement
function wpdocs_theme_slug_widgets_init()
{
	register_sidebar(array(
		'name'          => __('Ad for top of the quiz page', 'hestia'),
		'id'            => 'top-quiz',
		'description'   => __('Enter your ad code here.', 'hestia'),
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h2 class="widgettitle">',
		'after_title'   => '</h2>',
	));

	register_sidebar(array(
		'name'          => __('Ad for bottom of the quiz page', 'hestia'),
		'id'            => 'bottom-quiz',
		'description'   => __('Enter your ad code here.', 'hestia'),
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h2 class="widgettitle">',
		'after_title'   => '</h2>',
	));

	register_sidebar(array(
		'name'          => __('Ad between Quiz', 'hestia'),
		'id'            => 'between-quiz',
		'description'   => __('Enter your ad code here.', 'hestia'),
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h2 class="widgettitle">',
		'after_title'   => '</h2>',
	));
}
add_action('widgets_init', 'wpdocs_theme_slug_widgets_init');


add_action('admin_enqueue_scripts', 'add_style_for_quiz_admin_page');
function add_style_for_quiz_admin_page()
{
	echo '<style>
    .ui-buttonset,
		.buttonset {
			align-items: center;
		}
  </style>';
}
