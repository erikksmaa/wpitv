/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: [
    "./app/Livewire/**/*.php",
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          blue:       '#155DAC',
          'blue-lt':  '#0C78C4',
          cyan:       '#1B8CD1',
          navy:       '#1E368B',
          'navy-dk':  '#0B1730',
          red:        '#D9251D',
          orange:     '#E7861F',
        },
      },
      fontFamily: {
        heading: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
        body:    ['Inter', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        'sm':  '10px',
        'md':  '14px',
        'lg':  '18px',
        'xl':  '24px',
        'pill':'999px',
      },
    },
  },
  plugins: [],
}
