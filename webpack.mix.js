const mix = require("laravel-mix");

mix.postCss('./resources/assets/css/qcommerce-core.css', './resources/dist/css', [
    require('tailwindcss', './tailwind.config.js'),
])
