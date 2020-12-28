<?php
/* --------------------------------------------------
Plugin Name: WP Imsizer
Plugin URI: https://endurtech.com/wp-imsizer-wordpress-plugin/
Description: Auto resize and convert uploaded images to a set max height/width or file type. Also, limits file size of image uploads and disables WordPress (since v5.3) image 2560px threshold limit.
Author: Manny Rodrigues
Author URI: https://endurtech.com
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 5.3
Tested up to: 5.6
Version: 1.1.2
Text Domain: wp-imsizer
Domain Path: /locale

Special Thanks/Acknowledgements:
+ Resize Image After Upload - https://wordpress.org/plugins/resize-image-after-upload/
+ Imsanity - https://wordpress.org/plugins/imsanity/

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

NOTEPAD:
=====================================================
no bmp or gif conversion or resize, check for such an image and ignore

// FEATURE 2
// Limit Image Upload File Size
add_filter( 'wp_handle_upload_prefilter', 'prefilter_image_size' );
function prefilter_image_size( $file )
{
  $image_size  = $file['size']/1024; // Calculate image size in KB
  $image_type  = strpos( $file['type'], 'image' ); // Check if file is an image
  $image_limit = 500; // File size limit in KB's
  // If image file size exceeds our limit, display notice and prevent upload.
  if ( ( $image_size > $image_limit ) && ( $image_type !== false ) )
  {
    $file['error'] = 'Your image file size must be smaller than ' . $image_limit . 'KB.';
  }
  return $file;
}

// FEATURE 3
// Gravity Forms Image Upload Resizer. Replace _1 with your Form ID
add_action( "gform_after_submission_2", "gf_resize_images", 10, 2 );
function gf_resize_images( $entry, $form )
{
	// Replace 2 with field ID of upload field
	$url =  $entry[1];
	$parsed_url = parse_url( $url );
	$path = $_SERVER['DOCUMENT_ROOT'] . $parsed_url['path'];
	$image = wp_get_image_editor( $path );
	if ( ! is_wp_error( $image ) )
	{
		// Replace 800,600 with desired dimensions. If smaller, no crop applied.
		$result = $image->resize( 800, 600, false );
		$result = $image->save($path);
	}
}

-------------------------------------------------- */

if( ! defined( 'ABSPATH' ) )
{
  exit(); // No direct access
}

$PLUGIN_VERSION = '1.1.2';
$DEBUG_LOGGER = false;
//define( 'WPIMSIZER_TITLE', 'WP Imsizer' ); // Title
//define( 'WPIMSIZER_TITLE_OPTIONS', 'WP Imsizer Options' ); // Settings/Options page title 
//define( 'WPIMSIZER_VERSION', '1.1.0' );
//define( 'WPIMSIZER_DEBUG_LOGGER', false );

// WordPress plugin registering default values
add_action( 'admin_init', 'wp_imsizer_register' );
function wp_imsizer_register()
{
  //add_option( 'wp_imsizer_kill_wp_limit', '0', '' );
  add_option( 'wp_imsizer_wplimit_onoff', '0', '' );
  
  //add_option( 'wp_imsizer_onoff', '0', '' );
  add_option( 'wp_imsizer_onoff', 'no', '' );
  add_option( 'wp_imsizer_width', '1200', '' );
  add_option( 'wp_imsizer_height', '1200', '' );
  
  //add_option( 'wp_imsizer_restrict_size', '0', '' );
  add_option( 'wp_imsizer_restrict_size', 'no', '' );
  add_option( 'wp_imsizer_max_file_size', '500', '' );
  add_option( 'wp_imsizer_file_size_error', 'Your image file size must be smaller than 500KB.', '' );
  
  //add_option( 'wp_imsizer_png2jpg', '0', '' );
  add_option( 'wp_imsizer_convertpng_yesno', 'no', '' );
  
}

