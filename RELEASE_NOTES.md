- v1.6.8: Removed red background from add-to-cart modal X close button and set [banoks_view_cart_button] default URL to https://banoks.ph/cart.
- v1.6.7: Removed red hover/active background from the add-to-cart modal quantity controller.
- v1.6.7: Added stronger Elementor/theme hover reset for [banoks_cart_button] so hover background stays transparent and only the icon changes color.
- v1.6.7: Forced [banoks_cart_button] hover background to stay transparent; only the icon turns red on hover.
- v1.6.7: Removed active/has-items red color from [banoks_cart_button] icon while keeping hover/focus red.
- v1.6.7: Increased spacing for the Menu heading in [banoks_online_menu].
- v1.6.7: Updated [banoks_customer_address] to use the new uploaded address SVG icon.
- v1.6.7: Removed [banoks_cart_button] background; hover/active now affects only the cart icon color.
- v1.6.7: Restored the missing Menu heading label in [banoks_online_menu].
- v1.6.7: Updated [banoks_cart_button] default background to #21201e and active/has-items color to #e60913.
- v1.6.7: Fixed product filter buttons becoming red, restored white pill style with thin gray stroke, reduced filter padding, and corrected View My Cart circle counter styling.
- v1.6.7: Applied Banoks online ordering brand defaults, replaced cart item X with delete icon, added empty-cart Add Item button, made toggle text semibold, compacted product filters, and changed active cart color to #e60913.
- v1.6.7: Qty minus button changes to the uploaded delete icon at qty 1, qty hover effects removed, pickup address set to Manukan, Sunset Boulevard, and delivery/pickup toggle icons replaced with uploaded SVGs.
- v1.6.7: Normalized checkout/cart font sizes and weights, added cart X button to menu, made Order Summary total semi-bold/non-red, matched inactive progress circle stroke to the line, and made Checkout/Place Order buttons fully rounded.
- v1.6.7: Added checkout critical CSS in wp_head, cache-busting CSS/JS versions, stylesheet preload, and anti-FOUC rules to prevent old checkout design flashing before current styles load.
- v1.6.7: Added top spacing to checkout back arrow and matched inactive progress circle gray with the progress line.
- v1.6.7: Text hierarchy balanced with semi-bold headers and medium body text, order-summary total color normalized, progress circle/line adjusted, and cart/checkout fixed summary padding increased.
- v1.6.7: Checkout-only typography reduced, checkout footer padding increased moderately, and Discount/VAT rows added to checkout Order Summary.
## 1.4.6
- Cleaned `[banoks_checkout]` shortcode editing support: it now accepts only `id` and `class` for custom CSS targeting.
- Removed inline shortcode style variables from the checkout wrapper.
- Restored only the Delivery / Pick-up toggle styling to the earlier redesign version.

## 1.4.5
- Added `step_size` support for `[banoks_checkout]` progress labels.
- Strengthened `page_padding` support so it affects the checkout wrapper/form spacing.

## 1.4.4
- Added editable CSS support for the `[banoks_checkout]` shortcode using shortcode attributes and CSS variables.
- Checkout/cart spacing, fonts, item cards, buttons, and colors can now be adjusted directly from the shortcode.


## 1.4.3
- Moved the + Add more item link below the cart item list and kept it hidden when the cart is empty.
- Reduced cart/checkout heading font weights for a lighter, normal look.
- Added controlled top padding to the cart and checkout screens without using top margin.

## 1.3.7
- Removed exact stock quantity text from the online product list.
- Product cards now show only `Low Stock` when tracked stock is 1–5 and `Out of Stock` when tracked stock is 0.
- Products without stock tracking no longer display any stock label on the customer-facing menu.

## 1.3.3
- Reduced the View my cart shortcode width from full viewport to a centered responsive width.
- Slightly reduced View my cart button height, padding, and gap for a cleaner look.

# Release Notes

## 1.2.9
- Unified public red UI colors to one brand hex.
- Balanced online menu product card fonts, button sizes, and image backgrounds.
- Made View My Cart full-width with 10px left/right spacing.
- Updated stock display: tracked stock at 0 now shows Out of Stock; untracked stock is hidden.
- Matched add-to-cart modal quantity controls to checkout cart controls and removed blue hover states.
- Hid cart shortcode badge when cart count is 0.


## 1.2.7
- Updated online menu product image containers to use a balanced square 1:1 ratio, ideal for 1000 x 1000 product images.
- Kept product images on cover fit with centered positioning for consistent card appearance.
# Banoks POS System Release Notes

