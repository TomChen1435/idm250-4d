# 4D WMS API Documentation

**Last Updated:** February 28, 2026  

---

## Authentication

All API requests require an API key in the `X-API-Key` header:

```
X-API-Key: sir-4d-api-2026
```

---

## Base URL

**Production (Drexel Server):**
```
https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1
```

**Local Development:**
```
http://localhost:8888/api/v1
```

---

## Key Features

**Auto-Create SKUs** - If a SKU doesn't exist, it will be created automatically when `sku_details` is provided  
**Auto-Update SKUs** - If a SKU exists, it will be UPDATED with new details from `sku_details`  
**Quantity Aggregation** - Multiple items with same SKU are automatically counted and aggregated  
**Field Name Mapping** - Accepts both WMS and CMS field naming conventions  
**Inventory Warnings** - Orders return warnings if inventory is insufficient (but still accept the order)

---



## Receiving MPL from CMS

**Endpoint:** `POST /api/v1/mpls.php`

**Full URL:** `https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/mpls.php`

**Description:** CMS sends a new Master Packing List to the WMS. The MPL will be stored with status "pending" until warehouse staff confirms it.

### Request

**Headers:**
```
Content-Type: application/json
X-API-Key: sir-4d-api-2026
```

**Body (Flexible Format):**
```json
{
  "reference_number": "MPL-2024-001",
  "trailer_number": "TRAILER-12345",
  "expected_arrival": "2026-03-15",
  "items": [
    {
      "unit_id": "R2A2508584",
      "sku": "1720813-0132",
      "sku_details": {
        "description": "Product Description",
        "uom_primary": "PALLET",
        "pieces": 50,
        "length_inches": 48.0,
        "width_inches": 40.0,
        "height_inches": 50.0,
        "weight_lbs": 1200.0
      }
    },
    {
      "unit_id": "R2A2508585",
      "sku": "1720813-0132"
    }
  ]
}
```

**Important Notes:**
- `quantity` field is **optional** - if not provided, each item counts as 1 unit
- Multiple items with the same SKU are automatically aggregated into a single quantity
- Accepts either `mpl_number` OR `reference_number`
- `trailer_number` and `expected_arrival` are optional
- `sku_details` is optional but recommended for auto-create/update

### Field Mapping

The API accepts both WMS and CMS field naming conventions:

| CMS Field | WMS Field | Notes |
|-----------|-----------|-------|
| `reference_number` | `mpl_number` | Either name works |
| `uom_primary` | `uom` | Either name works |
| `length_inches` | `length` | Either name works |
| `width_inches` | `width` | Either name works |
| `height_inches` | `height` | Either name works |
| `weight_lbs` | `weight` | Either name works |

### SKU Auto-Create/Update Behavior

**If SKU doesn't exist:**
- Creates new SKU with provided `sku_details`
- Returns error if SKU missing and no `sku_details` provided

**If SKU exists:**
- **UPDATES** the SKU with new details from `sku_details`
- Skips update if no `sku_details` provided

**Example:**
```
1st MPL: SKU "ABC-123" doesn't exist → Creates with description "Product v1"
2nd MPL: SKU "ABC-123" exists → Updates to description "Product v2"
```

### Response

**Success (201 Created):**
```json
{
  "success": true,
  "message": "MPL received successfully",
  "mpl_id": 123,
  "mpl_number": "MPL-2024-001",
  "items_count": 2
}
```

**Error - Duplicate MPL (409 Conflict):**
```json
{
  "error": "Conflict",
  "details": "MPL with reference number MPL-2024-001 already exists"
}
```

**Error - Missing SKUs (400 Bad Request):**
```json
{
  "error": "Bad Request",
  "details": "Missing SKUs in WMS: SKU-12345. Provide full SKU details to auto-create."
}
```

---

## Receiving Order from CMS

**Endpoint:** `POST /api/v1/orders.php`

**Full URL:** `https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/orders.php`

**Description:** CMS sends a new customer order to the WMS. The order will be stored with status "pending" until warehouse staff ships it.

### Request

