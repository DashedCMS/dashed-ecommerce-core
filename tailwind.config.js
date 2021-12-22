const colors = require('tailwindcss/colors')

module.exports = {
    content: [
        './vendor/qubiqx/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                danger: colors.rose,
                primary: colors.orange,
                success: colors.green,
                warning: colors.yellow,
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
}
