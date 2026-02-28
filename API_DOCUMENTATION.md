# WMS API Documentation

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

**Body:**
```json
{
  "mpl_number": "MPL-2024-001",
  "items": [
    {
      "sku": "SKU-12345",
      "quantity": 100,
      "sku_details": {
        "description": "Product Description",
        "uom": "PALLET",
        "pieces": 50,
        "length": 48.0,
        "width": 40.0,
        "height": 50.0,
        "weight": 1200.0,
        "ficha": 12345
      }
    },
    {
      "sku": "SKU-67890",
      "quantity": 50
    }
  ]
}
```

**Field Descriptions:**
- `mpl_number` (required) - Unique MPL reference number from CMS
- `items` (required) - Array of items in the MPL
- `items[].sku` (required) - SKU code
- `items[].quantity` (required) - Quantity expected
- `items[].sku_details` (optional) - SKU details for auto-creation if SKU doesn't exist in WMS

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
  "details": "Missing SKUs in WMS: SKU-12345, SKU-67890. Provide full SKU details to auto-create."
}
```

**Error - Invalid Request (400 Bad Request):**
```json
{
  "error": "Bad Request",
  "details": "Missing required field: mpl_number"
}
```

**Error - Unauthorized (401):**
```json
{
  "error": "Unauthorized",
  "details": "Invalid or missing API key"
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

**Body:**
```json
{
  "order_number": "ORD-2024-001",
  "customer_name": "Acme Corporation",
  "address": "123 Main St, Philadelphia, PA 19103",
  "items": [
    {
      "sku": "SKU-12345",
      "quantity": 25
    },
    {
      "sku": "SKU-67890",
      "quantity": 10
    }
  ]
}
```

**Field Descriptions:**
- `order_number` (required) - Unique order number from CMS
- `customer_name` (required) - Customer/company name
- `address` (optional) - Shipping address
- `items` (required) - Array of items in the order
- `items[].sku` (required) - SKU code
- `items[].quantity` (required) - Quantity to ship

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

**Success with Warnings:**
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
      "SKU-12345 (need 25, have 10)",
      "SKU-67890 (need 10, have 0)"
    ]
  }
}
```

**Error - Duplicate Order (409 Conflict):**
```json
{
  "error": "Conflict",
  "details": "Order with number ORD-2024-001 already exists"
}
```

**Error - Missing SKUs (400 Bad Request):**
```json
{
  "error": "Bad Request",
  "details": "SKUs not found in WMS: SKU-12345, SKU-67890"
}
```

---

## WMS Callbacks to CMS

When warehouse staff confirms an MPL or ships an order, the WMS sends a callback notification to the CMS.

**Note:** You need to provide your CMS callback endpoints and configure them in the WMS.

### MPL Confirmation Callback

**Endpoint (CMS provides):** `POST https://your-cms-domain.com/api/v1/mpls.php`

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

**Expected CMS Response:**
```json
{
  "success": true,
  "message": "MPL confirmation received"
}
```

### Order Shipment Callback

**Endpoint (CMS provides):** `POST https://your-cms-domain.com/api/v1/orders.php`

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
  "shipped_at": "2025-02-25"
}
```

**Expected CMS Response:**
```json
{
  "success": true,
  "message": "Order shipment received"
}
```

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
    "mpl_number": "MPL-TEST-001",
    "items": [
      {
        "sku": "SKU-12345",
        "quantity": 100,
        "sku_details": {
          "description": "Test Product",
          "uom": "PALLET",
          "pieces": 50,
          "length": 48.0,
          "width": 40.0,
          "height": 50.0,
          "weight": 1200.0,
          "ficha": 12345
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
    "customer_name": "Test Customer",
    "address": "123 Test St, Philadelphia, PA",
    "items": [
      {"sku": "SKU-12345", "quantity": 25}
    ]
  }'
```

---

## WMS User Interface

After sending MPLs and Orders via the API, warehouse staff can access the WMS interface to process them:

**WMS Login:**
```
https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/login.php
```

**Workflow:**
1. CMS sends MPL via API → appears in WMS as "Pending"
2. Warehouse staff logs in → views MPL → clicks "Confirm MPL"
3. Inventory is automatically increased
4. WMS sends confirmation callback to CMS

5. CMS sends Order via API → appears in WMS as "Pending"
6. Warehouse staff views Order → clicks "Ship Order"
7. Inventory is automatically decreased
8. Shipment is logged in history
9. WMS sends shipment callback to CMS

---

## Integration Notes

### Auto-Creating SKUs

If a SKU in an MPL doesn't exist in the WMS database, the WMS can automatically create it **if** `sku_details` is provided in the request. This allows seamless integration when the CMS creates new products.

**Required fields in sku_details:**
- `description` (string)
- `uom` (string) - Unit of Measure (e.g., "PALLET", "BUNDLE", "BOX")
- `pieces` (integer) - Pieces per unit
- `length` (float) - Length in inches
- `width` (float) - Width in inches
- `height` (float) - Height in inches
- `weight` (float) - Weight in pounds
- `ficha` (integer) - Ficha number

If `sku_details` is not provided and the SKU is missing, the API will return a 400 error listing the missing SKUs.

### Inventory Warnings

When receiving an order, the WMS checks if sufficient inventory exists for each SKU. If inventory is insufficient, the order is still accepted but a `warnings` array is included in the response. This allows the CMS to be notified of potential fulfillment issues while not blocking order receipt.

### Transaction Safety

Both endpoints use database transactions to ensure data consistency. If any step fails (e.g., missing SKU, database error), all changes are rolled back and an error response is returned.

### Duplicate Prevention

The API prevents duplicate MPLs and Orders by checking the `mpl_number` and `order_number` respectively. If a duplicate is detected, a 409 Conflict error is returned.

---

## Support & Contact

For technical issues or questions about the API:

**WMS Administrator:** Elly Tang  
**Server:** Drexel University Web Server  
**Database:** et556_db  

**Important Files Location on Server:**
- API Endpoints: `~/public_html/idm250-4d/api/v1/`
- Configuration: `~/public_html/idm250-4d/.env`
- Callback Logs: `~/public_html/idm250-4d/api/mpl_callbacks.log` and `order_callbacks.log`

---

## Quick Reference

**Production API Endpoints:**
```
MPL:    https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/mpls.php
Orders: https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/orders.php
```

**API Key:**
```
sir-4d-api-2026
```

**WMS Interface:**
```
https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/login.php
```
