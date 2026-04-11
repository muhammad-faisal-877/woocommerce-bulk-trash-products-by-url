<?php
/**
 * Plugin Name: Woo Bulk Trash Products by URL
 * Description: Enter WooCommerce product URLs, list image URLs + count, and delete all products & images with one button.
 * Version: 1.1
 * Author: Muhammad Faisal
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_menu', function () {
    add_menu_page(
        'Bulk Trash Products',
        'Bulk Trash Products',
        'manage_woocommerce',
        'bulk-trash-products',
        'btp_admin_page',
        'dashicons-trash'
    );
});

function btp_admin_page() {

    $products    = [];
    $image_urls  = [];
    $image_count = 0;
    $success     = '';


    if ( isset($_POST['btp_delete']) && ! empty($_POST['product_ids']) ) {

        $ids = array_map('intval', explode(',', $_POST['product_ids']));
        $deleted_products = 0;
        $deleted_images   = 0;

        $image_only = (isset($_POST['btp_delete']) && $_POST['btp_delete'] === 'images');

        foreach ($ids as $id) {

            if ($image_only) {
                if (get_post_type($id) === 'attachment') {
                    wp_delete_attachment($id, true);
                    $deleted_images++;
                }
                continue;
            }

            $product = wc_get_product($id);
            if (! $product) continue;

            $main_id = $product->get_image_id();
            if ($main_id) {
                wp_delete_attachment($main_id, true);
                $deleted_images++;
            }

            foreach ($product->get_gallery_image_ids() as $gid) {
                wp_delete_attachment($gid, true);
                $deleted_images++;
            }

            wp_trash_post($id);
            $deleted_products++;
        }

        $success = $image_only
            ? "✅ Deleted {$deleted_images} images successfully."
            : "✅ Deleted {$deleted_products} products and {$deleted_images} images successfully.";
    }


    if ( isset($_POST['btp_process']) && ! empty($_POST['product_urls']) ) {

        $urls = array_filter(array_map('trim', explode("\n", $_POST['product_urls'])));

        foreach ($urls as $url) {

            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {

                $attachment_id = attachment_url_to_postid($url);

                if ($attachment_id) {
                    $products[]   = $attachment_id; 
                    $image_urls[] = $url;
                    $image_count++;
                }

                continue;
            }

            $pid = url_to_postid($url);
            if (! $pid || get_post_type($pid) !== 'product') continue;

            $product = wc_get_product($pid);
            if (! $product) continue;

            $products[] = $pid;

            if ($product->get_image_id()) {
                $image_urls[] = wp_get_attachment_url($product->get_image_id());
                $image_count++;
            }

            foreach ($product->get_gallery_image_ids() as $gid) {
                $image_urls[] = wp_get_attachment_url($gid);
                $image_count++;
            }
        }
    }
    ?>

    <div class="wrap">
        <h1>Bulk Trash WooCommerce Products</h1>

        <?php if ($success): ?>
            <div class="notice notice-success"><p><?= esc_html($success); ?></p></div>
        <?php endif; ?>

        <form method="post">
            <textarea name="product_urls" rows="6" style="width:100%" placeholder="Enter product OR image URLs (one per line)"><?php
                echo isset($_POST['product_urls']) ? esc_textarea($_POST['product_urls']) : '';
            ?></textarea>

            <p>
                <button class="button button-primary" name="btp_process">
                    Process
                </button>
            </p>
        </form>

        <?php if ($products): ?>

            <h2>Image URLs</h2>

            <textarea rows="8" style="width:100%" readonly><?php
                echo esc_textarea(implode("\n", $image_urls));
            ?></textarea>

            <p><strong>Total Images:</strong> <?= intval($image_count); ?></p>

            <form method="post" onsubmit="return confirm('Proceed? This action may delete data permanently.');">
                <input type="hidden" name="product_ids" value="<?= esc_attr(implode(',', $products)); ?>">

                <button class="button button-danger" name="btp_delete" value="products">
                    Delete Products & Images
                </button>

                <button class="button" name="btp_delete" value="images">
                    Delete Images Only
                </button>
            </form>

        <?php endif; ?>

    </div>

<?php
}