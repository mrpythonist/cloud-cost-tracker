/** @type {import('next').NextConfig} */
const nextConfig = {
  // Dashboard calls the Laravel API directly from the browser, so no
  // rewrites are needed. Set NEXT_PUBLIC_API_URL in .env.local.
};

module.exports = nextConfig;
