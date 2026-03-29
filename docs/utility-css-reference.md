# Plugs Utility CSS Reference

This document provides a comprehensive list of all utility classes available in the Plugs CSS engine.

---

## Spacing

Spacing utilities use a consistent scale. Each unit represents `0.25rem` (4px).

### Spacing Scale
| Unit | Value | Equivalent |
|---|---|---|
| `0` | `0px` | 0px |
| `px` | `1px` | 1px |
| `0.5` | `0.125rem` | 2px |
| `1` | `0.25rem` | 4px |
| `1.5` | `0.375rem` | 6px |
| `2` | `0.5rem` | 8px |
| `2.5` | `0.625rem` | 10px |
| `3` | `0.75rem` | 12px |
| `3.5` | `0.875rem` | 14px |
| `4` | `1rem` | 16px |
| `5` | `1.25rem` | 20px |
| `6` | `1.5rem` | 24px |
| `7` | `1.75rem` | 28px |
| `8` | `2rem` | 32px |
| `9` | `2.25rem` | 36px |
| `10` | `2.5rem` | 40px |
| `11` | `2.75rem` | 44px |
| `12` | `3rem` | 48px |
| `14` | `3.5rem` | 56px |
| `16` | `4rem` | 64px |
| `20` | `5rem` | 80px |
| `24` | `6rem` | 96px |
| `28` | `7rem` | 112px |
| `32` | `8rem` | 128px |
| `36` | `9rem` | 144px |
| `40` | `10rem` | 160px |
| `44` | `11rem` | 176px |
| `48` | `12rem` | 192px |
| `52` | `13rem` | 208px |
| `56` | `14rem` | 224px |
| `60` | `15rem` | 240px |
| `64` | `16rem` | 256px |
| `72` | `18rem` | 288px |
| `80` | `20rem` | 320px |
| `96` | `24rem` | 384px |
| `auto` | `auto` | - |
| `full` | `100%` | - |
| `screen` | `100vw` / `100vh` | - |

### Padding
| Class | CSS |
|---|---|
| `p-{n}` | `padding: {val};` |
| `px-{n}` | `padding-left: {val}; padding-right: {val};` |
| `py-{n}` | `padding-top: {val}; padding-bottom: {val};` |
| `pt-{n}` | `padding-top: {val};` |
| `pr-{n}` | `padding-right: {val};` |
| `pb-{n}` | `padding-bottom: {val};` |
| `pl-{n}` | `padding-left: {val};` |

### Margin
| Class | CSS |
|---|---|
| `m-{n}` | `margin: {val};` |
| `mx-{n}` | `margin-left: {val}; margin-right: {val};` |
| `my-{n}` | `margin-top: {val}; margin-bottom: {val};` |
| `mt-{n}` | `margin-top: {val};` |
| `mr-{n}` | `margin-right: {val};` |
| `mb-{n}` | `margin-bottom: {val};` |
| `ml-{n}` | `margin-left: {val};` |
| `-m-{n}` | `margin: -{val};` (Negative) |

### Gaps & Space Between
| Class | CSS |
|---|---|
| `gap-{n}` | `gap: {val};` |
| `gap-x-{n}` | `column-gap: {val};` |
| `gap-y-{n}` | `row-gap: {val};` |
| `space-x-{n}` | `margin-left: {val};` (on children) |
| `space-y-{n}` | `margin-top: {val};` (on children) |

---

## Typography

### Font Size
| Class | CSS |
|---|---|
| `text-xs` | `font-size: 0.75rem; line-height: 1rem;` |
| `text-sm` | `font-size: 0.875rem; line-height: 1.25rem;` |
| `text-base` | `font-size: 1rem; line-height: 1.5rem;` |
| `text-lg` | `font-size: 1.125rem; line-height: 1.75rem;` |
| `text-xl` | `font-size: 1.25rem; line-height: 1.75rem;` |
| `text-2xl` | `font-size: 1.5rem; line-height: 2rem;` |
| `text-3xl` | `font-size: 1.875rem; line-height: 2.25rem;` |
| `text-4xl` | `font-size: 2.25rem; line-height: 2.5rem;` |
| `text-5xl` | `font-size: 3rem; line-height: 1;` |
| `text-6xl` | `font-size: 3.75rem; line-height: 1;` |
| `text-7xl` | `font-size: 4.5rem; line-height: 1;` |
| `text-8xl` | `font-size: 6rem; line-height: 1;` |
| `text-9xl` | `font-size: 8rem; line-height: 1;` |