**Headers:**
```
Content-Type: application/json
X-API-Key: sir-4d-api-2026
```

**Body (Flexible Format):**
```json
{
  "order_number": "ORD-2024-001",
  "ship_to_company": "Acme Corporation",
  "ship_to_street": "123 Main St",
  "ship_to_city": "Philadelphia",
  "ship_to_state": "PA",
  "ship_to_zip": "19103",
  "items": [
    {
      "unit_id": "R2A2508584",
      "sku": "1720813-0132",
      "sku_details": {
        "description": "Updated Product Info",
        "uom_primary": "BOX",
        "pieces": 24,
        "length_inches": 12.0,
        "width_inches": 10.0,
        "height_inches": 8.0,
        "weight_lbs": 25.0
      }
    },
    {
      "unit_id": "R2A2508585",
      "sku": "1720813-0132"
    }
  ]
}
```

**Important Notes:**
- `quantity` field is **optional** - if not provided, each item counts as 1 unit
- Multiple items with the same SKU are automatically aggregated
- Accepts either `customer_name` OR `ship_to_company`
- Address can be single field OR separate fields (street, city, state, zip)
- `sku_details` is optional - will auto-create/update SKUs just like MPL endpoint

### Field Mapping

| CMS Field | WMS Field | Notes |
|-----------|-----------|-------|
| `ship_to_company` | `customer_name` | Either name works |
| `ship_to_street`, `ship_to_city`, `ship_to_state`, `ship_to_zip` | `address` | Combined into single address |

### Response

**Success (201 Created):**
```json
{
  "success": true,
  "message": "Order received successfully",
  "order_id": 456,
  "order_number": "ORD-2024-001",
  "customer_name": "Acme Corporation",
  "items_count": 2
}
```

**Success with Inventory Warnings:**
```json
{
  "success": true,
  "message": "Order received successfully",
  "order_id": 456,
  "order_number": "ORD-2024-001",
  "customer_name": "Acme Corporation",
  "items_count": 2,
  "warnings": {
    "insufficient_inventory": [
      "1720813-0132 (need 25, have 10)"
    ]
  }
}
```

**Note:** Orders are accepted even with insufficient inventory (warning only).

---

## WMS Callbacks to CMS

When warehouse staff confirms an MPL or ships an order, the WMS sends a callback notification to the CMS.

**CMS Callback Endpoints:**
- MPL: `https://digmstudents.westphal.drexel.edu/~sej84/idm250/api/v1/mpls.php`
- Orders: `https://digmstudents.westphal.drexel.edu/~sej84/idm250/api/v1/orders.php`

### MPL Confirmation Callback

**Triggered when:** Warehouse staff clicks "Confirm MPL" button in WMS UI

**Headers Sent:**
```
Content-Type: application/json
X-API-Key: sir-4d-api-2026
```

**Payload:**
```json
{
  "action": "confirm",
  "reference_number": "MPL-2024-001"
}
```

**What Happens in WMS:**
1. Inventory quantities are increased
2. MPL status changes to "confirmed"
3. MPL line items status changes to "received"
4. Callback sent to CMS

### Order Shipment Callback

**Triggered when:** Warehouse staff clicks "Ship Order" button in WMS UI

**Headers Sent:**
```
Content-Type: application/json
X-API-Key: sir-4d-api-2026
```

**Payload:**
```json
{
  "action": "ship",
  "order_number": "ORD-2024-001",
  "shipped_at": "2026-02-28"
}
```

**What Happens in WMS:**
1. Inventory quantities are decreased
2. Order status changes to "shipped"
3. Shipment logged to shipped_items history
4. Callback sent to CMS

---

## Quantity Aggregation

The API automatically aggregates multiple items with the same SKU into a single quantity.

**Example Input:**
```json
{
  "items": [
    {"unit_id": "U001", "sku": "ABC-123"},
    {"unit_id": "U002", "sku": "ABC-123"},
    {"unit_id": "U003", "sku": "ABC-123"},
    {"unit_id": "U004", "sku": "XYZ-789"}
  ]
}
```

