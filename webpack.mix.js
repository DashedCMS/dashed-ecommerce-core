const mix = require("laravel-mix");

mix.postCss('./resources/assets/css/dashed-ecommerce-core.css', './resources/dist/css', [
    require('tailwindcss', './tailwind.config.js'),
])
