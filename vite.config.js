import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { realpathSync } from 'node:fs'

const resolvedCwd = realpathSync(process.cwd())
if (resolvedCwd !== process.cwd()) {
  process.chdir(resolvedCwd)
}

// https://vite.dev/config/
export default defineConfig({
  base: '/SVARKA/',
  plugins: [react()],
})
