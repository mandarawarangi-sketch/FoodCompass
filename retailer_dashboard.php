<?php

require_once "../config/db.php";

$retailer_id = 1;

$sql = "SELECT retailer_name, city, status
        FROM retailers
        WHERE retailer_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $retailer_id);
$stmt->execute();

$result = $stmt->get_result();
$retailer = $result->fetch_assoc();

$sql = "SELECT COUNT(*) AS total
        FROM retailer_products
        WHERE retailer_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $retailer_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$total_products = $row['total'];

$sql = "SELECT COUNT(*) AS total
        FROM shared_lists
        WHERE retailer_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $retailer_id);
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$total_shared_lists = $row['total'];

$sql = "SELECT
            p.product_id,
            p.product_name,
            p.brand_name,
            rp.price,
            rp.stock_status
        FROM retailer_products rp
        INNER JOIN products p
            ON rp.product_id = p.product_id
        WHERE rp.retailer_id = ?
        ORDER BY p.product_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $retailer_id);
$stmt->execute();

$products = $stmt->get_result();

$sql = "SELECT
            sl.shared_list_id,
            sl.list_id,
            sl.shared_via,
            sl.shared_at,
            s.list_name
        FROM shared_lists sl
        INNER JOIN saved_lists s
            ON sl.list_id = s.list_id
        WHERE sl.retailer_id = ?
        ORDER BY sl.shared_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $retailer_id);
$stmt->execute();

$shared_lists = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Retailer Dashboard | FoodCompass</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f7f6;
            color: #222;
        }

        nav {
            background: #ffffff;
            padding: 18px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2e7d32;
        }

        nav a {
            text-decoration: none;
            color: #333;
            margin-left: 25px;
        }

        nav a:hover {
            color: #2e7d32;
        }

        .container {
            width: 84%;
            max-width: 1200px;
            margin: 40px auto;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .header p {
            color: #666;
        }

        .kpi-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 35px;
        }

        .kpi-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }

        .kpi {
            font-size: 32px;
            font-weight: bold;
            color: #2e7d32;
            margin-bottom: 5px;
        }

        .muted {
            color: #777;
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .section h2 {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f7f7f7;
        }

        .available {
            color: #2e7d32;
            font-weight: bold;
        }

        .out-of-stock {
            color: #c62828;
            font-weight: bold;
        }

        .unknown {
            color: #777;
            font-weight: bold;
        }

        .btn {
            background: #2e7d32;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #256628;
        }

        footer {
            text-align: center;
            padding: 30px;
            color: #777;
            margin-top: 40px;
        }

        @media (max-width: 768px) {

            .kpi-container {
                grid-template-columns: 1fr;
            }

            nav {
                flex-direction: column;
                gap: 15px;
            }

            nav a {
                margin-left: 10px;
            }

            .container {
                width: 92%;
            }

            table {
                font-size: 14px;
            }

        }

    </style>

</head>

<body>

<nav>

    <div class="logo">
        FoodCompass
    </div>

    <div>

        <a href="../index.html">Home</a>

        <a href="../products.html">Products</a>

        <a href="../compare.html">Compare</a>

        <a href="../lists.html">Lists</a>

        <a href="../login.html">Sign in</a>

    </div>

</nav>

<div class="container">

    <div class="header">

        <h1>Retailer Dashboard</h1>

        <p>
            Welcome,
            <?php echo htmlspecialchars($retailer['retailer_name']); ?>
        </p>

    </div>

    <div class="kpi-container">

        <div class="kpi-card">

            <div class="kpi">
                <?php echo $total_products; ?>
            </div>

            <p class="muted">
                Products listed
            </p>

        </div>

        <div class="kpi-card">

            <div class="kpi">
                <?php echo $total_shared_lists; ?>
            </div>

            <p class="muted">
                Shared lists
            </p>

        </div>

        <div class="kpi-card">

            <div class="kpi">
                <?php echo ucfirst($retailer['status']); ?>
            </div>

            <p class="muted">
                Retailer status
            </p>

        </div>

    </div>

    <div class="section">

        <h2>My Products</h2>

        <table>

            <thead>

                <tr>

                    <th>Product</th>
                    <th>Brand</th>
                    <th>Price</th>
                    <th>Availability</th>

                </tr>

            </thead>

            <tbody>

                <?php if ($products->num_rows > 0): ?>

                    <?php while ($product = $products->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($product['brand_name']); ?>
                            </td>

                            <td>
                                Rs.
                                <?php echo number_format($product['price'], 2); ?>
                            </td>

                            <td>

                                <?php if ($product['stock_status'] === 'available'): ?>

                                    <span class="available">
                                        Available
                                    </span>

                                <?php elseif ($product['stock_status'] === 'out_of_stock'): ?>

                                    <span class="out-of-stock">
                                        Out of stock
                                    </span>

                                <?php else: ?>

                                    <span class="unknown">
                                        Unknown
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="4">
                            No products found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

    <div class="section">

        <h2>Shared Customer Lists</h2>

        <?php if ($shared_lists->num_rows > 0): ?>

            <table>

                <thead>

                    <tr>

                        <th>List Name</th>
                        <th>Shared Via</th>
                        <th>Shared Date</th>
                        <th>Action</th>

                    </tr>

                </thead>

                <tbody>

                    <?php while ($list = $shared_lists->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php echo htmlspecialchars($list['list_name']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($list['shared_via']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($list['shared_at']); ?>
                            </td>

                            <td>

                                <a class="btn" href="#">
                                    View
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        <?php else: ?>

            <p class="muted">
                No customer lists have been shared with this retailer yet.
            </p>

        <?php endif; ?>

    </div>

</div>

<footer>

    © 2026 FoodCompass. All rights reserved.

</footer>

</body>

</html>