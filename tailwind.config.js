module.exports = {
  content: [
    "./src/**/*.{js,jsx,ts,tsx}",
    "./admin/views/**/*.php",
    "./includes/**/*.php"
  ],
  theme: {
    extend: {
      colors: {
        ghblue: '#0969da',
        ghbluehover: '#0353b3',
        textmain: '#24292f',
        textmuted: '#57606a',
        graybg: '#f6f8fa',
        bordergray: '#d0d7de',
        ambient: {
          50: '#f0fdfa',
          100: '#ccfbf1',
          200: '#99f6e4',
          500: '#14b8a6',
          900: '#134e4a',
        }
      }
    },
  },
  plugins: [],
}
