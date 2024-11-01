<?php
/*
Plugin Name: User Profile Page
Plugin URI: http://gengar.org
Description: Creates a 'user profile page' that is accessed by clicking a user's avatar or name on posts or comments.
Version: 0.0.5
Author: Austin Witt
Author URI: http://gengar.org
License: GPLv2 or later
*/

/*  Copyright 2010  Austin Witt  (email : witt.austin@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * A WordPress plugin to add a 'profile page' for each user. They can add text to their page, and choose from several account statistics to display.
 * 
 * @package User-Profile-Pages
 */
/**
 * The Plugin's name. Used whenever it references itself.
 */
define( 'UP_PLUGIN_NAME','user_profile_pages' );

load_plugin_textdomain( UP_PLUGIN_NAME, null, basename(dirname(__FILE__)) . '/lang/' );


/**
 * Installs and initializes the 'user-profile-pages' plugin.
 *
 * Sets up options in wp_options database table, and creates the 'user profile' WordPress Page that the plugin needs.
 * If the plugin is being re-activated, and 'restore defaults on re-activation' was checked, this will restore settings to the default values.
 *
 * Hooks on plugin activation (register_activation_hook(..))
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 */
function up_install() {

    global $wpdb;

    $up_page_title = __( 'User Profile', UP_PLUGIN_NAME );
    $up_page_name = __( 'user-profile', UP_PLUGIN_NAME );
    $up_page_id = 0;

    delete_option( 'up_profile_page_title' );
    update_option( 'up_profile_page_title', $up_page_title );

    delete_option( 'up_profile_page_name' );
    update_option( 'up_profile_page_name' , $up_page_name );

    delete_option( 'up_profile_page_id' );
    update_option( 'up_profile_page_id', '0' );

    $up_page = get_page_by_title( $up_page_title );

    if ( ! $up_page ) {
        $_p = array();
        $_p[ 'post_title' ]     = $up_page_title;
        $_p[ 'post_content' ]   = __("WordPress needs this page to exist in order to display user profiles. However, nothing you type here will show up, as the user profile plugin will create the user profile pages for you.", UP_PLUGIN_NAME );
        $_p[ 'post_status' ]    = 'publish';
        $_p[ 'post_type' ]      = 'page';
        $_p[ 'comment_status' ] = 'closed';
        $_p[ 'ping_status' ]    = 'closed';
        $_p[ 'post_category' ]  = array(1);

        $up_page_id = wp_insert_post( $_p );
        $up_page = get_page_by_title( $up_page_title );
        $up_page->post_content = $_p[ 'post_content' ] . "\nID: $up_page_id";

        wp_update_post($up_page);

    } else {
        $up_page_id = $up_page->ID;
        $up_page->post_status = 'publish';
        $up_page_id = wp_update_post( $up_page );
    }

    delete_option( 'up_profile_page_id' );
    update_option( 'up_profile_page_id', $up_page_id );

    // available statistics

    update_option( 'up_profile_stats', 'age,posts,comments,userlevel' );
    $stat_name_string = __('Age', UP_PLUGIN_NAME ).','.__('Post Count', UP_PLUGIN_NAME ).','.__('Comment Count', UP_PLUGIN_NAME ).','.__('User Level', UP_PLUGIN_NAME );
    update_option( 'up_profile_stat_names', $stat_name_string );

    // defaults
    
    $tmp = get_option( 'up_options' );
    if( ( 'on' == $tmp[ 'restore_defaults_on_reactivate' ] ) || !is_array( $tmp ) ) {
        update_option( 'up_options', array( 'restore_defaults_on_reactivate'=>'off', 'complete_uninstall_on_deactivate'=>'off', 'stat_age'=>'on', 'stat_posts'=>'on', 'stat_comments'=>'on', 'stat_userlevel'=>'on' ) );
    }

}
register_activation_hook( __FILE__, 'up_install' );

