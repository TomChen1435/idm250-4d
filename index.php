<?php

require_once('../db_connect.php');
require_once('../auth.php');

$sql = "SELECT sku_id, sku_name, sku_description,
        FROM recipes_idm_232";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<h2>SKU Management</h2>
<p>Manage and edit product SKUs in your warehouse</p>


<form method="get">
    <input
        type="text"
        name="search"
        placeholder="Search by SKU, name, or category..."
        value="<?= htmlspecialchars($searchTerm) ?>"
    >
    <button type="submit">Search</button>
</form>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Description</th>
            <th>Dimension</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($filteredSKUs as $sku): ?>
            <tr>
                <td><?= htmlspecialchars($sku['id']) ?></td>
                <td><?= htmlspecialchars($sku['name']) ?></td>
                <td><?= htmlspecialchars($sku['description']) ?></td>
                <td><?= htmlspecialchars($sku['dimension']) ?></td>
                <td class="actions">
                    <a href="?edit=<?= urlencode($sku['id']) ?>">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>





//product form

<body>
    <h1><?php echo isset($_GET['id']) ? 'Edit Product' : 'Create New Product'; ?></h1>
    <?php
    require '../lib/cms.php';

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $product = $id ? getProductById($id) : null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if($id) update_product($id, $_POST);
        else create_product($_POST);
        }
        header('Location: index.php');
        exit;
    }

    <form method='POST">
            <div class= "form-control">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" value="<?php echo $product['name']? htmlspecialchars($product['name']) : ''; ?>" required>
    '
</body>