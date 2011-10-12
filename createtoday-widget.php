<?php
/*
Plugin Name: CreateToday Widget for WordPress
Plugin URI: http://api.createtoday.com/site/widget?utm_source=wordpress&utm_medium=plugin&utm_campaign=createtoday-widget&utm_content=v1.0
Description: This plugin makes it simple to add the CreateToday Widget to your WordPress blog, allowing your users to create postcards and greeting cards from your site images.
Author: CreateToday
Version: 1.3.1
Requires at least: 2.8
Author URI: http://api.createtoday.com/site/widget
License: GPL
*/

/*
 * Admin User Interface
 */

if ( !class_exists( 'CreateToday_Vars' ) ) {
	class CreateToday_Vars {
		static $unique_id  = 'createtoday-widget-for-wordpress';
		static $filename   = 'createtoday-widget-for-wordpress/createtoday-widget.php';
		static $longname   = 'Createtoday Widget Configuration';
		static $shortname  = 'Createtoday Widget';
		static $homepage   = 'http://api.createtoday.com/site/widget/';
		static $plugin_url = '';

		static $min_size = array(615, 870);
		const CTDWDG_VERSION = '1.0';
	}

	CreateToday_Vars::$plugin_url = plugins_url( '', __FILE__ ).'/';
}

if ( is_admin() && ( !defined('DOING_AJAX') || !DOING_AJAX ) && !class_exists( 'CreateToday_Admin' ) ) {

	class CreateToday_Admin {

		/**
		 * PHP4 Constructor
		 */
		function CreateToday_Admin() {
			$this->__construct();
		}


		/**
		 *	load all required stuff.
		 */
		function __construct() {
			$this->upgrade();

			// Register the settings page
			add_action( 'admin_menu',                  array(&$this, 'register_settings_page') );

			// Register the contextual help for the settings page
			add_action( 'contextual_help',             array(&$this, 'plugin_help'), 10, 2 );

			// Give the settings page a nice icon in Ozh's menu
			add_filter( 'ozh_adminmenu_icon',          array(&$this, 'add_ozh_adminmenu_icon' ) );

			// Give the plugin a settings link in the plugin overview
			add_filter( 'plugin_action_links', 	       array(&$this, 'add_action_link'), 10, 2 );

			// Print Scripts and Styles
			add_action('admin_print_scripts',          array(&$this, 'config_page_scripts') );
			add_action('admin_print_styles',           array(&$this, 'config_page_styles') );

			// Drop a warning on each page of the admin when CreateToday Widget hasn't been configured
			add_action('admin_footer',                 array(&$this, 'warning') );

			// Save settings
			add_action('admin_init',                   array(&$this, 'save_settings') );

			//add_action('save_post',                  array(&$this, 'insert_image_meta'));
			//add_filter( 'wp_insert_post_data',       array(&$this, 'test' ) );

			add_filter( 'attachment_fields_to_edit',   array( &$this, 'view_widget_for_image' ), null, 2 );
			add_filter( 'attachment_fields_to_save',   array( &$this, 'save_widget_for_image' ), null, 2 );

			add_filter( 'image_send_to_editor', array(&$this, 'image_send_to_editor'), 99, 7 );

			add_filter('mce_css', array(&$this, 'add_mce_buttons'));
		}


		function add_mce_buttons($mce_css) {
			if (! empty($mce_css)) $mce_css .= ',';
			$mce_css .= plugins_url('css/mce.css', __FILE__);

			return $mce_css;
		}


		static function image_send_to_editor( $html, $id, $caption, $title, $align, $url, $size) {
			$meta = get_post_meta($id, '_use_ctdwdg', true);
			if ( !empty($meta) && $meta != '-' ) {
				$width = 0;
				$height = 0;
				$matches=array();
				$style = '';
				preg_match('/<img.*?width="(\d+)"/', $html, $matches);
				if (!empty($matches[1])) {
					$style .= 'width: '.((int)$matches[1]+14).'px;';	// 16px = (6px padding and 1px border) twice
				}
				$options = get_option(CreateToday_Vars::$unique_id);
				$btn_style = 'ctd_wdg_btns ctd_wdg_style'.$options['style'];
				if (!empty($options['advstyle'])) {
					$btn_style = $options['advstyle'];
				}

				$html = '<div class="ctd-wdg-img-wrap align'.$align.' size-'.$size.'" '.(!empty($style) ? 'style="'.$style.'"' : '').'>'.$html.' <span class="'.$btn_style.'"> <a class="ctd_wdg_create" href="'.$meta.'" title="send image as card">send image as card</a> <a class="ctd_wdg_shcart" href="#" title="cards in my cart">my cart</a></span></div>';
			}

			return $html;
		}


		/**
		 *	show a checkbox in upload image form to decide if the CreateToday Widget should be available for this image.
		 */
		function view_widget_for_image($form_fields, $post) {
			if (substr($post->post_mime_type, 0, 5) == 'image') {
				$image_details = wp_get_attachment_image_src($post->ID, 'full');
				// if image dimensions are bigger than min accepted dimension
				if (($image_details[1] > CreateToday_Vars::$min_size[0] && $image_details[2] > CreateToday_Vars::$min_size[1]) || ($image_details[1] > CreateToday_Vars::$min_size[1] && $image_details[2] > CreateToday_Vars::$min_size[0])) {
					$meta = get_post_meta($post->ID, '_use_ctdwdg', true);
					$checked = 'checked="checked"';
					if ( !empty($meta) && $meta == '-' ) {
						$checked = '';
					}
					$form_fields['use_ctdwdg'] = array(
						'label' => __('Show postcard links?'),
						'input' => 'html',
						'html'  => '&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" name="attachments['.$post->ID.'][use_ctdwdg]" value="1" '.$checked.' />',
						'value' => 1,
						'helps' => 'You can enable or disable the CreateToday Widget links for this image',
					);
				}
			}

			return $form_fields;
		}


		/**
		 *	add the ctdwdg_imgid metadata to image
		 */
		function save_widget_for_image($post, $attachment) {
			if (substr($post['post_mime_type'], 0, 5) == 'image') {
				if ( isset($attachment['use_ctdwdg']) ) {
					update_post_meta($post['ID'], '_use_ctdwdg', $post['guid']);
				} else {
					// need to use a non empty value here cause get_post_meta() returns '' if key is not set
					update_post_meta($post['ID'], '_use_ctdwdg', '-');
				}
			}

			return $post;
		}


		function register_settings_page() {
			add_options_page(CreateToday_Vars::$longname, CreateToday_Vars::$shortname, 'edit_users', CreateToday_Vars::$unique_id, array(&$this, 'config_page'));
		}


		function plugin_help($contextual_help, $screen_id) {
			if ( $screen_id == 'settings_page_'.CreateToday_Vars::$unique_id ) {

				$contextual_help = '<h2>'.__('Having problems?').'</h2>'.
				'<p>'.sprintf( __("If you're having problems with this plugin, please refer to its <a href='%s'>FAQ page</a>."), 'http://api.createtoday.com/site/widget/help/wordpress' ).'</p>';
			}
			return $contextual_help;
		}


		function upgrade() {
			$options = get_option(CreateToday_Vars::$unique_id);
			if ($options['version'] < CreateToday_Vars::CTDWDG_VERSION) {
				$options['version'] = CreateToday_Vars::CTDWDG_VERSION;
				update_option(CreateToday_Vars::$unique_id, $options);
			}
		}


		function config_page() {
			$options = get_option(CreateToday_Vars::$unique_id);
			if ( isset($options['msg']) ) {
				echo $options['msg'];
			}
			$options['msg'] = '';
			update_option(CreateToday_Vars::$unique_id, $options);

			if ( !isset($options['api_key']) ) {
				$options = $this->set_defaults();
			}

			?>
			<div class="wrap">
				<a href="http://api.createtoday.com/site/widget"><div style="background: url('<?php echo CreateToday_Vars::$plugin_url; ?>images/ctdwdg-wp-icon-32.png') no-repeat;" class="icon32"><br /></div></a>
				<h2><?php _e(CreateToday_Vars::$longname) ?></h2>
				<div class="postbox-container" style="width:65%;">
					<div class="metabox-holder">
						<div class="meta-box-sortables">
							<form action="<?php echo $this->plugin_options_url(); ?>" method="post" id="ctdwdg-wp-conf">
								<input type="hidden" name="plugin" value="<?php echo CreateToday_Vars::$unique_id ?>"/>
								<?php
									wp_nonce_field('ctdwdg-config');

									$content = '<table class="form-table">';
									$content .= '<tr>';
									$content .= '    <th><label for="api_key">Enter your api_key:</label></th>';
									$content .= '    <td><input id="api_key" name="api_key" type="text" size="20" maxlength="40" value="'.$options['api_key'].'"/></td>';
									$content .= '</tr>';
									$content .= '<tr>';
									$content .= '    <td colspan="2"><small>This is the api_key you received when signing up on the CreateToday widget site. <a href="http://api.createtoday.com/site/widget" target="_blank">Sign up now</a> and get your api_key if you didn\'t.</small></td>';
									$content .= '</tr>';
									$content .= '</table>';

									$content .= '<table class="form-table">';
									$content .= '<tr>';
									$content .= '    <th colspan="3"><label>Select a button style:</label></th>';
									$content .= '</tr>';
									$content .= '<tr>';
									$content .= $this->postbox_btn_style(11, $options['style']);
									$content .= $this->postbox_btn_style(1, $options['style']);
									$content .= $this->postbox_btn_style(2, $options['style']);
									$content .= '</tr>';
									$content .= '<tr>';
									$content .= $this->postbox_btn_style(12, $options['style']);
									$content .= $this->postbox_btn_style(3, $options['style']);
									$content .= $this->postbox_btn_style(4, $options['style']);
									$content .= '</tr>';
									$content .= '<tr>';
									$content .= $this->postbox_btn_style(13, $options['style']);
									$content .= $this->postbox_btn_style(5, $options['style']);
									$content .= $this->postbox_btn_style(6, $options['style']);
									$content .= '</tr>';
									$content .= '<tr>';
									$content .= $this->postbox_btn_style(14, $options['style']);
									$content .= $this->postbox_btn_style(7, $options['style']);
									$content .= $this->postbox_btn_style(8, $options['style']);
									$content .= '</tr>';
									$content .= '<tr>';
									$content .= $this->postbox_btn_style(15, $options['style']);
									$content .= $this->postbox_btn_style(9, $options['style']);
									$content .= $this->postbox_btn_style(10, $options['style']);
									$content .= '</tr>';
									$content .= '<tr>';
									$content .= '    <td colspan="3"><small>This is the button that will appear under your images. If you prefer to use a custom button/link style see the Advanced Settings below.</small></td>';
									$content .= '</tr>';
									$content .= '</table>';
									$content .= '<div class="alignright"><input type="submit" class="button-primary" value="'.__('Update Settings &raquo;').'" /></div><br class="clear"/>';

									$this->postbox('ctdwdgsettings', 'CreateToday Widget Settings', $content);

									$content = '<p>If you prefer to give the buttons a custom look instead of the styles above, you can do so by adding your own css class to the span that wraps the 2 buttons. This is, however, <strong>optional</strong>. Do it only if you know how to use css to style buttons.</p>';
									$content .= '<p>The html for the buttons is as follows:</p>';
									$content .= '<pre>&lt;span class="your-class-here">'."\n";
									$content .= '    &lt;a class="ctd_wdg_create" href="#">send as card&lt;/a>'."\n";
									$content .= '    &lt;a class="ctd_wdg_shcart" href="#">my cart&lt;/a>'."\n";
									$content .= '&lt;/span></pre>';
									$content .= '<p>Enter the name of your css class in the field below then, in your css file, style against <code>.your-class-here .ctd_wdg_create</code> and <code>.your-class-here .ctd_wdg_shcart</code>.</p>';
									$content .= '<table class="form-table">';
									$content .= '<tr>';
									$content .= '    <th><label for="advstyle">Custom button style:</label></th>';
									$content .= '    <td><input id="advstyle" name="advstyle" type="text" size="20" value="'.$options['advstyle'].'"/></td>';
									$content .= '</tr>';
									$content .= '</table>';
									$content .= '<div class="alignright"><input type="submit" class="button-primary" value="'.__('Update Settings &raquo;').'" /></div><br class="clear"/>';

									$this->postbox('ctdwdgadvanced', 'Advanced Settings', $content);
								?>
							</form>
						</div>
					</div>
				</div>

				<div class="postbox-container side" style="width:20%;">
					<div class="metabox-holder">
						<div class="meta-box-sortables">
							<?php
								$content = '<p>Why not do any or all of the following:</p>';
								$content .= '<ul>';
								$content .= '<li><a href="'.CreateToday_Vars::$homepage.'">Link to it so other folks can find out about it.</a></li>';
								$content .= '<li><a href="http://wordpress.org/extend/plugins/'.CreateToday_Vars::$unique_id.'/">Give it a good rating on WordPress.org.</a></li>';
								$content .= '<li><a href="http://wordpress.org/extend/plugins/'.CreateToday_Vars::$unique_id.'/">Let other people know that it works with your WordPress setup.</a></li>';
								$content .= '</ul>';
								$this->postbox(CreateToday_Vars::$unique_id.'-like', 'Like this plugin?', $content);

								$content = '<p>If you\'ve found a bug in this plugin, please submit it <a href="mailto:support@createtoday.com?Subject=wp%20plugin%20bug">by email</a> with a clear description.</p>';
								$this->postbox(CreateToday_Vars::$unique_id.'-support', 'Found a bug?', $content);
							?>
						</div>
						<br/><br/><br/>
					</div>
				</div>
			</div>
			<?php
		}


		function postbox_btn_style($num, $style) {
			$content = '    <td class="ctd-btn-choices">';
			$content .= '        <p><label><input type="radio" name="style" value="'.$num.'" '.($num == $style ? 'checked="checked"' : '').'><img src="http://st.ctast.com/site/images/widget/buttons/'.$num.'.png"></label>';
			$content .= '    </td>';
			return $content;
		}


		function save_settings() {
			$options = get_option( CreateToday_Vars::$unique_id );

			if ( isset($_POST['plugin']) && $_POST['plugin'] == CreateToday_Vars::$unique_id) {
				if (!current_user_can('manage_options')) {
					die(__('You cannot edit the CreateToday Widget for WordPress options.'));
				}
				check_admin_referer('ctdwdg-config');

				if (!empty($_POST['api_key'])) {
					$options['api_key'] = $_POST['api_key'];
				}

				if (!empty($_POST['style'])) {
					$options['style'] = (int)$_POST['style'];
				}

				if (isset($_POST['advstyle'])) {
					$options['advstyle'] = $_POST['advstyle'];
				}

				$cache = '';
				if ( function_exists('w3tc_pgcache_flush') ) {
					w3tc_pgcache_flush();
					w3tc_dbcache_flush();
					w3tc_minify_flush();
					w3tc_objectcache_flush();
					$cache = ' and <strong>W3TC Caches cleared</strong>';
				} else if ( function_exists('wp_cache_clear_cache') ) {
					wp_cache_clear_cache();
					$cache = ' and <strong>WP Super Cache cleared</strong>';
				}

				$options['msg'] = "<div id=\"updatemessage\" class=\"updated fade\"><p>CreateToday Widget for Wordpress <strong>settings updated</strong>$cache.</p></div>\n";
				$options['msg'] .= "<script type=\"text/javascript\">setTimeout(function(){jQuery('#updatemessage').hide('slow');}, 3000);</script>";
			}
			update_option(CreateToday_Vars::$unique_id, $options);
		}


		function set_defaults() {
			$options = array(
				'api_key' => '',
				'style' => 11,
				'advstyle' => '',
				'version' => CreateToday_Vars::CTDWDG_VERSION,
			);
			update_option(CreateToday_Vars::$unique_id, $options);
			return $options;
		}


		function warning() {
			$options = get_option(CreateToday_Vars::$unique_id);
			if (empty($options['api_key'])) {
				echo "<div id='message' class='error'><p><strong>The CreateToday Widget is not active.</strong> You must <a href='".$this->plugin_options_url()."'>enter your CreateToday api_key</a> before it can work.</p></div>";
			}
		}


		function add_ozh_adminmenu_icon( $hook ) {
			if ($hook == CreateToday_Vars::$unique_id) {
				return CreateToday_Vars::$plugin_url.'images/ctdwdg-wp-icon-16.png';
			}
			return $hook;
		}


		/**
		 * Add a link to the settings page to the plugins list
		 */
		function add_action_link( $links, $file ) {
			static $this_plugin;
			if( empty($this_plugin) ) {
				$this_plugin = CreateToday_Vars::$filename;
			}
			if ( $file == $this_plugin ) {
				$settings_link = '<a href="' . $this->plugin_options_url() . '">' . __('Settings') . '</a>';
				array_unshift( $links, $settings_link );
			}
			return $links;
		}


		function config_page_scripts() {
			if ( isset($_GET['page']) && $_GET['page'] == CreateToday_Vars::$unique_id ) {
				wp_enqueue_script('postbox');
				wp_enqueue_script('dashboard');
				//wp_enqueue_script('thickbox');
				//wp_enqueue_script('media-upload');
			}
		}


		function config_page_styles() {
			if ( isset($_GET['page']) && $_GET['page'] == CreateToday_Vars::$unique_id ) {
				wp_enqueue_style('dashboard');
				wp_enqueue_style('global');
				wp_enqueue_style('wp-admin');
				wp_enqueue_style('ctd-wdg-css', plugins_url('css/wp-admin.css', __FILE__), array(), CreateToday_Vars::CTDWDG_VERSION);
			}
		}


		/**
		 * Create a postbox widget
		 */
		function postbox($id, $title, $content) {
			?>
			<div id="<?php echo $id; ?>" class="postbox">
				<div class="handlediv" title="Click to toggle"><br /></div>
				<h3 class="hndle"><span><?php echo $title; ?></span></h3>
				<div class="inside">
					<?php echo $content; ?>
				</div>
			</div>
			<?php
		}


		function plugin_options_url() {
			return admin_url( 'options-general.php?page='.CreateToday_Vars::$unique_id );
		}
	} // end class CreateToday_Admin

	$ctdwdg_admin = new CreateToday_Admin();
} //endif