/**
 * Removes the 'user-profile-pages' plugin.
 *
 * Deletes UPP-specific rows from the wp_options table, and deletes the "User Profile" page that the plugin created.
 *
 * Hooks on deactivation (register_deactivation_hook(...))
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 */
function up_remove() {

    global $wpdb;

    $up_options = get_option( 'up_options' );

    if( $up_options[ 'complete_uninstall_on_deactivate' ] ) {

        $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE meta_key = %s OR meta_key REGEXP %s", 'up_profile_page_text', 'up_stat_' ) );
    }

    $up_page_title = get_option( 'up_profile_page_title' );
    $up_page_name = get_option( 'up_profile_page_name' );
    $up_page_id = get_option( 'up_profile_page_id' );

    if( $up_page_id ) {

        wp_delete_post( $up_page_id );
    }

    delete_option( 'up_profile_page_title' );
    delete_option( 'up_profile_page_name' );
    delete_option( 'up_profile_page_id' );
    delete_option( 'up_profile_stat_names' );
    delete_option( 'up_profile_stats' );
    delete_option( 'up_options' );
}
register_deactivation_hook( __FILE__, 'up_remove' );


/**
 * Adds 'username' to the list of query vars that WordPress will register.
 *
 * Hooks on filter: query_vars
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 * @param       Array       $qvars      WordPress' list of query variables
 * @return      Array                   Modified $qvars array with 'username' appended
 */
function up_filter_query_vars_fn( $qvars ) {

  $qvars[] = 'username';
  return $qvars;
}
add_filter ( 'query_vars', 'up_filter_query_vars_fn' );


/**
 * Creates the url rewrite rules necessary to display the users' profiles on index.php/user-profile/[Parameter]
 *
 * Hooks on action: generate_rewrite_rules
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 * @param    Object    $wp_rewrite    WordPress' rewrite rules
 */
function up_action_generate_rewrite_rules_fn( $wp_rewrite ) {

  $up_profile_page = get_option( 'up_profile_page_title' );
  $new_rules = array(
      $up_profile_page . '/(.+)' =>
        "index.php?pagename=$up_profile_page&username=" . $wp_rewrite->preg_index(1)
      );

  $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
}
add_action ( 'generate_rewrite_rules', 'up_action_generate_rewrite_rules_fn' );

/**
 * Determines if the current page is the 'User-Profile' page.
 *
 * Adds a query variable 'up_profile_page_activate'=>TRUE if the request page is a user profile page.
 * This allows other functions to react appropriately.
 *
 * Hooks on filter: parse_query
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 * @param       Object        $q        WordPress' Query Object
 * @return      Object                  WordPress' Query Object, modified.
 */
function up_filter_parse_query_fn( $q ) {

         $up_page_name = get_option( 'up_profile_page_name' );

         $qv = $q->query_vars;

         if($qv[ 'pagename' ] == $up_page_name ) {

             $q->set( 'up_profile_page_activate', TRUE );
             $q->set( 'username', preg_replace( '/[^0-9a-zA-Z]/', '', $qv['page'] ) );
             return $q;

         } else {

             $q->set( 'up_profile_page_activate', FALSE );
             return $q;
       }
}
add_filter( 'parse_query', 'up_filter_parse_query_fn' );


/**
 * Wraps the users' avatars in a link to their profile page.
 *
 * Hooks on filter: get_avatar
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 * @param       String        $avatar        The current HTML for the user's avatar.
 * @param       Object        $user_info     A WordPress User object
 * @param       integer       $image_id      The avatar's image id. Unused by this function.
 * @param       String        $avatar_url    The URL to the user's avatar. Unused by this function.
 * @return      String                       The user's avatar, replete with wrapping link to their profile page.
 */
function up_filter_get_avatar_fn( $avatar, $user_info, $image_id, $avatar_url ) {

  global $wpdb;
  $user_id=0;

  if( is_object( $user_info ) ) {
      $user_id = $user_info->user_id;
  }else {
      $user_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_email = %s;", $user_info ) );
  }

  return "<a href='/index.php/".get_option( 'up_profile_page_name' )."/$user_id'>$avatar</a>";
}
add_filter( 'get_avatar', 'up_filter_get_avatar_fn', 11, 4);


