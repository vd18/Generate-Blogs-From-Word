<?php
/*
Plugin Name: Word to Blog Converter
Description: Convert Word documents to WordPress blog posts.
Version: 1.0
author: vandan dave
*/


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Activation hook
register_activation_hook( __FILE__, 'wbc_activation' );
function wbc_activation() {
    // Activation tasks
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'wbc_deactivation' );
function wbc_deactivation() {
    // Deactivation tasks
}

// Add admin menu
add_action( 'admin_menu', 'wbc_add_menu' );
function wbc_add_menu() {
    add_menu_page(
        'Word to Blog Converter',
        'Word to Blog',
        'manage_options',
        'wbc-settings',
        'wbc_settings_page'
    );
}


// Function to convert Word to HTML and create posts based on H1 tags
function convert_word_to_html_and_create_posts( $file_path ) {
    require_once( 'vendor/autoload.php' ); // Include PHPWord library

    $phpWord = \PhpOffice\PhpWord\IOFactory::load( $file_path );
    $htmlWriter = new \PhpOffice\PhpWord\Writer\HTML($phpWord);
    ob_start(); // Start output buffering
    $htmlWriter->save('php://output');
    $html_content = ob_get_clean(); // Get buffered content and clear buffer

    // Extract H1 tags and their content from the HTML content
    $doc = new DOMDocument();
    libxml_use_internal_errors(true); // Suppress errors for loading malformed HTML
    $doc->loadHTML( $html_content );
    libxml_clear_errors(); // Clear any errors
    $xpath = new DOMXPath( $doc );
    $h1_nodes = $xpath->query( '//h1' );

    foreach ( $h1_nodes as $index => $h1_node ) {
        $post_title = $h1_node->nodeValue;

        // Check if post title already exists
        $existing_post = get_page_by_title( $post_title, OBJECT, 'post' );

        if ( $existing_post ) {
            // If post with same title already exists, skip creating a new post and display a message
            echo '<div class="error"><p>Post with title "' . $post_title . '" already exists. Skipping...</p></div>';
            continue;
        }

        // Capture content between current and next H1 tag (or end of content)
        $next_h1_node = isset( $h1_nodes[ $index + 1 ] ) ? $h1_nodes[ $index + 1 ] : null;
        $content_start_node = $h1_node->nextSibling;
        $content_end_node = $next_h1_node ? $next_h1_node->previousSibling : $doc->documentElement->lastChild;

        $content = '';
        $current_node = $content_start_node;

        while ( $current_node && $current_node !== $content_end_node ) {
            // Check if current node is an image
            if ($current_node->nodeName === 'img') {
                // Extract image source and append to content
                $img_src = $current_node->getAttribute('src');
                $content .= '<img src="' . esc_url($img_src) . '">';
            } else {
                $content .= wp_kses_post( $doc->saveHTML( $current_node ) ); // Sanitize HTML
            }
            $current_node = $current_node->nextSibling;
        }

        // Create a new WordPress post
        $post_id = wp_insert_post( array(
            'post_title'   => wp_strip_all_tags( $post_title ), // Strip any HTML tags from title
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
            'post_type'    => 'post'
        ) );

        if ( $post_id && !is_wp_error( $post_id ) ) {
            // If the post is successfully created, display a success message
            echo '<div class="updated"><p>New post created: <a href="' . get_permalink( $post_id ) . '">' . get_the_title( $post_id ) . '</a></p></div>';
        }
    }
}


   

// Settings page
function wbc_settings_page() {

    //
    echo '<div class="wrap">';
    echo '<h2>Word to Blog Converter Settings</h2>';
    echo '<div style="margin-bottom: 20px;">';
    echo '<h3>Rules:</h3>';
    echo '<ul>';
    echo '<li>Please ensure to upload Word files only.</li>';
    echo '<li>Each blog post should have only one H1 or title tag.</li>';
    echo '<li>Ensure that images are properly formatted and do not contain malicious code.</li>';
    echo '<li>Do not include executable code in Word documents.</li>';
    echo '</ul>';
    echo '</div>';

    // Handle file upload and conversion here
    if ( isset( $_FILES['word_file'] ) && ! empty( $_FILES['word_file']['tmp_name'] ) ) {
        $file_path = $_FILES['word_file']['tmp_name'];
        convert_word_to_html_and_create_posts( $file_path );
    } else {
        // Display file upload form
        ?>
        <div class="wrap">
            <h2>Upload Word Document</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="word_file">
                <input type="submit" value="Convert and Create Posts">
            </form>
        </div>
        <?php
    }
}