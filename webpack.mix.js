// webpack.mix.js

let mix = require('laravel-mix');

mix.js('./themes/demo/assets/js/app.js', 'dist/js')
    .setPublicPath('./themes/demo/assets/');

mix.browserSync({
    proxy: 'octobermovies.test',
    host: 'octobermovies.test'
});