// WP Imsizer Plugin Deactivation database cleanup. You're welcome!
register_deactivation_hook( __FILE__, 'wp_imsizer_deactivation_cleaner' );
function wp_imsizer_deactivation_cleaner()
{
  delete_option( 'wp_imsizer_wplimit_onoff' );
  
  delete_option( 'wp_imsizer_onoff' );
  delete_option( 'wp_imsizer_width' );
  delete_option( 'wp_imsizer_height' );
  
  delete_option( 'wp_imsizer_restrict_size' );
  delete_option( 'wp_imsizer_max_file_size' );
  delete_option( 'wp_imsizer_file_size_error' );
  
  //delete_option( 'wp_imsizer_png2jpg' );
  delete_option( 'wp_imsizer_convertpng_yesno' );
}

// Hook in the options page
add_action( 'admin_menu', 'wp_imsizer_options_page' );
// Hook the function to the upload handler
add_action( 'wp_handle_upload', 'wp_imsizer_upload_resize' );

// Plugin settings page
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wp_imsizer_plugin_links' );

// Filter WordPress image size threshold setting
// If image upload exceeds 2560px (width/height) WordPress auto scales it down keeping the original (inaccessible).
$wp_imsizer_wplimit_onoff = get_option( 'wp_imsizer_wplimit_onoff' );
if( $wp_imsizer_wplimit_onoff == '1' )
{
  add_filter( 'big_image_size_threshold', '__return_false' );
}

// Add ths link to Settings in Plugins page
function wp_imsizer_plugin_links( $links )
{
  $settings_link = '<a href="options-general.php?page=wp-imsizer">Settings</a>';
  array_unshift( $links, $settings_link );
  return $links;
}

// Options Page
function wp_imsizer_options_page( )
{
  global $wp_imsizer_settings_page;
  if( function_exists( 'add_options_page' ) )
  {
    $wp_imsizer_settings_page = add_options_page(
      'WP Imsizer Options',
			'WP Imsizer',
			'manage_options',
			'wp-imsizer',
			'wp_imsizer_options'
		);
	}
}