### Font Weight
| Class | CSS Weight |
|---|---|
| `font-thin` | `100` |
| `font-extralight` | `200` |
| `font-light` | `300` |
| `font-normal` | `400` |
| `font-medium` | `500` |
| `font-semibold` | `600` |
| `font-bold` | `700` |
| `font-extrabold` | `800` |
| `font-black` | `900` |

### Font Style & Decoration
| Class | CSS |
|---|---|
| `italic` | `font-style: italic;` |
| `not-italic` | `font-style: normal;` |
| `underline` | `text-decoration-line: underline;` |
| `overline` | `text-decoration-line: overline;` |
| `line-through` | `text-decoration-line: line-through;` |
| `no-underline` | `text-decoration-line: none;` |
| `uppercase` | `text-transform: uppercase;` |
| `lowercase` | `text-transform: lowercase;` |
| `capitalize` | `text-transform: capitalize;` |
| `normal-case` | `text-transform: none;` |

### Text Alignment
| Class | CSS |
|---|---|
| `text-left` | `text-align: left;` |
| `text-center` | `text-align: center;` |
| `text-right` | `text-align: right;` |
| `text-justify` | `text-align: justify;` |

### Other Typography
| Class | CSS |
|---|---|
| `truncate` | `overflow: hidden; text-overflow: ellipsis; white-space: nowrap;` |
| `whitespace-nowrap` | `white-space: nowrap;` |
| `whitespace-normal` | `white-space: normal;` |
| `whitespace-pre` | `white-space: pre;` |
| `break-words` | `overflow-wrap: break-word;` |
| `break-all` | `word-break: break-all;` |
| `antialiased` | `-webkit-font-smoothing: antialiased;` |
| `subpixel-antialiased`| `-webkit-font-smoothing: auto;` |

### Font Families
| Class | Description |
|---|---|
| `font-sans` | Default sans-serif (Plus Jakarta Sans) |
| `font-outfit` | Outfit Font |
| `font-serif` | Default serif family |
| `font-mono` | Default monospaced family |

### Line Height (Leading)
| Class | CSS |
|---|---|
| `leading-{n}` | `line-height: {n * 0.25}rem` (Numeric) |
| `leading-none` | `line-height: 1` |
| `leading-tight` | `line-height: 1.25` |
| `leading-snug` | `line-height: 1.375` |
| `leading-normal` | `line-height: 1.5` |
| `leading-relaxed` | `line-height: 1.625` |
| `leading-loose` | `line-height: 2` |

### Letter Spacing (Tracking)
| Class | CSS Value |
|---|---|
| `tracking-tighter` | `-0.05em` |
| `tracking-tight` | `-0.025em` |
| `tracking-normal` | `0em` |
| `tracking-wide` | `0.025em` |
| `tracking-wider` | `0.05em` |
| `tracking-widest` | `0.1em` |

---

## Colors

Plugs uses the **OKLCH** color space for better perceptual uniformity. All color utilities support opacity modifiers (e.g., `bg-red-500/50`).

### Palette Colors
Available for `text-`, `bg-`, `border-`, `ring-`, `outline-`, `accent-`, `decoration-`, `divide-`, `placeholder-`.

