<?php 

/**
 * Plugin Name: Redirect Draft Posts
 * Description: Lists all draft posts (excluding sdm_downloads) and allows you to redirect their slugs to published post URLs. Includes a "Test Redirect" link for proper published-style permalinks.
 * Version: 1.7
 * Author: Your Name
 */

// Register a custom admin menu
add_action('admin_menu', 'rdp_add_admin_menu');
function rdp_add_admin_menu() {
    add_menu_page(
        'Redirect Draft Posts',
        'Draft Redirects',
        'manage_options',
        'redirect-draft-posts',
        'rdp_render_admin_page',
        'dashicons-randomize',
        20
    );
}

// Render the admin page
function rdp_render_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rdp_redirects_nonce']) && wp_verify_nonce($_POST['rdp_redirects_nonce'], 'rdp_redirects')) {
        foreach ($_POST['draft_post'] as $draft_post_id => $redirect_to) {
            if (!empty($redirect_to)) {
                update_post_meta($draft_post_id, '_rdp_redirect_to', esc_url_raw($redirect_to));
            }
        }
        echo '<div class="updated"><p>Redirects updated successfully.</p></div>';
    }

    // Fetch strictly draft posts, excluding `sdm_downloads`
    $draft_posts = get_posts([
        'post_status' => 'draft',
        'post_type' => 'any',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'exclude' => get_posts([
            'post_status' => 'draft',
            'post_type' => 'sdm_downloads',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]),
    ]);

    // Fetch only published posts for redirection
    $published_posts = get_posts([
        'post_status' => 'publish',
        'post_type' => 'any',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    ?>
    <div class="wrap">
        <h1>Redirect Draft Posts</h1>
        <form method="POST">
            <?php wp_nonce_field('rdp_redirects', 'rdp_redirects_nonce'); ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Draft Post</th>
                        <th>Redirect To</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($draft_posts): ?>
                        <?php foreach ($draft_posts as $post): ?>
                            <tr>
                                <td>
                                    <?php
                                    echo esc_html($post->post_title) . ' (' . esc_html($post->post_type) . ')';
                                    ?>
                                    <br>
                                    <a href="<?php echo esc_url(home_url('/' . get_post_type_object($post->post_type)->rewrite['slug'] . '/' . $post->post_name . '/')); ?>" target="_blank" style="color: #0073aa; text-decoration: none;">Test Redirect</a> |
                                    <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" target="_blank" style="color: #0073aa; text-decoration: none;">Edit Post</a>
                                </td>
                                <td>
                                    <select name="draft_post[<?php echo esc_attr($post->ID); ?>]">
                                        <option value="">-- Select a Redirect --</option>
                                        <?php foreach ($published_posts as $published): ?>
                                            <option value="<?php echo esc_url(get_permalink($published->ID)); ?>" 
                                                <?php selected(get_post_meta($post->ID, '_rdp_redirect_to', true), get_permalink($published->ID)); ?>>
                                                <?php echo esc_html($published->post_title) . ' (' . esc_html($published->post_type) . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2">No draft posts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p><button type="submit" class="button button-primary">Save Redirects</button></p>
        </form>
    </div>
    <?php
}

// Redirect draft slugs
add_action('template_redirect', 'rdp_redirect_draft_posts');
function rdp_redirect_draft_posts() {
    if (is_singular()) {
        $post_id = get_queried_object_id();
        $redirect_url = get_post_meta($post_id, '_rdp_redirect_to', true);
        if (!empty($redirect_url)) {
            wp_redirect($redirect_url, 301);
            exit;
        }
    }
}

?>