// Define the Options page for the plugin
function wp_imsizer_options( )
{
  if( isset( $_POST['wp_imsizer_options_update'] ) )
  {
    if( ! ( current_user_can( 'manage_options' ) && wp_verify_nonce( $_POST['_wpnonce'], 'wp-imsizer-options-update' ) ) )
    {
      wp_die( "Not authorized" );
    }

    // Set plugin variables
    $threshold_option = ( $_POST['wp_imsizer_wplimit'] == '1' ? '1' : '0' );

    $resizing_enabled = ( $_POST['yesno'] == 'yes' ? 'yes' : 'no' );
    
    $max_width   = intval( $_POST['maxwidth'] );
    $max_height  = intval( $_POST['maxheight'] );
    
    $convert_png_to_jpg = ( isset( $_POST['convertpng'] ) && $_POST['convertpng'] == 'yes' ? 'yes' : 'no' );

    // Validate width input integer, or use prior setting
    $max_width = ( $max_width == '' ) ? 0 : $max_width;
    $max_width = ( ctype_digit( strval( $max_width ) ) == false ) ? get_option( 'wp_imsizer_width' ) : $max_width;
    update_option( 'wp_imsizer_width', $max_width );

    // Validate height input integer, or use prior setting
    $max_height = ( $max_height == '' ) ? 0 : $max_height;
    $max_height = ( ctype_digit( strval( $max_height ) ) == false ) ? get_option( 'wp_imsizer_height' ) : $max_height;
    update_option( 'wp_imsizer_height', $max_height );

    // On/Off threshold limit
    ( $threshold_option == '1' ) ? update_option( 'wp_imsizer_wplimit_onoff', '1' ) : update_option( 'wp_imsizer_wplimit_onoff', '0' );
    // On/Off power switch
    ( $resizing_enabled == 'yes' ) ? update_option( 'wp_imsizer_onoff', 'yes' ) : update_option( 'wp_imsizer_onoff', 'no' );
    // Convert PNG
    ( $convert_png_to_jpg == 'yes' ) ? update_option( 'wp_imsizer_convertpng_yesno', 'yes' ) : update_option( 'wp_imsizer_convertpng_yesno', 'no' );

    // Saved Notification
    echo( '<div id="message" class="updated fade" style="clear:both;"><p><strong>All options have been saved.</strong></p></div> ');
  }
  
  // Get options, show settings form
  $kill_wp_limit      = get_option( 'wp_imsizer_wplimit_onoff' );

  $resizing_enabled   = get_option( 'wp_imsizer_onoff' );
  $max_width          = get_option( 'wp_imsizer_width' );
  $max_height         = get_option( 'wp_imsizer_height' );
  
  $restrict_size  = get_option( 'wp_imsizer_restrict_size' );
  $max_file_size  = get_option( 'wp_imsizer_max_file_size' );
  $max_file_error = get_option( 'wp_imsizer_file_size_error' );
  
  $convert_png_to_jpg = get_option( 'wp_imsizer_convertpng_yesno' );

  // Options Page Stylesheet
  echo '<style type="text/css"></style>';
?>
<div class="wrap">
  <div style="padding:20px 0px 20px 20px;">
    <img src="<?php echo plugins_url( 'wp-imsizer-logo.png', __FILE__ ); ?>" style="max-width:80px; width:100%; height:auto; float:left;" />
    <h1 style="float:left; padding:20px 0px 0px 20px;">WP Imsizer Options</h1>
    <div style="clear:both;"></div>
  </div>

	<hr style="margin-bottom:20px;">
	<h3>Image Resizing Uploads</h3>

	<form method="post" accept-charset="utf-8">

    <?php ( $kill_wp_limit == '1' ) ? $wp_imsizer_wplimit_select = 'checked="checked" ' : $wp_imsizer_wplimit_select = ''; ?>
		<table class="form-table">
			<tr>
				<th scope="row">Remove Threshold Limit?</th>
				<td valign="top">
          <input name="wp_imsizer_wplimit" id="wp_imsizer_wplimit" type="checkbox" value="1" <?php echo $wp_imsizer_wplimit_select; ?>/> <label for="wp_imsizer_wplimit">Disable WordPress default 2560px image limit.</em></label>
				</td>
			</tr>
    </table>
    
    <?php /*( $resizing_enabled == '1' ) ? $wp_imsizer_resize_select = 'checked="checked" ' : $wp_imsizer_resize_select = '';*/ ?>
		<table class="form-table">
			<tr>
				<th scope="row">Resize Uploaded Images?</th>
				<td valign="top">
					<select name="yesno" id="yesno">
						<option value="no" <?php echo ( $resizing_enabled == 'no' ) ? 'selected="selected"' : ''; ?>>No</option>
						<option value="yes" <?php echo ( $resizing_enabled == 'yes' ) ? 'selected="selected"' : ''; ?>>Yes</option>
					</select>
          <p class="description"><strong>Only new image uploads are resized.</strong></p>
				</td>
			</tr>
			<tr>
				<th scope="row">Set Max Image Size</th>
				<td>
					<fieldset>
						<label for="maxwidth">Max width</label>
						<input name="maxwidth" step="1" min="0" id="maxwidth" class="small-text" type="number" value="<?php echo $max_width; ?>" />
            <span style="padding:0px 10px;"></span>
						<label for="maxheight">Max height</label>
						<input name="maxheight" step="1" min="0" id="maxheight" class="small-text" type="number" value="<?php echo $max_height; ?>" />
						<p class="description"><strong>Set to zero (0) to prevent resizing in that dimension.</strong></p>
					</fieldset>
				</td>
			</tr>
    </table>
    <!--
		<hr style="margin:20px 0px;">
		<h3>Limit Large Image Uploads</h3>

		<table class="form-table">
			<tr>
				<th scope="row">Restrict Image File Size?</th>
				<td valign="top">
					<select name="yesno" id="yesno">
						<option value="no" <?php echo ( $resizing_enabled == 'no' ) ? 'selected="selected"' : ''; ?>>No</option>
						<option value="yes" <?php echo ( $resizing_enabled == 'yes' ) ? 'selected="selected"' : ''; ?>>Yes</option>
					</select>
          <p class="description"><strong>Only new image uploads will be restricted in file size.</strong></p>
				</td>
			</tr>
			<tr>
				<th scope="row">Set Max File Size</th>
				<td>
					<fieldset>
						<input name="maxsize" step="1" min="0" id="maxsize" class="small-text" type="number" value="<?php echo $max_file_size; ?>" /> in KB.
						<p class="description"><strong>Prevents image file uploads exceeding  upload and display error notice to user.</p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row">File Size Error Message</th>
				<td>
					<fieldset>
						<input name="maxsizeerror" id="maxsizeerror" class="regular-text" type="text" value="<?php echo $max_file_error; ?>" />
						<p class="description"><strong>Error message shown to user if file size exceeds maximum set above.</strong></p>
					</fieldset>
				</td>
			</tr>
    </table>
    -->
		<hr style="margin:20px 0px;">
		<h3>Image Conversion Options</h3>
		
    <p>Enable PNG to JPG conversion of PNG images that <strong>don't have a transparency layer</strong>.<br />
		Conversion will occur on all suitable PNG images, reguardless if resizing is need.</p>
		
    <table class="form-table">
      <tr>
        <th scope="row">Convert PNG to JPG</th>
        <td>
          <select id="convert-png" name="convertpng">
            <option value="no" <?php if( $convert_png_to_jpg == 'no' ) : echo 'selected=selected'; endif; ?>>No</option>
            <option value="yes" <?php if( $convert_png_to_jpg == 'yes' ) : echo 'selected=selected'; endif; ?>>Yes</option>
          </select>
        </td>
      </tr>
		</table>
    
		<hr style="margin-top:30px;">
		<p class="submit" style="margin-top:10px;border-top:1px solid #eee;padding-top:20px;">
      <input type="hidden" name="action" value="update" />
      <?php wp_nonce_field( 'wp-imsizer-options-update' ); ?>
		  <input id="submit" name="wp_imsizer_options_update" class="button button-primary" type="submit" value="SAVE OPTIONS">
		</p>
    
	</form>
</div>
<?php
}

