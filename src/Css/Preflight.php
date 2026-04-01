<?php

declare(strict_types=1);

namespace Plugs\Css;

/**
 * Modern CSS reset/normalize (Preflight).
 *
 * Based on modern-normalize and Tailwind's Preflight.
 * Sets sensible defaults: box-sizing, margin resets,
 * image/form normalization, and typography baseline.
 */
class Preflight
{
    public static function css(array $options = []): string
    {
        $fluid = $options['fluid_typography'] ?? true;
        $fluidStyles = '';

        if ($fluid) {
            $fluidStyles = <<<CSS
h1 { font-size: clamp(2.25rem, 1.8rem + 4vw, 3.75rem); line-height: 1.2; margin-bottom: 1.5rem; }
h2 { font-size: clamp(1.875rem, 1.5rem + 3vw, 3rem); line-height: 1.25; margin-bottom: 1.25rem; }
h3 { font-size: clamp(1.5rem, 1.2rem + 2vw, 2.25rem); line-height: 1.3; margin-bottom: 1rem; }
h4 { font-size: clamp(1.25rem, 1.1rem + 1vw, 1.875rem); line-height: 1.4; margin-bottom: 0.75rem; }
h5 { font-size: clamp(1.125rem, 1rem + 0.5vw, 1.5rem); line-height: 1.5; margin-bottom: 0.5rem; }
h6 { font-size: clamp(1rem, 0.9rem + 0.25vw, 1.25rem); line-height: 1.6; margin-bottom: 0.25rem; }
p { margin-bottom: 1rem; }
CSS;
        }

        return <<<CSS
/*! Plugs CSS Preflight v1.0 | Modern CSS Reset */

*, ::before, ::after {
  box-sizing: border-box;
  border-width: 0;
  border-style: solid;
  border-color: currentColor;
}

::before, ::after {
  --tw-content: '';
}

html {
  line-height: 1.5;
  -webkit-text-size-adjust: 100%;
  -moz-tab-size: 4;
  tab-size: 4;
  font-family: ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
  font-feature-settings: normal;
  font-variation-settings: normal;
  -webkit-tap-highlight-color: transparent;
}

body {
  margin: 0;
  line-height: inherit;
}

hr {
  height: 0;
  color: inherit;
  border-top-width: 1px;
}

abbr:where([title]) {
  text-decoration: underline dotted;
}

h1, h2, h3, h4, h5, h6 {
  font-weight: 700;
  color: inherit;
}

{$fluidStyles}

a {
  color: inherit;
  text-decoration: inherit;
}

b, strong {
  font-weight: bolder;
}

code, kbd, samp, pre {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
  font-feature-settings: normal;
  font-variation-settings: normal;
  font-size: 1em;
}

small {
  font-size: 80%;
}

sub, sup {
  font-size: 75%;
  line-height: 0;
  position: relative;
  vertical-align: baseline;
}

sub {
  bottom: -0.25em;
}

sup {
  top: -0.5em;
}

table {
  text-indent: 0;
  border-color: inherit;
  border-collapse: collapse;
}

button, input, optgroup, select, textarea {
  font-family: inherit;
  font-feature-settings: inherit;
  font-variation-settings: inherit;
  font-size: 100%;
  font-weight: inherit;
  line-height: inherit;
  letter-spacing: inherit;
  color: inherit;
  margin: 0;
  padding: 0;
}

button, select {
  text-transform: none;
}

button, input:where([type='button']), input:where([type='reset']), input:where([type='submit']) {
  -webkit-appearance: button;
  background-color: transparent;
  background-image: none;
}

:-moz-focusring {
  outline: auto;
}

:-moz-ui-invalid {
  box-shadow: none;
}

progress {
  vertical-align: baseline;
}

::-webkit-inner-spin-button, ::-webkit-outer-spin-button {
  height: auto;
}

[type='search'] {
  -webkit-appearance: textfield;
  outline-offset: -2px;
}

::-webkit-search-decoration {
  -webkit-appearance: none;
}

::-webkit-file-upload-button {
  -webkit-appearance: button;
  font: inherit;
}

summary {
  display: list-item;
}

blockquote, dl, dd, h1, h2, h3, h4, h5, h6, hr, figure, p, pre {
  margin: 0;
}

fieldset {
  margin: 0;
  padding: 0;
}

legend {
  padding: 0;
}

ol, ul, menu {
  list-style: none;
  margin: 0;
  padding: 0;
}

dialog {
  padding: 0;
}

textarea {
  resize: vertical;
}

input::placeholder, textarea::placeholder {
  opacity: 1;
  color: #9ca3af;
}

button, [role="button"] {
  cursor: pointer;
}

:disabled {
  cursor: default;
}

img, svg, video, canvas, audio, iframe, embed, object {
  display: block;
  vertical-align: middle;
}

img, video {
  max-width: 100%;
  height: auto;
}

[hidden] {
  display: none;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes ping {
  75%, 100% { transform: scale(2); opacity: 0; }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: .5; }
}

@keyframes bounce {
  0%, 100% { transform: translateY(-25%); animation-timing-function: cubic-bezier(0.8,0,1,1); }
  50% { transform: translateY(0); animation-timing-function: cubic-bezier(0,0,0.2,1); }
}

@keyframes hue-rotate {
  from { filter: hue-rotate(0deg); }
  to { filter: hue-rotate(360deg); }
}

@keyframes shimmer {
  100% { transform: translateX(100%); }
}

@keyframes border-flow {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}

@keyframes reveal-up {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes reveal-down {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes reveal-fade {
  from { opacity: 0; }
  to { opacity: 1; }
}

.text-gradient {
  -webkit-background-clip: text !important;
  background-clip: text !important;
  -webkit-text-fill-color: transparent !important;
  text-fill-color: transparent !important;
}

[reveal] {
  opacity: 0;
  transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

[reveal].revealed {
  opacity: 1;
  transform: none;
}

CSS;
    }
}
