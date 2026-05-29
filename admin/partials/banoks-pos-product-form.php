<?php
/**
 * Provide a admin area view for the product add/edit form
 *
 * @link       https://banoks.com
 * @since      1.0.0
 * @package    Banoks_POS
 * @subpackage Banoks_POS/admin/partials
 * @author     Christian Fulache
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_edit = ( 'edit' === $action && ! empty( $product ) );
$product_image_id  = $is_edit && ! empty( $product->product_image_id ) ? absint( $product->product_image_id ) : 0;
$product_image_url = $product_image_id ? wp_get_attachment_image_url( $product_image_id, 'medium' ) : '';
$is_available = ! $is_edit || ! isset( $product->is_available ) || intval( $product->is_available );
$is_active    = ! $is_edit || ! isset( $product->is_active ) || intval( $product->is_active );
$product_description = $is_edit && isset( $product->product_description ) ? sanitize_textarea_field( $product->product_description ) : '';
$selected_category = $is_edit && ! empty( $product->category ) ? sanitize_text_field( $product->category ) : 'General';
$category_options  = array();
$category_sources  = array_merge(
    ! empty( $existing_categories ) && is_array( $existing_categories ) ? $existing_categories : array(),
    array( 'Meals', 'Rice', 'Drinks', 'General' )
);

foreach ( $category_sources as $category_name ) {
    $category_name = trim( sanitize_text_field( $category_name ) );
    if ( '' === $category_name ) {
        continue;
    }

    $category_key = strtolower( $category_name );
    if ( ! isset( $category_options[ $category_key ] ) ) {
        $category_options[ $category_key ] = $category_name;
    }
}

if ( '' !== $selected_category && ! isset( $category_options[ strtolower( $selected_category ) ] ) ) {
    $category_options[ strtolower( $selected_category ) ] = $selected_category;
}

natcasesort( $category_options );
$recipe_condition_options = array(
    'all'      => 'All orders',
    'walk_in'  => 'Walk-in only',
    'online'   => 'Online only',
    'delivery' => 'Delivery only',
    'pickup'   => 'Pickup only',
);
$recipe_rows = ! empty( $product_recipes ) && is_array( $product_recipes ) ? $product_recipes : array();
if ( empty( $recipe_rows ) ) {
    $recipe_rows = array( (object) array( 'inventory_item_id' => 0, 'quantity_used' => '', 'applies_to' => 'all' ) );
}
?>

<div class="wrap banoks-pos-admin banoks-pos-page banoks-products-page banoks-product-form-page">
    <div class="products-header">
        <div class="header-info">
            <h1><?php echo $is_edit ? 'Edit Product' : 'Add New Product'; ?></h1>
            <p><?php echo $is_edit ? 'Update menu item details' : 'Create a new restaurant menu item'; ?></p>
        </div>
    </div>

    <form method="post" class="banoks-product-form" action="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-products&branch_key=' . $selected_branch_key ) ); ?>">
        <?php wp_nonce_field( 'banoks_pos_product_action' ); ?>
        
        <?php if ( $is_edit ) : ?>
            <input type="hidden" name="product_id" value="<?php echo esc_attr( $product->product_id ); ?>">
        <?php endif; ?>

        <div class="banoks-product-form-grid">
            <section class="banoks-form-card banoks-form-card-main">
                <div class="banoks-form-card-header">
                    <h2>Menu Details</h2>
                    <p>Name, category, price, and product image.</p>
                </div>

                <div class="banoks-form-field">
                    <label for="product_name">Product Name</label>
                    <input name="product_name" type="text" id="product_name" value="<?php echo $is_edit ? esc_attr( $product->product_name ) : ''; ?>" class="regular-text" required>
                </div>

                <div class="banoks-form-field">
                    <label for="product_description">Description</label>
                    <textarea name="product_description" id="product_description" rows="3" class="large-text" placeholder="Short description shown on the online ordering menu."><?php echo esc_textarea( $product_description ); ?></textarea>
                </div>

                <div class="banoks-form-row">
                    <div class="banoks-form-field">
                        <label for="category">Category</label>
                        <div class="banoks-category-control">
                            <select name="category" id="category" class="regular-text" required>
                                <?php foreach ( $category_options as $category_name ) : ?>
                                    <option value="<?php echo esc_attr( $category_name ); ?>" <?php selected( $selected_category, $category_name ); ?>><?php echo esc_html( $category_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="button" id="banoks-add-product-category">New Category</button>
                        </div>
                    </div>
                    <div class="banoks-form-field">
                        <label for="current_price">Price</label>
                        <input name="current_price" type="number" step="0.01" id="current_price" value="<?php echo $is_edit ? esc_attr( $product->current_price ) : ''; ?>" class="small-text" required>
                    </div>
                </div>

                <div class="banoks-form-field">
                    <label for="product_image_id">Product Image</label>
                    <div class="banoks-product-image-field">
                        <input type="hidden" name="product_image_id" id="product_image_id" value="<?php echo esc_attr( $product_image_id ); ?>">
                        <div class="banoks-product-image-preview<?php echo $product_image_url ? ' has-image' : ''; ?>">
                            <?php if ( $product_image_url ) : ?>
                                <img src="<?php echo esc_url( $product_image_url ); ?>" alt="">
                            <?php else : ?>
                                <span>No image selected</span>
                            <?php endif; ?>
                        </div>
                        <div class="banoks-product-image-actions">
                            <button type="button" class="button" id="banoks-upload-product-image">Upload Image</button>
                            <button type="button" class="button" id="banoks-remove-product-image"<?php echo $product_image_id ? '' : ' style="display:none;"'; ?>>Remove</button>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="banoks-form-card banoks-form-card-side">
                <div class="banoks-form-card-header">
                    <h2>Availability</h2>
                    <p>Controls customer ordering and menu visibility.</p>
                </div>

                <label class="banoks-toggle-control banoks-toggle-compact">
                    <input name="is_available" type="checkbox" value="1" <?php checked( $is_available ); ?>>
                    <span class="banoks-toggle-switch"></span>
                    <span>
                        <strong>Available for ordering</strong>
                        <small>Customers can add this item online.</small>
                    </span>
                </label>

                <label class="banoks-toggle-control banoks-toggle-compact">
                    <input name="is_active" type="checkbox" value="1" <?php checked( $is_active ); ?>>
                    <span class="banoks-toggle-switch"></span>
                    <span>
                        <strong>Active menu item</strong>
                        <small>Inactive items stay in history but are hidden from selling.</small>
                    </span>
                </label>
            </aside>

            <section class="banoks-form-card">
                <div class="banoks-form-card-header">
                    <h2>Production Usage</h2>
                    <p>Choose the production inventory products used when this item is sold.</p>
                </div>

                    <div class="banoks-recipe-builder" id="banoks-recipe-builder">
                        <div class="banoks-recipe-builder-head">
                            <span>Production Inventory Product</span>
                            <span>Qty Used Per Product</span>
                            <span>Applies To</span>
                            <span></span>
                        </div>
                        <div class="banoks-recipe-rows">
                            <?php foreach ( $recipe_rows as $recipe_row ) : ?>
                                <div class="banoks-recipe-row">
                                    <select name="recipe_inventory_item_id[]">
                                        <option value="">Select inventory product</option>
                                        <?php if ( ! empty( $inventory_items ) ) : ?>
                                            <?php foreach ( $inventory_items as $inventory_item ) : ?>
                                                <option value="<?php echo esc_attr( $inventory_item->id ); ?>" <?php selected( intval( $recipe_row->inventory_item_id ), intval( $inventory_item->id ) ); ?>>
                                                    <?php echo esc_html( $inventory_item->item_name . ' (' . $inventory_item->unit . ')' ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <input name="recipe_quantity_used[]" type="number" min="0" step="0.001" value="<?php echo esc_attr( $recipe_row->quantity_used ); ?>" placeholder="0">
                                    <select name="recipe_applies_to[]">
                                        <?php
                                        $selected_condition = ! empty( $recipe_row->applies_to ) ? sanitize_key( $recipe_row->applies_to ) : 'all';
                                        foreach ( $recipe_condition_options as $condition_key => $condition_label ) :
                                            ?>
                                            <option value="<?php echo esc_attr( $condition_key ); ?>" <?php selected( $selected_condition, $condition_key ); ?>><?php echo esc_html( $condition_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="button banoks-remove-recipe-row">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button" id="banoks-add-recipe-row">Add Inventory Product</button>
                    </div>
                    <p class="description">Use conditions for packaging and order-specific supplies. Example: chicken and rice apply to all orders, while delivery bags apply to delivery only.</p>
                    <?php if ( empty( $inventory_items ) ) : ?>
                        <p class="description">No inventory items yet. Add ingredients in Stock Management first.</p>
                    <?php endif; ?>
            </section>

            <section class="banoks-form-card">
                <div class="banoks-form-card-header">
                    <h2>Online Add-ons</h2>
                    <p>Choose menu products that customers can add when ordering this product online.</p>
                </div>

                <?php if ( empty( $addon_products ) ) : ?>
                    <p class="description">No active products available for add-ons yet.</p>
                <?php else : ?>
                    <div class="banoks-addon-picker">
                        <?php foreach ( $addon_products as $addon_product ) : ?>
                            <?php
                            $addon_product_id = absint( $addon_product->product_id );
                            if ( $is_edit && ! empty( $product->product_id ) && $addon_product_id === absint( $product->product_id ) ) {
                                continue;
                            }
                            ?>
                            <label class="banoks-addon-option">
                                <input type="checkbox" name="addon_product_ids[]" value="<?php echo esc_attr( $addon_product_id ); ?>" <?php checked( in_array( $addon_product_id, $selected_addon_ids, true ) ); ?>>
                                <span>
                                    <strong><?php echo esc_html( $addon_product->product_name ); ?></strong>
                                    <small>&#8369;<?php echo esc_html( number_format( floatval( $addon_product->current_price ), 2 ) ); ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <p class="submit">
            <input type="submit" name="banoks_pos_save_product" id="banoks-save-product" class="button button-primary banoks-product-save-btn" value="<?php echo $is_edit ? 'Update Product' : 'Add Product'; ?>">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=banoks-pos-products&branch_key=' . $selected_branch_key ) ); ?>" class="button banoks-product-cancel-btn">Cancel</a>
        </p>
    </form>

    <div class="banoks-inventory-category-modal" id="banoks-product-category-modal" aria-hidden="true">
        <div class="banoks-inventory-category-dialog" role="dialog" aria-modal="true" aria-labelledby="banoks-product-category-title">
            <h2 id="banoks-product-category-title">Add Category</h2>
            <label for="banoks-new-product-category">Category Name</label>
            <input type="text" id="banoks-new-product-category" class="regular-text" placeholder="e.g. Inasal">
            <div class="modal-actions">
                <button type="button" class="button" id="banoks-cancel-product-category">Cancel</button>
                <button type="button" class="button button-primary" id="banoks-save-product-category">Add Category</button>
            </div>
        </div>
    </div>
</div>
