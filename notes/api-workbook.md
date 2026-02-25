# API Workbook

This guide walks through the key PHP functions, database patterns, and API logic used in the CMS/WMS demo project. Use it as a reference while building your own system. Don't copy-paste -- read, understand, then write your own version.

## Table of Contents

- [How the Project is Organized](#how-the-project-is-organized)
- [How the Two Systems Communicate](#how-the-two-systems-communicate)
- [Part 1: CMS (Content Management System)](#part-1-cms-content-management-system)
  - [Database Connection](#cms-database-connection)
  - [Configuration (.env.php)](#cms-configuration)
  - [Authentication](#cms-authentication)
  - [SKU Functions](#cms-sku-functions)
  - [Inventory Functions](#cms-inventory-functions)
  - [MPL Functions](#cms-mpl-functions)
  - [Order Functions](#cms-order-functions)
  - [Sending API Requests (Outbound)](#cms-sending-api-requests)
  - [Receiving API Callbacks (Inbound)](#cms-receiving-api-callbacks)
  - [Logging](#cms-logging)
- [Part 2: WMS (Warehouse Management System)](#part-2-wms-warehouse-management-system)
  - [Database Connection](#wms-database-connection)
  - [Configuration (.env.php)](#wms-configuration)
  - [Authentication](#wms-authentication)
  - [SKU Functions](#wms-sku-functions)
  - [Inventory Functions](#wms-inventory-functions)
  - [MPL Functions](#wms-mpl-functions)
  - [Order Functions](#wms-order-functions)
  - [Shipped Items Functions](#wms-shipped-items-functions)
  - [Receiving API Requests (Inbound)](#wms-receiving-api-requests)
  - [Sending API Callbacks (Outbound)](#wms-sending-api-callbacks)
- [Key PHP Functions Reference](#key-php-functions-reference)

---

## How the Project is Organized

Each system (CMS and WMS) is a standalone PHP application with the same basic folder structure:

```
cms/ (or wms/)
  .env.php              <- Database credentials and API keys (not in git)
  sql/
    database.sql        <- Creates the database
    schema.sql          <- Creates tables and seeds data
  includes/
    db.php              <- Database connection
    auth.php            <- Login, sessions, API key checking
    functions.php       <- All database read/write functions
    api_client.php      <- Functions that send HTTP requests to the other system
    log.php             <- Simple file logger
  api/v1/
    mpls.php            <- API endpoint (receives requests from the other system)
    orders.php          <- API endpoint (receives requests from the other system)
  skus/                 <- SKU management pages (list, create, edit, delete)
  inventory/            <- Inventory viewing pages
  mpls/                 <- MPL management pages (list, create, edit, send, delete)
  orders/               <- Order management pages (list, create, edit, send, delete)
```

---

## How the Two Systems Communicate

The CMS and WMS talk to each other by sending **HTTP POST requests** containing **JSON data**. Each request includes an **API key** in the headers for authentication.

There are four API interactions total:

```
1. CMS sends MPL ----------> WMS receives MPL
2. CMS sends Order --------> WMS receives Order
3. WMS confirms MPL -------> CMS receives confirmation
4. WMS ships Order --------> CMS receives ship notification
```

Each system has two roles:
- **Sender**: builds JSON data and POSTs it to the other system's API URL
- **Receiver**: has an API endpoint that reads the JSON, validates it, and saves it to the database

---

## Part 1: CMS (Content Management System)

The CMS is the production company's system. It manages SKUs, tracks inventory, creates MPLs (to send product to the warehouse), and creates orders (to request the warehouse ship product to a customer).

### CMS Database Connection

File: `cms/includes/db.php`

The connection is created using PHP's `mysqli` class. It reads credentials from `.env.php` and stores the connection in a global variable.

```php
$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$connection->set_charset('utf8mb4');
```

The `$connection` variable is used throughout the application via the `global` keyword:

```php
function some_function() {
    global $connection;
    // now you can use $connection to run queries
}
```

- [mysqli documentation](https://www.php.net/manual/en/book.mysqli.php)
- [mysqli::set_charset](https://www.php.net/manual/en/mysqli.set-charset.php)

### CMS Configuration

File: `cms/.env.php`

This file returns a PHP array with database credentials and API settings. It is loaded using `require`:

```php
$env = require dirname(__DIR__) . '/.env.php';
```

The file contains values like database host, name, user, password, your own API key (for validating inbound requests), and the other system's API URL and key (for sending outbound requests).

- [require](https://www.php.net/manual/en/function.require.php)
- [dirname](https://www.php.net/manual/en/function.dirname.php)

### CMS Authentication

File: `cms/includes/auth.php`

#### Starting a Session

Sessions are started at the top of the auth file. API endpoints skip this because they don't need browser sessions.

```php
if (!defined('API_REQUEST') && session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

- [session_start](https://www.php.net/manual/en/function.session-start.php)
- [session_status](https://www.php.net/manual/en/function.session-status.php)

#### Logging In

The `login_user()` function takes an email and password. It queries the `users` table, then checks the password against the stored hash using `password_verify()`.

On success, it stores the user's ID and email in `$_SESSION`:

```php
$_SESSION['user_id']    = $user['id'];
$_SESSION['user_email'] = $user['email'];
```

- [password_verify](https://www.php.net/manual/en/function.password-verify.php)
- [password_hash](https://www.php.net/manual/en/function.password-hash.php) (used to create the stored hash)

#### Requiring Login

Protected pages call `require_login()` at the top. If the user isn't logged in, they get redirected to the login page.

```php
function require_login() {
    if (!is_logged_in()) {
        header('Location: /your-app/login.php');
        exit;
    }
}
```

- [header](https://www.php.net/manual/en/function.header.php)

#### Checking API Keys

When an API endpoint receives a request, it validates the `x-api-key` header against the expected key. If invalid, it returns a 401 JSON error and stops.

```php
$headers = getallheaders();
$headers = array_change_key_case($headers, CASE_LOWER);
```

The `array_change_key_case()` call converts all header names to lowercase so the check works regardless of how the sender capitalized the header name.

- [getallheaders](https://www.php.net/manual/en/function.getallheaders.php)
- [array_change_key_case](https://www.php.net/manual/en/function.array-change-key-case.php)

### CMS SKU Functions

File: `cms/includes/functions.php`

SKU functions handle CRUD (Create, Read, Update, Delete) operations on the `skus` table.

#### Fetching All SKUs

Runs a simple `SELECT *` query and returns all rows as an array.

```php
$sql    = "SELECT * FROM skus ORDER BY sku";
$result = $connection->query($sql);
$skus   = [];

while ($row = $result->fetch_assoc()) {
    $skus[] = $row;
}

return $skus;
```

The `while` loop with `fetch_assoc()` pulls one row at a time as an associative array and appends it to the `$skus` array.

**Example return value:**

```php
[
    ['id' => 1, 'sku' => '1720813-0132', 'description' => 'MDF ST LX ...', 'uom_primary' => 'BUNDLE', ...],
    ['id' => 2, 'sku' => '1720814-0248', 'description' => 'PINE CLR VG ...', 'uom_primary' => 'BUNDLE', ...],
    // ...
]
```

- [mysqli::query](https://www.php.net/manual/en/mysqli.query.php)
- [mysqli_result::fetch_assoc](https://www.php.net/manual/en/mysqli-result.fetch-assoc.php)

#### Fetching a Single SKU by ID

Uses a **prepared statement** with a placeholder (`?`) to safely insert the ID into the query. This prevents SQL injection.

```php
$stmt = $connection->prepare("SELECT * FROM skus WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
return $result->fetch_assoc();
```

The `'i'` in `bind_param` means "integer". Other type characters:
- `'s'` = string
- `'d'` = double (decimal number)
- `'b'` = blob

**Example return value:**

```php
['id' => 1, 'sku' => '1720813-0132', 'description' => 'MDF ST LX ...', ...]
```

Returns `null` if no matching row is found.

- [mysqli::prepare](https://www.php.net/manual/en/mysqli.prepare.php)
- [mysqli_stmt::bind_param](https://www.php.net/manual/en/mysqli-stmt.bind-param.php)
- [mysqli_stmt::execute](https://www.php.net/manual/en/mysqli-stmt.execute.php)
- [mysqli_stmt::get_result](https://www.php.net/manual/en/mysqli-stmt.get-result.php)

#### Fetching a Single SKU by Code

Same pattern as fetching by ID, but searches by the `sku` column (a string) instead of `id`.

```php
$stmt = $connection->prepare("SELECT * FROM skus WHERE sku = ? LIMIT 1");
$stmt->bind_param('s', $sku_code);
```

The `'s'` tells `bind_param` this is a string value.

#### Creating a SKU

Inserts a new row into the `skus` table using a prepared statement with 8 parameters.

```php
$stmt = $connection->prepare(
    "INSERT INTO skus (sku, description, uom_primary, piece_count, length_inches, width_inches, height_inches, weight_lbs)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param('sssidddd', ...);
```

The type string `'sssidddd'` means: string, string, string, integer, double, double, double, double -- one character per `?` placeholder, in order.

On success, `$connection->insert_id` returns the auto-increment ID of the new row.

- [mysqli::$insert_id](https://www.php.net/manual/en/mysqli.insert-id.php)

#### Updating a SKU

Same idea as create, but uses `UPDATE ... WHERE id = ?` and adds the ID as an extra parameter at the end.

#### Deleting a SKU

Uses `DELETE FROM skus WHERE id = ?` with a prepared statement. Returns `true` on success, `false` on failure.

### CMS Inventory Functions

File: `cms/includes/functions.php`

CMS inventory has a `location` field that tracks whether a unit is `'internal'` (at the production facility) or `'warehouse'` (transferred to the warehouse).

#### Fetching Inventory with a JOIN

Inventory records only store a `sku_id` (foreign key). To display the SKU code and description alongside each unit, the query uses a **JOIN**:

```php
$sql = "SELECT i.*, s.sku, s.description, s.uom_primary
        FROM inventory i
        JOIN skus s ON i.sku_id = s.id";
```

The `i.*` selects all columns from the `inventory` table. The `s.sku, s.description, s.uom_primary` pulls specific columns from the `skus` table. The `ON i.sku_id = s.id` links the two tables.

An optional `$location` parameter filters by location:

```php
if ($location) {
    $sql .= " WHERE i.location = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param('s', $location);
} else {
    $stmt = $connection->prepare($sql);
}
```

**Example usage:**

```php
$internal_units  = get_inventory('internal');   // Units at the production facility
$warehouse_units = get_inventory('warehouse');  // Units transferred to the warehouse
$all_units       = get_inventory();             // Everything
```

#### Updating Inventory Location

When an MPL is confirmed, each unit's location changes from `'internal'` to `'warehouse'`:

```php
$stmt = $connection->prepare("UPDATE inventory SET location = ? WHERE unit_id = ?");
$stmt->bind_param('ss', $location, $unit_id);
```

### CMS MPL Functions

File: `cms/includes/functions.php`

MPLs (Material Packing Lists) represent shipments of inventory from the CMS to the warehouse. Each MPL has a header (reference number, trailer number, arrival date, status) and one or more items (inventory units).

#### Creating an MPL (Header + Items)

Creating an MPL is a two-step process:

1. Insert the MPL header row
2. Insert one `mpl_items` row per unit

```php
// Step 1: Insert the header
$stmt = $connection->prepare(
    "INSERT INTO mpls (reference_number, trailer_number, expected_arrival, status)
     VALUES (?, ?, ?, 'draft')"
);
$stmt->bind_param('sss', $data['reference_number'], $data['trailer_number'], $data['expected_arrival']);
$stmt->execute();

$mpl_id = $connection->insert_id;

// Step 2: Insert each item
$stmt = $connection->prepare("INSERT INTO mpl_items (mpl_id, unit_id) VALUES (?, ?)");

foreach ($unit_ids as $unit_id) {
    $stmt->bind_param('is', $mpl_id, $unit_id);
    $stmt->execute();
}
```

Notice how the same prepared statement (`$stmt`) is reused inside the loop -- you prepare once, then bind and execute for each unit.

#### Fetching MPL Items with a Multi-Table JOIN

To get item details (unit ID, SKU code, description), the query joins three tables:

```php
$stmt = $connection->prepare(
    "SELECT mi.*, i.sku_id, s.sku, s.description
     FROM mpl_items mi
     JOIN inventory i ON mi.unit_id = i.unit_id
     JOIN skus s ON i.sku_id = s.id
     WHERE mi.mpl_id = ?"
);
```

This chains two JOINs: `mpl_items` -> `inventory` (linked by `unit_id`) -> `skus` (linked by `sku_id`).

#### Updating an MPL

Only MPLs with status `'draft'` can be edited. The function first checks the status, then updates the header, deletes all old items, and inserts the new items:

```php
// Check if MPL is still a draft
$check = $connection->prepare("SELECT id FROM mpls WHERE id = ? AND status = 'draft'");
// ...

// Delete old items
$stmt = $connection->prepare("DELETE FROM mpl_items WHERE mpl_id = ?");

// Insert new items
$stmt = $connection->prepare("INSERT INTO mpl_items (mpl_id, unit_id) VALUES (?, ?)");
```

#### Updating MPL Status

A simple update that changes the `status` column:

```php
$stmt = $connection->prepare("UPDATE mpls SET status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $id);
```

CMS MPL statuses: `'draft'` -> `'sent'` -> `'confirmed'`

### CMS Order Functions

File: `cms/includes/functions.php`

Orders follow the same patterns as MPLs. Each order has a header (order number, shipping address, status) and items (inventory units).

#### Creating an Order

Same two-step pattern -- insert the header, get the ID, then insert items:

```php
$stmt = $connection->prepare(
    "INSERT INTO orders (order_number, ship_to_company, ship_to_street, ship_to_city, ship_to_state, ship_to_zip, status)
     VALUES (?, ?, ?, ?, ?, ?, 'draft')"
);
$stmt->bind_param('ssssss', ...);
$stmt->execute();

$order_id = $connection->insert_id;
```

#### Updating Order Status (with Optional Date)

Orders have an optional `shipped_at` date that gets set when the WMS ships the order. The function handles both cases:

```php
if ($shipped_at) {
    $stmt = $connection->prepare("UPDATE orders SET status = ?, shipped_at = ? WHERE id = ?");
    $stmt->bind_param('ssi', $status, $shipped_at, $id);
} else {
    $stmt = $connection->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
}
```

CMS order statuses: `'draft'` -> `'sent'` -> `'confirmed'`

#### Counting Order Items

Uses SQL `COUNT(*)` to get the number of items without loading all the data:

```php
$stmt = $connection->prepare("SELECT COUNT(*) as count FROM order_items WHERE order_id = ?");
```

The `as count` creates an alias so you can access the result as `$row['count']`.

### CMS Sending API Requests

File: `cms/includes/api_client.php`

This file contains functions that **send** HTTP requests from the CMS to the WMS.

#### The Core HTTP Function

`api_request()` is the base function that all API calls use. It sends an HTTP request using PHP's `stream_context_create()` and `file_get_contents()`.

```php
function api_request($url, $method, $data, $api_key) {
    $options = [
        'http' => [
            'method'  => $method,
            'header'  => "Content-Type: application/json\r\n" .
                         "x-api-key: " . $api_key . "\r\n",
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ];

    $context  = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    $result   = json_decode($response, true);

    return $result;
}
```

Breaking this down:

1. **`json_encode($data)`** converts a PHP array into a JSON string for the request body
2. **`stream_context_create()`** builds the HTTP request configuration (method, headers, body)
3. **`file_get_contents()`** sends the request and returns the response as a string
4. **`json_decode($response, true)`** converts the JSON response back into a PHP array (the `true` parameter returns an array instead of an object)
5. The `@` before `file_get_contents` suppresses PHP warnings (so they don't break the page)
6. `'ignore_errors' => true` tells PHP to return the response body even on HTTP errors (like 400 or 500)

**Example: what `json_encode` produces:**

```php
$data = ['name' => 'John', 'age' => 25];
echo json_encode($data);
// Output: {"name":"John","age":25}
```

**Example: what `json_decode` produces:**

```php
$json = '{"success":true,"message":"MPL received"}';
$result = json_decode($json, true);
// $result = ['success' => true, 'message' => 'MPL received']
```

- [json_encode](https://www.php.net/manual/en/function.json-encode.php)
- [json_decode](https://www.php.net/manual/en/function.json-decode.php)
- [stream_context_create](https://www.php.net/manual/en/function.stream-context-create.php)
- [file_get_contents](https://www.php.net/manual/en/function.file-get-contents.php)

#### Sending an MPL to the WMS

`send_mpl_to_wms()` builds the JSON payload and calls `api_request()`. The payload includes the MPL header data plus an `items` array. Each item includes the `unit_id`, `sku` code, and a `sku_details` object (so the WMS can auto-create the SKU if it doesn't exist yet).

**Example payload sent to WMS:**

```json
{
    "reference_number": "CA1A2B3C4D",
    "trailer_number": "TRL-5521",
    "expected_arrival": "2025-03-15",
    "items": [
        {
            "unit_id": "R2A2508584",
            "sku": "1720813-0132",
            "sku_details": {
                "sku": "1720813-0132",
                "description": "MDF ST LX C2-- ...",
                "uom_primary": "BUNDLE",
                "piece_count": 250,
                "length_inches": 96.00,
                "width_inches": 39.00,
                "height_inches": 29.65,
                "weight_lbs": 3945.22
            }
        },
        {
            "unit_id": "R2A2508591",
            "sku": "1720813-0132",
            "sku_details": { ... }
        }
    ]
}
```

**Example success response from WMS:**

```json
{
    "success": true,
    "message": "MPL received successfully",
    "mpl_id": 1
}
```

**Example error response from WMS:**

```json
{
    "error": "Conflict",
    "details": "MPL with this reference number already exists"
}
```

#### Sending an Order to the WMS

`send_order_to_wms()` works the same way, but the items only need `unit_id` (the WMS already has the SKU data from the MPL transfer).

**Example payload sent to WMS:**

```json
{
    "order_number": "A1B2C3D4",
    "ship_to_company": "Acme Corp",
    "ship_to_street": "123 Main St",
    "ship_to_city": "Springfield",
    "ship_to_state": "IL",
    "ship_to_zip": "62701",
    "items": [
        { "unit_id": "R2A2508584" },
        { "unit_id": "R2A2508591" }
    ]
}
```

### CMS Receiving API Callbacks

Files: `cms/api/v1/mpls.php`, `cms/api/v1/orders.php`

These endpoints receive notifications **from the WMS** (not from a browser). They are set up differently from regular pages.

#### API Endpoint Setup

Every API endpoint follows this pattern:

```php
// Capture any accidental PHP output so it doesn't corrupt our JSON
ob_start();

// Tell auth.php to skip session_start (API doesn't use sessions)
define('API_REQUEST', true);

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, x-api-key');

// Handle preflight OPTIONS request (browser CORS check)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load dependencies
require_once dirname(dirname(__DIR__)) . '/includes/db.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Clear any buffered PHP warnings before sending our JSON
ob_end_clean();

// Validate the API key
$env = require dirname(dirname(__DIR__)) . '/.env.php';
check_api_key($env);
```

- [ob_start / ob_end_clean](https://www.php.net/manual/en/function.ob-start.php) (output buffering)
- [header](https://www.php.net/manual/en/function.header.php)
- [http_response_code](https://www.php.net/manual/en/function.http-response-code.php)

#### Reading the Request Body

API requests send data as JSON in the request body (not as form fields). To read it:

```php
$data = json_decode(file_get_contents('php://input'), true);
```

`php://input` is a special PHP stream that contains the raw request body. `json_decode(..., true)` converts it to an associative array.

- [php://input](https://www.php.net/manual/en/wrappers.php.php)

#### Sending JSON Responses

To send data back to the caller:

```php
// Success
echo json_encode(['success' => true, 'message' => 'MPL confirmed']);

// Error
http_response_code(400);
echo json_encode(['error' => 'Bad Request', 'details' => 'Missing required fields']);
```

Common HTTP status codes used:
- **200** -- OK (default, don't need to set it)
- **400** -- Bad Request (missing or invalid data)
- **401** -- Unauthorized (bad API key)
- **404** -- Not Found (record doesn't exist)
- **405** -- Method Not Allowed (used wrong HTTP method)
- **409** -- Conflict (duplicate record)
- **500** -- Server Error (something broke)

#### MPL Confirmation Callback

When the WMS confirms an MPL, it sends a POST to `cms/api/v1/mpls.php` with:

```json
{ "action": "confirm", "reference_number": "CA1A2B3C4D" }
```

The CMS endpoint:
1. Looks up the MPL by reference number
2. Updates its status to `'confirmed'`
3. Changes each item's inventory location from `'internal'` to `'warehouse'`

#### Order Ship Callback

When the WMS ships an order, it sends a POST to `cms/api/v1/orders.php` with:

```json
{ "action": "ship", "order_number": "A1B2C3D4", "shipped_at": "2025-03-20" }
```

The CMS endpoint:
1. Looks up the order by order number
2. Updates its status to `'confirmed'` and sets the `shipped_at` date
3. Deletes the shipped units from the CMS inventory table

### CMS Logging

File: `cms/includes/log.php`

A simple function that appends timestamped messages to a log file:

```php
function log_event($message) {
    $log_path = dirname(__DIR__) . '/logs/cms.log';

    if (!is_dir($log_dir))
        mkdir($log_dir, 0755, true);

    file_put_contents($log_path, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}
```

**Example log output:**

```
2025-03-15 14:30:22 - SKU 1720813-0132 created
2025-03-15 14:31:05 - MPL CA1A2B3C4D created with 5 items
2025-03-15 14:32:18 - MPL 1 status updated to sent
```

- [file_put_contents](https://www.php.net/manual/en/function.file-put-contents.php) (the `FILE_APPEND` flag adds to the file instead of overwriting it)
- [mkdir](https://www.php.net/manual/en/function.mkdir.php)
- [date](https://www.php.net/manual/en/function.date.php)

---

## Part 2: WMS (Warehouse Management System)

The WMS is the warehouse company's system. It receives MPLs and orders from the CMS via API, manages its own inventory, confirms MPLs (transferring units into stock), and ships orders (removing units from stock).

### WMS Database Connection

File: `wms/includes/db.php`

Same pattern as CMS, but connects to a different database (e.g. `cb_wms` instead of `ca_cms`).

### WMS Configuration

File: `wms/.env.php`

Same structure as CMS, but with reversed API settings -- the WMS stores the CMS's API URL and key (for sending callbacks) instead of the other way around.

### WMS Authentication

File: `wms/includes/auth.php`

Identical pattern to CMS: `session_start`, `login_user()`, `require_login()`, `check_api_key()`. The only difference is the redirect URL points to the WMS login page.

### WMS SKU Functions

File: `wms/includes/functions.php`

Same functions as CMS: `get_skus()`, `get_sku()`, `get_sku_by_code()`, `create_sku()`, `update_sku()`, `delete_sku()`.

The WMS database starts with no SKUs. They are auto-created when MPLs are received via the API (see [Receiving API Requests](#wms-receiving-api-requests)).

### WMS Inventory Functions

File: `wms/includes/functions.php`

WMS inventory is simpler than CMS inventory -- there's no `location` column (the warehouse only tracks what it physically holds).

#### Fetching All Inventory

```php
$sql = "SELECT i.*, s.sku, s.description, s.uom_primary
        FROM inventory i
        JOIN skus s ON i.sku_id = s.id
        ORDER BY i.created_at DESC";
```

Same JOIN pattern as CMS, but no location filter.

#### Adding an Inventory Unit

When an MPL is confirmed, each unit gets added to WMS inventory:

```php
$stmt = $connection->prepare("INSERT INTO inventory (unit_id, sku_id) VALUES (?, ?)");
$stmt->bind_param('si', $unit_id, $sku_id);
```

#### Removing an Inventory Unit

When an order is shipped, each unit gets deleted from WMS inventory:

```php
$stmt = $connection->prepare("DELETE FROM inventory WHERE unit_id = ?");
$stmt->bind_param('s', $unit_id);
```

### WMS MPL Functions

File: `wms/includes/functions.php`

#### Creating an MPL (from API data)

Unlike the CMS (where users create MPLs via forms), the WMS creates MPLs from incoming API data. The status starts as `'open'` (not `'draft'`), and items include the SKU code:

```php
$stmt = $connection->prepare(
    "INSERT INTO mpls (reference_number, trailer_number, expected_arrival, status)
     VALUES (?, ?, ?, 'open')"
);

// Items include SKU code
$stmt = $connection->prepare("INSERT INTO mpl_items (mpl_id, unit_id, sku) VALUES (?, ?, ?)");

foreach ($items as $item) {
    $stmt->bind_param('iss', $mpl_id, $item['unit_id'], $item['sku']);
    $stmt->execute();
}
```

WMS MPL statuses: `'open'` -> `'closed'`

#### Looking Up an MPL by Reference Number

Used to check for duplicates when receiving an MPL via API:

```php
$stmt = $connection->prepare("SELECT * FROM mpls WHERE reference_number = ? LIMIT 1");
$stmt->bind_param('s', $reference_number);
```

### WMS Order Functions

File: `wms/includes/functions.php`

Same patterns as CMS orders, with a few differences:

- Orders are created from API data (status starts as `'open'`, not `'draft'`)
- Has a `get_order_by_number()` function for duplicate checking
- No `update_order()` function (WMS doesn't edit orders, only ships them)

WMS order statuses: `'open'` -> `'closed'`

### WMS Shipped Items Functions

File: `wms/includes/functions.php`

When an order is shipped, the inventory units are deleted. To keep a historical record, the WMS saves a snapshot of the shipped items to a `shipped_items` table before deleting.

#### Saving Shipped Items

```php
$stmt = $connection->prepare(
    "INSERT INTO shipped_items (order_id, order_number, unit_id, sku, sku_description, shipped_at)
     VALUES (?, ?, ?, ?, ?, ?)"
);

foreach ($items as $item) {
    $stmt->bind_param('isssss', $order_id, $order_number, $item['unit_id'], $item['sku'], $item['description'], $shipped_at);
    $stmt->execute();
}
```

This saves the SKU code and description as plain text (not foreign keys), so the data is preserved even if SKUs are later modified or deleted.

#### Getting Shipped Items Summary

Uses `COUNT` and `GROUP BY` to show one row per shipped order with the total number of items:

```php
$sql = "SELECT o.order_number, o.ship_to_company, o.shipped_at, COUNT(si.id) as item_count
        FROM orders o
        JOIN shipped_items si ON o.id = si.order_id
        WHERE o.status = 'closed'
        GROUP BY o.id
        ORDER BY o.shipped_at DESC";
```

### WMS Receiving API Requests

Files: `wms/api/v1/mpls.php`, `wms/api/v1/orders.php`

These are the primary inbound endpoints that receive data from the CMS.

#### Receiving an MPL

`wms/api/v1/mpls.php` handles incoming MPL data. The process:

1. **Read the JSON body**: `json_decode(file_get_contents('php://input'), true)`
2. **Validate required fields**: reference number, trailer number, expected arrival, items
3. **Check for duplicates**: Look up by reference number, return 409 if it already exists
4. **Auto-create missing SKUs**: For each item, check if the SKU exists in the WMS database. If not, create it using the `sku_details` included in the request.
5. **Create the MPL record**: Insert header and items into the database
6. **Return success response**: `{"success": true, "message": "MPL received successfully", "mpl_id": 1}`

**Example error responses:**

Missing fields:
```json
{ "error": "Bad Request", "details": "Missing required MPL fields" }
```

Duplicate MPL:
```json
{ "error": "Conflict", "details": "MPL with this reference number already exists" }
```

Missing SKUs (no details provided to auto-create):
```json
{ "error": "Bad Request", "details": "Missing SKUs in WMS: 1720813-0132, 1720814-0248. Provide full SKU details to auto-create." }
```

#### Receiving an Order

`wms/api/v1/orders.php` handles incoming order data. The process:

1. **Read and validate**: Same pattern as MPLs
2. **Check for duplicates**: Look up by order number
3. **Validate inventory**: Check that every `unit_id` in the order actually exists in the WMS inventory. If units are missing, return a 400 error listing them.
4. **Create the order**: Insert header and items
5. **Return success response**

**Example validation error:**

```json
{ "error": "Bad Request", "details": "Units not in WMS inventory: R2A2508584, R2A2508591" }
```

### WMS Sending API Callbacks

File: `wms/includes/api_client.php`

When the WMS confirms an MPL or ships an order, it sends a callback notification to the CMS. These use the same `api_request()` function.

#### Notifying CMS of MPL Confirmation

```php
function notify_cms_mpl_confirmed($reference_number) {
    global $env;

    $url     = $env['CMS_API_URL'] . '/mpls.php';
    $api_key = $env['CMS_API_KEY'];

    $data = [
        'action'           => 'confirm',
        'reference_number' => $reference_number
    ];

    return api_request($url, 'POST', $data, $api_key);
}
```

**Payload sent to CMS:**

```json
{ "action": "confirm", "reference_number": "CA1A2B3C4D" }
```

#### Notifying CMS of Order Shipment

```php
function notify_cms_order_shipped($order_number, $shipped_at) {
    global $env;

    $url     = $env['CMS_API_URL'] . '/orders.php';
    $api_key = $env['CMS_API_KEY'];

    $data = [
        'action'       => 'ship',
        'order_number' => $order_number,
        'shipped_at'   => $shipped_at
    ];

    return api_request($url, 'POST', $data, $api_key);
}
```

**Payload sent to CMS:**

```json
{ "action": "ship", "order_number": "A1B2C3D4", "shipped_at": "2025-03-20" }
```

#### The Confirm/Ship Action Flows

These are the most complex operations in the project because they involve both local database changes and an API call to the other system.

**MPL Confirm Flow (triggered by WMS user clicking "Confirm"):**

1. Load the MPL and its items from the WMS database
2. For each item, look up the SKU by code, then add the unit to WMS inventory
3. Update the WMS MPL status to `'closed'`
4. Send a callback to the CMS API: `{"action": "confirm", "reference_number": "..."}`
5. The CMS API updates its MPL status to `'confirmed'` and moves each unit's location to `'warehouse'`

**Order Ship Flow (triggered by WMS user clicking "Ship"):**

1. Load the order and its items from the WMS database
2. Save a snapshot of the items to the `shipped_items` table (history)
3. Delete each item's unit from WMS inventory
4. Update the WMS order status to `'closed'` with a `shipped_at` date
5. Send a callback to the CMS API: `{"action": "ship", "order_number": "...", "shipped_at": "..."}`
6. The CMS API updates its order status to `'confirmed'`, sets the shipped date, and deletes the units from CMS inventory

---

## Key PHP Functions Reference

Quick reference for the PHP built-in functions used throughout the project.

### Database

| Function | What It Does | Link |
|---|---|---|
| `new mysqli(...)` | Creates a database connection | [php.net](https://www.php.net/manual/en/mysqli.construct.php) |
| `$connection->prepare()` | Creates a prepared statement (prevents SQL injection) | [php.net](https://www.php.net/manual/en/mysqli.prepare.php) |
| `$stmt->bind_param()` | Binds values to the `?` placeholders in a prepared statement | [php.net](https://www.php.net/manual/en/mysqli-stmt.bind-param.php) |
| `$stmt->execute()` | Runs the prepared statement | [php.net](https://www.php.net/manual/en/mysqli-stmt.execute.php) |
| `$stmt->get_result()` | Gets the result set from a SELECT query | [php.net](https://www.php.net/manual/en/mysqli-stmt.get-result.php) |
| `$result->fetch_assoc()` | Gets the next row as a key-value array | [php.net](https://www.php.net/manual/en/mysqli-result.fetch-assoc.php) |
| `$connection->query()` | Runs a simple query (no user input = no prepared statement needed) | [php.net](https://www.php.net/manual/en/mysqli.query.php) |
| `$connection->insert_id` | Gets the auto-increment ID of the last INSERT | [php.net](https://www.php.net/manual/en/mysqli.insert-id.php) |

### JSON

| Function | What It Does | Link |
|---|---|---|
| `json_encode($array)` | Converts a PHP array to a JSON string | [php.net](https://www.php.net/manual/en/function.json-encode.php) |
| `json_decode($string, true)` | Converts a JSON string to a PHP array | [php.net](https://www.php.net/manual/en/function.json-decode.php) |

### HTTP / API

| Function | What It Does | Link |
|---|---|---|
| `file_get_contents('php://input')` | Reads the raw body of an incoming HTTP request | [php.net](https://www.php.net/manual/en/wrappers.php.php) |
| `file_get_contents($url, false, $context)` | Sends an HTTP request to a URL | [php.net](https://www.php.net/manual/en/function.file-get-contents.php) |
| `stream_context_create()` | Configures an HTTP request (method, headers, body) | [php.net](https://www.php.net/manual/en/function.stream-context-create.php) |
| `header()` | Sets an HTTP response header | [php.net](https://www.php.net/manual/en/function.header.php) |
| `http_response_code()` | Sets the HTTP status code (200, 400, 404, etc.) | [php.net](https://www.php.net/manual/en/function.http-response-code.php) |
| `getallheaders()` | Gets all HTTP request headers | [php.net](https://www.php.net/manual/en/function.getallheaders.php) |

### Sessions / Auth

| Function | What It Does | Link |
|---|---|---|
| `session_start()` | Starts or resumes a session | [php.net](https://www.php.net/manual/en/function.session-start.php) |
| `session_destroy()` | Destroys a session (logout) | [php.net](https://www.php.net/manual/en/function.session-destroy.php) |
| `password_verify()` | Checks a password against a bcrypt hash | [php.net](https://www.php.net/manual/en/function.password-verify.php) |
| `password_hash()` | Creates a bcrypt hash from a password | [php.net](https://www.php.net/manual/en/function.password-hash.php) |

### General

| Function | What It Does | Link |
|---|---|---|
| `intval()` | Converts a value to an integer (safety measure) | [php.net](https://www.php.net/manual/en/function.intval.php) |
| `htmlspecialchars()` | Escapes HTML characters (prevents XSS attacks) | [php.net](https://www.php.net/manual/en/function.htmlspecialchars.php) |
| `date()` | Formats the current date/time | [php.net](https://www.php.net/manual/en/function.date.php) |
| `uniqid()` | Generates a unique ID string | [php.net](https://www.php.net/manual/en/function.uniqid.php) |
| `implode()` | Joins array elements into a string | [php.net](https://www.php.net/manual/en/function.implode.php) |
| `file_put_contents()` | Writes data to a file | [php.net](https://www.php.net/manual/en/function.file-put-contents.php) |
