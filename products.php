<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

$approvedProducts = $db->query(
    "SELECT s.name, s.description, s.price,
            c.name AS category_name
     FROM products AS p
     JOIN product_submissions AS s ON s.id = (
         SELECT MAX(s2.id)
         FROM product_submissions AS s2
         WHERE s2.product_id = p.id
           AND s2.review_status = 'approved'
     )
     LEFT JOIN categories AS c ON c.id = s.category_id
     WHERE p.deleted_at IS NULL
     ORDER BY s.name"
)->fetchAll();

function productText(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Products | FoodCompass</title><style>
:root{
  --green:#2f7d4a; --green2:#3fa66a; --mint:#eaf7ef; --dark:#183225;
  --text:#26352d; --muted:#6d7b73; --white:#fff; --border:#dce8df;
  --shadow:0 10px 30px rgba(24,50,37,.08); --danger:#c94b4b; --warning:#b7791f;
}
*{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f7faf8;color:var(--text)}
a{text-decoration:none;color:inherit}.container{max-width:1180px;margin:auto;padding:0 24px}
.nav{height:72px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;position:sticky;top:0;z-index:20}
.nav-inner{display:flex;align-items:center;justify-content:space-between;width:100%}
.logo{font-weight:800;font-size:22px;color:var(--green);display:flex;align-items:center;gap:9px}
.logo span{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:var(--mint)}
.navlinks{display:flex;gap:8px;align-items:center}.navlinks a{padding:10px 13px;border-radius:9px;font-size:14px;color:#506057}.navlinks a:hover,.navlinks a.active{background:var(--mint);color:var(--green)}
.btn{border:0;border-radius:10px;padding:11px 16px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px}
.btn-primary{background:var(--green);color:#fff}.btn-secondary{background:#fff;border:1px solid var(--border);color:var(--text)}.btn-danger{background:#fff1f1;color:var(--danger)}
.hero{padding:62px 0;background:linear-gradient(135deg,#f1fbf4,#fff)}
.hero-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:40px;align-items:center}
.badge{display:inline-flex;background:var(--mint);color:var(--green);padding:7px 11px;border-radius:999px;font-weight:700;font-size:12px}
h1{font-size:48px;line-height:1.05;margin:16px 0;color:var(--dark)}h2{font-size:30px;margin:0 0 10px;color:var(--dark)}h3{margin:0 0 8px;color:var(--dark)}
.lead{font-size:18px;line-height:1.7;color:var(--muted);max-width:680px}
.actions{display:flex;gap:12px;margin-top:24px;flex-wrap:wrap}
.hero-card{background:#fff;border:1px solid var(--border);border-radius:22px;padding:24px;box-shadow:var(--shadow)}
.compass{height:280px;border-radius:18px;background:radial-gradient(circle at center,#fff 0 18%,#dff3e6 19% 30%,#b9e3c8 31% 44%,#8dcc9e 45% 60%,#5eaf78 61%);display:grid;place-items:center;color:#fff;text-align:center;font-weight:800;font-size:26px}
.section{padding:52px 0}.section-head{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:22px}.muted{color:var(--muted)}
.grid{display:grid;gap:18px}.grid-3{grid-template-columns:repeat(3,1fr)}.grid-4{grid-template-columns:repeat(4,1fr)}.grid-2{grid-template-columns:repeat(2,1fr)}
.card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:20px;box-shadow:0 5px 18px rgba(24,50,37,.04)}
.icon{width:44px;height:44px;border-radius:12px;background:var(--mint);display:grid;place-items:center;font-size:20px;margin-bottom:13px}
.kpi{font-size:28px;font-weight:800;color:var(--green)}.tag{display:inline-block;padding:5px 9px;border-radius:999px;background:#eef5f0;color:#47705a;font-size:12px;font-weight:700;margin:3px}
.searchbar{display:flex;gap:10px;background:#fff;padding:12px;border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow)}
input,select{width:100%;border:1px solid var(--border);border-radius:9px;padding:12px;background:#fff;color:var(--text);outline:none}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.filters select{width:auto;min-width:150px}
.product-card{overflow:hidden;padding:0}.product-img{height:155px;background:linear-gradient(135deg,#eaf7ef,#d6efe0);display:grid;place-items:center;font-size:56px}.product-body{padding:18px}
.price{font-size:20px;font-weight:800;color:var(--green)}.rating{font-size:13px;color:#9a6b19}
.layout{display:grid;grid-template-columns:240px 1fr;gap:24px}.sidebar{background:#fff;border:1px solid var(--border);border-radius:16px;padding:16px;height:max-content}.sidebar a{display:block;padding:11px;border-radius:9px;color:#607067}.sidebar a:hover,.sidebar a.active{background:var(--mint);color:var(--green);font-weight:700}
.table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden}.table th,.table td{padding:14px;text-align:left;border-bottom:1px solid var(--border);font-size:14px}.table th{background:#f3f8f4;color:#506057}
.status{padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700}.approved{background:#e8f7ed;color:#2f7d4a}.pending{background:#fff6df;color:#9a6b19}.rejected{background:#fff0f0;color:#b33c3c}
.footer{margin-top:50px;background:#183225;color:#d8e7dd;padding:34px 0}.footer-grid{display:flex;justify-content:space-between;gap:30px}.small{font-size:13px}
.auth{min-height:calc(100vh - 72px);display:grid;place-items:center;padding:40px}.auth-card{width:min(460px,100%);background:#fff;border:1px solid var(--border);border-radius:18px;padding:30px;box-shadow:var(--shadow)}.form-group{margin:14px 0}.form-group label{display:block;font-size:13px;font-weight:700;margin-bottom:7px}
.compare{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.compare .card{position:relative}.check{color:var(--green);font-weight:800}
.notice{padding:13px 15px;border-radius:10px;background:#eff8f2;border:1px solid #d7ebdd;margin-bottom:16px}
@media(max-width:850px){.hero-grid,.layout{grid-template-columns:1fr}.grid-3,.grid-4,.grid-2,.compare{grid-template-columns:1fr 1fr}.navlinks{display:none}h1{font-size:38px}}
@media(max-width:560px){.grid-3,.grid-4,.grid-2,.compare{grid-template-columns:1fr}.container{padding:0 16px}.section{padding:38px 0}}
</style></head>
<body>
<header class="nav"><div class="container nav-inner">
<a class="logo" href="index.html"><span>🧭</span> FoodCompass</a>
<nav class="navlinks"><a class="" href="index.html">Home</a><a class="active" href="products.php">Products</a><a class="" href="compare.html">Compare</a><a class="" href="lists.html">Lists</a><a href="login.html" class="btn btn-primary">Sign in</a></nav>
</div></header>

<main class="section"><div class="container">
<div class="section-head"><div><span class="badge">Customer</span><h2 style="margin-top:12px">Discover products</h2><p class="muted">Search and filter products according to dietary preferences and health requirements.</p></div></div>
<div class="searchbar"><input id="search" data-filter placeholder="Search products, ingredients or tags..."><button class="btn btn-primary">Search</button></div>
<div class="filters"><select><option>All categories</option><option>Breakfast</option><option>Pantry</option><option>Bakery</option><option>Snacks</option></select><select><option>Nutrition</option><option>High fibre</option><option>High protein</option><option>Plant based</option></select><select><option>Allergens</option><option>Gluten</option><option>Milk</option><option>Nuts</option></select><select><option>Ethical</option><option>Vegan</option><option>Ethical cocoa</option></select></div>
<div class="grid grid-3">
    <?php if (!$approvedProducts): ?>
        <p class="muted">No verified products are available yet.</p>
    <?php else: ?>
        <?php foreach ($approvedProducts as $product): ?>
            <article class="card product-card">
                <div class="product-img">🛒</div>
                <div class="product-body">
                    <span class="tag">
                        <?= productText($product['category_name'] ?? 'Uncategorized') ?>
                    </span>
                    <h3 style="margin-top:10px">
                        <?= productText($product['name']) ?>
                    </h3>
                    <span class="tag">✓ Verified</span>
                    <p class="muted"><?= productText($product['description']) ?></p>
                    <p class="price"><?= productText($product['price']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div></main>
<footer class="footer"><div class="container footer-grid"><div><strong>🧭 FoodCompass</strong><div class="small" style="margin-top:8px">Verified food information for informed, ethical choices.</div></div><div class="small">© 2026 FoodCompass · IS20 Project</div></div></footer>
<script>
document.querySelectorAll("[data-filter]").forEach(el=>el.addEventListener("input",()=>{
 const q=(document.querySelector("#search")?.value||"").toLowerCase();
 document.querySelectorAll(".product-card").forEach(c=>c.style.display=c.innerText.toLowerCase().includes(q)?"":"none");
}));
</script>
</body></html>