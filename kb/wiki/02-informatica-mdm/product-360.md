# Product 360

Product 360 is fundamentally a PIM (Product Information Management) system more than a classic MDM hub. It exists on a different lineage from Customer/Supplier 360 in some ways — its primary problem isn't deduplication of products across systems (products usually have stable SKUs) but **assembly, enrichment, and syndication of product content** across many channels.

If your problem is "we have 50 different ERPs and want a single product master," that's actually a classic MDM problem and Multidomain MDM or MDM SaaS may fit better. If your problem is "we have 500K SKUs that need rich attributes per channel, with supplier-provided data, AI-generated descriptions, marketing copy, technical specifications, multilingual variants, and images," that's Product 360's home turf.

## The core data model

- **Item / Product** — the SKU or product identity. Has core attributes (name, dimensions, weight, classification).
- **Attribute** — any property of a product. Hundreds to thousands per product type in some industries.
- **Attribute Group / Class** — collections of attributes specific to a product category (electronics have voltage; apparel has size and color; food has nutritional facts).
- **Hierarchy / Taxonomy** — category trees. Often multiple — one internal, one per channel (Amazon taxonomy vs Google Shopping vs internal merchandising).
- **Variant / Master-Variant** — a product with size/color variants is one master with multiple variants; attribute inheritance and override is core.
- **Asset / Media** — images, videos, datasheets, marketing collateral. Linked to product.
- **Supplier Catalog** — supplier-provided product data, imported and reconciled with internal product master.
- **Channel** — a destination for product data. Each channel has its own attribute requirements and constraints.

## Channel syndication

This is what most differentiates Product 360 from generic MDM. A product going to:

- **An e-commerce site** needs marketing-quality images, SEO descriptions, customer-facing taxonomy, pricing, inventory.
- **A marketplace** (Amazon, Walmart, eBay) needs marketplace-specific taxonomy, attribute names mapped to the marketplace's schema, image specifications.
- **A print catalog** needs print-resolution images, copy edited to length constraints.
- **A B2B portal** needs technical specifications, datasheets, integration data.
- **An internal merchandising system** needs cost data, supplier info, internal classifications.

Each channel pulls a *view* over the product master with channel-specific attribute mapping, attribute completeness scoring, and validation. Product 360 manages these views and the workflow that publishes data through them.

## AI enrichment

The recent direction for Product 360 (and PIM broadly) is AI-driven content generation and classification:

- **Attribute extraction from supplier data.** Supplier sends a catalog spreadsheet with unstructured "specs" columns; AI extracts dimensions, materials, certifications into structured attributes.
- **Auto-classification.** Given a product description and image, classify into the internal taxonomy.
- **Marketing copy generation.** Generate descriptions in brand voice, in multiple languages, with channel-appropriate length.
- **Image quality scoring and tagging.** Automated image classification, alt-text generation, accessibility tagging.

These features are evolving fast. Anything specific in this wiki about AI features should be considered tentative; refresh from product release notes when planning.

## Supplier integration

Product 360 expects supplier-provided data. Patterns:

- **Direct catalog upload** by supplier through a portal — Excel templates, supplier-specific transformations.
- **GDSN (Global Data Synchronization Network).** Industry-standard supplier-to-retailer product data exchange. Common in consumer packaged goods.
- **EDI** for transactional partners.
- **Crawled** from supplier websites for low-touch supplier relationships.

Supplier-provided data is **not trusted by default**. It goes through validation, enrichment, and (often) manual approval before merging into the internal master. The supplier is the data origin; the retailer/distributor remains the authority on what its catalog looks like.

## When to use Product 360 vs Customer/Supplier 360 MDM

| Problem | Tool |
|---|---|
| Multiple ERPs with overlapping product catalogs, need a single product master | Customer/Supplier 360 (MDM) lineage with a Product base object |
| Rich product content for commerce channels | Product 360 |
| Supplier-provided catalogs needing reconciliation with internal master | Product 360 |
| Stable SKU set, attribute volatility, multi-channel publishing | Product 360 |
| Cross-ERP SKU matching and golden-record consolidation | MDM (not P360) |
| Both? | Both, with P360 downstream of MDM |

The "both" pattern: MDM resolves cross-ERP product identity into a single master SKU; Product 360 enriches and syndicates that SKU to channels.

## Sources

- docs.informatica.com — Product 360 10.5 documentation.
- Informatica solution brief: *Informatica MDM - Product 360*.
- Informatica Success learning path: Product 360.

## Revision log

| Date | Change |
|---|---|
| 2026-05-23 | Initial page. |