**Stored As:**
- SKU "ABC-123": quantity = 3
- SKU "XYZ-789": quantity = 1

This allows CMS to send one record per physical unit while WMS tracks aggregated quantities.

---

## Error Codes

| Code | Meaning |
|------|---------|
| 200 | OK - Request successful |
| 201 | Created - Resource created successfully |
| 400 | Bad Request - Invalid data or missing fields |
| 401 | Unauthorized - Invalid or missing API key |
| 405 | Method Not Allowed - Wrong HTTP method (use POST) |
| 409 | Conflict - Resource already exists (duplicate MPL/Order number) |
| 500 | Internal Server Error - Contact WMS administrator |

---

## Testing with cURL

### Send MPL to WMS:
```bash
curl -X POST https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/mpls.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: sir-4d-api-2026" \
  -d '{
    "reference_number": "MPL-TEST-001",
    "trailer_number": "TRAILER-123",
    "expected_arrival": "2026-03-15",
    "items": [
      {
        "sku": "TEST-SKU-001",
        "quantity": 100,
        "sku_details": {
          "description": "Test Product",
          "uom_primary": "PALLET",
          "pieces": 50,
          "length_inches": 48.0,
          "width_inches": 40.0,
          "height_inches": 50.0,
          "weight_lbs": 1200.0
        }
      }
    ]
  }'
```

### Send Order to WMS:
```bash
curl -X POST https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/orders.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: sir-4d-api-2026" \
  -d '{
    "order_number": "ORD-TEST-001",
    "ship_to_company": "Test Customer",
    "ship_to_street": "123 Test St",
    "ship_to_city": "Philadelphia",
    "ship_to_state": "PA",
    "ship_to_zip": "19103",
    "items": [
      {"sku": "TEST-SKU-001", "quantity": 25}
    ]
  }'
```

---

## Complete Workflow Example

### 1. CMS Sends MPL
```
POST /api/v1/mpls.php
→ Creates MPL with status "pending"
→ Auto-creates SKU if doesn't exist
→ OR updates SKU if it exists
```

### 2. Warehouse Confirms MPL
```
User clicks "Confirm MPL" in WMS UI
→ Adds quantity to inventory
→ Sets MPL status to "confirmed"
→ Sets line items status to "received"
→ Sends callback to CMS
```

### 3. CMS Sends Order
```
POST /api/v1/orders.php
→ Creates order with status "pending"
→ Checks inventory (warning only)
→ Updates SKU details if provided
```

### 4. Warehouse Ships Order
```
User clicks "Ship Order" in WMS UI
→ Deducts quantity from inventory
→ Sets order status to "shipped"
→ Logs to shipped_items history
→ Sends callback to CMS
```

---

## Integration Notes

### Timezone
All timestamps are stored in **Eastern Time (EST/EDT)**.  
Database timezone: `-05:00` (EST) / `-04:00` (EDT)

### Data Persistence
- **MPLs and Orders:** Cannot be deleted once created (can only be cancelled)
- **Shipped Items:** Permanent audit trail, cannot be deleted
- **Inventory:** Auto-managed via MPL confirmations and order shipments
- **SKUs:** Can be manually edited in WMS or auto-updated via API

### Best Practices
1. Always include `sku_details` to ensure SKU information stays current
2. Use unique `reference_number` and `order_number` values
3. Monitor inventory warnings in order responses
4. Handle callback responses (200 OK expected)

---

## Support & Contact

**WMS Administrator:** Elly Tang  
**Server:** Drexel University Web Server  
**Database:** et556_db  

**System URLs:**
- WMS Login: `https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/login.php`
- API Base: `https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1`

---

## Changelog

**Version 2.0 (Feb 28, 2026)**
- Added SKU auto-update functionality
- Added quantity aggregation for multiple items
- Added support for CMS field naming conventions
- Added trailer_number and expected_arrival to MPL
- Updated line items to receive status on MPL confirmation
- Improved field mapping documentation

**Version 1.0 (Feb 25, 2026)**
- Initial API release
- Basic MPL and Order endpoints
- SKU auto-create functionality
- Callback system