/**
 * Replaces the "author URL" with a link to the author's profile page.
 *
 * Hooks on filter: get_the_author_url
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 * @param       String        $author_url        The current author URL
 * @param       integer       $author_id         The author's user ID
 * @return      String                           The URL to the author's profile page.
 */
function up_filter_get_the_author_url_fn( $author_url, $author_id ) {

    return "/index.php/".get_option( 'up_profile_page_name' )."/$author_id";
}
add_filter( 'get_the_author_url', 'up_filter_get_the_author_url_fn', 10, 2 );


/**
 * Generates the HTML code to display a user's profile page.
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 * @param       Object        $user_info        A WordPress User object
 * @return      String                          The HTML code for the users' profile page.
 */
function up_make_user_profile( $user_info ) {

    global $wpdb, $current_user;

    $display_name = $user_info->display_name;
    $user_id = $user_info->ID;
    $page_content = '';
    $page_content .= get_avatar( $user_info->user_email );

    $bio = up_process_user_profile_text( $user_id, $user_info );

    if('' != $bio ) {
        
        $page_content .= "</p><hr><p>$bio</p><br /><br /><hr>";
    }

    $stats = up_make_user_profile_stats( $user_info );

    if('' != $stats ) {

        $page_content .= "<h2>" . sprintf( __( '%s\'s Stats:', UP_PLUGIN_NAME ), $display_name ) . "</h2>";
        $page_content .= "<p>$stats</p>";
    }
    
    if( $user_id == $current_user->ID ) {

        $page_content .= "<hr><p><a href='" . get_option( 'siteurl' ) . "/wp-admin/profile.php#up_profile_page'>" . __( 'Edit your profile', UP_PLUGIN_NAME ) . "</a>";
    }

    return $page_content;
}

/**
 * Generates the HTML code to display a user's statistics.
 *
 * Generated stats can only be shown if they are one of the stats the admin has enabled in the dashboard.
 * Nothing, not even a header, will be returned if there are no stats to show.
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 * @param       Object       $user_info      A WordPress User object
 * @return      String                       The HTML code to display the user's statistics
 */
function up_make_user_profile_stats( $user_info ) {

    global $wpdb;

    $display_name = $user_info->display_name;
    $user_id = $user_info->ID;
    $user_registered = $user_info->user_registered;

    $stat_values[ 'age' ]       = floor( ( time() - strtotime( $user_registered ) ) / 86400 );
    $stat_values[ 'posts' ]     = count_user_posts( $user_id );
    $stat_values[ 'userlevel' ] = get_user_meta( $user_id, 'wp_user_level' ,TRUE );
    $stat_values[ 'comments' ]  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( * ) AS total_comments FROM $wpdb->comments WHERE comment_approved = 1 AND user_id = %d", $user_id ) );

    $stat_postfixes[ 'age' ] = __( 'days', UP_PLUGIN_NAME );

    $stats = preg_split( '/,/', get_option( 'up_profile_stats' ) );
    $names = preg_split( '/,/', get_option( 'up_profile_stat_names' ) );
    $x = 0;
    $options = get_option( 'up_options' );
    $ret_string = '';
    foreach ( $stats AS $stat)
    {
        $name = $names[ $x ];
        $value = $options[ "stat_$stat" ];

        if($value) {

            $checked = get_user_meta($user_id,"up_stat_$stat",TRUE);
            if( $checked ) {

                $ret_string .= "$name: " . $stat_values[$stat] . " " . $stat_postfixes[$stat] . "<br />";
            }
        }
      $x++;
    }
    return $ret_string;
}

