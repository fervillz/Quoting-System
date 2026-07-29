# Component repeaters

`repeaters.php` defines the only accepted row shapes. A repeater is a PHP array containing zero or more associative row arrays.

## Doors, drawers and drawer banks

Meta key: `_qs_doors_drawers`

| Field | Sanitiser | Meaning |
|---|---|---|
| `type` | allow list | `Door`, `Drawer`, or `Drawer Bank` |
| `width` | positive integer | Width in millimetres |
| `height` | positive integer | Door/drawer height; usually blank for a bank |
| `quantity` | positive integer | Number of identical items/banks |
| `edge_profile` | text | Compatibility field; current frontend saves blank |
| `drawer_count` | positive integer | Current builder offers 2, 3 or 4 |
| `top_height` | positive integer | Top front height |
| `top_middle_height` | positive integer | Used by a 4-drawer bank |
| `middle_height` | positive integer | Used by a 3-drawer bank and fallback |
| `bottom_middle_height` | positive integer | Supported by calculation/display helpers |
| `bottom_height` | positive integer | Bottom front height |

Current 4-drawer builder displays `top_height`, `top_middle_height`, `bottom_middle_height`, and `bottom_height`. Pricing also supports five derived heights if older/programmatic data supplies `drawer_count = 5`.

Door and Drawer UI entries begin with quantity `1`. Adding the exact same type/width/height again increments the stored quantity. Drawer Banks retain an explicit quantity input.

## End panels

Meta key: `_qs_end_panels`

| Field | Type | Current choices |
|---|---|---|
| `height` | positive integer | mm |
| `width` | positive integer | mm |
| `quantity` | positive integer | UI starts at `1` |
| `faces_seen` | text | 1 Face Only; 1 Face / 1 Return; 1 Face / 2 Returns; 2 Faces |
| `edges_seen` | text | Selected Top/Right/Bottom/Left joined with ` + ` |

At least one edge is required in frontend JavaScript.

## Fillers

Meta key: `_qs_fillers`

| Field | Type | Current choices |
|---|---|---|
| `height` | positive integer | mm |
| `width` | positive integer | mm |
| `quantity` | positive integer | UI starts at `1` |
| `faces_seen` | text | 1 Face or 2 Faces |
| `edges_seen` | text | 1 Long / 2 Short; 1 Long / 1 Short; 1 Long / No Short |

## Kickboards

Meta key: `_qs_kickboards`

| Field | Type | Meaning |
|---|---|---|
| `material` | text | Normally a Kickboard Quote Product ID |
| `height` | positive integer | Height in mm; selects the pricing band |
| `length` | positive integer | Length in mm; maximum enforced by browser is 2400 |
| `quantity` | positive integer | Piece count |

## Sanitisation rules

`qs_sanitise_component_rows()`:

1. rejects unknown component groups;
2. ignores values that are not row arrays;
3. applies the central field allow list;
4. converts measurements/quantities with `absint()`;
5. cleans text with `sanitize_text_field()`;
6. changes an invalid doors/drawers `type` to `Door`;
7. removes rows without quantity;
8. removes rows that have neither width nor length.

Important consequence: an End Panel/Filler with a width but no height can pass the generic row sanitiser. The frontend requires both. Imports and WordPress-admin edits should validate both dimensions explicitly.

## Read and write API

| Function | Use |
|---|---|
| `qs_component_definitions()` | Canonical schema |
| `qs_component_rows($quote_id, $component)` | Safe read; always returns an array and re-sanitises |
| `qs_sanitise_component_rows($component, $rows)` | Clean imported/programmatic rows |
| `qs_save_component_rows($quote_id, $posted_components)` | Replace all four groups |
| `qs_quote_component_count(...)` | Sum quantities, optionally filtered by type |

`qs_save_component_rows()` writes an empty array for any missing group. Do not call it with only one component unless clearing all other groups is intended.

## Example

```php
$components = array(
    'doors_drawers' => array(
        array(
            'type'     => 'Door',
            'width'    => 450,
            'height'   => 720,
            'quantity' => 2,
        ),
    ),
    'end_panels' => array(),
    'fillers'    => array(),
    'kickboards' => array(),
);

qs_save_component_rows( $quote_id, $components );
qs_recalculate_quote_pricing( $quote_id );
```

## Display transformations

`template-functions.php` adds display-only values:

- `configuration` → `3 Drawers`;
- `height_details` → labelled drawer heights;
- `faces_edges_seen` → joined face/edge description;
- dimensions → value plus ` mm`;
- kickboard material Product ID → title.

Never save these display-only keys back into component meta.
