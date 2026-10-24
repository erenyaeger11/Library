import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const phpApiOrigin = env.VITE_PHP_API_ORIGIN || 'http://127.0.0.1:8000';
  const certPath = env.VITE_HTTPS_CERT_FILE;
  const keyPath = env.VITE_HTTPS_KEY_FILE;

  if (Boolean(certPath) !== Boolean(keyPath)) {
    throw new Error('Set both VITE_HTTPS_CERT_FILE and VITE_HTTPS_KEY_FILE to enable HTTPS.');
  }

  const https = certPath && keyPath
    ? {
        cert: readFileSync(resolve(process.cwd(), certPath)),
        key: readFileSync(resolve(process.cwd(), keyPath))
      }
    : undefined;

  return {
    plugins: [react()],
    server: {
      port: 5173,
      host: '0.0.0.0',
      https,
      proxy: {
        '/api': {
          target: phpApiOrigin,
          changeOrigin: true
        }
      }
    }
  };
});
