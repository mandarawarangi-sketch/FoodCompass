# FoodCompass product labels extension

## Current behavior and scope

The supplied schema stores the description, category, price, and review status in `product_submissions`. It has no product image upload or structured nutrition values. Editing already creates another submission; the public catalog already selects the newest **approved** submission per product. This patch adds versioned declarations and review photos without changing the administrator approval action.

`None declared` means the owner declared none; it does **not** guarantee a product is allergen free. Legacy submissions default to `Information unavailable`. Photos are evidence, not numerical data for filtering. For future sugar/carbohydrate filters, add numeric quantities, their units, and a basis such as per serving, per 100 g, or per 100 mL to reviewed submission records.

## Install on XAMPP

1. Back up the `foodcompass` database and the existing PHP files. Keep the product and review history.
2. In phpMyAdmin select `foodcompass`, open **SQL**, and run `database/2026-09-product-labels.sql` **once**. It is an additive migration, not a database import. Do not run it twice.
3. Copy the eight changed/new PHP files from the patch to `C:\xampp\htdocs\foodcompass\`, preserving the original folder structure. Copy `uploads/product-labels/.htaccess` too (Windows may hide dotfiles). Ensure Apache can write to `uploads/product-labels`.
4. Confirm `db.php` still connects to your MariaDB port and password (your local setup previously used port 3307). The patch does not replace `db.php`.
5. If XAMPP rejects a photo over 2 MB, change `upload_max_filesize` to at least `5M` and `post_max_size` to at least `18M` in XAMPP's active `php.ini`, then restart Apache. Each photo must be JPEG, PNG, or WebP, 500–8000 pixels on both axes, and no more than 5 MB.

## Changed files

| File | Purpose |
| --- | --- |
| `database/2026-09-product-labels.sql` | Seven columns on `product_submissions`: allergen status, chosen allergens as JSON, vegetarian/vegan claims, three photo filenames. |
| `product-labels.php` | Form, validation, upload, output helpers. |
| `uploads/product-labels/.htaccess` | Denies direct HTTP requests to uploaded photos. |
| `label-photo.php` | Shows pending photos only to their owner or a Platform Administrator; shows only latest approved photos to visitors. |
| `submit-product.php` | Records structured declarations and optional photos with new pending submissions. |
| `edit-product.php` | Creates a new pending version, carrying forward photos until individually replaced. |
| `admin-reviews.php` | Displays the submitted declarations and photo links before approval. |
| `submission-history.php` | Shows every version's declarations and photos to its owner. |
| `products.php` | Shows latest approved declarations and photos only; removes nonfunctional filtering controls. |
| `product-owner.php` | Adds a per-product History link to the Product Owner dashboard. |

`admin-review-action.php`, `db.php`, Product Owner authentication, and existing product descriptions are unchanged.

## Quick local checks

1. As Product Owner, submit a product with `Declared allergens` and two selections, vegetarian/vegan choices, and three readable photos. In `submission-history.php`, verify the new pending version and three working photo links. Try `Information unavailable` on another product and verify it does not display as `None declared`.
2. In another browser signed out, visit `products.php`: the pending product and its photos must be absent. A direct `uploads/product-labels/<filename>` URL must return Forbidden; a pending `label-photo.php?submission_id=<id>&type=ingredients_photo` must return 404 to visitors.
3. Sign in as Platform Administrator and visit `admin-reviews.php`: inspect all fields and photo links. Approve. Refresh `products.php`: the approved fields and photo links should appear.
4. As owner, edit the product and change an allergen or claim and replace one photo. Before approval, the public catalog must still show the old approved details and photo; the administrator should see the new pending version. Reject the edit and verify the old approved version remains public. Repeat with approval and verify the new details become public.
5. Optional: try an unsupported file or an image smaller than 500 × 500 pixels; it must be rejected. Check `submission-history.php` for both versions. Test an existing legacy product: its new fields should read `Information unavailable`.

### Check before showing the presentation

The test here is a code and schema review. PHP and MariaDB are not installed in this workspace, so run the local XAMPP sequence above before presenting. Check that `uploads/product-labels/.htaccess` is present after copying; the PHP upload helper refuses to save if it is missing.
