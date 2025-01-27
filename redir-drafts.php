<?php
/**
 * Plugin Name: Redirect Draft Posts
 * Description: Lists all draft posts (excluding sdm_downloads) and lets you map their original slug to a chosen published URL for a 301 redirect. Works whether you're logged in or not—draft content won't leak. Includes a “Test Redirect” link that pops up a window to verify the final destination.
 * Version: 1.7
 * Author: Your Name
 */

// 1. Admin menu remains the same
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

// 2. Unified redirect logic: whether user is logged in or not
add_action('template_redirect', 'rdp_maybe_redirect_slug');
function rdp_maybe_redirect_slug() {
    global $post, $wp;

    // Load the existing slug map (draft_slug => published_URL)
    $redirect_map = get_option('rdp_draft_slug_map', []);

    // If we are on a single post, check if it's a draft and in our map
    if (is_singular() && $post && 'draft' === $post->post_status) {
        $draft_slug = $post->post_name;
        if (!empty($draft_slug) && isset($redirect_map[$draft_slug])) {
            // Found a match, 301 redirect
            wp_redirect($redirect_map[$draft_slug], 301);
            exit;
        }
    }
    // If it's a 404 for a slug that doesn't match a real post,
    // see if that slug is in our map
    elseif (is_404()) {
        $request_slug = trim($wp->request, '/');
        if (!empty($request_slug) && isset($redirect_map[$request_slug])) {
            wp_redirect($redirect_map[$request_slug], 301);
            exit;
        }
    }
}

// 3. Render the admin page for mapping drafts -> published posts
function rdp_render_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rdp_nonce']) && wp_verify_nonce($_POST['rdp_nonce'], 'rdp_redirects')) {
        $new_map = [];
        if (!empty($_POST['draft_post']) && is_array($_POST['draft_post'])) {
            foreach ($_POST['draft_post'] as $draft_id => $published_url) {
                $draft_id = (int) $draft_id;
                $draft_obj = get_post($draft_id);
                if ($draft_obj && 'draft' === $draft_obj->post_status) {
                    $draft_slug = $draft_obj->post_name;
                    $final_url = esc_url_raw($published_url);
                    if (!empty($draft_slug) && !empty($final_url)) {
                        $new_map[$draft_slug] = $final_url;
                    }
                }
            }
        }
        update_option('rdp_draft_slug_map', $new_map);
        echo '<div class="updated"><p>Redirects updated successfully.</p></div>';
    }

    // Fetch strictly draft posts, excluding `sdm_downloads`
    $draft_posts = get_posts([
        'post_status'    => 'draft',
        'post_type'      => 'any',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'exclude'        => get_posts([
            'post_status' => 'draft',
            'post_type'   => 'sdm_downloads',
            'posts_per_page' => -1,
            'fields'      => 'ids',
        ]),
    ]);

    // Fetch only published posts for redirection
    $published_posts = get_posts([
        'post_status'    => 'publish',
        'post_type'      => 'any',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    // Get current slug map
    $current_map = get_option('rdp_draft_slug_map', []);
    ?>
    <div class="wrap">
        <h1>Redirect Draft Posts</h1>
        <form method="POST">
            <?php wp_nonce_field('rdp_redirects', 'rdp_nonce'); ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Draft Post</th>
                        <th>Redirect To (Published)</th>
                        <th>Test Redirect</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($draft_posts): ?>
                        <?php foreach ($draft_posts as $draft): ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($draft->post_title) . ' (' . esc_html($draft->post_type) . ')'; ?>
                                </td>
                                <td>
                                    <?php
                                    $saved_url = '';
                                    if (!empty($current_map[$draft->post_name])) {
                                        $saved_url = $current_map[$draft->post_name];
                                    }
                                    ?>
                                    <select name="draft_post[<?php echo esc_attr($draft->ID); ?>]">
                                        <option value="">-- No Redirect --</option>
                                        <?php foreach ($published_posts as $published): ?>
                                            <?php $permalink = get_permalink($published->ID); ?>
                                            <option 
                                                value="<?php echo esc_url($permalink); ?>"
                                                <?php selected($saved_url, $permalink); ?>>
                                                <?php echo esc_html($published->post_title) . ' (' . esc_html($published->post_type) . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <?php
                                    // If a redirect is defined, offer a "Test Redirect" link
                                    if (!empty($saved_url)) {
                                        $test_slug_url = home_url('/' . $draft->post_name . '/');
                                        ?>
                                        <a href="<?php echo esc_url($test_slug_url); ?>"
                                           class="rdp-test-redirect"
                                           data-slug="<?php echo esc_attr($draft->post_name); ?>">
                                           Test Redirect
                                        </a>
                                    <?php } else {
                                        echo '<em>No redirect defined</em>';
                                    } ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3"><em>No draft posts found.</em></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p><button type="submit" class="button button-primary">Save Redirects</button></p>
        </form>

        <?php if (!empty($current_map)): ?>
            <h2>Current Draft Slug Mappings</h2>
            <ul>
                <?php foreach ($current_map as $slug => $url): ?>
                    <li>
                        <strong><?php echo esc_html($slug); ?></strong> &rarr; 
                        <a href="<?php echo esc_url($url); ?>" target="_blank"><?php echo esc_html($url); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No current slug mappings.</p>
        <?php endif; ?>
    </div>

    <!-- Simple script to open the slug in a popup window for testing -->
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        var links = document.querySelectorAll('.rdp-test-redirect');
        links.forEach(function(link){
            link.addEventListener('click', function(e){
                e.preventDefault();
                // Opens a popup to see the redirect in action
                window.open(link.href, 'rdpTestRedirect', 'width=800,height=600');
            });
        });
    });
    </script>
    <?php
}