/**
 * Filters the user's 'profile page' text blurb, replacing [tags] with their actual values.
 *
 * [biography], [aim], [yim], [gtalk], [email], [website], [website_link], and [display_name] are valid.
 * All of those are I18n'd, so the specific string may vary by language. (I18n'd)
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 * @param       integer       $user_id      The given users's ID
 * @param       Object        $user_info    A WordPress User object
 * @return      String                      The filtered profile page blurb
 */
function up_process_user_profile_text( $user_id, $user_info ) {

    $user_profile_page = get_user_meta( $user_id, 'up_profile_page_text', TRUE );

    $user_profile_page = preg_replace( "/\[" . __( 'biography', UP_PLUGIN_NAME ) . "\]/", get_user_meta( $user_id,'description', TRUE ), $user_profile_page );

    $user_profile_page = preg_replace( "/\[" . __( 'aim', UP_PLUGIN_NAME ) . "]/", get_user_meta( $user_id,'aim', TRUE ), $user_profile_page );
    $user_profile_page = preg_replace( "/\[" . __( 'yim', UP_PLUGIN_NAME ) . "]/", get_user_meta( $user_id,'yim', TRUE ), $user_profile_page );
    $user_profile_page = preg_replace( "/\[" . __( 'gtalk', UP_PLUGIN_NAME ) . "]/", get_user_meta( $user_id,'jabber', TRUE ), $user_profile_page );

    $user_profile_page = preg_replace( "/\[" . __( 'email', UP_PLUGIN_NAME ) . "]/", $user_info->user_email, $user_profile_page );
    $website = $user_info->user_url;
    $user_profile_page = preg_replace( "/\[" . __( 'website', UP_PLUGIN_NAME ) . "]/", $website, $user_profile_page );
    $user_profile_page = preg_replace( "/\[" . __( 'website_link', UP_PLUGIN_NAME ) . "]/", "<a href='$website' target='_blank'>$website</a>", $user_profile_page );
    $user_profile_page = preg_replace( "/\[" . __( 'display_name', UP_PLUGIN_NAME ) . "]/", $user_info->display_name, $user_profile_page );

    return $user_profile_page;
}

/**
 * Generates the HTML code to display when a profile page is viewed with NO user specified and NO user logged in.
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 * @return      String                     HTML code
 */
function up_make_user_list() {
    
  $page_content='';

  $page_content .= __( 'You are not logged in. You cannot have have a profile page unless you register an account.', UP_PLUGIN_NAME );

  return $page_content;
}


/**
 * Overwrites a 'page's content with a user's profile.
 *
 * If the page viewed is the "user profile" page that the plugin created. Otherwise, this function does not modify the post.
 *
 * Hooks on filter: the_posts
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 * @param       Array       $posts      All the posts to display on the page
 * @return      Array                   $posts with $posts[0]'s properties overwritten to display a user's profile.
 */
function up_filter_the_posts_fn( $posts ) {

         global $wp_query, $current_user;

         if( $wp_query->get('up_profile_page_activate') ) {
             $posts[0]->post_title = 'unset';
             $page_content = $posts[0]->page_content;
             $page_type = $posts[0]->post_type;

             $username = get_query_var( 'username' );
             $user_info = '';
             if(is_page()) {
                 
                    if( is_numeric( $username ) && '' != $username) {

                        $user_info = get_userdata( $username );
                        $page_content .= up_make_user_profile( $user_info );

                    }else if( '' != $username ) {

                        $user_info = get_userdatabylogin( $username );
                        $page_content .= up_make_user_profile( $user_info );

                    }else {

                        if(is_user_logged_in()) {

                            $user_info = $current_user;
                            $page_content .= up_make_user_profile( $current_user );

                        }else {

                            $page_content .= up_make_user_list();
                            $posts[0]->post_title = __( 'User Profile Page', UP_PLUGIN_NAME );

                        }
                    }

                    if( 'unset' == $posts[0]->post_title ) {
                        
                        $posts[0]->post_title = sprintf( __( '%s\'s Profile', UP_PLUGIN_NAME ), $user_info->display_name );
                        
                    }
                    
                    $posts[0]->post_content = $page_content;
             }
         }
         
         return $posts;
}
add_filter( 'the_posts', 'up_filter_the_posts_fn' );