## v1.2.4

### Summary

Banoks POS System v1.2.4 removes the GitHub-based updater so plugin updates can be handled manually through ZIP uploads.

### Changes

- Removed GitHub release update integration from the plugin bootstrap.
- Removed the GitHub updater class.
- Updated documentation to describe manual ZIP upload updates.
- Bumped the plugin version to `1.2.4`.

### Update Notes

- Future updates should be installed by uploading the plugin ZIP in WordPress.
- Use `Plugins > Add New > Upload Plugin`, then choose `Replace current with uploaded`.
- The Git remote for development can still be used for source control, but WordPress will no longer check GitHub for plugin updates.

## v1.2.3

### Summary

Banoks POS System v1.2.3 is a small visual refinement release for the admin navigation.

### Changes

- Reduced the blackness of the admin navigation hover and active states.
- Updated the desktop navigation hover and active background from pure black to a softer slate gray.
- Updated the mobile navigation hover and active background to a calmer blue-gray.

### Update Notes

- This is a CSS-only visual update with a patch version bump.
- Create the GitHub release using tag `v1.2.3` so WordPress can detect the update.

## v1.2.2

## Summary

Banoks POS System v1.2.2 is the first GitHub-ready release of the Eazera-built WordPress POS plugin. This release packages the core in-store POS workflow, online ordering, inventory controls, finance tracking, reporting, and GitHub-based plugin update support.

## What's New

- Added GitHub release update support for WordPress plugin updates.
- Added cashier role support with access limited to Banoks POS screens.
- Added walk-in POS order handling with cash and GCash payment support.
- Added online ordering support with cart, checkout, delivery and pickup options.
- Added online order notifications, pending order counts, and admin status updates.
- Added GCash payment proof review and status management.
- Added product management with product images, availability controls, pricing, categories, and stock settings.
- Added delivery area management with deliverable status, delivery fees, and sorting.
- Added stock management for inventory items, stock locations, purchases, movements, and low-stock alerts.
- Added owner dashboard and admin navigation for daily operations.
- Added cash management support for store cash, cash on hand, GCash balance, and bank balance.
- Added expense tracking with branch and cash-source support.
- Added business reports combining walk-in and online sales data.
- Added PDF report export support.
- Added database tables and migrations for orders, products, customers, online orders, payment proofs, inventory, stock logs, expenses, branches, and delivery areas.

## Improvements

- Restricted plugin assets so admin CSS and JavaScript load only on Banoks POS screens.
- Added cache-busting based on local asset file modification time.
- Added Chart.js loading only on the reports screen.
- Improved order status flow for preparing, completed, and cancelled states.
- Added stock deduction and restoration logic for order status changes.
- Added status logs for online order updates.
- Added branch-aware reporting and inventory tracking.

## Installation / Update Notes

- Upload the plugin folder to WordPress, or install the GitHub release ZIP.
- Activate `Banoks POS System` from the WordPress Plugins screen.
- Plugin activation creates or updates the required database tables automatically.
- The plugin version is `1.2.2`.
- GitHub updater repository is configured as `Eazera/banoks-pos-system`.

## Known Notes

- Make sure the GitHub release tag matches the plugin version, for example `v1.2.2`.
- For WordPress update checks to work correctly, publish this as a GitHub Release rather than only pushing source code.
- Existing sites should back up the database before updating, especially because this release includes custom POS, order, inventory, and finance tables.

## 1.3.2
- Removed PWA support from the plugin.
- Removed manifest/service worker output, PWA meta tags, service worker registration, and PWA icon files.

## 1.3.5
- Fixed WordPress login password show/hide eye icon alignment.
- Centered the eye icon inside the password visibility button and cleaned hover/focus styling.


## 1.3.6
- Added drag-and-drop sorting in Product Management.
- Saved product order updates the online menu and POS product list.
- Added database migration for product `sort_order`.


## 1.3.8
- Fixed product drag-and-drop sorting AJAX action registration.
- Added a database migration safety check before saving product order.
- Improved the admin-side error message when product sorting cannot save.

## 1.4.7
- Refined `[banoks_checkout]` spacing after design pass.
- Reduced left/right padding and added controlled top padding.
- Connected `+ Add more item` directly below the cart item list with a thin divider.
- Reduced bottom summary padding and checkout/place order button height.
- Made the order progress line full width.
- Reduced progress labels and number sizes.
- Added inactive progress ring style for pending step numbers; completed/current steps remain solid black.