/*
** This function will apply changes to the uploaded file
** @param $image_data - contains file, url, type
*/
function wp_imsizer_upload_resize( $image_data )
{
  wp_imsizer_error_log( "**-start--resize-image-upload" );
  $resizing_enabled = get_option( 'wp_imsizer_onoff' );
  $resizing_enabled = ( $resizing_enabled == 'yes' ) ? true : false;
  $max_width  = get_option( 'wp_imsizer_width ') == 0 ? false : get_option( 'wp_imsizer_width' );
  $max_height = get_option( 'wp_imsizer_height ') == 0 ? false : get_option( 'wp_imsizer_height' );
  $convert_png_to_jpg = get_option( 'wp_imsizer_convertpng_yesno' );
	$convert_png_to_jpg = ( $convert_png_to_jpg == 'yes') ? true : false;
  if( $convert_png_to_jpg && $image_data['type'] == 'image/png' )
  {
    $image_data = wp_imsizer_convert_image( $image_data );
  }
  if( $image_data['type'] == 'image/gif' && is_ani( $image_data['file'] ) )
  {
    //animated gif, don't resize
    wp_imsizer_error_log( "--animated-gif-not-resized" );
    return $image_data;
  }
  if( $resizing_enabled )
  {
		$fatal_error_reported = false;
		$valid_types = array( 'image/gif', 'image/png', 'image/jpeg', 'image/jpg' );
    if( empty( $image_data['file'] ) || empty( $image_data['type'] ) )
    {
    	wp_imsizer_error_log("--non-data-in-file-( ".print_r( $image_data, true )." )");
		  $fatal_error_reported = true;
    }
    else if( ! in_array( $image_data['type'], $valid_types ) )
    {
    	wp_imsizer_error_log("--non-image-type-uploaded-( ".$image_data['type']." )");
		  $fatal_error_reported = true;
    }
    wp_imsizer_error_log("--filename-( ".$image_data['file']." )");
    $image_editor = wp_get_image_editor( $image_data['file'] );
    $image_type = $image_data['type'];
    if( $fatal_error_reported || is_wp_error( $image_editor ) )
    {
      wp_imsizer_error_log("--wp-error-reported");
    }
    else
    {
      $to_save = false;
      $resized = false;
      // Perform resizing if required
      if( $resizing_enabled )
      {
        wp_imsizer_error_log("--resizing-enabled");
        $sizes = $image_editor->get_size();
        if( ( isset( $sizes['width'] ) && $sizes['width'] > $max_width ) || ( isset( $sizes['height'] ) && $sizes['height'] > $max_height ) )
        {
          $image_editor->resize( $max_width, $max_height, false );
          $resized = true;
          $to_save = true;
          $sizes = $image_editor->get_size();
          wp_imsizer_error_log("--new-size--".$sizes['width']."x".$sizes['height']);
        }
        else
        {
          wp_imsizer_error_log("--no-resizing-needed");
        }
      }
      else
      {
        wp_imsizer_error_log("--no-resizing-requested");
      }
      // Only save image if it has been resized or need recompressing
      if( $to_save )
      {
        $saved_image = $image_editor->save( $image_data['file'] );
        wp_imsizer_error_log("--image-saved");
      }
      else
      {
        wp_imsizer_error_log("--no-changes-to-save");
      }
    }
  }
  else
  {
    wp_imsizer_error_log("--no-action-required");
  }
  wp_imsizer_error_log("**-end--resize-image-upload\n");
  return $image_data;
}