/**
 * Code that actually inserts stuff into pages. Show it only on non-admin pages
 */
if ( !is_admin() ) {
	if ( ! class_exists( 'CreateToday_Filter' ) ) {
		class CreateToday_Filter {
			/*
			 * Insert the embed code into the page
			 */
			static function spool_widget() {
				$options  = get_option(CreateToday_Vars::$unique_id);

				// no point in loading this on a 404 page
				if ( !is_404() ) {
					if ( empty($options['api_key']) ) {
						if ( current_user_can('manage_options') ) {
							echo "<!-- CreateToday Widget code not shown because you haven't entered your api_key in settings yet. -->\n";
						}
						return;
					}
?><script type="text/javascript">
	// CreateToday Widget for WordPress v<?php echo CreateToday_Vars::CTDWDG_VERSION;  ?> | http://api.createtoday.com/site/widget/
	var ct_api_key = '<?php echo $options['api_key'] ?>';
	var ct_exuid = null;
	(function() {
	var ct = document.createElement('script'); ct.type = 'text/javascript'; ct.async = true;
	ct.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'w.createtoday.com/static/widget/js/o.js';
	var h = document.getElementsByTagName('head')[0]; h.appendChild(ct);
	})();
</script><?php
				}
			}
		} // class CreateToday_Filter
	}

	add_action( 'wp_head', array('CreateToday_Filter', 'spool_widget'), 2 );
	wp_enqueue_style('ctd-wdg-css', plugins_url('css/wp.css', __FILE__), array(), CreateToday_Vars::CTDWDG_VERSION);
}
