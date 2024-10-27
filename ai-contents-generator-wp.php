<?php
/*
 * Plugin Name: AI Article Generator for WordPress
 * Plugin URI: https://www.contents.ai/wordpress/
 * Description: Enhance your WordPress writing experience with Contents.ai's innovative AI plugin.
 * Version: 1.1.4
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: Contents
 * Author URI: https://www.contents.ai
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: aiwrc
 * Domain Path: /languages
*/

if (!defined('ABSPATH')) {
	die('Invalid request.');
}

require_once dirname( __FILE__ ) . '/include/functions.php';

class aiwrc_AiWriterContentsCom
{
	static $version = '1.1.4';
	static $name_long = 'AI Article Generator for WordPress';
	static $name = 'AI Article Generator for WordPress';
	static $name_short = 'AI Article Generator for WordPress';
	static $plugin_unique_slug = 'aiwrc';
	static $slug = 'aiwrc'; // same as above but shorter
	static $options = null;
	static $options_name = 'aiwrc_options';
	static $testing = false; // whether im testing or not. must be false in release package. uncomment //!devtest
	static $eol; // to be used in log messages
	static $assets_context = false;

	static $errors = [];
	static $notices = [];

	static $supported_languages = array(
		'Arabic, Modern Standard' => 'ar-SA',
		'Chinese, Mandarin (cmn-CN)' => 'cmn-CN',
		'Croatian' => 'hr',
		'Czech' => 'cs',
		'Danish' => 'da-DK',
		'Dutch' => 'nl-NL',
		'English' => 'en-us',
		'Estonian' => 'et',
		'Finnish' => 'fi',
		'French' => 'fr',
		'German' => 'de',
		'Greek' => 'el',
		'Hebrew' => 'he-IL',
		'Hindi' => 'hi-IN',
		'Hungarian' => 'hu',
		'Icelandic' => 'is-IS',
		'Indonesian' => 'id-ID',
		'Italian' => 'it',
		'Japanese' => 'ja-JP',
		'Korean' => 'ko-KR',
		'Malay' => 'ms-MY',
		'Norwegian' => 'nb-NO',
		'Polish' => 'pl-PL',
		'Portuguese' => 'pt',
		'Portuguese (Brazil)' => 'pt-BR',
		'Romanian' => 'ro-RO',
		'Russian' => 'ru-RU',
		'Slovenian' => 'sl',
		'Spanish' => 'es',
		'Swahili' => 'sw',
		'Swedish' => 'sv-SE',
		'Turkish' => 'tr-TR',
		'Vietnamese' => 'vi'
	);

	static $plugin_allowed_html = array(
		'a' => array(
			'href' => true,
			'rel' => true,
			'name' => true,
			'target' => true,
		),
		'b' => array(),
		'br' => array(),
		'button' => array(
			'class' => true,
			'disabled' => true,
			'name' => true,
			'type' => true,
			'value' => true,
		),
		'div' => array(
			'class' => true,
			'data-*' => true,
			'style' => true
		),
		'em' => array(),
		'i' => array(),
		'input' => array(
			'id' => true,
			'name' => true,
			'placeholder' => true,
			'type' => true,
			'value' => true,
		),
		'label' => array(
			'for' => true,
		),
		'option' => array(
			'value' => true,
			'selected' => true
		),
		'select' => array(
			'name' => true,
			'class' => true
		),
		'textarea' => array(
			'id' => true,
			'name' => true,
			'placeholder' => true
		),
	);

	// in init below after translations loaded
	static $supported_lengths = array();
	static $supported_voices = array();

	static $debugLogDir = null;
	static $debugLogUrl = null;
	static $debugLog = null;

