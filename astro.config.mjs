import { defineConfig } from 'astro/config';

export default defineConfig({
  site: 'https://lewissmartracing.com',
  trailingSlash: 'ignore',
  build: { inlineStylesheets: 'auto' },
  compressHTML: true,
  // `npm run dev` only: the PHP endpoints run in PHP's own server (see README, "Club32 and admin").
  vite: { server: { proxy: { '/api': 'http://localhost:8080', '/admin': 'http://localhost:8080' } } },
});