| Color Group | Shades |
|---|---|
| **Slate** | `slate-50` through `slate-950` |
| **Gray** | `gray-50` through `gray-950` |
| **Zinc** | `zinc-50` through `zinc-950` |
| **Neutral** | `neutral-50` through `neutral-950` |
| **Stone** | `stone-50` through `stone-950` |
| **Red** | `red-50` through `red-950` |
| **Orange** | `orange-50` through `orange-950` |
| **Amber** | `amber-50` through `amber-950` |
| **Yellow** | `yellow-50` through `yellow-950` |
| **Lime** | `lime-50` through `lime-950` |
| **Green** | `green-50` through `green-950` |
| **Emerald** | `emerald-50` through `emerald-950` |
| **Teal** | `teal-50` through `teal-950` |
| **Cyan** | `cyan-50` through `cyan-950` |
| **Sky** | `sky-50` through `sky-950` |
| **Blue** | `blue-50` through `blue-950` |
| **Indigo** | `indigo-50` through `indigo-950` |
| **Violet** | `violet-50` through `violet-950` |
| **Purple** | `purple-50` through `purple-950` |
| **Fuchsia** | `fuchsia-50` through `fuchsia-950` |
| **Pink** | `pink-50` through `pink-950` |
| **Rose** | `rose-50` through `rose-950` |
| **Emerald** | `emerald-50` through `emerald-950` |
| **Teal** | `teal-50` through `teal-950` |
| **Cyan** | `cyan-50` through `cyan-950` |
| **Sky** | `sky-50` through `sky-950` |
| **Lime** | `lime-50` through `lime-950` |
| **Fuchsia**| `fuchsia-50` through fuchsia-950 |

### Gradients
Gradients use the `var(--tw-gradient-stops)` system.

| Class | CSS |
|---|---|
| `bg-gradient-to-{dir}` | `t`, `tr`, `r`, `br`, `b`, `bl`, `l`, `tl` |
| `from-{color}` | Sets starting color and resets stops |
| `via-{color}` | Sets middle color stop |
| `to-{color}` | Sets ending color stop |

Example: `bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500`

#### Example Usage
```html
<div class="h-32 w-full bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 rounded-xl">
    <!-- Gradient Background -->
</div>
```

### Special Keywords
| Class | Value |
|---|---|
| `transparent` | `transparent` |
| `current` | `currentColor` |
| `inherit` | `inherit` |
| `white` | `#ffffff` |
| `black` | `#000000` |

---

## Sizing

