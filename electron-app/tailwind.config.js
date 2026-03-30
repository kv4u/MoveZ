/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./src/**/*.{vue,ts,html}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: {
          50:  '#f0f4ff',
          100: '#dce8ff',
          200: '#b9d0ff',
          300: '#87aeff',
          400: '#5282ff',
          500: '#2d59ff',
          600: '#1a37f5',
          700: '#1528e1',
          800: '#1823b6',
          900: '#1a248f',
          950: '#131761',
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'Fira Code', 'monospace']
      }
    }
  },
  plugins: []
}
