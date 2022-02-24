// webpack.mix.js

let mix = require('laravel-mix');

mix.js('./themes/demo/assets/js/app.js', 'dist').setPublicPath('dist');