### Width & Height
Uses the [Spacing Scale](#spacing-scale) unless otherwise specified.

| Class | CSS Value |
|---|---|
| `w-full` / `h-full` | `100%` |
| `w-screen` / `h-screen` | `100vw` / `100vh` |
| `w-auto` / `h-auto` | `auto` |
| `w-min` / `h-min` | `min-content` |
| `w-max` / `h-max` | `max-content` |
| `w-fit` / `h-fit` | `fit-content` |
| `w-{frac}` | `1/2`, `1/3`, `2/3`, `1/4`, `3/4`, etc. |
| `size-{n}` | Sets both `width` and `height` |

### Max-Width
| Class | Value |
|---|---|
| `max-w-none` | `none` |
| `max-w-xs` | `20rem` |
| `max-w-sm` | `24rem` |
| `max-w-md` | `28rem` |
| `max-w-lg` | `32rem` |
| `max-w-xl` | `36rem` |
| `max-w-2xl` | `42rem` |
| `max-w-3xl` | `48rem` |
| `max-w-4xl` | `56rem` |
| `max-w-5xl` | `64rem` |
| `max-w-6xl` | `72rem` |
| `max-w-7xl` | `80rem` |
| `max-w-full` | `100%` |
| `max-w-prose` | `65ch` |
| `max-w-screen-sm`| `640px` |
| `max-w-screen-md`| `768px` |

---

## Layout

### Display
| Class | CSS |
|---|---|
| `block` | `display: block;` |
| `inline-block` | `display: inline-block;` |
| `inline` | `display: inline;` |
| `flex` | `display: flex;` |
| `inline-flex` | `display: inline-flex;` |
| `grid` | `display: grid;` |
| `hidden` | `display: none;` |
| `flow-root` | `display: flow-root;` |
| `contents` | `display: contents;` |

### Flexbox & Grid
| Class | CSS |
|---|---|
| `flex` | `display: flex;` |
| `grid` | `display: grid;` |
| `items-center` | `align-items: center;` (start, end, baseline, stretch) |
| `justify-between` | `justify-content: space-between;` (start, end, center, around, evenly) |
| `flex-row` | `flex-direction: row;` (col, row-reverse, col-reverse) |
| `flex-wrap` | `flex-wrap: wrap;` (nowrap, wrap-reverse) |
| `flex-1` | `flex: 1 1 0%;` (auto, initial, none) |
| `grow` | `flex-grow: 1;` (grow-0) |
| `shrink-0` | `flex-shrink: 0;` (shrink) |
| `grid-cols-{n}` | `grid-template-columns: repeat({n}, minmax(0, 1fr));` (1-12) |
| `col-span-{n}` | `grid-column: span {n} / span {n};` |
| `col-span-full` | `grid-column: 1 / -1;` |
| `order-{n}` | `order: {n};` (first: -9999, last: 9999, none: 0) |
| `self-center` | `align-self: center;` (auto, start, end, stretch, baseline) |
| `justify-self-end`| `justify-self: end;` (auto, start, center, stretch) |
| `aspect-video` | `aspect-ratio: 16 / 9;` (auto, square) |
| `columns-{n}` | `columns: {n};` |

---

## Container Queries

Container queries allow you to style elements based on the size of a parent container rather than the viewport.

### Defining a Container
Use the `@container` directive or utility classes:

| Class | CSS |
|---|---|
| `container-type-size` | `container-type: size;` |
| `container-type-inline-size` | `container-type: inline-size;` |
| `container-type-normal` | `container-type: normal;` |
| `container-name-{name}` | `container-name: {name};` |

### Container Variants
Prefix utilities with `@container-[breakpoint]:` to apply styles based on the container size.

- `@container-sm:p-4`
- `@container-md:grid-cols-2`
- `@container-lg:flex-row`

> [!NOTE]
> Container variants use the same breakpoints as responsive utilities (640px, 768px, etc.) but relative to the container.

---

## Borders

### Border Width
| Class | CSS |
|---|---|
| `border` | `border-width: 1px;` |
| `border-{n}` | `border-width: {n}px;` |
| `border-t-{n}` | `border-top-width: {n}px;` |

### Border Style
| Class | CSS |
|---|---|
| `border-solid` | `border-style: solid;` |
| `border-dashed` | `border-style: dashed;` |
| `border-dotted` | `border-style: dotted;` |
| `border-none` | `border-style: none;` |

### Border Radius (Rounded)
| Class | Value |
|---|---|
| `rounded-none` | `0px` |
| `rounded-sm` | `0.125rem` |
| `rounded` | `0.25rem` |
| `rounded-md` | `0.375rem` |
| `rounded-lg` | `0.5rem` |
| `rounded-xl` | `0.75rem` |
| `rounded-2xl` | `1rem` |
| `rounded-3xl` | `1.5rem` |
| `rounded-full` | `9999px` |
| `rounded-t-{sz}`| Top corners only |

### Dividers
| Class | CSS |
|---|---|
| `divide-x` | `border-left-width: 1px; border-right-width: 0px;` |
| `divide-y` | `border-top-width: 1px; border-bottom-width: 0px;` |
| `divide-x-reverse` | `border-left-width: 0px; border-right-width: 1px;` |
| `divide-y-reverse` | `border-top-width: 0px; border-bottom-width: 1px;` |

### Rings
| Class | CSS |
|---|---|
| `ring` | `box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);` |
| `ring-{n}` | `box-shadow: 0 0 0 {n}px rgba(59, 130, 246, 0.5);` |
| `ring-inset` | `box-shadow: inset 0 0 0 3px ...;` |
| `ring-offset-{n}` | `--tw-ring-offset-width: {n}px;` |

---

## Effects

### Box Shadow
| Class | CSS |
|---|---|
| `shadow-sm` | `0 1px 2px 0 rgb(0 0 0 / 0.05)` |
| `shadow` | `0 1px 3px 0 rgb(0 0 0 / 0.1), ...` |
| `shadow-md` | `0 4px 6px -1px rgb(0 0 0 / 0.1), ...` |
| `shadow-lg` | `0 10px 15px -3px rgb(0 0 0 / 0.1), ...` |
| `shadow-xl` | `0 20px 25px -5px rgb(0 0 0 / 0.1), ...` |
| `shadow-inner` | `inset 0 2px 4px 0 rgb(0 0 0 / 0.05)` |
| `shadow-none` | `0 0 #0000` |

### Opacity & Blur
| Class | Value |
|---|---|
| `opacity-{n}` | `opacity: {n/100};` |
| `blur-sm` | `filter: blur(4px);` |
| `blur` | `filter: blur(8px);` |
| `backdrop-blur-md`| `backdrop-filter: blur(12px);` |

### Blend Modes
| Class | CSS |
|---|---|
| `mix-blend-normal` | `mix-blend-mode: normal;` |
| `mix-blend-multiply`| `mix-blend-mode: multiply;` |
| `mix-blend-screen` | `mix-blend-mode: screen;` |
| `mix-blend-overlay` | `mix-blend-mode: overlay;` |
| `mix-blend-darken` | `mix-blend-mode: darken;` |
| `mix-blend-lighten` | `mix-blend-mode: lighten;` |

---

## Transitions & Transforms

### Transitions
| Class | Description |
|---|---|
| `transition` | Generic transition for color, opacity, shadow, transform |
| `transition-all` | Transition for all properties |
| `transition-colors`| Colors, background, border |
| `duration-{n}` | `transition-duration: {n}ms;` |
| `delay-{n}` | `transition-delay: {n}ms;` |
| `ease-in-out` | `transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);` |

### Transforms
| Class | CSS |
|---|---|
| `scale-{n}` | `transform: scale({n/100});` |
| `rotate-{n}` | `transform: rotate({n}deg);` |
| `translate-x-{n}` | `transform: translateX({val});` |
| `translate-y-{n}` | `transform: translateY({val});` |
| `skew-x-{n}` | `transform: skewX({n}deg);` |
| `origin-center` | `transform-origin: center;` |

---

## Position

| Class | CSS |
|---|---|
| `static` | `position: static;` |
| `fixed` | `position: fixed;` |
| `absolute` | `position: absolute;` |
| `relative` | `position: relative;` |
| `sticky` | `position: sticky;` |
| `top-{n}` | `top: {val};` |
| `inset-{n}` | `inset: {val};` (all sides) |
| `z-{n}` | `z-index: {n};` |

---

## Misc Utilities

### Interactivity
| Class | CSS |
|---|---|
| `cursor-pointer` | `cursor: pointer;` |
| `cursor-not-allowed`| `cursor: not-allowed;` |
| `select-none` | `user-select: none;` |
| `pointer-events-none`| `pointer-events: none;` |
| `resize-y` | `resize: vertical;` |
| `appearance-none` | `appearance: none;` |

### SVG
| Class | CSS |
|---|---|
| `fill-current` | `fill: currentColor;` |
| `stroke-current` | `stroke: currentColor;` |
| `stroke-{n}` | `stroke-width: {n};` |

### Object Fit
| Class | CSS |
|---|---|
| `object-contain` | `object-fit: contain;` |
| `object-cover` | `object-fit: cover;` |
| `object-center` | `object-position: center;` |

---

## Variants & Features

### Breakpoints (Responsive)
Prefix any utility with a breakpoint: `md:w-1/2`, `lg:p-10`.
- `sm`: 640px
- `md`: 768px
- `lg`: 1024px
- `xl`: 1280px
- `2xl`: 1536px

### State Variants
- `hover:` (on hover)
- `focus:` (on focus)
- `active:` (on click)
- `disabled:` (when disabled)
- `dark:` (in dark mode)
- `first:` / `last:` / `odd:` / `even:`
- `group-hover:` (when parent with `.group` is hovered)

### Special Features
- **`auto-dark`**: Automatically invert colors for dark mode without manual prefixing.
- **`fluid:`**: Prefix to scale sizes smoothly based on viewport.
    - **Typography**: `fluid:text-4xl`
    - **Sizing**: `fluid:w-full`, `fluid:h-64`
    - **Spacing**: `fluid:p-10`, `fluid:m-5`, `fluid:gap-4`
    - **Positioning**: `fluid:top-20`, `fluid:left-10`
- **`@container-[bp]:`**: Container query variant (e.g., `@container-md:w-full`).
- **`Arbitrary Values`**: Use bracket syntax for custom values: `w-[200px]`, `bg-[#ff0000]`.
- **`GPU Acceleration`**: Use `transform-gpu` to force hardware acceleration for transforms.
