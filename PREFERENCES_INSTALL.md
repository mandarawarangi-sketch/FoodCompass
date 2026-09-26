# Customer preferences: local installation and test

This addition uses the existing shared `foodcompass` database and the already installed reviewed product-label columns. It does not replace tables or reset existing data.

1. In phpMyAdmin, select `foodcompass` and confirm `users` and the `product_submissions` label columns exist. Back up the database. Run `database/2026-09-customer-preferences.sql` **once**. If `customer_preferences` already exists, stop rather than running it again.
2. Copy `preferences.php`, `customer-account.php`, `lists.php`, `products.php`, `index.html`, and `login.html` into `C:\xampp\htdocs\foodcompass\`. Keep your existing root `db.php` and machine-specific MariaDB port. Keep existing `product-labels.php`.
3. Sign in as a customer, open **My food preferences**, select Peanuts and Vegan, optionally select Diabetes, then save and refresh. The selections should persist. Sign in as another customer in a separate browser session; their selections should be empty.
4. An approved product that declares Peanuts must be absent from suggestions when Peanuts is selected. A product with allergen information unavailable must also be absent. An approved product with a Vegan claim of Yes and no matching declared allergen can appear. A pending edit must not change suggestions until an administrator approves it.
5. Clear all preferences and save: approved products should appear again. Changing the health-condition choice alone must not change suggestions: nutrition photos are evidence, not structured numeric food data.
6. The homepage **Sign in** button should lead to the customer account. For the three team roles, open `login.html` directly; this separate page links to Product Owner, Retail Store, and Platform Administrator sign-in. Only Product Owner offers registration there.

The suggestions are a comparison of administrator-approved Product Owner claims. The declaration 'none declared' is not a guarantee of allergen safety, and the app cannot determine suitability for diabetes. Before any condition-specific screening, add reviewed numeric nutrition values, units, and basis (per serving or per 100 g/mL), then design and review appropriate rules. Always inspect current labels.