/**
 * Adds a top-level menu to the administrator dashboard.
 *
 * Hooks on action: admin_menu
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 */
function up_action_admin_menu_fn() {
    
  add_menu_page( __( 'User Profile Page Admin Options', UP_PLUGIN_NAME ), __( 'User Profile Pages', UP_PLUGIN_NAME ), 'manage_options', 'up_options', 'up_admin_page_fn' );
}
add_action( 'admin_menu','up_action_admin_menu_fn' );


/**
 * Creates the options page for the admin dashboard.
 *
 * Hooks on action: admin_init
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 */
function up_action_admin_settings_fn() {

  if ( current_user_can( 'manage_options' ) ) {

      register_setting( 'up_options', 'up_options', 'up_admin_validate_fn' );
      add_settings_section( 'main_section', __( 'Main Settings', UP_PLUGIN_NAME ), 'up_admin_settings_title_text_fn', __FILE__ );
      add_settings_section( 'stat_section',__( 'Available Statistics', UP_PLUGIN_NAME ),'up_admin_settings_stat_text_fn',__FILE__ );
      all_stats_fields();
      add_settings_field( 'up_restore', __( '<label for="up_restore"> Restore default settings upon reactivation? </label>', UP_PLUGIN_NAME ), 'up_admin_settings_generic_checkbox_fn', __FILE__, 'main_section', Array( 'up_restore', 'up_restore')  );
      add_settings_field( 'up_uninstall', __( '<label for="up_uninstall"> Completely un-install upon deactivation?<br><br><b>Warning:</b> This will completely erase all user-entered information in addition to all settings. You cannot undo this. </label>', UP_PLUGIN_NAME ), 'up_admin_settings_generic_checkbox_fn', __FILE__, 'main_section', Array( 'up_uninstall', 'up_uninstall') );
  }
}
add_action( 'admin_init','up_action_admin_settings_fn' );


/**
 * Sanitizes the input from the admin dashboard.
 *
 * Input is all checkboxes, anyway, so this is not needed.
 *
 *
 * @package User-Profile-Pages
 * @since 0.0.3
 */
function up_admin_validate_fn( $input ) {

    return $input;
}

/**
 * Echoes checkboxes for all 'statistic' fields in the admin dashboard's options page.
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 */
function all_stats_fields() {

  $stats = preg_split( '/,/', get_option('up_profile_stats') );
  $names = preg_split( '/,/', get_option('up_profile_stat_names') );
  $x=0;
  $options = get_option('up_options');
  foreach ( $stats AS $stat) {
      
      $name = $names[ $x ];
      $value = $options[ "stat_$stat" ] ? "checked=\'checked\'" : '';
      add_settings_field( "up_stat_$stat", "<label for='up_stat_$stat'> $name </label>", create_function( '', "echo '<input id=\'up_stat_$stat\' name=\'up_options[stat_$stat]\' type=\'checkbox\' $value /> " . __( 'Allowed' ) . "';" ), __FILE__, 'stat_section' );
      $x++;
  }
}

/**
 * Echoes checkboxes for all 'statistic' fields for the end-user's profile options page.
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 */
function up_all_stats_fields_user() {

    global $user_id;
    $stats = preg_split( '/,/', get_option( 'up_profile_stats' ) );
    $names = preg_split( '/,/', get_option( 'up_profile_stat_names' ) );
    $x=0;
    $options = get_option( 'up_options' );
    foreach ( $stats AS $stat) {

        $name = $names[ $x ];
        $value = $options[ "stat_$stat" ];

        if( $value ) {
            
            $checked = get_the_author_meta( "up_stat_$stat", $user_id ) ? "checked='checked'" : '';
            ?>
            <tr>
                <th><label for="<?php echo "up_stat_$stat"; ?>"><?php echo $name; ?></label></th>
                <td><input type="checkbox" name="<?php echo "up_options[stat_$stat]"; ?>" id="<?php echo "up_stat_$stat"; ?>"  <?php echo "$checked > ".__('Visible', UP_PLUGIN_NAME ); ?></td>
            </tr>
            <?php
        }
      $x++;
    }
}

