/** @type {import('tailwindcss').Config} */
const colors = require("tailwindcss/colors");

module.exports = {
  content: [
    "./project/**/*.{php,html,js}",
    "./project/static/*.php",
    "./project/admin/*.php",
    "./project/components/*.php",
  ],
  safelist: [
    "bg-red-500",
    "text-white",
    "rounded-full",
    "inline-flex",
    "items-center",
    "justify-center",
    "min-w-[18px]",
    "h-[18px]",
    "text-[10px]",
    "leading-[18px]",
    "z-40",
    "z-50",
  ],
  darkMode: "class", // Hỗ trợ dark mode với class
  theme: {
    extend: {
      colors: {
        // Custom colors từ CSS hiện tại
        main: "#4834d4",
        // Preserve Tailwind red scale (bg-red-500, text-red-500, v.v.) but default red uses the brand red.
        red: {
          ...colors.red,
          DEFAULT: "#e74c3c",
        },
        orange: {
          ...colors.orange,
          DEFAULT: "#f39c12",
        },
        black: {
          ...colors.black,
          DEFAULT: "#34495e",
        },
        "light-bg": "#f5f5f5",
        "light-color": "#999",
      },
      fontFamily: {
        sans: ["Montserrat", "sans-serif"],
        montserrat: ["Montserrat", "sans-serif"],
      },
      boxShadow: {
        custom: "0 0.5rem 1rem rgba(0, 0, 0, 0.1)",
      },
    },
  },
  plugins: [],
};
