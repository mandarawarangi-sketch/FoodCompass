<?php
declare(strict_types=1);
require __DIR__ . '/admin-auth.php';
require __DIR__ . '/db.php';
$_SESSION['category_token'] ??= bin2hex(random_bytes(32));

function categoryError(string $message, int $status = 400): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}
function categoryInput(): array {
    $value = json_decode((string) file_get_contents('php://input'), true);
    return is_array($value) ? $value : [];
}
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        $query = $db->query(
            'SELECT c.id, c.name, c.description AS `desc`, COUNT(DISTINCT s.product_id) AS `count`
             FROM categories AS c
             LEFT JOIN product_submissions AS s ON s.category_id = c.id
             GROUP BY c.id, c.name, c.description ORDER BY c.id ASC'
        );
        echo json_encode(array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'], 'name' => $row['name'],
                'desc' => $row['desc'], 'count' => (int) $row['count']
            ];
        }, $query->fetchAll()));
        exit;
    }
    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        categoryError('Method not allowed.', 405);
    }
    if (!hash_equals($_SESSION['category_token'], (string) ($_SERVER['HTTP_X_CATEGORY_TOKEN'] ?? ''))) {
        categoryError('Invalid form. Refresh the page and try again.', 403);
    }
    $data = categoryInput();
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if ($method !== 'POST' && (!$id || $id < 1)) {
        categoryError('Invalid category ID.');
    }
    $name = trim((string) ($data['name'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    if ($method !== 'DELETE' && ($name === '' || strlen($name) > 40 || strlen($description) > 120)) {
        categoryError('Enter a name of at most 40 bytes and description of at most 120 bytes.');
    }
    try {
        if ($method === 'POST') {
            $statement = $db->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $statement->execute([$name, $description]);
            echo json_encode(['success' => true, 'message' => 'Category added', 'id' => (int) $db->lastInsertId()]);
        } elseif ($method === 'PUT') {
            $statement = $db->prepare('SELECT id FROM categories WHERE id = ?');
            $statement->execute([$id]);
            if (!$statement->fetch()) categoryError('Category not found.', 404);
            $statement = $db->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
            $statement->execute([$name, $description, $id]);
            echo json_encode(['success' => true, 'message' => 'Changes saved']);
        } else {
            // The category foreign key blocks deletion if any submission refers to it.
            $statement = $db->prepare('DELETE FROM categories WHERE id = ?');
            $statement->execute([$id]);
            if (!$statement->rowCount()) categoryError('Category not found.', 404);
            echo json_encode(['success' => true, 'message' => 'Category deleted']);
        }
    } catch (PDOException $error) {
        if ($method === 'DELETE') {
            categoryError('This category is used by product submissions and cannot be deleted.', 409);
        }
        categoryError('Could not save this category. The name may already exist.', 409);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Product categories · Platform admin</title>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&display=swap" rel="stylesheet">
<style>
:root{--bg:#f7faf8;--panel:#fff;--ink:#26352d;--mute:#6d7b73;--line:#dce8df;--accent:#2f7d4a;--accent-ink:#fff;--soft:#eaf7ef;--warn:#9a6b19;--warnbg:#fff6df;--danger:#b3372b;--dangerbg:#fff0f0;
box-sizing:border-box;padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)}
html{scroll-padding-top:env(safe-area-inset-top,0px)}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:400 15px/1.5 "Bricolage Grotesque",system-ui,-apple-system,"Segoe UI",sans-serif}
.wrap{max-width:980px;margin:0 auto;padding:28px 20px 60px}
header{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:22px}
h1{font-size:34px;line-height:1.1;margin:0;font-weight:800;letter-spacing:-.02em}
.sub{color:var(--mute);margin:6px 0 0}
.who{font-size:13px;color:var(--mute);display:flex;align-items:center;gap:8px}
.av{width:30px;height:30px;border-radius:50%;background:var(--accent);color:var(--accent-ink);display:grid;place-items:center;font-weight:600;font-size:13px}
.stats{display:flex;gap:28px;margin:0 0 22px;padding:0 0 18px;border-bottom:1px solid var(--line);flex-wrap:wrap}
.stats b{display:block;font-size:26px;font-weight:800;line-height:1.1}
.stats span{color:var(--mute);font-size:13px}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:12px}
.add{padding:16px;margin-bottom:22px}
.add h2,.list h2{font-size:16px;margin:0 0 12px;font-weight:600}
.row{display:grid;grid-template-columns:1fr 1.6fr auto;gap:10px;align-items:end}
label{display:block;font-size:13px;color:var(--mute);margin-bottom:4px}
input,textarea{width:100%;font:inherit;color:var(--ink);background:var(--bg);border:1px solid var(--line);border-radius:8px;padding:9px 11px}
input:focus,textarea:focus,button:focus-visible{outline:2px solid var(--accent);outline-offset:1px}
.err{color:var(--danger);font-size:13px;min-height:18px;margin:6px 0 0}
button{font:inherit;cursor:pointer;border-radius:8px;border:1px solid var(--line);background:var(--panel);color:var(--ink);padding:9px 14px}
button.pri{background:var(--accent);color:var(--accent-ink);border-color:var(--accent);font-weight:600}
button.dng{background:var(--danger);border-color:var(--danger);color:#fff;font-weight:600}
button.ghost{border-color:transparent;background:transparent;padding:6px 10px;color:var(--accent);font-weight:600}
button.ghost.del{color:var(--danger)}
button:disabled{opacity:.4;cursor:not-allowed}
.list{overflow:hidden}
.bar{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid var(--line);flex-wrap:wrap}
.bar h2{margin:0}.bar input{width:230px;max-width:100%}
.scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:640px}
th{text-align:left;font-weight:600;font-size:13px;color:var(--mute);padding:10px 16px;border-bottom:1px solid var(--line)}
td{padding:13px 16px;border-bottom:1px solid var(--line);vertical-align:top}
tr:last-child td{border-bottom:0}
td.n{font-weight:600}td.d{color:var(--mute);max-width:280px}
.chip{display:inline-block;padding:2px 10px;border-radius:99px;font-size:12.5px;font-weight:600}
.used{background:var(--soft);color:var(--accent)}.unused{background:var(--warnbg);color:var(--warn)}
.acts{white-space:nowrap;text-align:right}
.empty{padding:36px 16px;text-align:center;color:var(--mute)}
dialog{border:1px solid var(--line);border-radius:14px;background:var(--panel);color:var(--ink);width:min(440px,calc(100% - 32px));padding:22px}
dialog::backdrop{background:rgba(5,12,8,.55)}
dialog h3{margin:0 0 14px;font-size:19px}
dialog .f{margin-bottom:12px}
.btns{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}
.toast{position:fixed;left:50%;bottom:calc(20px + env(safe-area-inset-bottom,0px));transform:translateX(-50%);background:var(--ink);color:var(--bg);padding:10px 16px;border-radius:10px;font-size:14px;opacity:0;pointer-events:none;transition:opacity .2s}
.toast.on{opacity:1}
@media (max-width:640px){.row{grid-template-columns:1fr}h1{font-size:28px}}
@media (prefers-reduced-motion:reduce){.toast{transition:none}}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div>
      <h1>Product categories</h1>
      <p class="sub">Keep the catalogue organised. Categories in use can be renamed but not deleted.</p>
    </div>
    <div class="who"><a href="admin-reviews.php">Product reviews</a> · <div class="av">PA</div>Platform administrator</div>
  </header>

  <div class="stats" id="stats"></div>

  <form class="panel add" id="addForm" novalidate>
    <h2>Add a category</h2>
    <div class="row">
      <div><label for="aName">Name</label><input id="aName" maxlength="40" placeholder="e.g. Bakery" autocomplete="off"></div>
      <div><label for="aDesc">Description (optional)</label><input id="aDesc" maxlength="120" placeholder="What belongs here" autocomplete="off"></div>
      <button class="pri" type="submit">Add category</button>
    </div>
    <p class="err" id="aErr" role="alert"></p>
  </form>

  <section class="panel list">
    <div class="bar">
      <h2>All categories</h2>
      <input id="q" type="search" placeholder="Search categories" aria-label="Search categories">
    </div>
    <div class="scroll">
      <table>
        <thead><tr><th>Name</th><th>Description</th><th>Products</th><th></th></tr></thead>
        <tbody id="rows"></tbody>
      </table>
    </div>
    <div class="empty" id="empty" hidden></div>
  </section>
</div>

<dialog id="editDlg">
  <form id="editForm" novalidate>
    <h3>Edit category</h3>
    <div class="f"><label for="eName">Name</label><input id="eName" maxlength="40" autocomplete="off"></div>
    <div class="f"><label for="eDesc">Description</label><textarea id="eDesc" rows="3" maxlength="120"></textarea></div>
    <p class="err" id="eErr" role="alert"></p>
    <div class="btns"><button type="button" id="eCancel">Cancel</button><button class="pri" type="submit">Save changes</button></div>
  </form>
</dialog>

<dialog id="delDlg">
  <h3>Delete category?</h3>
  <p id="delMsg" style="margin:0;color:var(--mute)"></p>
  <div class="btns"><button id="dCancel">Cancel</button><button class="dng" id="dOk">Delete category</button></div>
</dialog>

<div class="toast" id="toast" role="status"></div>

<script>
var categoryToken = <?= json_encode($_SESSION["category_token"]) ?>;
var cats = [];
var editId = null;
var delId = null;

var $ = function(id){ return document.getElementById(id); };

function esc(s){
    return String(s).replace(/[&<>"']/g,function(c){
        return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c];
    });
}

