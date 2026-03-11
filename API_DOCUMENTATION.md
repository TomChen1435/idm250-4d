# WMS API Documentation

Complete reference for integrating with the 4D Warehouse Management System.

**Administrator:** Enoch Tuffour  
**Server:** Drexel University Web Server  
**Database:** et556_db

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Authentication](#authentication)
3. [Key Concepts](#key-concepts)
4. [Sending MPLs](#sending-mpls)
5. [Sending Orders](#sending-orders)
6. [Receiving Callbacks](#receiving-callbacks)
7. [Error Handling](#error-handling)
8. [Testing with Yaak](#testing-with-yaak)
9. [Technical Reference](#technical-reference)

---

## Quick Start

### Understanding the System

The WMS uses **unit-based tracking**. Each physical item must have a unique `unit_id`.

**Key Concept:** One item = one unit_id (not quantity-based)

### Base URL

**Production:**
```
https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1
```

### Endpoints

- **Send MPL:** `POST /mpls.php`
- **Send Order:** `POST /orders.php`

---

## Authentication

Include this header in all requests:

```
X-API-Key: sir-4d-api-2026
```

All requests without a valid API key will return `401 Unauthorized`.

---

## Key Concepts

### Unit-Based Inventory

Each physical unit is tracked individually with a unique `unit_id`:

```
MPL receives 3 units of SKU "ABC-123":
- unit_id: U001, sku: ABC-123
- unit_id: U002, sku: ABC-123
- unit_id: U003, sku: ABC-123

Inventory = 3 separate records (not aggregated quantity)
UI displays: ABC-123 (3 units available)
```

### Important Rules

- `unit_id` must be **globally unique** across all MPLs
- Each `unit_id` can only exist once in the system
- Orders reference specific `unit_id` values
- All `unit_id` values in an order must exist in inventory
- Orders are rejected if any `unit_id` is missing (no partial orders)

---

## Sending MPLs

### Endpoint

```
POST https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/mpls.php
```

### Required Headers

```
Content-Type: application/json
X-API-Key: sir-4d-api-2026
```

### Minimal Example

Send 3 units of the same SKU:

```json
{
  "reference_number": "MPL-2026-001",
  "items": [
    {"unit_id": "U001", "sku": "ABC-123"},
    {"unit_id": "U002", "sku": "ABC-123"},
    {"unit_id": "U003", "sku": "ABC-123"}
  ]
}
```

### Full Example with SKU Details

```json
{
  "reference_number": "MPL-2026-001",
  "trailer_number": "TRAILER-12345",
  "expected_arrival": "2026-03-15",
  "items": [
    {
      "unit_id": "U001",
      "sku": "1720813-0132",
      "sku_details": {
        "description": "ASH WHT FAS 4/4 RGH KD 9-11FT",
        "uom_primary": "PALLET",
        "pieces": 110,
        "length_inches": 48.0,
        "width_inches": 40.0,
        "height_inches": 50.0,
        "weight_lbs": 1200.0,
        "ficha": 452
      }
    },
    {
      "unit_id": "U002",
      "sku": "1720813-0132"
    }
  ]
}
```

### Field Reference

| Field | Required? | Description |
|-------|-----------|-------------|
| `reference_number` | Yes | Unique MPL identifier (also accepts `mpl_number`) |
| `trailer_number` | No | Trailer tracking number |
| `expected_arrival` | No | Expected arrival date (YYYY-MM-DD) |
| `items` | Yes | Array of units (minimum 1) |
| `items[].unit_id` | Yes | Globally unique unit identifier |
| `items[].sku` | Yes | SKU code |
| `items[].sku_details` | Recommended | Product information |

### SKU Details Fields

When providing `sku_details`:

| Field | Type | Description |
|-------|------|-------------|
| `description` | String | Product description |
| `uom_primary` (or `uom`) | String | PALLET or BUNDLE |
| `pieces` | Integer | Pieces per unit |
| `length_inches` (or `length`) | Float | Length in inches |
| `width_inches` (or `width`) | Float | Width in inches |
| `height_inches` (or `height`) | Float | Height in inches |
| `weight_lbs` (or `weight`) | Float | Weight in pounds |
| `ficha` | Integer | Ficha number |

**Auto-Create/Update SKUs:**
- If SKU doesn't exist → WMS creates it with provided details
- If SKU exists → WMS updates it with new details
- If omitted and SKU doesn't exist → Error returned

### Success Response (201 Created)

```json
{
  "success": true,
  "message": "MPL received successfully",
  "mpl_id": 123,
  "reference_number": "MPL-2026-001",
  "units_count": 3
}
```

### Error Responses

**Duplicate MPL (409 Conflict):**
```json
{
  "error": "Conflict",
  "details": "MPL with reference number MPL-2026-001 already exists"
}
```

**Missing SKU (400 Bad Request):**
```json
{
  "error": "Bad Request",
  "details": "Missing SKUs in WMS: ABC-123. Provide full SKU details to auto-create."
}
```

**Duplicate unit_id (500 Internal Server Error):**
```json
{
  "error": "Internal Server Error",
  "details": "Failed to process MPL: Duplicate entry 'U001' for key 'unit_id'"
}
```

---

## Sending Orders

### Endpoint

```
POST https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/orders.php
```

### Required Headers

```
Content-Type: application/json
X-API-Key: sir-4d-api-2026
```

### Example Request

```json
{
  "order_number": "ORD-2026-001",
  "ship_to_company": "Acme Corporation",
  "ship_to_street": "123 Main St",
  "ship_to_city": "Philadelphia",
  "ship_to_state": "PA",
  "ship_to_zip": "19103",
  "items": [
    {"unit_id": "U001", "sku": "ABC-123"},
    {"unit_id": "U002", "sku": "ABC-123"}
  ]
}
```

### Field Reference

| Field | Required? | Description |
|-------|-----------|-------------|
| `order_number` | Yes | Unique order identifier |
| `ship_to_company` (or `customer_name`) | Yes | Customer name |
| `ship_to_street` | No | Street address |
| `ship_to_city` | No | City |
| `ship_to_state` | No | State |
| `ship_to_zip` | No | ZIP code |
| `items` | Yes | Array of units (minimum 1) |
| `items[].unit_id` | Yes | Must exist in WMS inventory |
| `items[].sku` | Yes | SKU code |

### Validation Rules

**All units must exist in inventory:**
- Each `unit_id` must have status `available` in inventory
- If **any** `unit_id` is not found → entire order rejected
- No partial orders accepted

**Example:**
```
Inventory: U001, U002, U003
Order: U001, U002 → SUCCESS
Order: U001, U999 → REJECTED (U999 not in inventory)
```

### Success Response (201 Created)

```json
{
  "success": true,
  "message": "Order received successfully",
  "order_id": 456,
  "order_number": "ORD-2026-001",
  "customer_name": "Acme Corporation",
  "units_count": 2
}
```

### Error Responses

**Units Not in Inventory (400 Bad Request):**
```json
{
  "error": "Bad Request",
  "details": "Units not in WMS inventory: U001, U002"
}
```

**Duplicate Order (409 Conflict):**
```json
{
  "error": "Conflict",
  "details": "Order with number ORD-2026-001 already exists"
}
```

---

## Receiving Callbacks

When warehouse staff confirms an MPL or ships an order, WMS sends callbacks to CMS.

### Callback Endpoints (CMS)

**MPL Confirmations:**
```
POST https://digmstudents.westphal.drexel.edu/~sej84/idm250/api/v1/mpls.php
```

**Order Shipments:**
```
POST https://digmstudents.westphal.drexel.edu/~sej84/idm250/api/v1/orders.php
```

### MPL Confirmation Callback

**When:** Warehouse confirms an MPL

**Headers Sent:**
```
Content-Type: application/json
X-API-Key: sir-4d-api-2026
```

**Payload:**
```json
{
  "action": "confirm",
  "reference_number": "MPL-2026-001"
}
```

**What Happens:**
1. All units from MPL added to inventory (status = 'available')
2. MPL status changes to 'confirmed'
3. All line items status changes to 'received'
4. Callback sent to CMS

**CMS Should:**
- Update system to mark MPL as confirmed
- Return HTTP 200 OK

### Order Shipment Callback

**When:** Warehouse ships an order

**Headers Sent:**
```
Content-Type: application/json
X-API-Key: sir-4d-api-2026
```

**Payload:**
```json
{
  "action": "ship",
  "order_number": "ORD-2026-001",
  "shipped_at": "2026-03-08"
}
```

**What Happens:**
1. Individual units deleted from inventory
2. Order status changes to 'shipped'
3. Units logged to shipped_items history
4. Callback sent to CMS

**CMS Should:**
- Update system to mark order as shipped
- Notify customer
- Return HTTP 200 OK

---

## Error Handling

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK - Request successful |
| 201 | Created - Resource created successfully |
| 400 | Bad Request - Invalid data or units not in inventory |
| 401 | Unauthorized - Invalid or missing API key |
| 405 | Method Not Allowed - Use POST method |
| 409 | Conflict - Duplicate reference_number/order_number |
| 500 | Internal Server Error - Database error or duplicate unit_id |

### Common Errors

**Invalid API Key:**
```json
{
  "error": "Unauthorized",
  "details": "Invalid or missing API key"
}
```

**Missing Required Field:**
```json
{
  "error": "Bad Request",
  "details": "Missing required field: reference_number"
}
```

**Duplicate unit_id:**
```json
{
  "error": "Internal Server Error",
  "details": "Failed to process MPL: Duplicate entry 'U001' for key 'unit_id'"
}
```

### Best Practices

1. **Always generate globally unique unit_ids** - Use UUIDs, timestamps, or sequential IDs with prefix
2. **Include sku_details on first send** - Ensures SKU information is current
3. **Handle 400 errors gracefully** - Check which units are missing from inventory
4. **Retry on 500 errors** - Temporary database issues
5. **Don't retry 409 errors** - Resource already exists

---

## Testing with Yaak

### Test 1: Send MPL

**Method:** POST  
**URL:** `https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/mpls.php`

**Headers:**
```
Content-Type: application/json
X-API-Key: sir-4d-api-2026
```

**Body:**
```json
{
  "reference_number": "TEST-MPL-2026-001",
  "trailer_number": "TRAILER-999",
  "expected_arrival": "2026-03-15",
  "items": [
    {
      "unit_id": "TEST-U-001",
      "sku": "TEST-SKU-001",
      "sku_details": {
        "description": "Test Product",
        "uom_primary": "PALLET",
        "pieces": 100,
        "length_inches": 48.0,
        "width_inches": 40.0,
        "height_inches": 50.0,
        "weight_lbs": 1200.0,
        "ficha": 999
      }
    },
    {
      "unit_id": "TEST-U-002",
      "sku": "TEST-SKU-001"
    }
  ]
}
```

**Expected Response:**
```json
{
  "success": true,
  "message": "MPL received successfully",
  "mpl_id": 123,
  "reference_number": "TEST-MPL-2026-001",
  "units_count": 2
}
```

### Test 2: Verify in UI

1. Login: `https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/login.php`
2. Go to MPL page → See "TEST-MPL-2026-001" (status: Pending)
3. Click to view items → See 2 individual units
4. Go to SKU page → See "TEST-SKU-001" with UOM = "PALLET"
5. Confirm MPL → Units move to inventory

### Test 3: Send Order

**Method:** POST  
**URL:** `https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/orders.php`

**Headers:**
```
Content-Type: application/json
X-API-Key: sir-4d-api-2026
```

**Body:**
```json
{
  "order_number": "TEST-ORD-2026-001",
  "ship_to_company": "Test Customer Inc",
  "ship_to_street": "123 Test St",
  "ship_to_city": "Philadelphia",
  "ship_to_state": "PA",
  "ship_to_zip": "19103",
  "items": [
    {"unit_id": "TEST-U-001", "sku": "TEST-SKU-001"}
  ]
}
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Order received successfully",
  "order_id": 456,
  "order_number": "TEST-ORD-2026-001",
  "customer_name": "Test Customer Inc",
  "units_count": 1
}
```

### Test 4: Verify Order

1. Go to Orders page → See "TEST-ORD-2026-001" (status: Pending)
2. Go to Inventory → See 1 unit remaining (2 received - 1 ordered)
3. Ship Order → Units removed from inventory
4. Go to Shipped Items → See unit shipped

---

## Technical Reference

### Database Schema

**packing_list:**
- `id` - Primary key
- `reference_number` - UNIQUE, MPL identifier
- `trailer_number` - Trailer tracking
- `expected_arrival` - Expected date
- `status` - pending, confirmed, closed
- `created_at` - Timestamp
- `confirmed_at` - Confirmation timestamp
- `confirmed_by_user_id` - User who confirmed

**packing_list_items:**
- `id` - Primary key
- `mpl_id` - Foreign key to packing_list
- `unit_id` - UNIQUE, unit identifier
- `sku` - SKU code
- `status` - pending, received

**orders:**
- `id` - Primary key
- `order_number` - UNIQUE, order identifier
- `ship_to_company` - Customer name
- `ship_to_street`, `ship_to_city`, `ship_to_state`, `ship_to_zip` - Address
- `status` - pending, shipped, closed
- `created_at` - Timestamp
- `shipped_at` - Shipment timestamp

**order_items:**
- `id` - Primary key
- `order_id` - Foreign key to orders
- `unit_id` - UNIQUE, unit identifier
- `sku` - SKU code

**inventory:**
- `id` - Primary key
- `unit_id` - UNIQUE, unit identifier
- `sku` - SKU code
- `location` - Warehouse location (default: 'warehouse')
- `status` - available, reserved, shipped
- `received_at` - Timestamp

**shipped_items:**
- `id` - Primary key
- `order_id` - Order reference
- `order_number` - Order number
- `unit_id` - Unit identifier
- `sku` - SKU code
- `sku_description` - Product description
- `shipped_at` - Timestamp

### Unique Constraints

1. `packing_list.reference_number` - MPL number must be unique
2. `packing_list_items.unit_id` - Each unit can only appear once across ALL MPLs
3. `orders.order_number` - Order number must be unique
4. `order_items.unit_id` - Each unit can only appear once across ALL orders
5. `inventory.unit_id` - Each unit can only exist once in inventory

### Timezone

All timestamps stored in **Eastern Time (EST/EDT)**.  
Automatically adjusts for daylight saving time.

### Unit Workflow

1. **MPL Received** → `unit_id` in `packing_list_items` (status: pending)
2. **MPL Confirmed** → `unit_id` in `inventory` (status: available)
3. **Order Created** → `unit_id` in `order_items`, removed from `inventory`
4. **Order Shipped** → `unit_id` in `shipped_items`, removed from `order_items`

### Field Name Compatibility

The API accepts both CMS and WMS field names:

| CMS Field | WMS Field | Both Work |
|-----------|-----------|-----------|
| `mpl_number` | `reference_number` | Yes |
| `customer_name` | `ship_to_company` | Yes |
| `uom_primary` | `uom` | Yes |
| `length_inches` | `length` | Yes |
| `width_inches` | `width` | Yes |
| `height_inches` | `height` | Yes |
| `weight_lbs` | `weight` | Yes |

---

## Support

**WMS Administrator:** Enoch Tuffour  
**WMS Login:** https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/login.php  
**API Base:** https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1

---

## Changelog

### Current Version
- Unit-based tracking system (one record per physical unit)
- `unit_id` required for all items
- Individual unit tracking in inventory
- SKU auto-create/update via API
- Timezone auto-adjusts for EST/EDT
- UOM field uses dropdown (PALLET or BUNDLE)
- Inventory displays individual units with reference numbers
- Fixed bind_param bug in SKU auto-update

### Previous Versions
- Version 2.0: Added SKU auto-update and CMS field compatibility
- Version 1.0: Initial quantity-based system
