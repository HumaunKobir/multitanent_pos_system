# POS SYSTEM — Frontend Redesign Master Plan

**Stack:** Laravel · Inertia v3 · React 19 · Tailwind v4 · Framer Motion  
**Scope:** Customer-facing storefront only (no admin Blade)  
**Brand:** POS SYSTEM — modern navy/coral/purple gradient identity

---

## Phase Checklist

- [x] Phase 0 — Master doc (this file)
- [x] Phase 1 — Design tokens, framer-motion, StoreButton/Input/Badge
- [x] Phase 2 — Layout shell (header, footer, trust strip) + shared Inertia props + routes
- [x] Phase 3 — Drawer cart (CartProvider, JSON add-to-cart)
- [x] Phase 4 — ProductCard, VariantModal, ProductFilters, QuantityStepper, Toast
- [x] Phase 5 — Home page redesign
- [x] Phase 6 — Listing pages (category, collection, section, search)
- [x] Phase 7 — Single product (PDP)
- [x] Phase 8 — Cart + checkout + order-success
- [x] Phase 9 — Static CMS + contact
- [x] Phase 10 — Customer portal
- [x] Phase 11 — Tests, FB Pixel, polish
- [x] Phase 12 — Pathao integration (API stub ready for package)

---

## Design Tokens

| Token | CSS Variable | Value | Usage |
|-------|-------------|-------|-------|
| Primary | `--store-primary` | `#1a1a2e` | Header, footer, headings |
| Accent | `--store-accent` | `#e94560` | CTAs, badges, focus |
| Gradient start | `--store-gradient-from` | `#667eea` | Buttons, hero overlay |
| Gradient end | `--store-gradient-to` | `#764ba2` | Buttons, hero overlay |
| Warm | `--store-warm` | `#fef3c7` | Trust strip |
| Surface | `--store-surface` | `#fafafa` | Page background |

Tailwind classes: `bg-store-primary`, `text-store-accent`, `from-store-gradient-from`, etc.

---

## Page Inventory (React/Inertia)

| # | Component | Route |
|---|-----------|-------|
| 1 | `frontend/home` | `/` |
| 2 | `frontend/category-products` | `/category/{id}/products` |
| 3 | `frontend/section-products` | `/section/{id}/products` |
| 4 | `frontend/collection-products` | `/collection/{name}` |
| 5 | `frontend/single-product` | `/products/{slug}` |
| 6 | `frontend/search` | `/search?q=` |
| 7 | `frontend/static-page` | `/about`, `/faq`, policies |
| 8 | `frontend/contact` | `/contact` |
| 9 | `frontend/cart` | `/cart` |
| 10 | `frontend/checkout` | `/checkout` |
| 11 | `frontend/order-success` | `/checkout/success/{order}` |
| 12–16 | `frontend/customer/*` | `/customer/*` |

---

## Route Map

| URL | Route Name | Controller |
|-----|------------|------------|
| `/` | `home` | HomeController@index |
| `/products/{slug}` | `product.show` | HomeController@show |
| `/category/{id}/products` | `category.products` | HomeController@categoryProducts |
| `/section/{id}/products` | `section.products` | HomeController@sectionProducts |
| `/collection/{name}` | `collection.products` | HomeController@collectionProducts |
| `/search` | `search` | HomeController@search |
| `/cart` | `cart` | CartController@index |
| `/cart/add` | `cart.add` | CartController@addToCart (JSON) |
| `/cart/json` | `cart.json` | CartController@json |
| `/checkout` | `checkout` | CheckoutController@index |
| `/checkout/success/{order}` | `checkout.success` | CheckoutController@success |
| `/customer/profile` | `customer.profile` | CustomerDashboardController |

---

## Component API Contracts

### `<ProductCard product={} />`
- `product`: `{ id, name, slug, image, price, sale_price, discount_price, type, variations?, youtube_link? }`
- Hover quick-add or opens VariantModal when variations exist

### `<CartDrawer />`
- Controlled via `useCartDrawer()` context
- Props from shared `cart` session + local optimistic updates

### `<VariantModal product onClose onAdd />`
- Radio SKU chips, dynamic price display

### `<ProductFilters filters allColors allSizes baseUrl />`
- Debounced filter apply, mobile bottom sheet

### `<QuantityStepper value onChange min={1} />`

### `<StoreButton variant="primary|outline|ghost" />`

---

## Known Bugs (from legacy doc, React-adapted)

| # | Issue | Fix Phase |
|---|-------|-----------|
| 1 | Mobile header icons not linked | Phase 2 |
| 2 | Product card links to `/product/` not `/products/` | Phase 4 |
| 3 | `/customer/dashboard` broken URL | Phase 2 |
| 4 | Missing category nav / mega menu | Phase 2 |
| 5 | Cart empty-state always visible | Phase 8 |
| 6 | Collection route slug mismatch | Phase 2 |
| 7 | `order.success` vs `checkout.success` route name | Phase 8 |
| 8 | addToCart uses router.post but returns JSON | Phase 3 |
| 9 | Section products route missing | Phase 2 |
| 10 | Home section "View All" links to `/` | Phase 5 |

---

## File Structure

```
resources/js/
├── layouts/frontend/
│   ├── frontend-layout.jsx
│   ├── store-header.jsx
│   ├── store-footer.jsx
│   └── trust-strip.jsx
├── components/frontend/
│   ├── product-card.jsx
│   ├── cart-drawer.jsx
│   ├── cart-provider.jsx
│   ├── variant-modal.jsx
│   ├── product-filters.jsx
│   ├── product-listing-layout.jsx
│   ├── quantity-stepper.jsx
│   ├── payment-method-cards.jsx
│   ├── hero-slider.jsx
│   ├── section-block.jsx
│   ├── page-hero.jsx
│   ├── store-button.jsx
│   ├── store-input.jsx
│   ├── store-badge.jsx
│   └── toast-notification.jsx
├── hooks/
│   ├── use-cart-drawer.jsx
│   └── use-add-to-cart.js
└── pages/frontend/
```

---

## Shared Inertia Props (HandleInertiaRequests)

```php
'cart' => session('cart'),
'cartCount' => sum of quantities,
'categories' => Category::active()->get(['id','name','image']),
'logo' => ConfigDictionary::get('logo'),
'siteName', 'topNotice',
'contact' => ['phone','email','address'],
'social' => ['facebook','youtube','twitter','linkedin'],
'flash' => ['success','error'],
```

---

*Last updated: 2026-06-03*
