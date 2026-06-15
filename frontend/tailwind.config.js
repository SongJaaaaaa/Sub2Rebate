/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,ts}'],
  theme: {
    extend: {
      colors: {
        surface: '#f7f9fb',
        'surface-white': '#ffffff',
        'surface-container-low': '#f2f4f6',
        'surface-container': '#eceef0',
        'surface-container-high': '#e6e8ea',
        'border-subtle': '#e2e8f0',
        primary: '#0f172a',
        secondary: '#4648d4',
        muted: '#64748b',
        success: '#10b981',
        danger: '#ef4444',
        warning: '#f59e0b',
        info: '#6366f1',
      },
      boxShadow: {
        card: '0 1px 3px 0 rgb(15 23 42 / 0.08), 0 1px 2px -1px rgb(15 23 42 / 0.08)',
        'card-hover': '0 4px 12px 0 rgb(15 23 42 / 0.08), 0 2px 4px -2px rgb(15 23 42 / 0.06)',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      maxWidth: {
        content: '1440px',
      },
    },
  },
  plugins: [],
}