function toast(m){
    var t=$("toast");
    t.textContent=m;
    t.classList.add("on");
    clearTimeout(toast.h);
    toast.h=setTimeout(function(){t.classList.remove("on")},2200);
}

async function api(method, body){
    var options = {method: method, headers: {"Content-Type":"application/json", "X-Category-Token": categoryToken}};
    if(body !== undefined) options.body = JSON.stringify(body);

    var response = await fetch("?api=1", options);
    var data = await response.json();

    if(!response.ok || data.error) {
        throw new Error(data.error || "Request failed");
    }

    return data;
}

async function loadCategories(){
    try {
        cats = await api("GET");
        render();
    } catch(error) {
        console.error(error);
        $("rows").innerHTML="";
        $("empty").hidden=false;
        $("empty").textContent=error.message;
    }
}

function nameError(n,ignore){
    n=n.trim();
    if(!n) return "Enter a category name.";

    var dup=cats.some(function(c){
        return c.id!==ignore &&
               c.name.toLowerCase()===n.toLowerCase();
    });

    return dup ? "A category named “"+n+"” already exists." : "";
}

function render(){
    var used=cats.filter(function(c){return c.count>0}).length;

    $("stats").innerHTML=
        '<div><b>'+cats.length+'</b><span>Categories</span></div>'+
        '<div><b>'+used+'</b><span>In use</span></div>'+
        '<div><b>'+(cats.length-used)+'</b><span>Unused, safe to delete</span></div>';

    var q=$("q").value.trim().toLowerCase();

    var list=cats.filter(function(c){
        return !q || (c.name+" "+c.desc).toLowerCase().indexOf(q)>-1;
    });

    $("rows").innerHTML=list.map(function(c){
        var inUse=c.count>0;

        return '<tr>'+
            '<td class="n">'+esc(c.name)+'</td>'+
            '<td class="d">'+(esc(c.desc)||"—")+'</td>'+
            '<td><span class="chip '+(inUse?"used":"unused")+'">'+
                (inUse?c.count+" products":"Unused")+
            '</span></td>'+
            '<td class="acts">'+
                '<button class="ghost" data-edit="'+c.id+'">Edit</button>'+
                '<button class="ghost del" data-del="'+c.id+'"'+
                    (inUse?' disabled title="In use by '+c.count+' products. Move them to another category first."':'')+
                '>Delete</button>'+
            '</td>'+
        '</tr>';
    }).join("");

    var e=$("empty");

    if(!list.length){
        e.hidden=false;
        e.textContent=cats.length
            ?"No categories match “"+$("q").value+"”."
            :"No categories yet. Add the first one above.";
    }else{
        e.hidden=true;
    }
}

