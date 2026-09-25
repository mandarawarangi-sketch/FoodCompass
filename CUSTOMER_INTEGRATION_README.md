# FoodCompass customer teammate review and safe integration

## What came in n-team.zip

The ZIP has 32 entries: `customer-account.php`, `lists.php`, `test.php`, a separate `food_compass_db.sql` dump, a nested user-interface ZIP, and copies of many existing administrator, Product Owner, and static site files. Its `README.md` contains only `# FoodCompass`.

The teammate has built shopping-list create/edit/delete screens and account registration/profile screens. They use a standalone `food_compass_db` schema, while your installed project uses `foodcompass`, `users(id, name, email, password_hash, role)`, approved product submissions, `saved_lists`, and `saved_list_items`. The original `lists.php` fixes every visitor to customer ID 1; its update and delete statements do not check ownership. The original `customer-account.php` stores plaintext passwords and uses `test.php` to publish every customer record, including the password column, to the browser. Do not copy or run those original pages or the database dump.

## Files in this integration patch

- `customer-account.php`: Customer registration and sign-in with `users` and PHP password hashing, profile name update, server-side session, and sign-out. No separate customer table or password-list endpoint.
- `lists.php`: Customer-owned named lists and approved-product items, with create/view/rename/delete lists and add/view/update/remove items. Uses installed `saved_lists` and `saved_list_items` tables, product IDs, prepared statements, and form tokens; accepts additions from the catalog.
- `index.html`, `compare.html`, `products.php`: Point public Lists and Sign in navigation to the working customer pages. `products.php` keeps the previously tested approved product and label information and now supports name search, category filtering, price sorting, and direct Add to list actions.
- `branch-search.php`: Search active branches by area, branch name, or retailer name; inspect last reported availability for the chosen list; send a request snapshot; read the branch's response without changing the reported availability.

**No new SQL migration is needed** if `saved_lists` and `saved_list_items` already appear in phpMyAdmin under the `foodcompass` database, as they did after the retailer schema import. Do not import `food_compass_db.sql`; it defines conflicting `users` and `products` tables with different columns and example plaintext credentials. Do not copy `test.php` or older Product Owner/administrator files from `n-team.zip`.

## Local install and quick test

1. Keep the backups you made today. Check phpMyAdmin → `foodcompass` → Structure for `saved_lists` and `saved_list_items`. If they are missing, stop: the retailer database migration must be installed first, once, rather than creating a second customer database.
2. Copy the customer files listed above from this patch to `C:\xampp\htdocs\foodcompass\`; confirm your active `db.php` still connects to the existing `foodcompass` database on your MariaDB port.
3. Open `http://localhost/foodcompass/customer-account.php`. Register a new customer using an unused email and a password of at least eight characters. It should send you to My shopping lists. Sign out and sign back in.
4. Create a named list. In `products.php`, search for a previously approved Product Owner product, choose the list and quantity on its card, then click **Add to list**. Filter by category and sort by price; check the cards change accordingly. Refresh `lists.php`, change the item's quantity, remove the item, rename the list, and delete it. Pending-only products must not be selectable.
5. Register a **second** customer in a private browser window. Its lists must be empty. A direct URL such as `lists.php?list_id=<first-customer-list-id>` must say List not found, and a forged list action using the other customer's list ID must not modify it.
6. From `index.html` and `products.php`, click Lists and Sign in; each should reach the new PHP pages.

## Add the customer availability request flow to an already tested installation

Copy **only** this patch's `branch-search.php` and updated `lists.php` into `C:\xampp\htdocs\foodcompass\`. The updated Lists page adds a **Find a branch for this list** link. The existing retailer migration already created `branches`, `branch_products`, `availability_requests`, and `request_items`, so do not import new SQL. Keep the installed `db.php` configuration for your MariaDB port.

1. Sign in as the customer who has a list with an approved product. Open the list and click **Find a branch for this list**. Search by area or branch/retailer name; the active branch and its address should appear. Its last reported status should match the retailer's Availability page; an unlisted item says **No report**.
2. Click **Ask this branch to confirm** once. The retailer's `retailer/requests.php` should now show the request. A suspended branch must not appear in the search or accept a forged submission.
3. Sign in as that branch's retailer, open the request and confirm each item at `retailer/respond.php?id=...`. Return to the customer page and expand **My availability requests**: it should show the reply and any notes. Changing branch-reported availability afterwards must not rewrite this reply.
4. Change the original list's quantity or delete the list. The earlier request must still display its original product name and quantity. Sign in as a second customer: the first customer's request history must not appear.

The current retailer response page does not permit **Unknown** as a saved confirmation; choose available, unavailable, or partially available for every item. The request is a confirmation only: it does not reserve stock, create an order, or take payment.

## Presentation limits

- The teammate's original per-item dietary preference labels were self-selected text, not verified product attributes. They are omitted here to avoid presenting an unverified Vegan/No Peanuts label as a safety filter. The Product Owner's reviewed claims are stored separately.
- The shared user schema has no contact-number or account-status field. The safe account screen updates the name; account deletion/deactivation needs a separate design that retains availability-request history.
- Branch search, list-based reported availability, sending requests, and viewing replies are included in this patch. Runtime testing of this newly added step is still needed in XAMPP before calling the entire customer-to-retailer flow presentation-ready.
- PHP and MariaDB are not available in this workspace; perform the XAMPP checks before claiming the patch runs end to end.