function wp_imsizer_convert_image( $params )
{
  $transparent = 0;
  $image = $params['file'];
  $contents = file_get_contents( $image );
  if( ord ( file_get_contents( $image, false, null, 25, 1 ) ) & 4 ) $transparent = 1;
  if( stripos( $contents, 'PLTE' ) !== false && stripos( $contents, 'tRNS' ) !== false ) $transparent = 1;
  $transparent_pixel = $img = $bg = false;
  if( $transparent )
  {
    $img = imagecreatefrompng( $params['file'] );
    $w = imagesx( $img ); // Get the width of the image
    $h = imagesy( $img ); // Get the height of the image
    //run through pixels until transparent pixel is found:
    for( $i = 0; $i < $w; $i++ )
    {
      for( $j = 0; $j < $h; $j++ )
      {
        $rgba = imagecolorat( $img, $i, $j );
        if( ( $rgba & 0x7F000000 ) >> 24 )
        {
          $transparent_pixel = true;
          break;
        }
      }
    }
  }
  if( !$transparent || !$transparent_pixel )
  {
    if( !$img ) $img = imagecreatefrompng( $params['file'] );
    $bg = imagecreatetruecolor( imagesx( $img ), imagesy( $img ) );
    imagefill( $bg, 0, 0, imagecolorallocate( $bg, 255, 255, 255 ) );
    imagealphablending( $bg, 1 );
    imagecopy( $bg, $img, 0, 0, 0, 0, imagesx( $img ), imagesy( $img ) );
    $newPath = preg_replace( "/\.png$/", ".jpg", $params['file'] );
    $newUrl = preg_replace( "/\.png$/", ".jpg", $params['url'] );
    for( $i = 1; file_exists( $newPath ); $i++ )
    {
      $newPath = preg_replace( "/\.png$/", "-".$i.".jpg", $params['file'] );
    }
    if( imagejpeg( $bg, $newPath ) )
    {
      unlink( $params['file'] );
      $params['file'] = $newPath;
      $params['url']  = $newUrl;
      $params['type'] = 'image/jpeg';
    }
  }
  return $params;
}

/*
function is_ani( $filename )
{
  if( !( $fh = @fopen( $filename, 'rb' ) ) )
  {
    return false;
  }
  $count = 0;
  //an animated gif contains multiple "frames", with each frame having a
  //header made up of:
  // * a static 4-byte sequence (\x00\x21\xF9\x04)
  // * 4 variable bytes
  // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)
  // We read through the file til we reach the end of the file, or we've found
  // at least 2 frame headers
  $chunk = false;
  while( !feof( $fh ) && $count < 2 )
  {
    //add the last 20 characters from the previous string, to make sure the searched pattern is not split.
    $chunk = ( $chunk ? substr( $chunk, -20 ) : "" ) . fread( $fh, 1024 * 100 ); //read 100kb at a time
    $count += preg_match_all( '#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches );
  }
  fclose( $fh );
  return $count > 1;
}
*/

// Output debug log to file if debug is enabled
function wp_imsizer_error_log( $message )
{
  global $DEBUG_LOGGER;
  if( $DEBUG_LOGGER )
  {
    error_log( print_r( $message, true ) );
  }
}

// Thank you for checking out my code. Let me know how I can improve it!
?>