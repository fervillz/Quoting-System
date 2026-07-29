# Quote Products and pricing data

Quote Products are private posts of type `quote_products`. Their Product Type taxonomy determines where they appear and how they are resolved.

## Product types

| Taxonomy slug | Intended use |
|---|---|
| `door-profile` | Builder Profile picker; matrix base for doors/drawers |
| `timber` | Builder Timber picker; fixed/percentage overall modifier |
| `finish` | Builder Finish picker; fixed/percentage overall modifier |
| `modifier` | Reserved general adjustments |
| `kickboard` | Builder Kick Material selector; linear pricing |
| `paint` | Paint matrix applied to every painted panel |
| `accessory` | Handle Profile picker; fixed per-panel price |

Taxonomy slugs are generated from the default term names.

## Common product meta

| Key | Type | Meaning |
|---|---|---|
| `active` | `1` or missing | Builder includes products with `1` or no key |
| `pricing_method` | string | `matrix`, `fixed`, `percentage`, `linear`, `square` |
| `pricing_matrix_source` | relationship/ID | Product whose matrix should be reused |
| `pricing_matrix_copy` | relationship/ID | Legacy alias for source |
| featured image | attachment | Swatch/thumbnail in pickers and review |

The plugin reads raw WordPress meta. ACF may manage this UI, but no `get_field()` call is required.

## Matrix pricing

Repeater base: `pricing_matrix`

| Child key | Meaning |
|---|---|
| `height_min` / `height_max` | Inclusive mm range |
| `width_min` / `width_max` | Inclusive mm range |
| `price` | Price for one panel in that band |

Raw ACF storage is supported:

```text
pricing_matrix = row count
pricing_matrix_0_height_min
pricing_matrix_0_height_max
pricing_matrix_0_width_min
pricing_matrix_0_width_max
pricing_matrix_0_price
```

A normal PHP array saved under `pricing_matrix` is also supported.

The first inclusive band that matches both dimensions wins. No match returns `0`.

## Fixed pricing

Price key lookup:

1. `fixed_price`
2. legacy alias `price`

For Finger Pull this value is added per priced panel only when the selected profile title is exactly Evans, Valley, or 30 Shaker (case-insensitive).

## Percentage pricing

Key: `percentage`

Timber and Finish percentage products are summed, then applied once to the component/fixed base. Examples:

- Walnut `10` → adds 10%;
- Raw `-10` → subtracts 10%.

## Linear pricing

Repeater base: `linear_pricing`

Compatible child aliases:

| Calculator field | Accepted keys |
|---|---|
| Minimum height | `height_min`, `min` |
| Maximum height | `height_max`, `max` |
| Price per linear metre | `price_per_lm`, `price` |

Formula:

```text
(length_mm / 1000) × matching_band_price
```

Kickboards use length and height. The matching height band chooses the rate.

## Square pricing

Repeater bases: `square_metre` or `square_metre_pricing`

Compatible child keys:

- `min`
- `max`
- `price_per_sqm` or `price`

Formula:

```text
(width_mm / 1000) × (height_mm / 1000) × matching_band_price
```

## Required naming dependencies

Some rules deliberately resolve exact product titles:

| Title/type | Used for |
|---|---|
| `Evans` / Door Profile | End Panel and Filler base matrix |
| `Painted` / Paint | Paint matrix when a paint colour/timber indicates painting |
| `Finger Pull` / Accessory | Conditional per-panel rule |
| `{Material} Kickboard` / Kickboard | Fallback if a saved material title omits “Kickboard” |

Renaming these products requires updating lookup code or maintaining compatible titles.

## Painted Oak behavior

The builder presents **Painted Oak** as a Timber choice.

When selected:

- Paint Colour is shown and required in the browser.
- Finish picker is hidden/disabled.
- Save logic clears `_finish`.
- A Paint Product is applied to doors, drawer fronts, End Panels and Fillers.

The pricing engine tries `_paint_product` and `_paint`, then falls back to the exact `Painted` product when `_paint_colour` is present or the Timber title contains `paint`.

## Selection query

`qs_builder_products($type)` returns published products:

- with the requested Product Type slug;
- ordered by `menu_order`, then title;
- where `active = 1` or the `active` key does not exist.

Setting `active = 0` hides a product from the builder but does not invalidate an already-saved quote.

## Pricing troubleshooting

If a component returns `$0.00`:

1. Confirm the saved selection resolves to a published Quote Product.
2. Confirm its taxonomy slug.
3. Confirm `pricing_method` spelling.
4. Inspect raw repeater count and child keys.
5. Confirm dimensions fall inside an inclusive band.
6. Confirm `Evans` and `Painted` exist when relevant.
7. Read `_pricing_breakdown` on the Quote to identify the zero group.
