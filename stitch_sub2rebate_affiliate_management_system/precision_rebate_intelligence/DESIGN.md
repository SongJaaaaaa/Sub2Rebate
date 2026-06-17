---
name: Precision Rebate Intelligence
colors:
  surface: '#f7f9fb'
  surface-dim: '#d8dadc'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#45464d'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#76777d'
  outline-variant: '#c6c6cd'
  surface-tint: '#565e74'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#131b2e'
  on-primary-container: '#7c839b'
  inverse-primary: '#bec6e0'
  secondary: '#4648d4'
  on-secondary: '#ffffff'
  secondary-container: '#6063ee'
  on-secondary-container: '#fffbff'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#002113'
  on-tertiary-container: '#009668'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dae2fd'
  primary-fixed-dim: '#bec6e0'
  on-primary-fixed: '#131b2e'
  on-primary-fixed-variant: '#3f465c'
  secondary-fixed: '#e1e0ff'
  secondary-fixed-dim: '#c0c1ff'
  on-secondary-fixed: '#07006c'
  on-secondary-fixed-variant: '#2f2ebe'
  tertiary-fixed: '#6ffbbe'
  tertiary-fixed-dim: '#4edea3'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#f7f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
  surface-white: '#FFFFFF'
  border-subtle: '#E2E8F0'
  text-muted: '#64748B'
  rebate-success: '#10B981'
  error-destructive: '#EF4444'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  metric-xl:
    fontFamily: Inter
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.03em
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.05em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  container-padding: 24px
  gutter-desktop: 24px
  gutter-mobile: 16px
  stack-sm: 8px
  stack-md: 16px
  stack-lg: 32px
---

## Brand & Style

The visual identity of the design system is anchored in **Professional Minimalism** with a focus on high-trust fintech aesthetics. It is designed to evoke a sense of security, institutional reliability, and data-driven precision. The target audience includes high-volume affiliates and financial administrators who require a tool that feels like a premium utility rather than a consumer toy.

The style draws heavy inspiration from modern "Systemic Design" movements, utilizing a structured layout, generous whitespace, and a rigorous adherence to a functional grid. It avoids unnecessary decoration, opting instead for refined micro-interactions and high-quality typography to communicate value. Every element is intentional, designed to reduce cognitive load while managing complex financial data.

## Colors

The color palette is architected to prioritize legibility and status communication. 

- **Primary Blue (#0F172A):** Used for core navigational elements, primary headers, and foundational text to establish authority.
- **Indigo Accent (#6366F1):** Utilized sparingly for interactive call-to-actions, active states, and focus indicators to guide the user's eye.
- **Emerald Green (#10B981):** Reserved strictly for positive financial outcomes, "Rebate" indicators, and success statuses, creating a psychological link between the brand and profitability.
- **Surface & Neutral:** The background utilizes a crisp slate-tinted white to reduce eye strain, while cards and containers use pure white with a light 1px border (#E2E8F0) to define structure without the weight of heavy shadows.

## Typography

This design system uses **Inter** exclusively to maintain a utilitarian and modern feel. The typography is treated as a functional interface element rather than decoration.

- **Metrics:** Use the `metric-xl` style for financial figures. These should always be high-contrast and prominent.
- **Hierarchy:** Use weight (600 vs 400) to distinguish between data labels and the data itself. 
- **Labels:** Small labels (`label-sm`) should be set in uppercase with slight letter spacing to improve readability in dense data tables or small-caps navigation items.
- **Scaling:** For mobile devices, headlines transition to a more compact size to ensure that data-heavy dashboards remain legible without excessive scrolling.

## Layout & Spacing

The layout follows a **Fixed-Fluid Hybrid** model. The main content area is capped at a maximum width (1440px) for desktop clarity, while the internal grid components flex to fill the container.

- **Grid:** A 12-column grid system is used for desktop, shifting to a 4-column grid for mobile.
- **Rhythm:** An 8px linear scale (with 4px increments for micro-adjustments) governs all margins and padding. 
- **Standard Padding:** All primary dashboard cards and page sections must use a consistent `p-6` (24px) padding to maintain a clean, breathable interface.
- **Mobile Reflow:** On mobile, side-by-side metric cards should stack vertically to ensure the `metric-xl` typography has sufficient horizontal space.

## Elevation & Depth

The design system utilizes **Tonal Layering** and **Low-Contrast Outlines** to create hierarchy, avoiding heavy shadows to keep the UI feeling "light" and "fast."

- **Level 0 (Background):** The base layer is `#F8FAFC`.
- **Level 1 (Cards/Surfaces):** Pure white `#FFFFFF` with a 1px border of `#E2E8F0`. 
- **Level 2 (Interactive/Floating):** Use a very subtle, diffused shadow: `box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1)`. This is reserved for dropdown menus, tooltips, and active card states.
- **Depth via Color:** Depth is also achieved by using subtle shifts in background color for "Inner Wells" (e.g., using a slightly darker gray background for code blocks or data table headers).

## Shapes

The shape language is defined by modern, medium-rounded corners that strike a balance between friendly and corporate.

- **Standard Radius:** 8px (0.5rem) for most buttons, input fields, and small cards.
- **Large Radius:** 16px (1rem) for primary dashboard containers.
- **Pill Shapes:** Used exclusively for "Status Chips" (e.g., Paid, Pending) to differentiate them from interactive buttons.

## Components

- **Buttons:** 
    - *Primary:* Deep Blue (#0F172A) background with white text. High-contrast and bold.
    - *Secondary:* White background with the subtle 1px border (#E2E8F0).
    - *Accent:* Indigo (#6366F1) for key conversion points like "Withdraw Funds."
- **Data Cards:** Must feature a clear `label-md` header and a prominent `metric-xl` figure. Optional trend indicators should use `label-sm` with success (Emerald) or error (Red) colors.
- **Input Fields:** Use 8px rounded corners with a subtle border. On focus, use a 2px Indigo (#6366F1) ring with an inset shadow for a tactile feel.
- **Chips/Badges:** Small, pill-shaped indicators with low-opacity background tints of the status color (e.g., light green background with dark green text for "Success").
- **Data Tables:** Clean, borderless rows with 1px horizontal dividers. Headers should be set in `label-sm` with a muted gray text color to keep the focus on the data rows.
- **Visualizations:** Bar and line charts should use the Primary Blue and Indigo colors for data series, with Emerald used exclusively for rebate-related growth lines.