/**
 * Echoes description of the statistics section of the plugin's admin dashboard options page.
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 */
function up_admin_settings_stat_text_fn() {

  echo __( 'These are the things that users can choose to display on their profile page. If you disable an option here, users will <b>not</b> be able to display it.', UP_PLUGIN_NAME );
}

/**
 * Echoes the description of the general settings section of the plugin's admin dashboard options page.
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 */
function up_admin_settings_title_text_fn() {

  echo __( 'General settings pertaining to User Profile Pages.', UP_PLUGIN_NAME );
}


/**
 * Echoes a checkbox for the provided form variable
 *
 *
 * @package User-Profile-Pages
 * @since 0.0.3
 *
 * @param       Array       $field_identifiers      An array containing the element name at position 0, and id at position 1
 */
function up_admin_settings_generic_checkbox_fn( $field_identifiers = Array( 'generic_name', 'generic_id' ) ) {

  $options = get_option( 'up_options' );
  $value = $options[ $field_identifiers[0] ] ? "checked='checked'" : '';
  echo "<input id='$field_identifiers[1]' name='up_options[$field_identifiers[0]]' type='checkbox' $value />";
}



/**
 * Echoes the plugin's admin dashboard option page
 *
 * I18n'd, access-restricted
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 */
function up_admin_page_fn() {

  if ( !current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }

  ?>
  <div class="wrap">
    <div class="icon32" id="icon-options-general"><br></div>
    <h2><?php _e( 'User Profile Page Options', UP_PLUGIN_NAME ); ?></h2>
    <?php _e( 'Each user has a "profile page" on which they can display certain statistics. You can control which statistics users are able to choose from on this page.', UP_PLUGIN_NAME ); ?>
    <form action="options.php" method="post">
    <?php settings_fields( 'up_options' ); ?>
    <?php do_settings_sections( __FILE__ ); ?>
    <p class="submit">
      <input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ); ?>" />
    </p>
    </form>
  </div>
<?php
}

/**
 * Echoes the dashboard user profile page options.
 *
 * Hooks on actions: show_user_profile, edit_user_profile
 * 
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 */
function up_action_show_user_profile_fn() {

    global $user_id;
    ?>
    <h3><a name="up_profile_page"></a><?php _e( 'Profile Page', UP_PLUGIN_NAME ) ?></h3>
    <table class="form-table">

    <tr>
	<th><label for="up_profile_page_text"><?php _e( 'Profile Page', UP_PLUGIN_NAME ); ?></label></th>
	<td><textarea name="up_profile_page_text" id="up_profile_page_text" rows="5" cols="30"><?php echo esc_html( get_the_author_meta( 'up_profile_page_text', $user_id ) ); ?></textarea><br />
	<span class="description"><?php
                                    _e( 'Your public profile page. You can use [', UP_PLUGIN_NAME );
                                    _e( 'biography', UP_PLUGIN_NAME );
                                    _e( '], [', UP_PLUGIN_NAME );
                                    _e( 'display_name', UP_PLUGIN_NAME );
                                    _e( '], [', UP_PLUGIN_NAME );
                                    _e( 'email', UP_PLUGIN_NAME );
                                    _e( '], [', UP_PLUGIN_NAME );
                                    _e( 'website', UP_PLUGIN_NAME );
                                    _e( '], [', UP_PLUGIN_NAME );
                                    _e( 'website_link', UP_PLUGIN_NAME );
                                    _e( '], [', UP_PLUGIN_NAME );
                                    _e( 'aim', UP_PLUGIN_NAME );
                                    _e( '], [', UP_PLUGIN_NAME );
                                    _e( 'yim', UP_PLUGIN_NAME );
                                    _e( '], and [', UP_PLUGIN_NAME );
                                    _e( 'gtalk', UP_PLUGIN_NAME );
                                    _e( '] tags. ', UP_PLUGIN_NAME ); ?></span></td>
    </tr>

    <tr>
        <th colspan="2">
            <h4><?php _e( 'User Stats', UP_PLUGIN_NAME ); ?></h4>
            <span class="description"><?php _e('Your account statistics that will be publicly displayed at the end of your user profile page.',UP_PLUGIN_NAME ); ?></span>
        </th>
    </tr>

    <?php
    up_all_stats_fields_user();
    ?>
    </table>

    <?php

 }
