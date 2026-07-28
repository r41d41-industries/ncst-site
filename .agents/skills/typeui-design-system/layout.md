# Layout & Spacing

## Spacing Rhythm

Base unit: **4px**. All spacing values should be multiples of 4px.

| Context | Value |
|---|---|
| Section vertical padding | 64px |
| Section header → content | 32px or 48px |
| Heading → paragraph | 12px |
| Container horizontal padding | 24px |
| Flex/grid row gap | 16px |
| Card grid gap | 24px |
| Wide component grid gap | 32px |
| Column layout gap | 48px |

## Container

Standard section container: max-width 1280px, centered, 24px horizontal padding.

Every major section wraps content in this container.

## Content Composition Order

Inside each section, follow this order:
1. Heading (`h1`–`h3`)
2. Leading paragraph
3. Normal paragraph(s)
4. Lists, CTA links, or component grids

## Section Pattern

Each section has:
- 64px vertical padding
- A background color (alternate between neutral-primary-soft and neutral-secondary-soft)
- A centered container (max-width 1280px, 24px horizontal padding)
- A section header area with 32px bottom margin
- Section content below

## Motion & Animation

- Prefer CSS-native: `transition`, `animation`, `@keyframes`. Use Motion library only when CSS cannot achieve the behavior.
- Prioritize high-impact orchestrated moments over scattered micro-interactions. A single well-sequenced page-load animation using staggered `animation-delay` delivers more perceived quality than many isolated effects.
- Reserve scroll-triggered and hover transitions for moments that reinforce hierarchy or reward attention.

## Backgrounds & Visual Depth

- Default to clean, flat backgrounds with subtle variation through border-based separation.
- Apply minimal visual treatments — subtle background alternation, clean borders, and careful spacing — to create visual hierarchy.
- Every decorative element must serve a compositional purpose (depth or separation). Avoid ornamental effects that compete with content.

## Must

- All sections: consistent 64px vertical padding
- All containers: max-width 1280px, centered, 24px horizontal padding
- Section headers: 32px or 48px bottom margin
- Consistent vertical rhythm, no crowded sections
- Layouts readable and properly spaced on both desktop and mobile
