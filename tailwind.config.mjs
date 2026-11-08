// Tailwind config generated from the Google Stitch "Apex Precision" design system.
// Red palette replaced with the #32 livery: fluorescent yellow-green + purple.
/** @type {import("tailwindcss").Config} */
export default {
  "darkMode": "class",
  "content": [
    "./src/**/*.{astro,html,js,ts}"
  ],
  "corePlugins": {
    "container": false
  },
  "theme": {
    "extend": {
      "colors": {
        "surface-container-low": "#1c1b1d",
        "on-primary": "#1f2600",
        "inverse-on-surface": "#313032",
        "surface-dim": "#131315",
        "on-secondary": "#24104f",
        "on-surface": "#e5e1e4",
        "surface-container-lowest": "#0e0e10",
        "tertiary": "#c9b6ff",
        "on-tertiary-fixed-variant": "#3d1d71",
        "on-tertiary-container": "#f4efff",
        "on-primary-fixed-variant": "#3d4800",
        "on-background": "#e5e1e4",
        "tertiary-fixed-dim": "#c9b6ff",
        "secondary-fixed": "#e8dfff",
        "on-error": "#690005",
        "on-primary-container": "#0b0b0d",
        "surface-bright": "#39393b",
        "secondary-container": "#5b35b5",
        "surface-tint": "#d6f20a",
        "primary-fixed-dim": "#e3f76a",
        "outline-variant": "#3c3f46",
        "on-secondary-fixed": "#1a0a3d",
        "secondary": "#c9b6ff",
        "primary-fixed": "#f1ffb0",
        "inverse-primary": "#8fa300",
        "error-container": "#93000a",
        "secondary-fixed-dim": "#c9b6ff",
        "tertiary-fixed": "#e8dfff",
        "on-primary-fixed": "#151a00",
        "tertiary-container": "#6d4bc9",
        "on-error-container": "#ffdad6",
        "background": "#131315",
        "inverse-surface": "#e5e1e4",
        "on-tertiary": "#24104f",
        "on-tertiary-fixed": "#1a0a3d",
        "surface-variant": "#353437",
        "primary": "#e3f76a",
        "primary-container": "#d6f20a",
        "surface-container-high": "#2a2a2c",
        "on-surface-variant": "#c4c7cf",
        "on-secondary-fixed-variant": "#3d1d71",
        "surface-container": "#201f21",
        "surface": "#131315",
        "on-secondary-container": "#e8dfff",
        "outline": "#8b8f98",
        "surface-container-highest": "#353437",
        "error": "#ffb4ab"
      },
      "borderRadius": {
        "DEFAULT": "0.25rem",
        "lg": "0.5rem",
        "xl": "0.75rem",
        "full": "9999px"
      },
      "spacing": {
        "space-lg": "1.5rem",
        "space-xl": "2.5rem",
        "margin": "2rem",
        "gutter": "1.5rem",
        "space-md": "1rem",
        "space-sm": "0.5rem",
        "margin-lg": "4rem",
        "space-xs": "0.25rem",
        "margin-sm": "1rem",
        "gutter-sm": "1rem"
      },
      "fontFamily": {
        "label-md": [
          "Inter Variable",
          "Inter",
          "system-ui",
          "sans-serif"
        ],
        "label-xs": [
          "Inter Variable",
          "Inter",
          "system-ui",
          "sans-serif"
        ],
        "display-hero-mobile": [
          "Barlow Condensed",
          "Arial Narrow",
          "sans-serif"
        ],
        "display-hero": [
          "Barlow Condensed",
          "Arial Narrow",
          "sans-serif"
        ],
        "body-md": [
          "Inter Variable",
          "Inter",
          "system-ui",
          "sans-serif"
        ],
        "headline-xl": [
          "Barlow Condensed",
          "Arial Narrow",
          "sans-serif"
        ],
        "body-lg": [
          "Inter Variable",
          "Inter",
          "system-ui",
          "sans-serif"
        ],
        "headline-lg-mobile": [
          "Barlow Condensed",
          "Arial Narrow",
          "sans-serif"
        ],
        "body-sm": [
          "Inter Variable",
          "Inter",
          "system-ui",
          "sans-serif"
        ],
        "headline-lg": [
          "Barlow Condensed",
          "Arial Narrow",
          "sans-serif"
        ],
        "telemetry-num": [
          "Barlow Condensed",
          "Arial Narrow",
          "sans-serif"
        ],
        "headline-xl-mobile": [
          "Barlow Condensed",
          "Arial Narrow",
          "sans-serif"
        ],
        "headline-md": [
          "Barlow Condensed",
          "Arial Narrow",
          "sans-serif"
        ],
        "headline-sm": [
          "Barlow Condensed",
          "Arial Narrow",
          "sans-serif"
        ]
      },
      "fontSize": {
        "label-md": [
          "12px",
          {
            "lineHeight": "16px",
            "letterSpacing": "0.08em",
            "fontWeight": "600"
          }
        ],
        "label-xs": [
          "10px",
          {
            "lineHeight": "12px",
            "letterSpacing": "0.12em",
            "fontWeight": "700"
          }
        ],
        "display-hero-mobile": [
          "56px",
          {
            "lineHeight": "56px",
            "letterSpacing": "-0.01em",
            "fontWeight": "900"
          }
        ],
        "display-hero": [
          "96px",
          {
            "lineHeight": "96px",
            "letterSpacing": "-0.02em",
            "fontWeight": "900"
          }
        ],
        "body-md": [
          "15px",
          {
            "lineHeight": "24px",
            "fontWeight": "400"
          }
        ],
        "headline-xl": [
          "64px",
          {
            "lineHeight": "68px",
            "letterSpacing": "0.01em",
            "fontWeight": "800"
          }
        ],
        "body-lg": [
          "18px",
          {
            "lineHeight": "28px",
            "fontWeight": "400"
          }
        ],
        "headline-lg-mobile": [
          "32px",
          {
            "lineHeight": "36px",
            "letterSpacing": "0.02em",
            "fontWeight": "800"
          }
        ],
        "body-sm": [
          "13px",
          {
            "lineHeight": "20px",
            "fontWeight": "400"
          }
        ],
        "headline-lg": [
          "48px",
          {
            "lineHeight": "52px",
            "letterSpacing": "0.02em",
            "fontWeight": "800"
          }
        ],
        "telemetry-num": [
          "36px",
          {
            "lineHeight": "36px",
            "letterSpacing": "0.02em",
            "fontWeight": "900"
          }
        ],
        "headline-xl-mobile": [
          "40px",
          {
            "lineHeight": "44px",
            "letterSpacing": "0.01em",
            "fontWeight": "800"
          }
        ],
        "headline-md": [
          "32px",
          {
            "lineHeight": "36px",
            "letterSpacing": "0.03em",
            "fontWeight": "700"
          }
        ],
        "headline-sm": [
          "24px",
          {
            "lineHeight": "28px",
            "letterSpacing": "0.04em",
            "fontWeight": "700"
          }
        ]
      }
    }
  }
};
