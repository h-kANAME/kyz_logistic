import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  let base = env.VITE_BASE_PATH ?? '/';
  if (base !== '/' && !base.endsWith('/')) {
    base = `${base}/`;
  }

  return {
    plugins: [react()],
    base,
  };
});
