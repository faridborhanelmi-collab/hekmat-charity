/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './*.php',
    './admin/*.php',
    './includes/*.php',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Vazirmatn', 'sans-serif'],
      },
      colors: {
        primary: {
          900: '#00141e',
          800: '#115e59',
          600: '#14b8a6',
        },
        accent: {
          500: '#fb7185',
          600: '#f43f5e',
          700: '#e11d48',
        },
      },
      keyframes: {
        'spin-y': {
          '0%, 80%': { transform: 'rotateY(0deg)' },
          '100%': { transform: 'rotateY(360deg)' },
        },
        shimmer: {
          '0%': { left: '-100%' },
          '100%': { left: '100%' },
        },
        fadeInUp: {
          from: { opacity: '0', transform: 'translate3d(0, 40px, 0)' },
          to: { opacity: '1', transform: 'translate3d(0, 0, 0)' },
        },
      },
      animation: {
        'spin-y': 'spin-y 7s ease-in-out infinite',
        'shimmer': 'shimmer 2.5s infinite',
        'fade-in-up': 'fadeInUp 1s both',
      },
    },
  },
  plugins: [],
}