$("addForm").addEventListener("submit",async function(ev){
    ev.preventDefault();

    var name=$("aName").value.trim();
    var desc=$("aDesc").value.trim();

    var err=nameError(name,null);
    $("aErr").textContent=err;

    if(err)return;

    try{
        await api("POST",{name:name,description:desc});

        $("aName").value="";
        $("aDesc").value="";

        await loadCategories();
        toast("Category added");
    }catch(error){
        $("aErr").textContent=error.message;
    }
});

$("q").addEventListener("input",render);

$("rows").addEventListener("click",function(ev){
    var b=ev.target.closest("button");

    if(!b||b.disabled)return;

    if(b.dataset.edit){
        var c=cats.find(function(x){return x.id==b.dataset.edit});

        editId=c.id;
        $("eName").value=c.name;
        $("eDesc").value=c.desc;
        $("eErr").textContent="";
        $("editDlg").showModal();
        $("eName").focus();

    }else if(b.dataset.del){
        var d=cats.find(function(x){return x.id==b.dataset.del});

        delId=d.id;
        $("delMsg").textContent=
            "“"+d.name+"” has no products and will be removed for good.";

        $("delDlg").showModal();
    }
});

$("editForm").addEventListener("submit",async function(ev){
    ev.preventDefault();

    var name=$("eName").value.trim();
    var desc=$("eDesc").value.trim();

    var err=nameError(name,editId);
    $("eErr").textContent=err;

    if(err)return;

    try{
        await api("PUT",{
            id:editId,
            name:name,
            description:desc
        });

        $("editDlg").close();

        await loadCategories();
        toast("Changes saved");
    }catch(error){
        $("eErr").textContent=error.message;
    }
});

$("eCancel").onclick=function(){
    $("editDlg").close();
};

$("dCancel").onclick=function(){
    $("delDlg").close();
};

$("dOk").onclick=async function(){
    try{
        await api("DELETE",{id:delId});

        $("delDlg").close();

        await loadCategories();
        toast("Category deleted");
    }catch(error){
        alert(error.message);
    }
};

loadCategories();
</script>
</body>
</html>
