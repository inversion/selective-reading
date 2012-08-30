<?php
/**
 * @package Selective_reading
 * @version 0.3
 */
/*
Plugin Name: Selective Reading
Plugin URI: http://wordpress.org/extend/plugins/selective-reading/
Description: Simple plugin to allow website visitors to deselect categories they don't want to see. Works for unregistered users (using cookies).
Author: Andrew Moss
Version: 0.3
Author URI: http://www.amoss.me.uk/
*/

// TODO: Error checking
class SelectiveReading {

    private static $cookieKey = 'wp-selective-reading-';

    /**
     * Add show/hide javascript links to the categories list, based on the state of the cookie
     */
    public static function edit_categories_list($content) {
        $dom = new DOMDocument();
        // Thanks to http://www.php.net/manual/en/domdocument.loadhtml.php#74777 for this UTF-8 fix
        $encodedContent = mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8");
        $dom->loadHTML($encodedContent);
        
        // Track if any categories have been hidden
        $anyHidden = false;
        
        $listItems = $dom->getElementsByTagName('li');
        foreach($listItems as $item) {
            // Find the category ID from the class of the list element
            preg_match( '%(\d+)$%', $item->getAttribute('class'), $matches );
            $categoryID = $matches[1];
            
            // Create the link to show or hide the element
            $currentState = SelectiveReading::get_state($categoryID);
            $anyHidden = $anyHidden || !$currentState;
            
            $showHideLink = $dom->createElement( 'a', $currentState ? '(hide)' : '(show)' );
            $showHideLink->setAttribute( 'onclick', 'wp_selective_reading_set_category_state(' . $categoryID . ', ' . ($currentState ? '0' : '1') .');' );
            $showHideLink->setAttribute( 'title', ($currentState ? 'Hide' : 'Show') . ' posts from this category.' );
            $showHideLink->setAttribute( 'class', 'wp-selective-reading-toggle-' . $categoryID . ' wp-selective-reading-link' );
            
            $item->insertBefore( $showHideLink, $item->childNodes->item(2) );
        }
        
        // Add 'show all' link only if one or more categories are hidden
        if( $anyHidden ) {
            $showAllItem = $dom->createElement( 'li', '' );
            $showAllItem->setAttribute( 'class', 'cat-item wp-selective-reading-link' );
            $showAllLink = $dom->createElement( 'a', '(show all)' );
            $showAllLink->setAttribute( 'title', 'Show posts from all categories.' );
            $showAllLink->setAttribute( 'onclick', 'wp_selective_reading_clear_cookies();' );
            $showAllItem->appendChild( $showAllLink );
            $dom->appendChild( $showAllItem );
        }
        
        // http://www.php.net/manual/en/domdocument.savehtml.php
        $content = preg_replace('/^<!DOCTYPE.+?>/', '', str_replace( array('<html>', '</html>', '<body>', '</body>'), array('', '', '', ''), $dom->saveHTML()));
        return $content;
    }

    /**
     * Hides posts from disabled categories from being displayed when posts are listed on the main page
     */
    public static function edit_displayed_categories( $query ) {
        if( $query->is_home() && $query->is_main_query() ) {
            $query->set( 'cat', SelectiveReading::get_disabled_categories_str() );
        }
    }
    
    public static function enqueue_scripts() {
        wp_enqueue_script( 'selective-reading', plugins_url() . '/selective-reading/selective-reading.js' );
    }
    
    public static function enqueue_styles() {
        wp_enqueue_style( 'selective-reading', plugins_url() . '/selective-reading/selective-reading.css' );
    }
    
    /** 
     * Checks which categories are disabled in the cookie and returns the appropriate exclusion string
     */
    private static function get_disabled_categories_str() {
        $disabledCategories = '';
        $firstMatch = true;
        foreach( $_COOKIE as $key => $val ) {
            if( preg_match( '%^' . SelectiveReading::$cookieKey . '(\d+)$%', $key, $matches ) ) {
                $categoryID = $matches[1];
                if( $val === '0' ) {
                    if( $firstMatch ) {
                        $firstMatch = false;
                    } else {
                        $disabledCategories .= ',';
                    }
                    $disabledCategories .= '-' . $categoryID;
                }
            }
        }
        return $disabledCategories;
    }
    
    /**
     * Set the enabled or disabled state for a category in a cookie
     * true = enabled
     */
    private static function set_cookie($categoryID, $state) {
        $expiry = time()+2629743; // ~1 month from now
        return setcookie(SelectiveReading::$cookieKey . $categoryID, $state ? '1' : '0', $expiry, "/");
    }
    
    /**
     * Returns the enabled or disabled state of a category, defaulting to enabled
     * Take into account parents' states
     */
    private static function get_state($categoryID) {
        // State in cookie, defaulting to enabled if unset
        $cookieState = array_key_exists( SelectiveReading::$cookieKey . $categoryID, $_COOKIE ) ? $_COOKIE[SelectiveReading::$cookieKey . $categoryID] : true;

        // Check if any parent categories are hidden
        $ancestors = get_ancestors( $categoryID, 'category' );
        $parentState = true;
        foreach( $ancestors as $ancestorID ) {
            $parentState = $parentState && (array_key_exists( SelectiveReading::$cookieKey . $ancestorID, $_COOKIE ) ? $_COOKIE[SelectiveReading::$cookieKey . $ancestorID] : true);
        }
        
        // If parent state is hidden and we have tried to show this category, reset it to hidden
        if( !$parentState ) {
            //SelectiveReading::set_cookie( $categoryID, false );
        }
        
        return ($parentState && $cookieState);
    }
}

// Don't use the plugin in the admin panel
if( !is_admin() ) {
    add_action( 'wp_enqueue_scripts', 'SelectiveReading::enqueue_scripts' );
    add_action( 'wp_enqueue_scripts', 'SelectiveReading::enqueue_styles' );
    add_filter( 'wp_list_categories', 'SelectiveReading::edit_categories_list' );
    add_action( 'pre_get_posts', 'SelectiveReading::edit_displayed_categories' );
}
?>