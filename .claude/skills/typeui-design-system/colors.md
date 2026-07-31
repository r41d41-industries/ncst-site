# Color Tokens

## Background Tokens

### Neutral
| Token | Light | Dark |
|---|---|---|
| neutral-primary-soft | #FFFFFF | #09090B |
| neutral-primary | #FFFFFF | #09090B |
| neutral-primary-medium | #FFFFFF | #18181B |
| neutral-primary-strong | #FFFFFF | #27272A |
| neutral-secondary-soft | #FAFAFA | #09090B |
| neutral-secondary | #FAFAFA | #09090B |
| neutral-secondary-medium | #F4F4F5 | #18181B |
| neutral-secondary-strong | #F4F4F5 | #27272A |
| neutral-tertiary-soft | #F4F4F5 | #09090B |
| neutral-tertiary | #F4F4F5 | #18181B |
| neutral-tertiary-medium | #E4E4E7 | #27272A |
| neutral-quaternary | #E4E4E7 | #27272A |
| quaternary-medium | #D4D4D8 | #3F3F46 |
| gray | #A1A1AA | #3F3F46 |

### Brand
| Token | Light | Dark |
|---|---|---|
| brand-softer | #F5F5F5 | #1A1A1A |
| brand-soft | #E5E5E5 | #252525 |
| brand | #2E2E2E | #D4D4D4 |
| brand-medium | #D4D4D4 | #252525 |
| brand-strong | #1A1A1A | #2E2E2E |

### Status
| Token | Light | Dark |
|---|---|---|
| success-soft | #F0FDF4 | #052E16 |
| success | #16A34A | #22C55E |
| success-medium | #DCFCE7 | #14532D |
| success-strong | #15803D | #16A34A |
| danger-soft | #FEF2F2 | #450A0A |
| danger | #DC2626 | #EF4444 |
| danger-medium | #FEE2E2 | #991B1B |
| danger-strong | #B91C1C | #DC2626 |
| warning-soft | #FFFBEB | #78350F |
| warning | #F59E0B | #F59E0B |
| warning-medium | #FEF3C7 | #92400E |
| warning-strong | #D97706 | #D97706 |

### Button Glint (CSS custom properties, used for the glint box-shadow effect)
| Variable | Light | Dark |
|---|---|---|
| `--color-1-400` | rgba(255,255,255,0.06) | rgba(255,255,255,0.04) |
| `--color-1-700` | rgba(0,0,0,0.04) | rgba(0,0,0,0.06) |

### Utility
| Token | Light | Dark |
|---|---|---|
| dark | #18181B | #18181B |
| dark-strong | #09090B | #27272A |
| disabled | #F4F4F5 | #18181B |

### Accent
| Token | Value (same both modes) |
|---|---|
| purple | #8B5CF6 |
| sky | #0EA5E9 |
| teal | #0D9488 |
| pink | #EC4899 |
| cyan | #06B6D4 |
| fuchsia | #C026D3 |
| indigo | #4F46E5 |
| orange | #F97316 |

## Text Color Tokens

### Base
| Token | Light | Dark |
|---|---|---|
| white | #FFFFFF | #FFFFFF |
| black | #09090B | #09090B |
| heading | #09090B | #FAFAFA |
| body | #71717A | #A1A1AA |
| body-subtle | #A1A1AA | #71717A |

### Brand
| Token | Light | Dark |
|---|---|---|
| fg-brand-subtle | #D4D4D4 | #252525 |
| fg-brand | #2E2E2E | #D4D4D4 |
| fg-brand-strong | #1A1A1A | #E5E5E5 |

### Status
| Token | Light | Dark |
|---|---|---|
| fg-success | #16A34A | #22C55E |
| fg-success-strong | #15803D | #4ADE80 |
| fg-danger | #DC2626 | #EF4444 |
| fg-danger-strong | #B91C1C | #FCA5A5 |
| fg-warning-subtle | #D97706 | #F59E0B |
| fg-warning | #92400E | #FDE68A |
| fg-disabled | #A1A1AA | #52525B |

### Informational / Accent
| Token | Light | Dark |
|---|---|---|
| fg-yellow | #EAB308 | #FACC15 |
| fg-info | #3730A3 | #93C5FD |
| fg-purple | #7C3AED | #8B5CF6 |
| fg-purple-strong | #6D28D9 | #DDD6FE |
| fg-cyan | #0891B2 | #06B6D4 |
| fg-indigo | #4F46E5 | #4F46E5 |
| fg-pink | #DB2777 | #EC4899 |
| fg-lime | #65A30D | #84CC16 |

## Border Color Tokens

| Token | Light | Dark |
|---|---|---|
| border-dark | #18181B | #3F3F46 |
| border-buffer | #FFFFFF | #09090B |
| border-buffer-medium | #FFFFFF | #18181B |
| border-buffer-strong | #FFFFFF | #27272A |
| border-muted | #FAFAFA | #09090B |
| border-light-subtle | #F4F4F5 | #09090B |
| border-light | #F4F4F5 | #18181B |
| border-light-medium | #E4E4E7 | #27272A |
| border-default-subtle | #E4E4E7 | #09090B |
| border-default | #E4E4E7 | #27272A |
| border-default-medium | #E4E4E7 | #3F3F46 |
| border-default-strong | #D4D4D8 | #52525B |
| border-success-subtle | #BBF7D0 | #14532D |
| border-success | #16A34A | #15803D |
| border-danger-subtle | #FECACA | #991B1B |
| border-danger | #DC2626 | #DC2626 |
| border-warning-subtle | #FDE68A | #92400E |
| border-warning | #D97706 | #F59E0B |
| border-brand-subtle | #D4D4D4 | #252525 |
| border-brand-light | #2E2E2E | #D4D4D4 |
| border-brand | #2E2E2E | #A3A3A3 |
| border-dark-subtle | #18181B | #27272A |
| border-purple | #8B5CF6 | #8B5CF6 |
| border-orange | #F97316 | #F97316 |

## Semantic Usage Rules

- Page/section backgrounds: neutral-primary-soft (default), neutral-secondary-soft (alternating)
- Primary buttons: brand background
- Headings: heading text color
- Body text: body text color
- CTA links: fg-brand text color
- Default borders: border-default
- Status borders match intent: success → border-success, danger → border-danger, warning → border-warning
- Disabled: disabled background + fg-disabled text

## Prohibited

- No raw hex/rgb values in component code — always use design tokens
- No brand text color for long-form paragraphs
- No accent text tokens (fg-purple, etc.) for body copy or navigation
- No brand/accent backgrounds for large layout surfaces (pages, sections) unless it's a hero/campaign area
- No manual light/dark value swapping — let the CSS custom properties handle it
