/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./src/**/*.{vue,ts,html}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Neutral near-black greys (replaces Tailwind's blue-tinted slate)
        slate: {
          50:  '#fafaf8',
          100: '#f4f4f2',
          200: '#dcdcd9',
          300: '#b8b8b9',
          400: '#8d8d92',
          500: '#6b6b70',
          600: '#47474b',
          700: '#2e2e32',
          800: '#1b1b1d',
          900: '#121213',
          950: '#0b0b0c',
        },
        // Butter-yellow accent, matching the website
        brand: {
          50:  '#fdfdf3',
          100: '#fbfae2',
          200: '#f7f5c8',
          300: '#f3f0b5',
          400: '#eeeaa2',
          500: '#f6f3bb',
          600: '#eeeaa2',
          700: '#d8d37c',
          800: '#aaa55a',
          900: '#6f6b38',
          950: '#3a381d',
        },
        ink: '#141410',
      },
      fontFamily: {
        sans: ['"Manrope Variable"', 'Manrope', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'Cascadia Code', 'Consolas', 'monospace']
      }
    }
  },
  plugins: []
}
