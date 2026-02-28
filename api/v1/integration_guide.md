# 4D WMS API - SIR CMS Integration Guide

## Important: Required Payload Format


---

## MPL Endpoint

**URL:** `https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/mpls.php`

### Accepted Payload Format:

```json
{
  "reference_number": "CA1A2B3C4D",
  "trailer_number": "T12345",
  "expected_arrival": "2024-03-15",
  "items": [
    {
      "unit_id": "R2A2508584",
      "sku": "1720813-0132",
      "quantity": 1,              
      "sku_details": {
        "sku": "1720813-0132",
        "description": "Product description",
        "uom_primary": "PALLET",
        "pieces": 50,
        "length_inches": 48.0,
        "width_inches": 40.0,
        "height_inches": 50.0,
        "weight_lbs": 1200.0
      }
    }
  ]
}
```

### Field Mapping:

| CMS Field | WMS Accepts | Notes |
|-----------|-------------|-------|
| `reference_number` |  Maps to `mpl_number` | Either name works |
| `trailer_number` |  Accepted | Stored but not currently used |
| `expected_arrival` |  Accepted | Stored but not currently used |
| `items[].quantity`  | Must add this field! |
| `sku_details.uom_primary` |  Maps to `uom` | Either name works |
| `sku_details.length_inches` |  Maps to `length` | Either name works |
| `sku_details.width_inches` |  Maps to `width` | Either name works |
| `sku_details.height_inches` |  Maps to `height` | Either name works |
| `sku_details.weight_lbs` |  Maps to `weight` | Either name works |

---

## Order Endpoint

**URL:** `https://digmstudents.westphal.drexel.edu/~et556/idm250-4d/api/v1/orders.php`

### Accepted Payload Format:

```json
{
  "order_number": "A1B2C3D4",
  "ship_to_company": "Acme Corporation",
  "ship_to_street": "123 Main St",
  "ship_to_city": "Philadelphia",
  "ship_to_state": "PA",
  "ship_to_zip": "19103",
  "items": [
    {
      "unit_id": "R2A2508584",
      "sku": "1720813-0132",
      "quantity": 1,       
      "sku_details": {
        "sku": "1720813-0132",
        "description": "Product description",
        "uom_primary": "PALLET",
        "pieces": 50,
        "length_inches": 48.0,
        "width_inches": 40.0,
        "height_inches": 50.0,
        "weight_lbs": 1200.0
      }
    }
  ]
}
```

### Field Mapping:

| CMS Field | WMS Accepts | Notes |
|-----------|-------------|-------|
| `ship_to_company` | Maps to `customer_name` | Either name works |
| `ship_to_street` | Combined into `address` | All 4 fields combined |
| `ship_to_city` | Combined into `address` | |
| `ship_to_state` | Combined into `address` | |
| `ship_to_zip` | Combined into `address` | |
| `items[].quantity` | | Must add this field! |

**Address is stored as:** `"123 Main St, Philadelphia, PA, 19103"`



---

## Complete Working Example for CMS Team

### Send MPL:
```php
$data = [
    'reference_number' => $header['reference_number'],
    'trailer_number' => $header['trailer_number'],
    'expected_arrival' => $header['expected_arrival'],
    'items' => []
];

foreach ($mpl as $item) {
    $data['items'][] = [
        'unit_id' => $item['unit_id'],
        'sku' => $item['sku'],
        'quantity' => 1,  
        'sku_details' => [
            'sku' => $item['sku'],
            'description' => $item['description'],
            'uom_primary' => $item['uom_primary'],
            'pieces' => $item['pieces'],
            'length_inches' => $item['length_inches'],
            'width_inches' => $item['width_inches'],
            'height_inches' => $item['height_inches'],
            'weight_lbs' => $item['weight_lbs']
        ]
    ];
}
```

### Send Order:
```php
$data = [
    'order_number' => $header['order_number'],
    'ship_to_company' => $header['ship_to_company'],
    'ship_to_street' => $header['ship_to_street'],
    'ship_to_city' => $header['ship_to_city'],
    'ship_to_state' => $header['ship_to_state'],
    'ship_to_zip' => $header['ship_to_zip'],
    'items' => []
];

foreach ($order as $item) {
    $data['items'][] = [
        'unit_id' => $item['unit_id'],
        'sku' => $item['sku'],
        'quantity' => 1,  
        'sku_details' => [
            'sku' => $item['sku'],
            'description' => $item['description'],
            'uom_primary' => $item['uom_primary'],
            'pieces' => $item['pieces'],
            'length_inches' => $item['length_inches'],
            'width_inches' => $item['width_inches'],
            'height_inches' => $item['height_inches'],
            'weight_lbs' => $item['weight_lbs']
        ]
    ];
}
```

---