	static function this_plugin_init()
	{
		global $wpdb;
		register_activation_hook(__FILE__, array(__CLASS__, 'activation_hook'));
		add_action('admin_init', array(__CLASS__, 'on_post_forms'));
		add_action('admin_menu', array(__CLASS__, 'admin_menu_action'));
		add_action('admin_footer', array(__CLASS__, 'admin_footer_action'));
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'plugin_action_links_filter'));
		add_action('init', array(__CLASS__, 'init_action'));
		//add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts_action'), 10, 1);
		add_action('wp_ajax_aiwrc_ajax_submission', array(__CLASS__, 'aiwrc_ajax_submission_action'));
		add_action('enqueue_block_editor_assets', array(__CLASS__, 'example_enqueue_editor_assets_action'));

		// these will be used only by a developer by setting self::$testing = true some lines above
		$upload_dir = wp_upload_dir();
		self::$debugLogDir = trailingslashit($upload_dir['basedir']);
		self::$debugLog = self::$debugLogDir . 'ai-writer-contents.txt';
		self::$debugLogUrl = trailingslashit($upload_dir['baseurl']) . 'ai-writer-contents.txt';
	}

	static function init_action()
	{
		//global $pagenow;
		//die($pagenow);

		load_plugin_textdomain( 'aiwrc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		self::$supported_lengths = array(
			__('500 words', 'aiwrc') => '500',
			__('1000 words', 'aiwrc') => '1000',
			__('2000 words', 'aiwrc') => '2000'
		);

		self::$supported_voices = array(
			__('Neutral', 'aiwrc') => 'neutral',
			__('Bold', 'aiwrc') => 'bold',
			__('Casual', 'aiwrc') => 'casual',
			__('Dramatic', 'aiwrc') => 'dramatic',
			__('Excited', 'aiwrc') => 'excited',
			__('Formal', 'aiwrc') => 'formal',
			__('Funny', 'aiwrc') => 'funny',
			__('Informal', 'aiwrc') => 'informal',
			__('Luxury', 'aiwrc') => 'luxury',
			__('Motivational', 'aiwrc') => 'motivational',
			__('Professional', 'aiwrc') => 'professional',
			__('Sarcastic', 'aiwrc') => 'sarcastic',
			__('Secretive', 'aiwrc') => 'secretive',
			__('Witty', 'aiwrc') => 'witty',
		);
	}

	/**
	 * this functions does nothing when self::$testing = false (always is in production)
	 * a dev would set the var to true to enable the logging
	 * @return void
	 */
	static function log()
	{
		if (!self::$testing) {
			return;
		}
		$args = func_get_args();
		foreach ($args as $i => $arg) {
			if ('DELETE' === $arg) {
				wp_delete_file(self::$debugLog);
				continue;
			}
			// "pretty" print if array or object
			if (is_array($arg) || is_object($arg)) {
				$arg = print_r($arg, true);
				$arg = preg_replace("~\r?\n~", PHP_EOL, $arg);
			}
			elseif (is_bool($arg)) {
				$arg = $arg ? 'TRUE' : 'FALSE';
			}
			elseif (is_null($arg)) {
				$arg = 'NULL';
			}
			elseif (is_string($arg)) {
				if ('::' == substr($arg, 0, 2)) {
					self::log('--------------------------------------------------------------------------------');
				}
			}
			// prepend a line of separation if first argument begins with ::
			//!devtest file_put_contents(self::$debugLog, $arg . PHP_EOL, FILE_APPEND);
		}
	}

	static function activation_hook()
	{
	}

	static function options()
	{
		$opts = get_option(self::$options_name);
		if (!is_array($opts)) {
			$opts = array();
		}
		$opts = array_merge(array(
			'user_email' => '',
			'user_id' => '',
			'token' => '',
		), $opts);
		return $opts;
	}

	static function admin_menu_action()
	{
		$parent_slug = self::$slug . '-page-settings';
		add_menu_page(self::$name, self::$name, 'manage_options', $parent_slug, array(__CLASS__, 'menu_settings_callback'));
		$aiwriter_setup = add_submenu_page($parent_slug, __('Setup', 'aiwrc'), __('Setup', 'aiwrc'), 'manage_options', $parent_slug, array(__CLASS__, 'menu_settings_callback'));
		$aiwriter_page = add_submenu_page($parent_slug, __('Generate a post', 'aiwrc'), __('Generate a post', 'aiwrc'), 'manage_options', self::$slug . '-page-writer', array(__CLASS__, 'menu_writer_callback'));

		// selectively load js only for the above page
		add_action('load-' . $aiwriter_setup, array(__CLASS__, 'load_assets_selectively'));
		add_action('load-' . $aiwriter_page, array(__CLASS__, 'load_assets_selectively'));
		add_action('load-post.php', array(__CLASS__, 'load_assets_selectively'));
		add_action('load-post-new.php', array(__CLASS__, 'load_assets_selectively'));
	}

	static function load_assets_selectively()
	{
		self::$assets_context = true;
		add_action('admin_enqueue_scripts', function() {
			$ver = (string)time(); // self::$version;
			wp_enqueue_style(self::$slug . '-style', plugins_url('assets/css/style.css', __FILE__), array(), $ver);
			// https://developer.wordpress.org/reference/functions/wp_add_inline_script/ ??
			wp_register_script(self::$slug . '-main', plugins_url('assets/js/main.js', __FILE__), array('jquery', 'jquery-ui-sortable'), $ver, false); // 'jquery' dep removed due to theme deregistering jquery
			wp_enqueue_script(self::$slug . '-main');
			wp_localize_script(self::$slug . '-main', 'aiwrc_globals', array(
				'ajaxurl' => admin_url('admin-ajax.php')
			));
		}, 10);
	}

	static function example_enqueue_editor_assets_action()
	{
		$ver = (string)time();
		wp_enqueue_script('aiwrc-editor-scripts', plugins_url('build/index.js', __FILE__), array('jquery'), $ver);
	}

	static function admin_footer_action()
	{
		if (!self::$assets_context) {
		    return;
		}
		$opts = self::options();
		$contents_logged_in = !empty($opts['token']);
		if ($contents_logged_in) {
			$html_ksesd = wp_kses( self::get_step1_html('gutenberg'), self::$plugin_allowed_html );
		}
	    ?>
		<div id="aiwrc-editor-popup-wrapper">
			<div id="aiwrc-editor-popup">
				<?php if($contents_logged_in): ?>
					<?php echo $html_ksesd; ?>
				<?php else: ?>
					<div class="aiwrc-editor-login-warn">
						<p><strong><?php esc_html_e('You need to Setup the plugin with your Contents.ai account first.', 'aiwrc'); ?></strong></p>
						<p><a class="button button-primary" href="<?php echo esc_attr( admin_url('admin.php?page=aiwrc-page-settings') ); ?>"><?php esc_html_e('Go to Setup page', 'aiwrc'); ?> &raquo;</a></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php if($contents_logged_in): ?>
		<div id="aiwrc-editor-popup-template-step-1" style="display: none;">
			<?php echo $html_ksesd; ?>
		</div>
		<?php endif; ?>
	    <?php
	}

	/**
	 * Plugin action links.
	 *
	 * Adds action links to the plugin list table
	 *
	 * Fired by `plugin_action_links` filter.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param array $links An array of plugin action links.
	 *
	 * @return array An array of plugin action links.
	 */
	static function plugin_action_links_filter($links)
	{
		$links[] = sprintf('<a href="%1$s">%2$s</a>', admin_url('admin.php?page=' . self::$slug . '-page-settings'), esc_html__('Setup', 'aiwrc'));
		return $links;
	}

	static function messages_echo()
	{
		// notices
		if (!empty(self::$notices)) {
			foreach (self::$notices as $notice) {
      			?><div class="notice notice-success is-dismissible"><p><strong><?php echo esc_html( $notice ); ?></strong></p></div><?php
		    }
			self::$notices = array();
		}
		// errors
		if (!empty(self::$errors)) {
			foreach (self::$errors as $error) {
      			?><div class="notice notice-error is-dismissible"><p><strong><?php echo esc_html( $error ); ?></strong></p></div><?php
		    }
			self::$errors = array();
		}
	}

	static function add_error($msg)
	{
		self::$errors[] = $msg;
	}

	static function add_notice($msg)
	{
		self::$notices[] = $msg;
	}

	static function on_post_forms()
	{
		self::on_maybe_logout();
		self::on_post_form_settings_login();
	}

	static function on_maybe_logout()
	{
	    if (isset($_GET['aiwrcdisconnect']) && $_GET['aiwrcdisconnect']) {
			$opts = self::options();
			$opts['token'] = '';
			update_option(self::$options_name, $opts);
			wp_redirect( admin_url('admin.php?page=aiwrc-page-settings') );
	    }
	}

	static function on_post_form_settings_login()
	{
		if (!isset($_POST[self::$slug . '_form_settings_login'])) {
			return;
		}

		$bail = false;

		// email check
		$_aiwrc_email = isset($_POST['aiwrc_email']) ? sanitize_email($_POST['aiwrc_email']) : '';
		if ('' === $_aiwrc_email) {
			self::add_error(__('Please provide your Contents.ai account email address that you used during registration.', 'aiwrc'));
			$bail = true;
		} elseif (!is_email($_aiwrc_email)) {
			self::add_error(__('The email address you entered is not valid.', 'aiwrc'));
			$bail = true;
		}

		// password check
		$_aiwrc_password = sanitize_text_field($_POST['aiwrc_password']);
		if ('' === $_aiwrc_password) {
			self::add_error(__('The password cannot be empty. Please provide your Contents.ai account password that you chose during registration.', 'aiwrc'));
			$bail = true;
		}

		if ($bail) {
		    return;
		}

		$result = aiwrc_ContentsComAPI::login($_aiwrc_email, $_aiwrc_password);

		if (false === $result[0]) {
			self::add_error(__('Failed to login.', 'aiwrc') . ' ' . $result[1]);
			return;
		}

		$body = $result[1];

		$opts = self::options();
		$opts['token'] = $body['service-api'];
		$opts['user_email'] = $_aiwrc_email;
		$opts['user_id'] = (int)$body['user']['id'];
		update_option(self::$options_name, $opts);
		self::add_notice(__('Successfully connected. You can proceed to generate a post.', 'aiwrc'));
	}

	static function menu_settings_callback()
	{
		$opts = self::options();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( self::$name_long ); ?></h1>
			<h2><?php esc_html_e('Setup', 'aiwrc'); ?></h2>
			<?php self::messages_echo(); ?>

			<?php if(empty($opts['token'])): ?>
			<form method="post" autocomplete="off">
				<input type="hidden" name="<?php echo esc_attr( self::$slug ); ?>_form_settings_login" value="1" />

				<table class="form-table" role="presentation">
				<tbody>

				<tr>
					<th scope="row" colspan="2"><?php esc_html_e('Login to Contents.ai', 'aiwrc'); ?></th>
				</tr>

				<tr>
					<th scope="row"><label for="aiwrc_email"><?php esc_html_e('E-mail', 'aiwrc'); ?></label></th>
					<td><input name="aiwrc_email" type="text" id="aiwrc_email" value="<?php echo esc_attr( $opts['user_email'] ); ?>" class="regular-text" autocomplete="off"></td>
				</tr>

				<tr>
					<th scope="row"><label for="aiwrc_password"><?php esc_html_e('Password', 'aiwrc'); ?></label></th>
					<td><input name="aiwrc_password" type="password" id="aiwrc_password" value="" class="regular-text" autocomplete="off"></td>
				</tr>

				</tbody>
				</table>
				<?php submit_button(__('Connect', 'aiwrc'), 'primary', null); ?>
			</form>
			<?php endif; ?>

			<?php if(!empty($opts['token'])): ?>
				<p><?php esc_html_e('Contents.ai user connected:', 'aiwrc'); ?> <strong><?php echo esc_html( $opts['user_email'] );  ?></strong> <a href="<?php echo esc_attr( admin_url('admin.php?page=aiwrc-page-settings&aiwrcdisconnect=1') ); ?>" class="button"><?php esc_html_e('Disconnect', 'aiwrc'); ?></a></p>

				<p><a class="button button-primary" href="<?php echo esc_attr( admin_url('admin.php?page=aiwrc-page-writer') ); ?>"><?php esc_html_e('Generate a post now', 'aiwrc'); ?> &raquo;</a></p>
				<?php if(self::$testing): ?>
				<p>Token: <?php echo esc_html( $opts['token'] ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<div class="aiwrc-s-box">
				<p>Hello!</p>

				<p>We're excited that you're interested in enhancing your creative process with the <em>Contents.ai</em> plugin, designed to deliver SEO-optimized content. Before using the plugin, you need to create an account on Contents.ai. For full access to all the plugin's capabilities, consider opting for the Advanced plan at <a href="https://www.contents.ai/pricing/" target="_blank">https://www.contents.ai/pricing/</a>. After selecting the plan, simply input your access key into the plugin, and it's all set for use.</p>

				<p>Understanding how the plugin works: start by selecting a title, deciding on the length of the article, either in the standalone plugin or inside Gutenberg’s editor, and setting your preferred tone. In just minutes, you'll receive content that's not only well-written but also crafted with care. The plugin's ability to generate flexible outlines and its use of unique native-language data sets make it a groundbreaking tool for efficient and effective blogging.</p>

				<p>&nbsp;</p>

				<div class="aiwrc-s-logo"><a href="https://www.contents.ai/pricing/" target="_blank"><img src="<?php echo esc_attr( plugin_dir_url(__FILE__) . 'assets/images/contents-ai-ultraviolet.svg' ); ?>" /></a></div>
			</div>

		</div>
		<?php
	}

	static function menu_writer_callback()
	{
		global $wpdb;
		$opts = self::options();
		?>
		<?php if(empty($opts['token'])): ?>
		<p><strong><?php esc_html_e('You need to Setup the plugin with your Contents.ai account first.', 'aiwrc'); ?></strong></p>
		<p><a class="button button-primary" href="<?php echo esc_attr( admin_url('admin.php?page=aiwrc-page-settings') ); ?>"><?php esc_html_e('Go to Setup page', 'aiwrc'); ?> &raquo;</a></p>
		<?php return; endif; ?>

		<div class="wrap">
			<h1><?php echo esc_html( self::$name_long ); ?></h1>
			<h2><?php esc_html_e('Generate a post', 'aiwrc'); ?></h2>
			<?php echo wp_kses( self::get_step1_html('new_post'), self::$plugin_allowed_html ); ?>
		</div>
		<?php
	}

	/**
	 * @param string $destination ; one of 'new_post', 'gutenberg', 'classic'
	 * @return false|string
	 */
	static function get_step1_html($destination = 'new_post')
	{
		global $wpdb;
		$opts = self::options();
		$class = 'aiwrc-for-' . $destination;
		ob_start();
		?>
		<div class="aiwrc-wrap aiwrc-step-1 <?php echo esc_attr( $class ); ?>" data-destination="<?php echo esc_attr( $destination ); ?>">
			<div class="aiwrc-spinner"></div>
			<div class="aiwrc-head">
				<div class="aiwrc-favicon"></div>
				<div class="aiwrc-main-title"><?php esc_html_e('Article Generator', 'aiwrc'); ?></div>
			</div>
			<div class="aiwrc-progress-bar"><div class="aiwrc-progress-bar-filler"></div></div>
			<div class="aiwrc-content">

				<div class="aiwrc-label"><?php esc_html_e('Language', 'aiwrc'); ?></div>
				<div class="aiwrc-input">
					<?php echo wp_kses( aiwrc_AIWRCHelper::getSelectHTML(array('name' => 'language', 'options' => self::$supported_languages, 'selected' => 'en-us')), self::$plugin_allowed_html ); ?>
				</div>

				<div class="aiwrc-label"><?php esc_html_e('Title', 'aiwrc'); ?></div>
				<div class="aiwrc-input">
					<input id="aiwrc-input-title" type="text" name="title" value="" placeholder="<?php esc_attr_e('eg. Delicious Mexican food', 'aiwrc'); ?>" />
				</div>

				<div class="aiwrc-label"><?php esc_html_e('Instructions', 'aiwrc'); ?></div>
				<div class="aiwrc-input">
					<textarea id="aiwrc-input-instructions" name="instructions" placeholder="<?php esc_attr_e('eg. An article about Mexico food cuisine, tradition and recipes', 'aiwrc'); ?>"></textarea>
				</div>

				<div class="aiwrc-label"><?php esc_html_e('Length', 'aiwrc'); ?></div>
				<div class="aiwrc-input">
					<?php echo wp_kses( aiwrc_AIWRCHelper::getSelectHTML(array('name' => 'length', 'options' => self::$supported_lengths, 'selected' => '1000')), self::$plugin_allowed_html ); ?>
				</div>

				<div class="aiwrc-label"><?php esc_html_e('Tone of voice', 'aiwrc'); ?></div>
				<div class="aiwrc-input aiwrc-input-last">
					<?php echo wp_kses( aiwrc_AIWRCHelper::getSelectHTML(array('name' => 'tov', 'options' => self::$supported_voices, 'selected' => 'neutral')), self::$plugin_allowed_html ); ?>
				</div>

			</div>
			<div class="aiwrc-foot">
				<button class="button button-primary submit-step-1"><?php esc_html_e('Generate title and subheadings', 'aiwrc'); ?> &raquo;</button>
			</div>
			<div class="aiwrc-message message-error"></div>
			<div class="aiwrc-message message-info"></div>
		</div>
		<?php
		return ob_get_clean();
	}

//	static function admin_enqueue_scripts_action($hook_suffix)
//	{
//		$screen = get_current_screen();
//		if ($screen->base !== $hook_suffix) {
//		    return;
//		}
//	    $ver = self::$version;
//	    // https://developer.wordpress.org/reference/functions/wp_add_inline_script/ ??
//		wp_register_script(self::$slug . '-main', plugins_url('assets/js/main.js', __FILE__), array(), $ver, false); // 'jquery' dep removed due to theme deregistering jquery
//		wp_enqueue_script(self::$slug . '-main');
//		wp_localize_script(self::$slug . '-main', 'sscnts_globals', array(
//			'ajaxurl' => admin_url('admin-ajax.php'),
//		));
//	}

	/**
	 * all ajax calls when hitting buttons etc
	 *
	 * @return void
	 */
	static function aiwrc_ajax_submission_action()
	{
		//!devtest self::log($_POST);
		$ret = array(); // the object to return to the client at the end as json
		$opts = self::options(); // the plugin options as saved in the database

		$_step = isset($_POST['step']) ? intval($_POST['step']) : -1; // the step in the generation wizard (1 or 2)
		$_title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';

		switch($_step) {
			case 1:

				$_instructions = '';
				if (isset($_POST['instructions'])) {
					$_instructions = sanitize_textarea_field($_POST['instructions']);
				}

				$_language = 'en-us';
				if (isset($_POST['language'])) {
					$tmp = sanitize_text_field($_POST['language']);
					if (in_array($tmp, self::$supported_languages)) {
						$_language = $tmp;
					}
				}

				$_length = '500';
				if (isset($_POST['length'])) {
					$tmp = sanitize_text_field($_POST['length']);
					if (in_array($tmp, self::$supported_lengths)) {
						$_length = $tmp;
					}
				}

				$_tov = 'neutral';
				if (isset($_POST['tov'])) {
					$tmp = sanitize_text_field($_POST['tov']);
					if (in_array($tmp, self::$supported_voices)) {
						$_tov = $tmp;
					}
				}

				$resp = aiwrc_ContentsComAPI::api_article_generator__titles(
					$opts['token'],
					$opts['user_id'],
					$_title,
					$_instructions,
					$_language,
					$_length,
					$_tov
				);

				$ret = $resp;

				break;
			case 2:

				$_paragraphs = isset($_POST['paragraphs']) ? sanitize_text_field($_POST['paragraphs']) : '';
				$_order_detail_id = isset($_POST['order_detail_id']) ? intval($_POST['order_detail_id']) : 0;
				if (0 === $_order_detail_id) {
					$ret = array(false, __('Invalid order detail id', 'aiwrc'));
					break;
				}
				$_destination = (isset($_POST['destination']) && in_array($_POST['destination'], array('new_post', 'gutenberg'))) ? sanitize_text_field($_POST['destination']) : '';
				if ('' === $_destination) {
					$ret = array(false, __('Invalid destination', 'aiwrc'));
					break;
				}

				$resp = aiwrc_ContentsComAPI::api_article_generator__paragraphs(
					$opts['token'],
					$opts['user_id'],
					$_paragraphs,
					$_order_detail_id
				);

				if ($resp[0]) {
					/*
					$resp[1]:
					Array
					(
						[status] => success
						[message] => Sucess!
						[order_id] => 286260
						[order_detail_id] => 301314
						[result] => Italian food is more than just pizza and pasta. ...
					)
					*/
					self::log('api2 response', $resp);

					// destination 'new_post' is when we use the plugin embedded in the dedicated admin page
					if ('new_post' === $_destination) {
						$postID = wp_insert_post(array(
							'post_title' => $_title,
							'post_content' => $resp[1]['result'],
							'post_status' => 'draft'
						));
						if (is_wp_error($postID)) {
							$ret = array(false, 'WP err: ' . $postID->get_error_message());
							break;
						}
						else {
							$ret = array(true, array(
								'post_id' => $postID,
								'edit_post_link' => get_edit_post_link($postID, 'display')
							));
						}
					}

					// destination 'gutenberg' is when we use the plugin in the gutenberg editor
					if ('gutenberg' === $_destination) {
						// transform provided html into an array of p's and h2's
						$html = $resp[1]['result'];
						$lines = explode("\n", $html);
						$lines = array_map('trim', $lines);
						$lines = array_filter($lines);
						// at this point we should have an array of ['blabla', '<h2>qweqwe</h2>', 'blabla2', '<h2>qweqwe2</h2>', ...]
						$data = array();
						foreach ($lines as $line) {
							// heading (h2, h3, ...)
							if (preg_match('~^<h([1-9])>~i', $line, $regs)) {
								$data[] = array(
									'type' => 'heading',
									'text' => wp_strip_all_tags($line),
									'level' => (int)$regs[1]
								);
							}
							// paragraph p
							else {
								$data[] = array(
									'type' => 'paragraph',
									'text' => $line
								);
							}
						}
						$ret = array(true, $data);
					}
				}
				else {
					$ret = $resp; // [false, "message"]
				}
				break;
			default:

				$ret = array(false, __('Invalid step', 'aiwrc'));

		} // switch

		echo wp_json_encode($ret);
		wp_die();
	}
}
aiwrc_AiWriterContentsCom::this_plugin_init();
