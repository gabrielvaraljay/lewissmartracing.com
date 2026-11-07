import { defineConfig } from 'astro/config';

export default defineConfig({
  site: 'https://lewissmartracing.com',
  trailingSlash: 'ignore',
  build: { inlineStylesheets: 'auto' },
  compressHTML: true,
});
