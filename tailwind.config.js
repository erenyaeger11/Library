/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx,ts,tsx}'],
  theme: {
    extend: {
      colors: {
        ssu: {
          50: '#eef9ff',
          100: '#d9f2ff',
          200: '#bfe7ff',
          300: '#8bd2ff',
          400: '#4aafff',
          500: '#1c8ce8',
          600: '#0c6ec7',
          700: '#0b568d',
          800: '#0e446d',
          900: '#102d4d'
        },
        success: '#16a34a',
        danger: '#dc2626'
      },
      boxShadow: {
        soft: '0 10px 25px rgba(15, 23, 42, 0.08)'
      }
    }
  },
  plugins: []
};