add_action( 'show_user_profile', 'up_action_show_user_profile_fn' );
add_action( 'edit_user_profile', 'up_action_show_user_profile_fn' );


/**
 * Saves submitted user profile page <b>statistic</b> settings into the database
 *
 * Uses usermeta table
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 */
function up_update_all_stats_fields_user() {

    global $user_id;
    $stats = preg_split( '/,/', get_option( 'up_profile_stats' ) );
    $x=0;
    $options = get_option( 'up_options' );

    $up_options = $_POST[ 'up_options' ];

    foreach ( $stats AS $stat)
    {
        $value = $options[ "stat_$stat" ];
        update_usermeta( $user_id, "up_stat_$stat", $up_options[ "stat_$stat" ] );
        $x++;
    }
}

/**
 * Saves submitted user profile page settings into the database.
 *
 * Uses usermeta table
 *
 * Hooks on actions: personal_options_update, edit_user_profile_update
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 */
function up_action_process_option_update_fn() {

    global $user_id;
    update_usermeta( $user_id, 'up_profile_page_text', ( isset( $_POST[ 'up_profile_page_text' ] ) ? $_POST[ 'up_profile_page_text' ] : '' ) );
    up_update_all_stats_fields_user();
}
add_action( 'personal_options_update', 'up_action_process_option_update_fn' );
add_action( 'edit_user_profile_update', 'up_action_process_option_update_fn' );

/**
 * Makes the comment section invisible on user profile pages.
 *
 * Points to "up-no-comments.php," an empy page, as the 'comment template.'
 *
 * Hooks on filter: comments_template
 *
 * @package User-Profile-Pages
 * @since 0.0.2
 *
 */
function up_hide_comment_template_fn( $file ) {

    global $wp_query;
    if( is_page() && $wp_query->get( 'up_profile_page_activate' ) ) {

        return dirname( __FILE__ ) . '/up-no-comments.php';
    }
    
    return $file;
}
add_filter( 'comments_template', 'up_hide_comment_template_fn' );


/**
 * Adds "settings" to the left of "Activate/Deactivate | Edit" on the plugin list page.
 *
 * Hooks on filter: plugin_action_links
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.4
 *
 */
function up_filter_plugin_action_links_fn( $links, $file ) {

    if ( basename(dirname( __FILE__)) . '/'. basename(__FILE__) == $file ) {

        array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=up_options' ) . '">'. __( 'Settings', UP_PLUGIN_NAME ) . '</a>' );
    }
    return $links;
}
add_filter( 'plugin_action_links', 'up_filter_plugin_action_links_fn', 10, 2 );


/**
 * Adds "E-mail Support" to the right of "Version #.#.# | By Author | Visit plugin site" on the plugin list page.
 *
 * Hooks on filter: plugin_row_meta
 *
 * I18n'd
 *
 * @package User-Profile-Pages
 * @since 0.0.4
 *
 */
function up_filter_plugin_row_meta_fn( $meta_links, $file ) {

    if ( basename(dirname( __FILE__)) . '/'. basename(__FILE__) == $file ) {

        $meta_links[] = '<a href="mailto:gengar003@gmail.com">'. __( 'E-mail Support', UP_PLUGIN_NAME ) . '</a>';
    }
    return $meta_links;
}
add_filter( 'plugin_row_meta', 'up_filter_plugin_row_meta_fn', 10, 2 );


?>